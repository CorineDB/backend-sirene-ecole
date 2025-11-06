<?php

use App\Http\Controllers\API\AbonnementController;
use App\Http\Controllers\API\PaiementController;
use App\Http\Controllers\API\PermissionController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EcoleController;
use App\Http\Controllers\Api\SireneController;
use App\Http\Controllers\Api\CinetPayController;
use App\Http\Controllers\API\TechnicienController;
use App\Http\Controllers\API\PanneController;
use App\Http\Controllers\API\InterventionController;
use App\Http\Controllers\API\OrdreMissionController;
use App\Http\Controllers\Api\CalendrierScolaireController;
use App\Http\Controllers\Api\JourFerieController;
use App\Http\Controllers\ProgrammationController;
use App\Http\Controllers\Api\UserController;
use App\Models\Pays;
use App\Models\Ville;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get("/villes", function(Request $request){
    return Ville::all();
});
Route::get("/pays", function(Request $request){
    return Pays::all();
});

Route::prefix('permissions')->group(function () {
    Route::get('/', [PermissionController::class, 'index']);
    Route::get('{id}', [PermissionController::class, 'show']);
    Route::get('slug/{slug}', [PermissionController::class, 'showBySlug']);
    Route::get('role/{roleId}', [PermissionController::class, 'showByRole']);
});

Route::prefix('roles')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::get('{id}', [RoleController::class, 'show']);
    Route::post('/', [RoleController::class, 'store']);
    Route::put('{id}', [RoleController::class, 'update']);
    Route::delete('{id}', [RoleController::class, 'destroy']);
});

Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('{id}', [UserController::class, 'show']);
    Route::post('/', [UserController::class, 'store']);
    Route::put('{id}', [UserController::class, 'update']);
    Route::delete('{id}', [UserController::class, 'destroy']);
});

