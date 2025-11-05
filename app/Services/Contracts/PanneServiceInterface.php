<?php

namespace App\Services\Contracts;

use App\Models\Panne;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

interface PanneServiceInterface extends BaseServiceInterface
{
    /**
     * Valider une panne (Admin)
     */
    //public function validate(string $panneId, string $validateur): Panne;

    /**
     * Assigner une panne à un technicien
     */
    //public function assign(string $panneId, string $technicienId): Panne;

    /**
     * Marquer une panne comme en cours
     */
    //public function markInProgress(string $panneId): Panne;

    /**
     * Marquer une panne comme résolue
     */
    //public function markResolved(string $panneId, array $rapportData): Panne;

    /**
     * Fermer une panne
     */
    //public function close(string $panneId): Panne;

    /**
     * Annuler une panne
     */
    //public function cancel(string $panneId, string $raison): Panne;

    /**
     * Récupérer les pannes d'une école
     */
    //public function getByEcole(string $ecoleId): Collection;

    /**
     * Récupérer les pannes d'une sirène
     */
    //public function getBySirene(string $sireneId): Collection;

    /**
     * Récupérer les pannes par statut
     */
    //public function getByStatut(string $statut): Collection;

    /**
     * Récupérer les pannes par priorité
     */
    //public function getByPriorite(string $priorite): Collection;

    /**
     * Récupérer les pannes non assignées
     */
    //public function getUnassigned(): Collection;

    /**
     * Récupérer les pannes d'une ville
     */
    //public function getByVille(string $villeId): Collection;

    /**
     * Récupérer les pannes d'un technicien
     */
    //public function getByTechnicien(string $technicienId): Collection;

    /**
     * Notifier les techniciens d'une nouvelle panne
     */
    //public function notifyTechniciens(Panne $panne): bool;

    /**
     * Obtenir les statistiques des pannes
     */
    //public function getStatistics(array $filters = []): array;

    /**
     * Rechercher des pannes
     */
    //public function search(string $query, array $filters = []): LengthAwarePaginator;

    public function validerPanne(string $panneId, string $adminId, array $ordreMissionData = []): JsonResponse;
    public function cloturerPanne(string $panneId): JsonResponse;
}
