<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Abonnement\UpdateAbonnementRequest;
use App\Services\Contracts\AbonnementServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Abonnements",
 *     description="Gestion des abonnements"
 * )
 *
 * @OA\Schema(
 *     schema="Abonnement",
 *     title="Abonnement",
 *     description="Détails d'un abonnement",
 *     @OA\Property(property="id", type="string", format="ulid", description="ID de l'abonnement"),
 *     @OA\Property(property="ecole_id", type="string", format="ulid", description="ID de l'école associée"),
 *     @OA\Property(property="site_id", type="string", format="ulid", description="ID du site associé"),
 *     @OA\Property(property="sirene_id", type="string", format="ulid", description="ID de la sirène associée"),
 *     @OA\Property(property="parent_abonnement_id", type="string", format="ulid", nullable=true, description="ID de l'abonnement parent (pour les renouvellements)"),
 *     @OA\Property(property="numero_abonnement", type="string", description="Numéro unique de l'abonnement"),
 *     @OA\Property(property="date_debut", type="string", format="date", description="Date de début de l'abonnement"),
 *     @OA\Property(property="date_fin", type="string", format="date", description="Date de fin de l'abonnement"),
 *     @OA\Property(property="montant", type="number", format="float", description="Montant de l'abonnement"),
 *     @OA\Property(property="statut", type="string", enum={"en_attente", "actif", "suspendu", "expire", "annule"}, description="Statut de l'abonnement"),
 *     @OA\Property(property="auto_renouvellement", type="boolean", description="Indique si le renouvellement automatique est activé"),
 *     @OA\Property(property="notes", type="string", nullable=true, description="Notes additionnelles"),
 *     @OA\Property(property="qr_code_path", type="string", nullable=true, description="Chemin vers le QR code de l'abonnement"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="ecole", ref="#/components/schemas/Ecole", description="Détails de l'école associée"),
 *     @OA\Property(property="site", ref="#/components/schemas/Site", description="Détails du site associé"),
 *     @OA\Property(property="sirene", ref="#/components/schemas/Sirene", description="Détails de la sirène associée"),
 *     @OA\Property(property="paiements", type="array", @OA\Items(ref="#/components/schemas/Paiement")),
 *     @OA\Property(property="token", ref="#/components/schemas/TokenSirene"),
 * )
 *
 * @OA\Schema(
 *     schema="Paiement",
 *     title="Paiement",
 *     description="Détails d'un paiement",
 *     @OA\Property(property="id", type="string", format="ulid", description="ID du paiement"),
 *     @OA\Property(property="abonnement_id", type="string", format="ulid", description="ID de l'abonnement associé"),
 *     @OA\Property(property="ecole_id", type="string", format="ulid", description="ID de l'école associée"),
 *     @OA\Property(property="numero_transaction", type="string", description="Numéro de transaction unique"),
 *     @OA\Property(property="montant", type="number", format="float", description="Montant du paiement"),
 *     @OA\Property(property="moyen", type="string", enum={"cinetpay", "virement", "especes"}, description="Moyen de paiement"),
 *     @OA\Property(property="statut", type="string", enum={"en_attente", "valide", "echoue", "annule"}, description="Statut du paiement"),
 *     @OA\Property(property="reference_externe", type="string", nullable=true, description="Référence de la transaction externe"),
 *     @OA\Property(property="metadata", type="object", nullable=true, description="Métadonnées du paiement"),
 *     @OA\Property(property="date_paiement", type="string", format="date-time", description="Date du paiement"),
 *     @OA\Property(property="date_validation", type="string", format="date-time", nullable=true, description="Date de validation du paiement"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 * )
 *
 * @OA\Schema(
 *     schema="TokenSirene",
 *     title="Token Sirene",
 *     description="Détails d'un token de sirène",
 *     @OA\Property(property="id", type="string", format="ulid", description="ID du token"),
 *     @OA\Property(property="abonnement_id", type="string", format="ulid", description="ID de l'abonnement associé"),
 *     @OA\Property(property="sirene_id", type="string", format="ulid", description="ID de la sirène associée"),
 *     @OA\Property(property="site_id", type="string", format="ulid", description="ID du site associé"),
 *     @OA\Property(property="token_crypte", type="string", description="Token crypté"),
 *     @OA\Property(property="token_hash", type="string", description="Hash du token"),
 *     @OA\Property(property="date_debut", type="string", format="date", description="Date de début de validité du token"),
 *     @OA\Property(property="date_fin", type="string", format="date", description="Date de fin de validité du token"),
 *     @OA\Property(property="actif", type="boolean", description="Indique si le token est actif"),
 *     @OA\Property(property="date_generation", type="string", format="date-time", description="Date de génération du token"),
 *     @OA\Property(property="date_expiration", type="string", format="date-time", description="Date d'expiration du token"),
 *     @OA\Property(property="date_activation", type="string", format="date-time", nullable=true, description="Date d'activation du token"),
 *     @OA\Property(property="genere_par", type="string", format="ulid", nullable=true, description="ID de l'utilisateur ayant généré le token"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 * )
 *
 * @OA\Schema(
 *     schema="Site",
 *     title="Site",
 *     description="Détails d'un site",
 *     @OA\Property(property="id", type="string", format="ulid", description="ID du site"),
 *     @OA\Property(property="ecole_principale_id", type="string", format="ulid", description="ID de l'école principale"),
 *     @OA\Property(property="nom", type="string", description="Nom du site"),
 *     @OA\Property(property="est_principale", type="boolean", description="Indique si le site est le site principal"),
 *     @OA\Property(property="adresse", type="string", nullable=true, description="Adresse du site"),
 *     @OA\Property(property="ville_id", type="string", format="ulid", nullable=true, description="ID de la ville"),
 *     @OA\Property(property="latitude", type="number", format="float", nullable=true, description="Latitude du site"),
 *     @OA\Property(property="longitude", type="number", format="float", nullable=true, description="Longitude du site"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="ville", ref="#/components/schemas/Ville", description="Détails de la ville"),
 *     @OA\Property(property="sirene", ref="#/components/schemas/Sirene", description="Détails de la sirène"),
 * )
 */
class AbonnementController extends Controller
{
    protected AbonnementServiceInterface $abonnementService;

    public function __construct(AbonnementServiceInterface $abonnementService)
    {
        //$this->middleware('auth:api')->except(['details', 'paiement']);
        $this->abonnementService = $abonnementService;
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/{id}/details",
     *     tags={"Abonnements"},
     *     summary="Afficher les détails d'un abonnement (via QR Code)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(ref="#/components/schemas/Abonnement")),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function details(string $id): JsonResponse
    {
        //Gate::authorize('voir_abonnement');

        return $this->abonnementService->getById($id, relations: [
            'ecole',
            'site',
            'sirene',
            'paiements',
            'token'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/{id}/paiement",
     *     tags={"Abonnements"},
     *     summary="Afficher la page de paiement d'un abonnement (via QR Code)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(ref="#/components/schemas/Abonnement")),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function paiement(string $id): JsonResponse
    {
        //Gate::authorize('voir_abonnement');

        return $this->abonnementService->getById($id, relations: [
            'ecole',
            'site',
            'sirene'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements",
     *     tags={"Abonnements"},
     *     summary="Lister tous les abonnements (admin)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Abonnement"))),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('voir_les_abonnements');

        $perPage = $request->get('per_page', 15);
        return $this->abonnementService->getAll($perPage, ['ecole', 'site', 'sirene']);
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/{id}",
     *     tags={"Abonnements"},
     *     summary="Afficher un abonnement",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(ref="#/components/schemas/Abonnement")),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        Gate::authorize('voir_abonnement');

        return $this->abonnementService->getById($id, relations: [
            'ecole',
            'site',
            'sirene',
            'paiements',
            'token'
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/abonnements/{id}",
     *     tags={"Abonnements"},
     *     summary="Mettre à jour un abonnement",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/UpdateAbonnementRequest")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(ref="#/components/schemas/Abonnement")),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function update(UpdateAbonnementRequest $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_abonnement');
        return $this->abonnementService->update($id, $request->validated());
    }

    /**
     * @OA\Delete(
     *     path="/api/abonnements/{id}",
     *     tags={"Abonnements"},
     *     summary="Supprimer un abonnement",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="No Content"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        Gate::authorize('supprimer_abonnement');
        return $this->abonnementService->delete($id);
    }

    // ========== GESTION DU CYCLE DE VIE ==========

    /**
     * @OA\Post(
     *     path="/api/abonnements/{id}/renouveler",
     *     tags={"Abonnements"},
     *     summary="Renouveler un abonnement",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(ref="#/components/schemas/Abonnement")),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function renouveler(string $id): JsonResponse
    {
        Gate::authorize('modifier_abonnement');
        return $this->abonnementService->renouvelerAbonnement($id);
    }

    /**
     * @OA\Post(
     *     path="/api/abonnements/{id}/suspendre",
     *     tags={"Abonnements"},
     *     summary="Suspendre un abonnement",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(@OA\JsonContent(required={"raison"}, @OA\Property(property="raison", type="string"))),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(ref="#/components/schemas/Abonnement")),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function suspendre(Request $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_abonnement');
        $validated = $request->validate(['raison' => 'required|string']);
        return $this->abonnementService->suspendre($id, $validated['raison']);
    }

    /**
     * @OA\Post(
     *     path="/api/abonnements/{id}/reactiver",
     *     tags={"Abonnements"},
     *     summary="Réactiver un abonnement",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(ref="#/components/schemas/Abonnement")),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function reactiver(string $id): JsonResponse
    {
        Gate::authorize('modifier_abonnement');

        return $this->abonnementService->reactiver($id);
    }

    /**
     * @OA\Post(
     *     path="/api/abonnements/{id}/annuler",
     *     tags={"Abonnements"},
     *     summary="Annuler un abonnement",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(@OA\JsonContent(required={"raison"}, @OA\Property(property="raison", type="string"))),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(ref="#/components/schemas/Abonnement")),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function annuler(Request $request, string $id): JsonResponse
    {
        Gate::authorize('modifier_abonnement');

        $validated = $request->validate(['raison' => 'required|string']);
        return $this->abonnementService->annuler($id, $validated['raison']);
    }

    // ========== RECHERCHE ==========

    /**
     * @OA\Get(
     *     path="/api/abonnements/ecole/{ecoleId}/actif",
     *     tags={"Abonnements"},
     *     summary="Récupérer l'abonnement actif d'une école",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="ecoleId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(ref="#/components/schemas/Abonnement")),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function getActif(string $ecoleId): JsonResponse
    {
        Gate::authorize('voir_les_abonnements');

        return $this->abonnementService->getAbonnementActif($ecoleId);
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/ecole/{ecoleId}",
     *     tags={"Abonnements"},
     *     summary="Lister les abonnements d'une école",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="ecoleId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Abonnement"))),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function parEcole(string $ecoleId): JsonResponse
    {
        Gate::authorize('voir_les_abonnements');

        return $this->abonnementService->getAbonnementsByEcole($ecoleId);
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/sirene/{sireneId}",
     *     tags={"Abonnements"},
     *     summary="Lister les abonnements d'une sirène",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="sireneId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Abonnement"))),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function parSirene(string $sireneId): JsonResponse
    {
        Gate::authorize('voir_les_abonnements');
        return $this->abonnementService->getAbonnementsBySirene($sireneId);
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/expirant-bientot",
     *     tags={"Abonnements"},
     *     summary="Lister les abonnements expirant bientôt",
     *     @OA\Parameter(name="jours", in="query", @OA\Schema(type="integer", default=30)),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Abonnement")))
     * )
     */
    public function expirantBientot(Request $request): JsonResponse
    {
        Gate::authorize('voir_les_abonnements');
        $jours = $request->get('jours', 30);
        return $this->abonnementService->getExpirantBientot($jours);
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/expires",
     *     tags={"Abonnements"},
     *     summary="Lister les abonnements expirés",
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Abonnement")))
     * )
     */
    public function expires(): JsonResponse
    {
        Gate::authorize('voir_les_abonnements');
        return $this->abonnementService->getExpires();
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/actifs",
     *     tags={"Abonnements"},
     *     summary="Lister les abonnements actifs",
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Abonnement")))
     * )
     */
    public function actifs(): JsonResponse
    {
        Gate::authorize('voir_les_abonnements');
        return $this->abonnementService->getActifs();
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/en-attente",
     *     tags={"Abonnements"},
     *     summary="Lister les abonnements en attente",
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Abonnement")))
     * )
     */
    public function enAttente(): JsonResponse
    {
        Gate::authorize('voir_les_abonnements');
        return $this->abonnementService->getEnAttente();
    }

    // ========== VÉRIFICATIONS ==========

    /**
     * @OA\Get(
     *     path="/api/abonnements/{id}/est-valide",
     *     tags={"Abonnements"},
     *     summary="Vérifier si un abonnement est valide",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(@OA\Property(property="est_valide", type="boolean")))
     * )
     */
    public function estValide(string $id): JsonResponse
    {
        Gate::authorize('voir_abonnement');
        return $this->abonnementService->estValide($id);
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/ecole/{ecoleId}/a-abonnement-actif",
     *     tags={"Abonnements"},
     *     summary="Vérifier si une école a un abonnement actif",
     *     @OA\Parameter(name="ecoleId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(@OA\Property(property="a_abonnement_actif", type="boolean")))
     * )
     */
    public function ecoleAAbonnementActif(string $ecoleId): JsonResponse
    {
        Gate::authorize('voir_les_abonnements');
        return $this->abonnementService->ecoleAAbonnementActif($ecoleId);
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/{id}/peut-etre-renouvele",
     *     tags={"Abonnements"},
     *     summary="Vérifier si un abonnement peut être renouvelé",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(@OA\Property(property="peut_etre_renouvele", type="boolean")))
     * )
     */
    public function peutEtreRenouvele(string $id): JsonResponse
    {
        Gate::authorize('voir_abonnement');
        return $this->abonnementService->peutEtreRenouvele($id);
    }

    // ========== STATISTIQUES ==========

    /**
     * @OA\Get(
     *     path="/api/abonnements/statistiques",
     *     tags={"Abonnements"},
     *     summary="Récupérer les statistiques des abonnements",
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(type="object"))
     * )
     */
    public function statistiques(): JsonResponse
    {
        Gate::authorize('voir_tableau_de_bord');
        return $this->abonnementService->getStatistiques();
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/revenus-periode",
     *     tags={"Abonnements"},
     *     summary="Récupérer les revenus sur une période",
     *     @OA\Parameter(name="date_debut", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_fin", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(type="object"))
     * )
     */
    public function revenusPeriode(Request $request): JsonResponse
    {
        Gate::authorize('voir_tableau_de_bord');
        $validated = $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
        ]);
        return $this->abonnementService->getRevenusPeriode($validated['date_debut'], $validated['date_fin']);
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/taux-renouvellement",
     *     tags={"Abonnements"},
     *     summary="Récupérer le taux de renouvellement",
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(type="object"))
     * )
     */
    public function tauxRenouvellement(): JsonResponse
    {
        Gate::authorize('voir_tableau_de_bord');
        return $this->abonnementService->getTauxRenouvellement();
    }

    // ========== CALCULS ==========

    /**
     * @OA\Get(
     *     path="/api/abonnements/{id}/prix-renouvellement",
     *     tags={"Abonnements"},
     *     summary="Calculer le prix de renouvellement d'un abonnement",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(type="object"))
     * )
     */
    public function prixRenouvellement(string $id): JsonResponse
    {
        Gate::authorize('voir_abonnement');
        return $this->abonnementService->calculerPrixRenouvellement($id);
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/{id}/jours-restants",
     *     tags={"Abonnements"},
     *     summary="Récupérer le nombre de jours restants d'un abonnement",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(type="object"))
     * )
     */
    public function joursRestants(string $id): JsonResponse
    {
        Gate::authorize('voir_abonnement');
        return $this->abonnementService->getJoursRestants($id);
    }

    // ========== TÂCHES AUTOMATIQUES (CRON) ==========

    /**
     * @OA\Get(
     *     path="/api/abonnements/marquer-expires",
     *     tags={"Abonnements"},
     *     summary="Marquer les abonnements expirés",
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function marquerExpires(): JsonResponse
    {
        Gate::authorize('modifier_abonnement');
        return $this->abonnementService->marquerExpires();
    }

    /**
     * @OA\Get(
     *     path="/api/abonnements/envoyer-notifications",
     *     tags={"Abonnements"},
     *     summary="Envoyer les notifications d'expiration",
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function envoyerNotifications(): JsonResponse
    {
        Gate::authorize('voir_les_abonnements');
        return $this->abonnementService->envoyerNotificationsExpiration();
    }

    /**
     * @OA\Post(
     *     path="/api/abonnements/auto-renouveler",
     *     tags={"Abonnements"},
     *     summary="Renouveler automatiquement les abonnements",
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function autoRenouveler(): JsonResponse
    {
        Gate::authorize('modifier_abonnement');
        return $this->abonnementService->autoRenouveler();
    }

    /**
     * Régénérer le QR code d'un abonnement en attente
     *
     * @OA\Post(
     *     path="/api/abonnements/{id}/regenerer-qr-code",
     *     tags={"Abonnements"},
     *     summary="Régénérer le QR code d'un abonnement en attente",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'abonnement",
     *         @OA\Schema(type="string", format="ulid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="QR code régénéré avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="QR code régénéré avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="qr_code_path", type="string", example="ecoles/01XX/qrcodes/01YY/abonnement_01ZZ.png"),
     *                 @OA\Property(property="qr_code_url", type="string", example="http://localhost:8000/storage/ecoles/01XX/qrcodes/01YY/abonnement_01ZZ.png")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Abonnement non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Abonnement non trouvé")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="L'abonnement n'est pas en attente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Seuls les abonnements en attente peuvent avoir leur QR code régénéré")
     *         )
     *     )
     * )
     */
    public function regenererQrCode(string $id): JsonResponse
    {
        try {
            // Charger l'abonnement avec ses relations
            $abonnement = \App\Models\Abonnement::with(['ecole', 'site.ville', 'sirene'])
                ->find($id);

            if (!$abonnement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Abonnement non trouvé'
                ], 404);
            }

            // Vérifier que l'abonnement est en attente
            if ($abonnement->statut->value !== 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seuls les abonnements en attente peuvent avoir leur QR code régénéré',
                    'data' => [
                        'statut_actuel' => $abonnement->statut->value
                    ]
                ], 422);
            }

            // Régénérer le QR code
            $abonnement->regenererQrCode();
            $abonnement->refresh();

            return response()->json([
                'success' => true,
                'message' => 'QR code régénéré avec succès',
                'data' => [
                    'qr_code_path' => $abonnement->qr_code_path,
                    'qr_code_url' => $abonnement->getQrCodeUrl()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur régénération QR code: ' . $e->getMessage(), [
                'abonnement_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la régénération du QR code',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Régénérer le token crypté ESP8266 pour un abonnement
     *
     * @OA\Post(
     *     path="/api/abonnements/{id}/regenerer-token",
     *     tags={"Abonnements"},
     *     summary="Régénérer le token crypté ESP8266",
     *     description="Régénère le token crypté pour un abonnement actif. Utile si la génération automatique a échoué.",
     *     security={{"passport": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'abonnement",
     *         @OA\Schema(type="string", format="ulid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token régénéré avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token régénéré avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token_id", type="string", format="ulid"),
     *                 @OA\Property(property="date_generation", type="string", format="date-time"),
     *                 @OA\Property(property="date_expiration", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Abonnement non trouvé"),
     *     @OA\Response(response=422, description="L'abonnement n'est pas actif ou n'a pas de paiement validé"),
     *     @OA\Response(response=500, description="Erreur serveur")
     * )
     */
    public function regenererToken(string $id): JsonResponse
    {
        return $this->abonnementService->regenererToken($id);
    }

    /**
     * Obtenir l'URL signée du QR code
     *
     * @OA\Get(
     *     path="/api/abonnements/{id}/qr-code-url",
     *     tags={"Abonnements"},
     *     summary="Obtenir l'URL signée du QR code (valide 1 heure)",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'abonnement",
     *         @OA\Schema(type="string", format="ulid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="URL signée générée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="qr_code_url", type="string", description="URL signée temporaire"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time", description="Date d'expiration de l'URL")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Abonnement non trouvé ou QR code non disponible"
     *     )
     * )
     */
    public function getQrCodeUrl(string $id): JsonResponse
    {
        try {
            $abonnement = \App\Models\Abonnement::find($id);

            if (!$abonnement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Abonnement non trouvé'
                ], 404);
            }

            if (!$abonnement->qr_code_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun QR code disponible pour cet abonnement'
                ], 404);
            }

            // Vérifier que le fichier existe
            $path = storage_path('app/public/' . $abonnement->qr_code_path);
            if (!\File::exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fichier QR code introuvable'
                ], 404);
            }

            // Générer URL signée valide 1 heure
            $expiresAt = now()->addHour();
            $url = \URL::temporarySignedRoute(
                'abonnements.qr-code.download',
                $expiresAt,
                ['id' => $id]
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'qr_code_url' => $url,
                    'expires_at' => $expiresAt->toIso8601String()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur génération URL signée QR code: ' . $e->getMessage(), [
                'abonnement_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération de l\'URL signée'
            ], 500);
        }
    }

    /**
     * Télécharger le QR code d'un abonnement
     *
     * @OA\Get(
     *     path="/api/abonnements/{id}/qr-code",
     *     tags={"Abonnements"},
     *     summary="Télécharger le QR code d'un abonnement",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'abonnement",
     *         @OA\Schema(type="string", format="ulid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="QR code image",
     *         @OA\MediaType(
     *             mediaType="image/png"
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Abonnement non trouvé ou QR code non disponible"
     *     )
     * )
     */
    public function telechargerQrCode(string $id)
    {
        try {
            $abonnement = \App\Models\Abonnement::find($id);

            if (!$abonnement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Abonnement non trouvé'
                ], 404);
            }

            if (!$abonnement->qr_code_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR code non disponible pour cet abonnement'
                ], 404);
            }

            $path = storage_path('app/public/' . $abonnement->qr_code_path);

            if (!\File::exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fichier QR code introuvable'
                ], 404);
            }

            return response()->file($path, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="qr-code-' . $abonnement->numero_abonnement . '.png"',
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur téléchargement QR code: ' . $e->getMessage(), [
                'abonnement_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement du QR code'
            ], 500);
        }
    }
}