// Authentication routes (public)
Route::prefix('auth')->group(function () {
    Route::post('request-otp', [AuthController::class, 'requestOtp']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected authentication routes
Route::prefix('auth')->middleware('auth:api')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('changerMotDePasse', [AuthController::class, 'changerMotDePasse']);
    Route::get('me', [AuthController::class, 'me']);
});

// Ecole routes
Route::prefix('ecoles')->group(function () {
    // Public: Inscription
    Route::post('inscription', [EcoleController::class, 'inscrire']);

    // Protected routes for Ecole management
    Route::middleware('auth:api')->group(function () {
        Route::get('/', [EcoleController::class, 'index']); // List all schools
        Route::get('me', [EcoleController::class, 'show']); // Get authenticated school details
        Route::put('me', [EcoleController::class, 'update']); // Update authenticated school details
        Route::delete('{id}', [EcoleController::class, 'destroy']); // Delete a school by ID

        // School-specific holidays
        Route::get('{ecoleId}/jours-feries', [JourFerieController::class, 'indexForEcole']);
        Route::get('{ecoleId}/abonnements', [AbonnementController::class, 'parEcole']);
        Route::post('{ecoleId}/abonnements/{abonnementId}', [PaiementController::class, 'traiter']);
        Route::post('{ecoleId}/jours-feries', [JourFerieController::class, 'storeForEcole']);

        // School calendar with merged holidays
        Route::get('me/calendrier-scolaire/with-ecole-holidays', [EcoleController::class, 'getCalendrierScolaireWithJoursFeries']);
    });
});

// Sirene routes (Protected - Admin/Technicien)
Route::prefix('sirenes')->middleware('auth:api')->group(function () {
    Route::get('/', [SireneController::class, 'index']);
    Route::get('disponibles', [SireneController::class, 'disponibles']);
    Route::get('numero-serie/{numeroSerie}', [SireneController::class, 'showByNumeroSerie']);
    Route::get('{id}', [SireneController::class, 'show']);
    Route::post('/', [SireneController::class, 'store']); // Admin only
    Route::put('{id}', [SireneController::class, 'update']); // Admin/Technicien
    Route::post('{id}/affecter', [SireneController::class, 'affecter']); // Admin/Technicien
    Route::delete('{id}', [SireneController::class, 'destroy']); // Admin only

    // Programmations for a sirene
    Route::apiResource('{sirene}/programmations', ProgrammationController::class);
});

// Technicien routes (Protected)
Route::prefix('techniciens')->middleware('auth:api')->group(function () {
    Route::get('/', [TechnicienController::class, 'index']);
    Route::post('/', [TechnicienController::class, 'store']);
    Route::get('{id}', [TechnicienController::class, 'show']);
    Route::put('{id}', [TechnicienController::class, 'update']);
    Route::delete('{id}', [TechnicienController::class, 'destroy']);
});

// CalendrierScolaire routes (Protected)
Route::prefix('calendrier-scolaire')->middleware('auth:api')->group(function () {
    Route::get('/', [CalendrierScolaireController::class, 'index']);
    Route::post('/', [CalendrierScolaireController::class, 'store']);
    Route::get('{id}', [CalendrierScolaireController::class, 'show']);
    Route::put('{id}', [CalendrierScolaireController::class, 'update']);
    Route::delete('{id}', [CalendrierScolaireController::class, 'destroy']);
    Route::get('{id}/jours-feries', [CalendrierScolaireController::class, 'getJoursFeries']);
    Route::get('{id}/calculate-school-days', [CalendrierScolaireController::class, 'calculateSchoolDays']);

    // Bulk operations for jours fériés
    Route::post('{id}/jours-feries/bulk', [CalendrierScolaireController::class, 'storeMultipleJoursFeries']);
    Route::put('{id}/jours-feries/bulk', [CalendrierScolaireController::class, 'updateMultipleJoursFeries']);
});

// JoursFeries routes (Protected)
Route::prefix('jours-feries')->middleware('auth:api')->group(function () {
    Route::get('/', [JourFerieController::class, 'index']);
    Route::post('/', [JourFerieController::class, 'store']);
    Route::get('{id}', [JourFerieController::class, 'show']);
    Route::put('{id}', [JourFerieController::class, 'update']);
    Route::delete('{id}', [JourFerieController::class, 'destroy']);
});

// Abonnement routes
Route::prefix('abonnements')->group(function () {
    // Public: Accès via QR Code
    Route::get('{id}/details', [AbonnementController::class, 'details']);
    Route::get('{id}/paiement', [AbonnementController::class, 'paiement']);

    // Protected routes
    Route::middleware('auth:api')->group(function () {
        // CRUD de base
        Route::get('/', [AbonnementController::class, 'index']);
        Route::get('{id}', [AbonnementController::class, 'show']);
        Route::put('{id}', [AbonnementController::class, 'update']);
        Route::delete('{id}', [AbonnementController::class, 'destroy']);

        // Gestion du cycle de vie
        Route::post('{id}/renouveler', [AbonnementController::class, 'renouveler']);
        Route::post('{id}/suspendre', [AbonnementController::class, 'suspendre']);
        Route::post('{id}/reactiver', [AbonnementController::class, 'reactiver']);
        Route::post('{id}/annuler', [AbonnementController::class, 'annuler']);

        // Recherche
        Route::get('ecole/{ecoleId}/actif', [AbonnementController::class, 'getActif']);
        Route::get('ecole/{ecoleId}', [AbonnementController::class, 'parEcole']);
        Route::get('sirene/{sireneId}', [AbonnementController::class, 'parSirene']);
        Route::get('liste/expirant-bientot', [AbonnementController::class, 'expirantBientot']);
        Route::get('liste/expires', [AbonnementController::class, 'expires']);
        Route::get('liste/actifs', [AbonnementController::class, 'actifs']);
        Route::get('liste/en-attente', [AbonnementController::class, 'enAttente']);

        // Vérifications
        Route::get('{id}/est-valide', [AbonnementController::class, 'estValide']);
        Route::get('ecole/{ecoleId}/a-abonnement-actif', [AbonnementController::class, 'ecoleAAbonnementActif']);
        Route::get('{id}/peut-etre-renouvele', [AbonnementController::class, 'peutEtreRenouvele']);

        // Statistiques (Admin)
        Route::get('stats/global', [AbonnementController::class, 'statistiques']);
        Route::get('stats/revenus-periode', [AbonnementController::class, 'revenusPeriode']);
        Route::get('stats/taux-renouvellement', [AbonnementController::class, 'tauxRenouvellement']);

        // Calculs
        Route::get('{id}/prix-renouvellement', [AbonnementController::class, 'prixRenouvellement']);
        Route::get('{id}/jours-restants', [AbonnementController::class, 'joursRestants']);

        // Tâches automatiques (CRON - Admin only)
        Route::post('cron/marquer-expires', [AbonnementController::class, 'marquerExpires']);
        Route::post('cron/envoyer-notifications', [AbonnementController::class, 'envoyerNotifications']);
        Route::post('cron/auto-renouveler', [AbonnementController::class, 'autoRenouveler']);
    });
});

// Paiement routes
Route::prefix('paiements')->group(function () {
    // Public: Traiter un paiement via QR Code
    Route::post('abonnements/{abonnementId}', [PaiementController::class, 'traiter']);

    // Protected routes
    Route::middleware('auth:api')->group(function () {
        Route::get('/', [PaiementController::class, 'index']);
        Route::get('{id}', [PaiementController::class, 'show']);
        Route::put('{id}/valider', [PaiementController::class, 'valider']);
        Route::get('abonnements/{abonnementId}', [PaiementController::class, 'parAbonnement']);
    });
});

// CinetPay Payment Gateway routes
Route::prefix('cinetpay')->group(function () {
    // Callback de notification (appelé par CinetPay)
    Route::post('notify', [CinetPayController::class, 'notify']);

    // Page de retour après paiement (redirection utilisateur)
    Route::get('return', [CinetPayController::class, 'return']);
    Route::post('return', [CinetPayController::class, 'return']);

    // Vérifier le statut d'une transaction (pour le frontend)
    Route::post('check-status', [CinetPayController::class, 'checkStatus']);
});

// Panne routes
Route::prefix('pannes')->middleware('auth:api')->group(function () {
    Route::post('sirenes/{sireneId}/declarer', [PanneController::class, 'declarer']);
    Route::put('{panneId}/valider', [PanneController::class, 'valider']);
    Route::put('{panneId}/cloturer', [PanneController::class, 'cloturer']);
});

// Ordre de mission routes
Route::prefix('ordres-mission')->middleware('auth:api')->group(function () {
    Route::get('/', [OrdreMissionController::class, 'index']);
    Route::get('{id}', [OrdreMissionController::class, 'show']);
    Route::post('/', [OrdreMissionController::class, 'store']);
    Route::put('{id}', [OrdreMissionController::class, 'update']);
    Route::delete('{id}', [OrdreMissionController::class, 'destroy']);
    Route::get('{id}/candidatures', [OrdreMissionController::class, 'getCandidatures']);
    Route::get('ville/{villeId}', [OrdreMissionController::class, 'getByVille']);
    Route::put('{id}/cloturer-candidatures', [OrdreMissionController::class, 'cloturerCandidatures']);
    Route::put('{id}/rouvrir-candidatures', [OrdreMissionController::class, 'rouvrirCandidatures']);
});

// Intervention routes
Route::prefix('interventions')->middleware('auth:api')->group(function () {
    Route::get('/', [InterventionController::class, 'index']);
    Route::get('{id}', [InterventionController::class, 'show']);

    // Gestion des candidatures
    Route::post('ordres-mission/{ordreMissionId}/candidature', [InterventionController::class, 'soumettreCandidature']);
    Route::put('candidatures/{missionTechnicienId}/accepter', [InterventionController::class, 'accepterCandidature']);
    Route::put('candidatures/{missionTechnicienId}/refuser', [InterventionController::class, 'refuserCandidature']);
    Route::put('candidatures/{missionTechnicienId}/retirer', [InterventionController::class, 'retirerCandidature']);

    // Gestion des interventions
    Route::put('{interventionId}/demarrer', [InterventionController::class, 'demarrer']);
    Route::put('{interventionId}/retirer-mission', [InterventionController::class, 'retirerMission']);
    Route::post('{interventionId}/rapport', [InterventionController::class, 'redigerRapport']);

    // Notations
    Route::put('{interventionId}/noter', [InterventionController::class, 'noterIntervention']);
    Route::put('rapports/{rapportId}/noter', [InterventionController::class, 'noterRapport']);

    // Avis détaillés
    Route::post('{interventionId}/avis', [InterventionController::class, 'ajouterAvisIntervention']);
    Route::get('{interventionId}/avis', [InterventionController::class, 'getAvisIntervention']);
    Route::post('rapports/{rapportId}/avis', [InterventionController::class, 'ajouterAvisRapport']);
    Route::get('rapports/{rapportId}/avis', [InterventionController::class, 'getAvisRapport']);
});
