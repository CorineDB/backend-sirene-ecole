<?php

namespace App\Services\Contracts;

use Illuminate\Http\JsonResponse;

interface DashboardServiceInterface
{
    /**
     * Obtenir les statistiques dashboard pour une école
     */
    public function getStatistiquesEcole(): JsonResponse;

    /**
     * Obtenir les statistiques dashboard pour un technicien
     */
    public function getStatistiquesTechnicien(): JsonResponse;

    /**
     * Récupérer les interventions en cours avec filtres optionnels
     */
    public function getInterventionsEnCours(array $filters, ?int $perPage): JsonResponse;

    /**
     * Récupérer les interventions du jour
     */
    public function getInterventionsDuJour(?int $perPage): JsonResponse;

    /**
     * Récupérer les interventions à venir
     */
    public function getInterventionsAVenir(?int $perPage): JsonResponse;

    /**
     * Récupérer les ordres de mission disponibles pour candidature
     */
    public function getOrdresMissionDisponibles(?int $perPage): JsonResponse;
}
