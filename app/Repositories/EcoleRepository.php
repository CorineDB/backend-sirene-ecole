<?php

namespace App\Repositories;

use App\Models\Ecole;
use App\Repositories\Contracts\EcoleRepositoryInterface;
use App\Repositories\Contracts\SiteRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;

class EcoleRepository extends BaseRepository implements EcoleRepositoryInterface
{
    public $siteRepository;
    protected $userRepository;

    public function __construct(Ecole $model, SiteRepositoryInterface $siteRepository, UserRepositoryInterface $userRepository)
    {
        parent::__construct($model);
        $this->siteRepository = $siteRepository;
        $this->userRepository = $userRepository;
    }

    public function createEcoleWithSites(array $ecoleData, array $sitesData = [])
    {
        return DB::transaction(function () use ($ecoleData, $sitesData) {
            // Créer l'école
            $ecole = $this->create($ecoleData);

            // Créer le site principal par défaut
            $sitePrincipal = $this->siteRepository->create([
                'ecole_principale_id' => $ecole->id,
                'nom' => $ecoleData['nom'] . ' - Site Principal',
                'est_principale' => true,
                'ville_id' => $ecoleData['ville_id'] ?? null,
                'adresse' => $ecoleData['adresse'] ?? null,
                'telephone' => $ecoleData['telephone'] ?? null,
            ]);

            // Créer les sites additionnels si fournis
            $sitesAdditionnels = [];
            if (!empty($sitesData)) {
                foreach ($sitesData as $siteData) {
                    $sitesAdditionnels[] = $this->siteRepository->create(array_merge($siteData, [
                        'ecole_principale_id' => $ecole->id,
                        'est_principale' => false,
                    ]));
                }
            }

            return $ecole->load(['sites', 'abonnementActif']);
        });
    }
}
