<?php

namespace App\Services;

use App\Repositories\Contracts\EcoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\SireneRepositoryInterface;
use App\Repositories\Contracts\SiteRepositoryInterface;
use App\Services\Contracts\AbonnementServiceInterface;
use App\Services\Contracts\EcoleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EcoleService extends BaseService implements EcoleServiceInterface
{
    protected $userRepository;
    protected $sireneRepository;
    protected $abonnementService;
    protected $siteRepository;

    public function __construct(
        EcoleRepositoryInterface $repository,
        UserRepositoryInterface $userRepository,
        SireneRepositoryInterface $sireneRepository,
        AbonnementServiceInterface $abonnementService,
        SiteRepositoryInterface $siteRepository
    ) {
        parent::__construct($repository);
        $this->userRepository = $userRepository;
        $this->sireneRepository = $sireneRepository;
        $this->abonnementService = $abonnementService;
        $this->siteRepository = $siteRepository;
    }

    /**
     * Inscription complète d'une école avec sites et affectation de sirènes
     *
     * @param array $ecoleData - Données de l'école (nom, email, telephone, etc.)
     * @param array $sitePrincipalData - Données du site principal avec sirène
     * @param array $sitesAnnexeData - Tableau des sites annexes avec leurs sirènes (optionnel)
     * @return JsonResponse
     */
    public function inscrireEcole(array $ecoleData, array $sitePrincipalData, array $sitesAnnexeData = []): JsonResponse
    {
        try {
            DB::beginTransaction();

            // 1. Créer l'école
            $ecole = $this->repository->create($ecoleData);

            // 2. Créer le site principal avec sa sirène
            $sitePrincipalData['nom'] = $ecoleData['nom'];
            $this->createSiteWithSirene(
                $ecole->id,
                $sitePrincipalData,
                true // est_principale
            );

            // 3. Créer les sites annexes avec leurs sirènes (si fournis)
            if (!empty($sitesAnnexeData)) {
                foreach ($sitesAnnexeData as $siteAnnexeData) {
                    $this->createSiteWithSirene(
                        $ecole->id,
                        $siteAnnexeData,
                        false // est_principale
                    );
                }
            }

            // 4. Créer le compte utilisateur pour l'école
            $motDePasse = Str::random(12); // Générer un mot de passe temporaire

            $userData = [
                'nom_utilisateur' => $ecoleData['nom'],
                'mot_de_passe' => $motDePasse, // Password en clair (sera haché automatiquement dans UserRepository)
                'type' => 'ECOLE',
                'user_account_type_id' => $ecole->id,
                'user_account_type_type' => get_class($ecole),
                'userInfoData' => [
                    'nom' => $ecoleData['nom'],
                    'telephone' => $ecoleData['telephone_contact'],
                    'email' => $ecoleData['email_contact'] ?? null,
                ],
            ];

            $this->userRepository->create($userData);

            DB::commit();

            // Recharger l'école avec toutes les relations
            $ecole->load([
                'sites.sirene.modeleSirene',
                'sites.sirene.abonnementActif',
                'sites.sirene.abonnementEnAttente',
                'abonnementActif',
                'user'
            ]);

            $ecole->setAttribute('mot_de_passe_temporaire', $motDePasse);

            return $this->createdResponse($ecole);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::inscrireEcole - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Créer un site avec sa sirène affectée
     */
    protected function createSiteWithSirene(string $ecoleId, array $siteData, bool $estPrincipale)
    {
        // Extraire les données de la sirène
        $sireneData = $siteData['sirene'] ?? null;
        unset($siteData['sirene']);

        // Créer le site
        $site = $this->siteRepository->create(array_merge($siteData, [
            'ecole_principale_id' => $ecoleId,
            'est_principale' => $estPrincipale,
        ]));

        // Affecter la sirène au site si fournie
        if ($sireneData && isset($sireneData['numero_serie'])) {
            $sirene = $this->sireneRepository->findByNumeroSerie($sireneData['numero_serie']);

            if (!$sirene) {
                throw new \Exception("Sirène avec numéro de série {$sireneData['numero_serie']} introuvable.");
            }

            if ($sirene->statut && $sirene->statut !== 'DISPONIBLE' || $sirene->site_id !== null) {
                throw new \Exception("La sirène {$sireneData['numero_serie']} n'est pas disponible.");
            }

            // Affecter la sirène au site
            $this->sireneRepository->affecterSireneASite($sirene->id, $site->id, $ecoleId);

            // Créer un abonnement en attente pour l'école
            $montantAbonnement = config('services.subscription.price_per_year', 50000);

            $abonnementData = [
                'ecole_id' => $ecoleId,
                'site_id' => $site->id,
                'sirene_id' => $sirene->id,
                // Le numero_abonnement sera généré automatiquement par le trait HasNumeroAbonnement
                'date_debut' => now(),
                'date_fin' => now()->addYear(),
                'montant' => $montantAbonnement,
                'statut' => \App\Enums\StatutAbonnement::EN_ATTENTE->value,
                'auto_renouvellement' => false,
                'notes' => "Abonnement créé automatiquement lors de l'inscription de l'école",
            ];

            // Créer l'abonnement via le repository
            $abonnement = $this->abonnementService->repository->create($abonnementData);

            // Le QR code sera généré automatiquement par le trait HasQrCodeAbonnement
            // Le token sera généré automatiquement par le trait HasTokenCrypte quand le statut passe à ACTIF
            Log::info("Abonnement créé avec succès", [
                'abonnement_id' => $abonnement->id,
                'numero_abonnement' => $abonnement->numero_abonnement,
                'ecole_id' => $ecoleId,
                'montant' => $montantAbonnement,
            ]);
        }

        return $site;
    }
}
