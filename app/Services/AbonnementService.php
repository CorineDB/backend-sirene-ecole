<?php

namespace App\Services;

use App\Enums\StatutAbonnement;
use App\Repositories\Contracts\AbonnementRepositoryInterface;
use App\Services\Contracts\AbonnementServiceInterface;
use App\Traits\FiltersByEcole;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class AbonnementService extends BaseService implements AbonnementServiceInterface
{
    use FiltersByEcole;

    public function __construct(AbonnementRepositoryInterface $abonnementRepository)
    {
        parent::__construct($abonnementRepository);
    }

    /**
     * Surcharge de getAll pour filtrer par école si nécessaire
     */
    public function getAll(int $perPage = 15, array $relations = []): JsonResponse
    {
        try {
            $query = $this->repository->query();

            // Appliquer le filtre école si l'utilisateur est une école
            $query = $this->applyEcoleFilterForAbonnements($query);

            if (!empty($relations)) {
                $query->with($relations);
            }

            $data = $query->paginate($perPage);
            return $this->successResponse('Données récupérées avec succès.', $data);
        } catch (Exception $e) {
            Log::error("Error in AbonnementService::getAll - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Surcharge de getById pour vérifier l'accès si école
     */
    public function getById(string $id, array $columns = ['*'], array $relations = []): JsonResponse
    {
        try {
            $query = $this->repository->query()->where('id', $id);

            // Appliquer le filtre école si l'utilisateur est une école
            $query = $this->applyEcoleFilterForAbonnements($query);

            if (!empty($relations)) {
                $query->with($relations);
            }

            $data = $query->first($columns);

            if (!$data) {
                return $this->errorResponse('Abonnement non trouvé ou accès non autorisé.', 404);
            }

            return $this->successResponse('Donnée récupérée avec succès.', $data);
        } catch (Exception $e) {
            Log::error("Error in AbonnementService::getById - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ========== 1. CRÉATION D'ABONNEMENT ==========

    /**
     * Créer un nouvel abonnement
     * Les validations métier sont gérées par les hooks Eloquent (creating, updating)
     */
    public function create(array $data): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Définir le statut par défaut
            if (empty($data['statut'])) {
                $data['statut'] = StatutAbonnement::EN_ATTENTE;
            }

            // Créer l'abonnement (les validations sont gérées par le hook creating)
            $abonnement = $this->repository->create($data);

            // Mettre à jour le statut de la sirène
            $abonnement->updateSireneStatus();

            // Note: Le QR code est généré automatiquement via HasQrCodeAbonnement boot hook

            // Si l'abonnement est créé directement comme ACTIF (avec paiement validé)
            if ($abonnement->statut === StatutAbonnement::ACTIF) {
                $abonnement->activate();
            }

            DB::commit();
            return $this->successResponse('Abonnement créé avec succès.', $abonnement->load(['sirene', 'ecole', 'site']));

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in AbonnementService::create - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    // ========== 2. GESTION DU CYCLE DE VIE ==========

    /**
     * Renouveler un abonnement
     * Les validations métier sont gérées par les hooks Eloquent (creating)
     */
    public function renouvelerAbonnement(string $abonnementId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $abonnement = $this->repository->find($abonnementId, relations: ['sirene', 'ecole']);
            if (!$abonnement) {
                DB::rollBack();
                return $this->notFoundResponse('Abonnement non trouvé.');
            }

            // Créer le nouvel abonnement (les validations sont gérées par le hook creating)
            // Le hook vérifiera automatiquement que le parent peut être renouvelé
            // et qu'il n'y a pas déjà un abonnement actif/en attente/suspendu pour la sirène
            $nouveauAbonnement = $this->repository->create([
                'ecole_id' => $abonnement->ecole_id,
                'site_id' => $abonnement->site_id,
                'sirene_id' => $abonnement->sirene_id,
                'parent_abonnement_id' => $abonnement->id,
                'date_debut' => Carbon::parse($abonnement->date_fin)->addDay(),
                'date_fin' => Carbon::parse($abonnement->date_fin)->addYear()->addDay(),
                'montant' => $abonnement->montant,
                'statut' => StatutAbonnement::EN_ATTENTE,
                'auto_renouvellement' => $abonnement->auto_renouvellement,
            ]);

            // Mettre à jour le statut de la sirène (QR code généré automatiquement via boot hook)
            $nouveauAbonnement->updateSireneStatus();

            DB::commit();
            return $this->successResponse('Abonnement renouvelé avec succès.', $nouveauAbonnement);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in AbonnementService::renouvelerAbonnement - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Suspendre un abonnement
     * Les validations métier sont gérées par le hook updating
     */
    public function suspendre(string $abonnementId, string $raison): JsonResponse
    {
        try {
            DB::beginTransaction();

            $abonnement = $this->repository->find($abonnementId);
            if (!$abonnement) {
                DB::rollBack();
                return $this->notFoundResponse('Abonnement non trouvé.');
            }

            // Mettre à jour le statut (la validation est gérée par le hook updating)
            $this->repository->update($abonnementId, [
                'statut' => StatutAbonnement::SUSPENDU,
                'notes' => ($abonnement->notes ? $abonnement->notes . "\n" : '') .
                          "[" . now()->format('Y-m-d H:i:s') . "] Suspendu: " . $raison
            ]);

            // Recharger l'abonnement
            $abonnement = $this->repository->find($abonnementId);

            // Gérer la suspension (expirer les tokens)
            $abonnement->suspend();

            DB::commit();
            return $this->successResponse('Abonnement suspendu avec succès.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in AbonnementService::suspendre - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Réactiver un abonnement suspendu
     * Les validations métier sont gérées par le hook updating
     */
    public function reactiver(string $abonnementId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $abonnement = $this->repository->find($abonnementId);
            if (!$abonnement) {
                DB::rollBack();
                return $this->notFoundResponse('Abonnement non trouvé.');
            }

            // Validation manuelle pour réactivation (car le hook ne vérifie que ACTIF depuis SUSPENDU)
            if (!$abonnement->canBeReactivated()) {
                DB::rollBack();
                return $this->errorResponse(
                    'Cet abonnement ne peut pas être réactivé. ' .
                    'Seuls les abonnements suspendus et non expirés peuvent être réactivés.',
                    422
                );
            }

            // Mettre à jour le statut (la validation du changement SUSPENDU -> ACTIF est permise par le hook)
            $this->repository->update($abonnementId, [
                'statut' => StatutAbonnement::ACTIF,
                'notes' => ($abonnement->notes ? $abonnement->notes . "\n" : '') .
                          "[" . now()->format('Y-m-d H:i:s') . "] Réactivé"
            ]);

            // Recharger l'abonnement
            $abonnement = $this->repository->find($abonnementId);

            // Gérer la réactivation (token + sirène)
            $abonnement->activate();

            DB::commit();
            return $this->successResponse('Abonnement réactivé avec succès.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in AbonnementService::reactiver - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Annuler un abonnement
     * Les validations métier sont gérées par le hook updating
     */
    public function annuler(string $abonnementId, string $raison): JsonResponse
    {
        try {
            DB::beginTransaction();

            $abonnement = $this->repository->find($abonnementId);
            if (!$abonnement) {
                DB::rollBack();
                return $this->notFoundResponse('Abonnement non trouvé.');
            }

            // Mettre à jour le statut (la validation est gérée par le hook updating)
            $this->repository->update($abonnementId, [
                'statut' => StatutAbonnement::ANNULE,
                'date_fin' => now(),
                'notes' => ($abonnement->notes ? $abonnement->notes . "\n" : '') .
                          "[" . now()->format('Y-m-d H:i:s') . "] Annulé: " . $raison
            ]);

            // Recharger l'abonnement
            $abonnement = $this->repository->find($abonnementId);

            // Gérer l'annulation (expirer tokens + mettre à jour sirène)
            $abonnement->cancel();

            DB::commit();
            return $this->successResponse('Abonnement annulé avec succès.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in AbonnementService::annuler - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Activer un abonnement après validation du paiement
     * Les validations métier sont gérées par le hook updating
     */
    public function activerAbonnement(string $abonnementId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $abonnement = $this->repository->find($abonnementId, relations: ['paiements']);
            if (!$abonnement) {
                DB::rollBack();
                return $this->notFoundResponse('Abonnement non trouvé.');
            }

            // Validation: vérifier que l'abonnement est en attente
            if ($abonnement->statut !== StatutAbonnement::EN_ATTENTE) {
                DB::rollBack();
                return $this->errorResponse(
                    'Seuls les abonnements en attente peuvent être activés.',
                    422
                );
            }

            // Mettre à jour le statut (le hook vérifiera qu'un paiement validé existe)
            $this->repository->update($abonnementId, [
                'statut' => StatutAbonnement::ACTIF,
                'notes' => ($abonnement->notes ? $abonnement->notes . "\n" : '') .
                          "[" . now()->format('Y-m-d H:i:s') . "] Activé après paiement validé"
            ]);

            // Recharger l'abonnement
            $abonnement = $this->repository->find($abonnementId);

            // Gérer l'activation (générer token + mettre à jour sirène)
            $abonnement->activate();

            DB::commit();
            return $this->successResponse('Abonnement activé avec succès.', $abonnement->load(['token', 'sirene']));

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in AbonnementService::activerAbonnement - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    // ========== 3. RECHERCHE ET FILTRAGE ==========

    public function getAbonnementActif(string $ecoleId): JsonResponse
    {
        try {
            $abonnement = $this->repository->findBy([
                'ecole_id' => $ecoleId,
                'statut' => StatutAbonnement::ACTIF
            ], relations: ['sirene', 'site', 'token']);

            if (!$abonnement) {
                return $this->notFoundResponse('Aucun abonnement actif trouvé pour cette école.');
            }

            return $this->successResponse(null, $abonnement);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::getAbonnementActif - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getAbonnementsByEcole(string $ecoleId): JsonResponse
    {
        try {
            $abonnements = $this->repository->findAllBy(['ecole_id' => $ecoleId], relations: ['sirene', 'site', 'paiements']);
            return $this->successResponse(null, $abonnements);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::getAbonnementsByEcole - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getAbonnementsBySirene(string $sireneId): JsonResponse
    {
        try {
            $abonnements = $this->repository->findAllBy(['sirene_id' => $sireneId], relations: ['ecole', 'site', 'paiements']);
            return $this->successResponse(null, $abonnements);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::getAbonnementsBySirene - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getExpirantBientot(int $jours = 30): JsonResponse
    {
        try {
            $dateLimit = Carbon::now()->addDays($jours);

            $abonnements = $this->repository->model
                ->where('statut', StatutAbonnement::ACTIF)
                ->where('date_fin', '<=', $dateLimit)
                ->where('date_fin', '>=', now())
                ->with(['ecole', 'sirene', 'site'])
                ->get();

            return $this->successResponse(null, $abonnements);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::getExpirantBientot - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getExpires(): JsonResponse
    {
        try {
            $abonnements = $this->repository->model
                ->where('statut', StatutAbonnement::ACTIF)
                ->where('date_fin', '<', now())
                ->with(['ecole', 'sirene', 'site'])
                ->get();

            return $this->successResponse(null, $abonnements);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::getExpires - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getActifs(): JsonResponse
    {
        try {
            $abonnements = $this->repository->findAllBy(['statut' => StatutAbonnement::ACTIF], relations: ['ecole', 'sirene', 'site']);
            return $this->successResponse(null, $abonnements);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::getActifs - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getEnAttente(): JsonResponse
    {
        try {
            $abonnements = $this->repository->findAllBy(['statut' => StatutAbonnement::EN_ATTENTE], relations: ['ecole', 'sirene', 'site']);
            return $this->successResponse(null, $abonnements);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::getEnAttente - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ========== 3. VÉRIFICATIONS ET VALIDATIONS ==========

    public function estValide(string $abonnementId): JsonResponse
    {
        try {
            $abonnement = $this->repository->find($abonnementId);
            if (!$abonnement) {
                return $this->notFoundResponse('Abonnement non trouvé.');
            }

            $valide = $abonnement->statut === StatutAbonnement::ACTIF &&
                     $abonnement->date_fin >= now();

            return $this->successResponse(null, ['valide' => $valide]);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::estValide - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function ecoleAAbonnementActif(string $ecoleId): JsonResponse
    {
        try {
            $existe = $this->repository->exists([
                'ecole_id' => $ecoleId,
                'statut' => StatutAbonnement::ACTIF
            ]);

            return $this->successResponse(null, ['a_abonnement_actif' => $existe]);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::ecoleAAbonnementActif - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function peutEtreRenouvele(string $abonnementId): JsonResponse
    {
        try {
            $abonnement = $this->repository->find($abonnementId);
            if (!$abonnement) {
                return $this->notFoundResponse('Abonnement non trouvé.');
            }

            // Peut être renouvelé si actif ou expiré depuis moins de 30 jours
            $peutRenouveler = in_array($abonnement->statut, [StatutAbonnement::ACTIF, StatutAbonnement::EXPIRE]) &&
                             $abonnement->date_fin >= now()->subDays(30);

            return $this->successResponse(null, ['peut_etre_renouvele' => $peutRenouveler]);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::peutEtreRenouvele - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ========== 4. TÂCHES AUTOMATIQUES ==========

    public function marquerExpires(): JsonResponse
    {
        try {
            DB::beginTransaction();

            $count = $this->repository->model
                ->where('statut', StatutAbonnement::ACTIF)
                ->where('date_fin', '<', now())
                ->update(['statut' => StatutAbonnement::EXPIRE]);

            DB::commit();
            return $this->successResponse("$count abonnements marqués comme expirés.", ['count' => $count]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in AbonnementService::marquerExpires - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function envoyerNotificationsExpiration(): JsonResponse
    {
        try {
            // Récupérer les abonnements expirant dans 7, 15 et 30 jours
            $abonnements = $this->repository->model
                ->where('statut', StatutAbonnement::ACTIF)
                ->whereIn('date_fin', [
                    now()->addDays(7)->format('Y-m-d'),
                    now()->addDays(15)->format('Y-m-d'),
                    now()->addDays(30)->format('Y-m-d')
                ])
                ->with(['ecole'])
                ->get();

            // TODO: Envoyer les notifications (email, SMS, etc.)
            $count = $abonnements->count();

            return $this->successResponse("$count notifications d'expiration envoyées.", ['count' => $count]);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::envoyerNotificationsExpiration - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function autoRenouveler(): JsonResponse
    {
        try {
            DB::beginTransaction();

            $abonnements = $this->repository->model
                ->where('statut', StatutAbonnement::ACTIF)
                ->where('auto_renouvellement', true)
                ->where('date_fin', '<=', now()->addDays(7))
                ->get();

            $count = 0;
            foreach ($abonnements as $abonnement) {
                $this->renouvelerAbonnement($abonnement->id);
                $count++;
            }

            DB::commit();
            return $this->successResponse("$count abonnements auto-renouvelés.", ['count' => $count]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in AbonnementService::autoRenouveler - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ========== 5. STATISTIQUES ==========

    public function getStatistiques(): JsonResponse
    {
        try {
            $stats = [
                'total' => $this->repository->count(),
                'actifs' => $this->repository->count(['statut' => StatutAbonnement::ACTIF]),
                'en_attente' => $this->repository->count(['statut' => StatutAbonnement::EN_ATTENTE]),
                'expires' => $this->repository->count(['statut' => StatutAbonnement::EXPIRE]),
                'annules' => $this->repository->count(['statut' => StatutAbonnement::ANNULE]),
                'suspendus' => $this->repository->count(['statut' => StatutAbonnement::SUSPENDU]),
                'expirant_7j' => $this->repository->model
                    ->where('statut', StatutAbonnement::ACTIF)
                    ->where('date_fin', '<=', now()->addDays(7))
                    ->where('date_fin', '>=', now())
                    ->count(),
                'revenus_mois' => $this->repository->model
                    ->join('paiements', 'abonnements.id', '=', 'paiements.abonnement_id')
                    ->where('paiements.statut', 'valide')
                    ->whereMonth('paiements.date_paiement', now()->month)
                    ->whereYear('paiements.date_paiement', now()->year)
                    ->sum('paiements.montant'),
            ];

            return $this->successResponse(null, $stats);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::getStatistiques - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getRevenusPeriode(string $dateDebut, string $dateFin): JsonResponse
    {
        try {
            $revenus = $this->repository->model
                ->join('paiements', 'abonnements.id', '=', 'paiements.abonnement_id')
                ->where('paiements.statut', 'valide')
                ->whereBetween('paiements.date_paiement', [$dateDebut, $dateFin])
                ->sum('paiements.montant');

            return $this->successResponse(null, ['revenus' => $revenus, 'periode' => compact('dateDebut', 'dateFin')]);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::getRevenusPeriode - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getTauxRenouvellement(): JsonResponse
    {
        try {
            $totalExpires = $this->repository->model
                ->where('date_fin', '<', now())
                ->where('date_fin', '>=', now()->subMonths(3))
                ->count();

            $renouveles = $this->repository->model
                ->whereNotNull('parent_abonnement_id')
                ->whereHas('parentAbonnement', function($query) {
                    $query->where('date_fin', '<', now())
                          ->where('date_fin', '>=', now()->subMonths(3));
                })
                ->count();

            $taux = $totalExpires > 0 ? ($renouveles / $totalExpires) * 100 : 0;

            return $this->successResponse(null, [
                'taux_renouvellement' => round($taux, 2),
                'total_expires' => $totalExpires,
                'renouveles' => $renouveles
            ]);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::getTauxRenouvellement - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ========== 6. CALCULS ==========

    public function calculerPrixRenouvellement(string $abonnementId): JsonResponse
    {
        try {
            $abonnement = $this->repository->find($abonnementId);
            if (!$abonnement) {
                return $this->notFoundResponse('Abonnement non trouvé.');
            }

            // Prix de base = prix actuel
            $prix = $abonnement->montant;

            // Réduction si renouvellement anticipé (> 30 jours avant expiration)
            if ($abonnement->date_fin > now()->addDays(30)) {
                $prix = $prix * 0.95; // 5% de réduction
            }

            return $this->successResponse(null, ['prix_renouvellement' => $prix]);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::calculerPrixRenouvellement - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getJoursRestants(string $abonnementId): JsonResponse
    {
        try {
            $abonnement = $this->repository->find($abonnementId);
            if (!$abonnement) {
                return $this->notFoundResponse('Abonnement non trouvé.');
            }

            $joursRestants = max(0, now()->diffInDays($abonnement->date_fin, false));

            return $this->successResponse(null, [
                'jours_restants' => $joursRestants,
                'date_fin' => $abonnement->date_fin->format('Y-m-d')
            ]);

        } catch (Exception $e) {
            Log::error("Error in AbonnementService::getJoursRestants - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ========== OVERRIDE UPDATE FROM BASESERVICE ==========

    /**
     * Mettre à jour un abonnement
     * Les validations de changement de statut sont gérées par le hook updating
     */
    public function update(string $id, array $data): JsonResponse
    {
        try {
            DB::beginTransaction();

            $abonnement = $this->repository->find($id);
            if (!$abonnement) {
                DB::rollBack();
                return $this->notFoundResponse('Abonnement non trouvé.');
            }

            // Validation métier : ne pas permettre de modifier un abonnement expiré
            if ($abonnement->statut === StatutAbonnement::EXPIRE && !isset($data['statut'])) {
                DB::rollBack();
                return $this->errorResponse('Impossible de modifier un abonnement expiré.', 422);
            }

            // Ajouter une note de modification si des champs importants changent
            if (isset($data['date_debut']) || isset($data['date_fin']) || isset($data['montant']) || isset($data['statut'])) {
                $modifications = [];
                if (isset($data['date_debut']) && $data['date_debut'] != $abonnement->date_debut) {
                    $modifications[] = "Date début: {$abonnement->date_debut} → {$data['date_debut']}";
                }
                if (isset($data['date_fin']) && $data['date_fin'] != $abonnement->date_fin) {
                    $modifications[] = "Date fin: {$abonnement->date_fin} → {$data['date_fin']}";
                }
                if (isset($data['montant']) && $data['montant'] != $abonnement->montant) {
                    $modifications[] = "Montant: {$abonnement->montant} → {$data['montant']}";
                }
                if (isset($data['statut']) && $data['statut'] != $abonnement->statut->value) {
                    $modifications[] = "Statut: {$abonnement->statut->value} → {$data['statut']}";
                }

                if (!empty($modifications)) {
                    $data['notes'] = ($abonnement->notes ? $abonnement->notes . "\n" : '') .
                                    "[" . now()->format('Y-m-d H:i:s') . "] Modifié: " . implode(', ', $modifications);
                }
            }

            // Mettre à jour (les validations de changement de statut sont gérées par le hook)
            $updated = $this->repository->update($id, $data);

            DB::commit();
            return $this->successResponse('Abonnement modifié avec succès.', $updated);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in AbonnementService::update - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    // ========== 7. GESTION DES TOKENS ==========

    public function regenererToken(string $abonnementId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $abonnement = $this->repository->find($abonnementId, relations: ['sirene', 'ecole', 'site', 'paiements', 'tokenActif']);
            if (!$abonnement) {
                DB::rollBack();
                return $this->notFoundResponse('Abonnement non trouvé.');
            }

            // Vérifier que l'abonnement est actif
            if ($abonnement->statut !== StatutAbonnement::ACTIF) {
                DB::rollBack();
                return $this->errorResponse('Seuls les abonnements actifs peuvent avoir leur token régénéré.', 422);
            }

            // Vérifier qu'un paiement validé existe
            $paiementValide = $abonnement->paiements()
                ->where('statut', 'valide')
                ->exists();

            if (!$paiementValide) {
                DB::rollBack();
                return $this->errorResponse('Impossible de régénérer le token sans paiement validé.', 422);
            }

            // Régénérer le token via la méthode du trait
            $abonnement->regenererToken();

            // Récupérer le nouveau token actif
            $abonnement->load('tokenActif');
            $token = $abonnement->tokenActif;

            if (!$token) {
                DB::rollBack();
                return $this->errorResponse('Erreur lors de la génération du token. Consultez les logs pour plus de détails.', 500);
            }

            DB::commit();

            return $this->successResponse('Token régénéré avec succès.', [
                'token_id' => $token->id,
                'date_generation' => $token->date_generation->toIso8601String(),
                'date_expiration' => $token->date_expiration->toIso8601String(),
                'actif' => $token->actif,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in AbonnementService::regenererToken - " . $e->getMessage(), [
                'abonnement_id' => $abonnementId,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Erreur lors de la régénération du token.', 500);
        }
    }

    // ========== HELPERS PRIVÉS ==========
    // Note: La génération du numéro d'abonnement est gérée automatiquement par HasNumeroAbonnement trait
}
