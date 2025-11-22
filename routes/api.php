<?php

use App\Http\Controllers\Api\AbonnementController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ModeleSireneController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EcoleController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\SireneController;
use App\Http\Controllers\Api\CinetPayController;
use App\Http\Controllers\Api\TechnicienController;
use App\Http\Controllers\Api\PanneController;
use App\Http\Controllers\Api\InterventionController;
use App\Http\Controllers\Api\OrdreMissionController;
use App\Http\Controllers\Api\CalendrierScolaireController;
use App\Http\Controllers\Api\JourFerieController;
use App\Http\Controllers\Api\ProgrammationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PaysController;
use App\Http\Controllers\Api\VilleController;
use Illuminate\Support\Facades\Route;

Route::prefix('permissions')->middleware('auth:api')->group(function () {
    Route::get('/', [PermissionController::class, 'index'])->middleware('can:voir_les_permissions');
    Route::get('{id}', [PermissionController::class, 'show'])->middleware('can:voir_permission');
    Route::get('slug/{slug}', [PermissionController::class, 'showBySlug'])->middleware('can:voir_permission');
    Route::get('role/{roleId}', [PermissionController::class, 'showByRole'])->middleware('can:voir_les_permissions_role');
});

Route::prefix('pays')->middleware('auth:api')->group(function () {
    Route::get('/', [PaysController::class, 'index'])->middleware('can:voir_les_pays');
    Route::get('{id}', [PaysController::class, 'show'])->middleware('can:voir_pays');
    Route::post('/', [PaysController::class, 'store'])->middleware('can:creer_pays');
    Route::put('{id}', [PaysController::class, 'update'])->middleware('can:modifier_pays');
    Route::delete('{id}', [PaysController::class, 'destroy'])->middleware('can:supprimer_pays');
});
Route::prefix('villes')->middleware('auth:api')->group(function () {
    Route::get('/', [VilleController::class, 'index'])->middleware('can:voir_les_villes');
    Route::get('{id}', [VilleController::class, 'show'])->middleware('can:voir_ville');
    Route::post('/', [VilleController::class, 'store'])->middleware('can:creer_ville');
    Route::put('{id}', [VilleController::class, 'update'])->middleware('can:modifier_ville');
    Route::delete('{id}', [VilleController::class, 'destroy'])->middleware('can:supprimer_ville');
});

Route::prefix('roles')->middleware('auth:api')->group(function () {
    Route::get('/', [RoleController::class, 'index'])->middleware('can:voir_les_roles');
    Route::get('{id}', [RoleController::class, 'show'])->middleware('can:voir_role');
    Route::post('/', [RoleController::class, 'store'])->middleware('can:creer_role');
    Route::put('{id}', [RoleController::class, 'update'])->middleware('can:modifier_role');
    Route::delete('{id}', [RoleController::class, 'destroy'])->middleware('can:supprimer_role');

    // Routes pour la gestion des permissions d'un rôle
    Route::post('{roleId}/permissions/assign', [RoleController::class, 'assignPermissions'])->middleware('can:assigner_permissions_role');
    Route::post('{roleId}/permissions/sync', [RoleController::class, 'syncPermissions'])->middleware('can:assigner_permissions_role');
    Route::post('{roleId}/permissions/remove', [RoleController::class, 'removePermissions'])->middleware('can:assigner_permissions_role');
});

Route::prefix('users')->middleware('auth:api')->group(function () {
    Route::get('/', [UserController::class, 'index'])->middleware('can:voir_les_utilisateurs');
    Route::get('{id}', [UserController::class, 'show'])->middleware('can:voir_utilisateur');
    Route::post('/', [UserController::class, 'store'])->middleware('can:creer_utilisateur');
    Route::put('{id}', [UserController::class, 'update'])->middleware('can:modifier_utilisateur');
    Route::delete('{id}', [UserController::class, 'destroy'])->middleware('can:supprimer_utilisateur');
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
    // Public: Inscription & Checkout
    Route::post('inscription', [EcoleController::class, 'inscrire']);
    Route::get('{id}', [EcoleController::class, 'showById']); // Public pour checkout via QR code

    // Protected routes for Ecole management
    Route::middleware('auth:api')->group(function () {
        Route::get('/', [EcoleController::class, 'index'])->middleware('can:voir_les_ecoles');
        Route::get('me', [EcoleController::class, 'show'])->middleware('can:voir_ecole');
        Route::put('me', [EcoleController::class, 'update'])->middleware('can:modifier_ecole');
        Route::put('{id}', [EcoleController::class, 'updateById'])->middleware('can:modifier_ecole');
        Route::delete('{id}', [EcoleController::class, 'destroy'])->middleware('can:supprimer_ecole');

        // School-specific holidays
        Route::get('{ecoleId}/jours-feries', [JourFerieController::class, 'indexForEcole']);
        Route::get('{ecoleId}/abonnements', [AbonnementController::class, 'parEcole']);
        Route::post('{ecoleId}/abonnements/{abonnementId}', [PaiementController::class, 'traiter']);
        Route::post('{ecoleId}/jours-feries', [JourFerieController::class, 'storeForEcole']);

        // School pannes
        Route::get('{ecoleId}/pannes', [PanneController::class, 'pannesByEcole']);

        // School calendar with merged holidays
        Route::get('me/calendrier-scolaire/with-ecole-holidays', [EcoleController::class, 'getCalendrierScolaireWithJoursFeries'])->middleware('can:voir_ecole');

        // Sites management for a school
        Route::get('{ecoleId}/sites', [SiteController::class, 'index'])->middleware('can:voir_les_sites');
    });
});

