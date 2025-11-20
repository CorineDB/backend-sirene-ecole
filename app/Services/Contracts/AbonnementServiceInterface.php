<?php

namespace App\Services\Contracts;

use Illuminate\Http\JsonResponse;

interface AbonnementServiceInterface extends BaseServiceInterface
{
    // Override de BaseServiceInterface avec validation métier
    public function update(string $id, array $data): JsonResponse;

    // 1. Gestion du cycle de vie
    public function renouvelerAbonnement(string $abonnementId): JsonResponse;
    public function suspendre(string $abonnementId, string $raison): JsonResponse;
    public function reactiver(string $abonnementId): JsonResponse;
    public function annuler(string $abonnementId, string $raison): JsonResponse;

    // 2. Recherche et filtrage
    public function getAbonnementActif(string $ecoleId): JsonResponse;
    public function getAbonnementsByEcole(string $ecoleId): JsonResponse;
    public function getAbonnementsBySirene(string $sireneId): JsonResponse;
    public function getExpirantBientot(int $jours = 30): JsonResponse;
    public function getExpires(): JsonResponse;
    public function getActifs(): JsonResponse;
    public function getEnAttente(): JsonResponse;

    // 3. Vérifications et validations
    public function estValide(string $abonnementId): JsonResponse;
    public function ecoleAAbonnementActif(string $ecoleId): JsonResponse;
    public function peutEtreRenouvele(string $abonnementId): JsonResponse;

    // 4. Tâches automatiques
    public function marquerExpires(): JsonResponse;
    public function envoyerNotificationsExpiration(): JsonResponse;
    public function autoRenouveler(): JsonResponse;

    // 5. Statistiques
    public function getStatistiques(): JsonResponse;
    public function getRevenusPeriode(string $dateDebut, string $dateFin): JsonResponse;
    public function getTauxRenouvellement(): JsonResponse;

    // 6. Calculs
    public function calculerPrixRenouvellement(string $abonnementId): JsonResponse;
    public function getJoursRestants(string $abonnementId): JsonResponse;

    // 7. Gestion des tokens
    public function regenererToken(string $abonnementId): JsonResponse;
}
