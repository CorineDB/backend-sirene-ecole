<?php

namespace App\Services;

use App\Repositories\Contracts\SireneRepositoryInterface;
use App\Services\Contracts\SireneServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SireneService extends BaseService implements SireneServiceInterface
{
    public function __construct(SireneRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function findByNumeroSerie(string $numeroSerie, array $relations = []): JsonResponse
    {
        try {
            $sirene = $this->repository->findByNumeroSerie($numeroSerie, $relations);
            if (!$sirene) {
                return $this->notFoundResponse('Sirène non trouvée.');
            }
            return $this->successResponse(null, $sirene);
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::findByNumeroSerie - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getSirenesDisponibles(array $relations = []): JsonResponse
    {
        try {
            $sirenes = $this->repository->getSirenesDisponibles($relations);
            return $this->successResponse(null, $sirenes);
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::getSirenesDisponibles - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function affecterSireneASite(string $sireneId, string $siteId, ?string $ecoleId = null): JsonResponse
    {
        try {
            DB::beginTransaction();
            $sirene = $this->repository->affecterSireneASite($sireneId, $siteId, $ecoleId);
            DB::commit();
            return $this->successResponse('Sirène affectée avec succès.', $sirene);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::affecterSireneASite - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getProgrammationByNumeroSerie(string $numeroSerie, ?string $tokenCrypte = null): JsonResponse
    {
        try {
            // Rechercher la sirène par numéro de série avec abonnement et token actif
            $sirene = $this->repository->model
                ->where('numero_serie', $numeroSerie)
                ->with([
                    'programmations' => function ($query) {
                        $query->where('actif', true)
                            ->where('date_debut', '<=', now())
                            ->where('date_fin', '>=', now())
                            ->orderBy('created_at', 'desc');
                    },
                    'abonnements' => function ($query) {
                        $query->where('statut', \App\Enums\StatutAbonnement::ACTIF->value)
                            ->where('date_debut', '<=', now())
                            ->where('date_fin', '>=', now())
                            ->with(['tokenActif']);
                    }
                ])
                ->first();

            if (!$sirene) {
                return $this->notFoundResponse('Sirène non trouvée pour ce numéro de série.');
            }

            // Vérifier l'authentification par token crypté si fourni
            if ($tokenCrypte) {
                $abonnementActif = $sirene->abonnements->first();

                if (!$abonnementActif) {
                    Log::warning("Tentative d'accès sans abonnement actif", [
                        'numero_serie' => $numeroSerie,
                    ]);
                    return $this->errorResponse('Aucun abonnement actif pour cette sirène.', 401);
                }

                $tokenActif = $abonnementActif->tokenActif;

                if (!$tokenActif) {
                    Log::warning("Tentative d'accès sans token actif", [
                        'numero_serie' => $numeroSerie,
                        'abonnement_id' => $abonnementActif->id,
                    ]);
                    return $this->errorResponse('Aucun token actif trouvé.', 401);
                }

                // Vérifier que le token correspond
                $tokenHash = hash('sha256', $tokenCrypte);
                if ($tokenHash !== $tokenActif->token_hash) {
                    Log::warning("Token crypté invalide", [
                        'numero_serie' => $numeroSerie,
                        'token_hash_fourni' => $tokenHash,
                        'token_hash_attendu' => $tokenActif->token_hash,
                    ]);
                    return $this->errorResponse('Token d\'authentification invalide.', 401);
                }

                // Vérifier que le token n'est pas expiré
                if ($tokenActif->date_expiration < now()) {
                    Log::warning("Token expiré", [
                        'numero_serie' => $numeroSerie,
                        'date_expiration' => $tokenActif->date_expiration->toIso8601String(),
                    ]);
                    return $this->errorResponse('Token expiré.', 401);
                }

                Log::info("Authentification ESP8266 réussie", [
                    'numero_serie' => $numeroSerie,
                    'abonnement_id' => $abonnementActif->id,
                    'token_id' => $tokenActif->id,
                ]);
            }

            // Vérifier qu'il y a une programmation active
            $programmation = $sirene->programmations->first();
            if (!$programmation) {
                return $this->notFoundResponse('Aucune programmation active trouvée pour cette sirène.');
            }

            // Retourner les données de programmation
            return $this->successResponse(null, [
                'chaine_cryptee' => $programmation->chaine_cryptee,
                'version' => '01',
                'date_generation' => $programmation->updated_at->format('Y-m-d H:i:s'),
            ]);

        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::getProgrammationByNumeroSerie - " . $e->getMessage(), [
                'numero_serie' => $numeroSerie,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Erreur lors de la récupération de la programmation.', 500);
        }
    }
}