// Site routes
Route::prefix('sites')->middleware('auth:api')->group(function () {
    Route::get('{id}', [SiteController::class, 'show'])->middleware('can:voir_site');
    Route::post('/', [SiteController::class, 'store'])->middleware('can:creer_site');
    Route::put('{id}', [SiteController::class, 'update'])->middleware('can:modifier_site');
    Route::delete('{id}', [SiteController::class, 'destroy'])->middleware('can:supprimer_site');
});

// Sirene routes
Route::get('sirenes-programmable', [SireneController::class, 'avecAbonnementActif'])
    ->middleware(['auth:api', 'can:voir_les_sirenes']);

Route::prefix('sirenes')->group(function () {
    // Public: Programmations actives pour les sirènes authentifiées
    // getProgrammation nécessite le token dans le header X-Sirene-Token
    // La sirène est identifiée via le token (pas besoin du numéro de série dans l'URL)
    Route::get('programmations-actives', [SireneController::class, 'getProgrammation'])
        ->middleware('auth.esp8266');

    // Protected - Admin/Technicien
    Route::middleware('auth:api')->group(function () {
        Route::get('/', [SireneController::class, 'index'])->middleware('can:voir_les_sirenes');
        Route::get('disponibles', [SireneController::class, 'disponibles'])->middleware('can:voir_les_sirenes');
        Route::get('numero-serie/{numeroSerie}', [SireneController::class, 'showByNumeroSerie'])->middleware('can:voir_sirene');
        Route::get('{id}', [SireneController::class, 'show'])->middleware('can:voir_sirene');
        Route::post('/', [SireneController::class, 'store'])->middleware('can:creer_sirene');
        Route::put('{id}', [SireneController::class, 'update'])->middleware('can:modifier_sirene');
        Route::post('{id}/affecter', [SireneController::class, 'affecter'])->middleware('can:modifier_sirene');
        Route::delete('{id}', [SireneController::class, 'destroy'])->middleware('can:supprimer_sirene');

        Route::post('{id}/declarer-panne', [PanneController::class, 'declarer']);
        Route::get('{sireneId}/pannes', [PanneController::class, 'pannesBySirene']);

        // Programmations for a sirene
        Route::apiResource('{sirene}/programmations', ProgrammationController::class);
    });
});

// Public: Configuration initiale des sirènes ESP8266 (sans authentification)
Route::get('config-sirene/{numeroSerie}', [SireneController::class, 'getConfig']);

Route::prefix('modeles-sirene')->middleware('auth:api')->group(function () {
    Route::get('/', [ModeleSireneController::class, 'index'])->middleware('can:voir_les_modeles_sirene');
    Route::get('{id}', [ModeleSireneController::class, 'show'])->middleware('can:voir_modele_sirene');
    Route::post('/', [ModeleSireneController::class, 'store'])->middleware('can:creer_modele_sirene');
    Route::put('{id}', [ModeleSireneController::class, 'update'])->middleware('can:modifier_modele_sirene');
    Route::delete('{id}', [ModeleSireneController::class, 'destroy'])->middleware('can:supprimer_modele_sirene');
});

// Technicien routes (Protected)
Route::prefix('techniciens')->middleware('auth:api')->group(function () {
    Route::get('/', [TechnicienController::class, 'index']);
    Route::post('/', [TechnicienController::class, 'store']);
    Route::get('{id}', [TechnicienController::class, 'show']);
    Route::get('{id}/interventions', [TechnicienController::class, 'getInterventions']); // Interventions du technicien
    Route::put('{id}', [TechnicienController::class, 'update']);
    Route::delete('{id}', [TechnicienController::class, 'destroy']);
});

