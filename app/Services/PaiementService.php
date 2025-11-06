<?php

namespace App\Services;

use App\Enums\StatutAbonnement;
use App\Models\User;
use App\Notifications\PaymentValidatedNotification;
use App\Repositories\Contracts\AbonnementRepositoryInterface;
use App\Repositories\Contracts\PaiementRepositoryInterface;
use App\Services\Contracts\PaiementServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class PaiementService extends BaseService implements PaiementServiceInterface
{
    protected AbonnementRepositoryInterface $abonnementRepository;

    public function __construct(
        PaiementRepositoryInterface $repository,
        AbonnementRepositoryInterface $abonnementRepository
    ) {
        parent::__construct($repository);
        $this->abonnementRepository = $abonnementRepository;
    }

    public function traiterPaiement(string $abonnementId, array $paiementData): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Vérifier que l'abonnement existe
            $abonnement = $this->abonnementRepository->find($abonnementId);
            if (!$abonnement) {
                DB::rollBack();
                return $this->notFoundResponse('Abonnement non trouvé.');
            }

            // Vérifier que l'abonnement n'est pas déjà payé
            if ($abonnement->statut->value === 'actif') {
                DB::rollBack();
                return $this->errorResponse('Cet abonnement a déjà été payé.', 400);
            }

            // Générer le numéro de transaction
            $numeroTransaction = $this->generateNumeroTransaction();

            // Créer le paiement
            $paiement = $this->repository->create([
                'abonnement_id' => $abonnementId,
                'ecole_id' => $abonnement->ecole_id,
                'numero_transaction' => $numeroTransaction,
                'montant' => $paiementData['montant'] ?? $abonnement->montant,
                'moyen' => $paiementData['moyen'],
                'statut' => 'en_attente',
                'reference_externe' => $paiementData['reference_externe'] ?? null,
                'metadata' => $paiementData['metadata'] ?? null,
                'date_paiement' => now(),
            ]);

            DB::commit();

            return $this->createdResponse($paiement);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in PaiementService::traiterPaiement - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function validerPaiement(string $paiementId): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Récupérer le paiement
            $paiement = $this->repository->find($paiementId, relations: ['abonnement']);
            if (!$paiement) {
                DB::rollBack();
                return $this->notFoundResponse('Paiement non trouvé.');
            }

            // Mettre à jour le paiement
            $this->repository->update($paiementId, [
                'statut' => 'valide',
                'date_validation' => now(),
            ]);

            // Activer l'abonnement
            $this->abonnementRepository->update($paiement->abonnement_id, [
                'statut' => StatutAbonnement::ACTIF,
            ]);

            // Envoyer la notification à l'admin
            $adminUsers = User::whereHas('roles', function ($query) {
                $query->where('name', 'admin');
            })->get();

            foreach ($adminUsers as $admin) {
                $admin->notify(new PaymentValidatedNotification([
                    'montant' => $paiement->montant,
                    'abonnement_id' => $paiement->abonnement_id,
                    'numero_transaction' => $paiement->numero_transaction,
                    'ecole_id' => $paiement->ecole_id,
                    'payment_id' => $paiement->id,
                ]));
            }

            DB::commit();

            return $this->successResponse('Paiement validé et abonnement activé avec succès.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in PaiementService::validerPaiement - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getPaiementsByAbonnement(string $abonnementId): JsonResponse
    {
        try {
            $paiements = $this->repository->findAllBy(['abonnement_id' => $abonnementId]);
            return $this->successResponse(null, $paiements);
        } catch (Exception $e) {
            Log::error("Error in PaiementService::getPaiementsByAbonnement - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    private function generateNumeroTransaction(): string
    {
        do {
            $numero = 'TXN-' . date('Ymd') . '-' . strtoupper(Str::random(8));
        } while ($this->repository->exists(['numero_transaction' => $numero]));

        return $numero;
    }

    public function processAutomaticPayment(string $abonnementId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $abonnement = $this->abonnementRepository->find($abonnementId);
            if (!$abonnement) {
                DB::rollBack();
                return $this->notFoundResponse('Abonnement non trouvé pour le paiement automatique.');
            }

            if (!$abonnement->auto_renouvellement) {
                DB::rollBack();
                return $this->errorResponse('L\'abonnement n\'est pas configuré pour le renouvellement automatique.', 400);
            }

            // TODO: Ici, la logique réelle d'intégration avec le fournisseur de paiement
            // et l'utilisation des informations de paiement enregistrées de l'école.
            // Pour l'instant, nous simulons un paiement réussi.

            // Créer un enregistrement de paiement "simulé"
            $paiement = $this->repository->create([
                'abonnement_id' => $abonnementId,
                'ecole_id' => $abonnement->ecole_id,
                'numero_transaction' => $this->generateNumeroTransaction(),
                'montant' => $abonnement->montant,
                'moyen' => 'auto_prelevement', // Ou le moyen de paiement réel
                'statut' => 'en_attente', // Sera validé par validerPaiement
                'reference_externe' => 'AUTO_PAY_' . Str::random(10),
                'date_paiement' => now(),
            ]);

            // Valider le paiement (ce qui activera l'abonnement)
            $response = $this->validerPaiement($paiement->id);

            if ($response->getStatusCode() !== 200) {
                DB::rollBack();
                return $this->errorResponse('Échec de la validation du paiement automatique.', 500);
            }

            DB::commit();
            return $this->successResponse('Paiement automatique traité avec succès.', ['paiement_id' => $paiement->id]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in PaiementService::processAutomaticPayment - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