// CalendrierScolaire routes (Protected)
Route::prefix('calendrier-scolaire')->middleware('auth:api')->group(function () {
    Route::get('/', [CalendrierScolaireController::class, 'index'])->middleware('can:voir_les_calendriers_scolaires');
    Route::get('{id}', [CalendrierScolaireController::class, 'show'])->middleware('can:voir_calendrier_scolaire');
    Route::post('/', [CalendrierScolaireController::class, 'store'])->middleware('can:creer_calendrier_scolaire');
    Route::put('{id}', [CalendrierScolaireController::class, 'update'])->middleware('can:modifier_calendrier_scolaire');
    Route::delete('{id}', [CalendrierScolaireController::class, 'destroy'])->middleware('can:supprimer_calendrier_scolaire');

    Route::get('{id}/jours-feries', [CalendrierScolaireController::class, 'getJoursFeries'])->middleware('can:voir_calendrier_scolaire');
    Route::get('{id}/calculate-school-days', [CalendrierScolaireController::class, 'calculateSchoolDays'])->middleware('can:voir_calendrier_scolaire');

    // Bulk operations for jours fériés
    Route::post('{id}/jours-feries/bulk', [CalendrierScolaireController::class, 'storeMultipleJoursFeries'])->middleware('can:creer_calendrier_scolaire');
    Route::put('{id}/jours-feries/bulk', [CalendrierScolaireController::class, 'updateMultipleJoursFeries'])->middleware('can:modifier_calendrier_scolaire');
});

// JoursFeries routes (Protected)
Route::prefix('jours-feries')->middleware('auth:api')->group(function () {
    Route::get('/', [JourFerieController::class, 'index']);
    Route::post('/', [JourFerieController::class, 'store']);
    Route::post('bulk', [JourFerieController::class, 'storeBulk']);
    Route::get('{id}', [JourFerieController::class, 'show']);
    Route::put('{id}', [JourFerieController::class, 'update']);
    Route::delete('{id}', [JourFerieController::class, 'destroy']);
});

// Abonnement routes
Route::prefix('abonnements')->group(function () {
    // Public: Accès via QR Code
    Route::get('{id}/details', [AbonnementController::class, 'details']);
    Route::get('{id}/paiement', [AbonnementController::class, 'paiement']);
    Route::get('{id}/qr-code-url', [AbonnementController::class, 'getQrCodeUrl']); // Obtenir URL signée du QR code
    Route::get('{id}', [AbonnementController::class, 'show']); // Public pour checkout via QR code

    // Protected routes
    Route::middleware('auth:api')->group(function () {
        // CRUD de base
        Route::get('/', [AbonnementController::class, 'index']);
        Route::put('{id}', [AbonnementController::class, 'update']);
        Route::delete('{id}', [AbonnementController::class, 'destroy']);

        // Gestion du cycle de vie
        Route::post('{id}/renouveler', [AbonnementController::class, 'renouveler']);
        Route::post('{id}/suspendre', [AbonnementController::class, 'suspendre']);
        Route::post('{id}/reactiver', [AbonnementController::class, 'reactiver']);
        Route::post('{id}/annuler', [AbonnementController::class, 'annuler']);
        Route::post('{id}/regenerer-qr-code', [AbonnementController::class, 'regenererQrCode']);
        Route::post('{id}/regenerer-token', [AbonnementController::class, 'regenererToken']);
        Route::get('{id}/qr-code', [AbonnementController::class, 'telechargerQrCode']);

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

// Route signée pour téléchargement sécurisé du QR code (avec vérification de signature)
Route::get('abonnements/{id}/qr-code-download', [AbonnementController::class, 'telechargerQrCode'])
    ->name('abonnements.qr-code.download')
    ->middleware('signed');

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

    // Get CinetPay configuration for frontend
    Route::get('config', [CinetPayController::class, 'getConfig']);

    // Vérifier le statut d'une transaction (pour le frontend)
    Route::post('check-status', [CinetPayController::class, 'checkStatus']);
});

// Panne routes
Route::prefix('pannes')->middleware('auth:api')->group(function () {
    Route::get('/', [PanneController::class, 'index']);
    Route::get('{id}', [PanneController::class, 'show']);
    Route::put('{panneId}', [PanneController::class, 'update']);
    Route::put('{panneId}/valider', [PanneController::class, 'valider']);
    Route::put('{panneId}/cloturer', [PanneController::class, 'cloturer']);
    Route::put('{panneId}/assigner/{technicienId}', [PanneController::class, 'assignerTechnicien']);
});

// Statistiques pannes
Route::get('statistiques-pannes', [PanneController::class, 'statistiques'])->middleware('auth:api');

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

    // Création et gestion manuelle
    Route::post('ordres-mission/{ordreMissionId}/creer', [InterventionController::class, 'creerIntervention']);
    Route::post('{interventionId}/techniciens', [InterventionController::class, 'assignerTechnicien']);
    Route::delete('{interventionId}/techniciens', [InterventionController::class, 'retirerTechnicien']);
    Route::put('{interventionId}/planifier', [InterventionController::class, 'planifierIntervention']);

    // Gestion des interventions
    Route::put('{interventionId}/demarrer', [InterventionController::class, 'demarrer']);
    Route::put('{interventionId}/terminer', [InterventionController::class, 'terminer']);
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
