<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ServiceLayerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Services
        $this->app->bind(
            \App\Services\Contracts\PermissionServiceInterface::class,
            \App\Services\PermissionService::class
        );
        $this->app->bind(
            \App\Services\Contracts\RoleServiceInterface::class,
            \App\Services\RoleService::class
        );
        $this->app->bind(
            \App\Services\Contracts\UserServiceInterface::class,
            \App\Services\UserService::class
        );
        $this->app->bind(
            \App\Services\Contracts\AuthServiceInterface::class,
            \App\Services\AuthService::class
        );
        $this->app->bind(
            \App\Services\Contracts\EcoleServiceInterface::class,
            \App\Services\EcoleService::class
        );
        $this->app->bind(
            \App\Services\Contracts\SiteServiceInterface::class,
            \App\Services\SiteService::class
        );
        $this->app->bind(
            \App\Services\Contracts\SireneServiceInterface::class,
            \App\Services\SireneService::class
        );
        $this->app->bind(
            \App\Services\Contracts\AbonnementServiceInterface::class,
            \App\Services\AbonnementService::class
        );
        $this->app->bind(
            \App\Services\Contracts\TechnicienServiceInterface::class,
            \App\Services\TechnicienService::class
        );
        $this->app->bind(
            \App\Services\Contracts\PaiementServiceInterface::class,
            \App\Services\PaiementService::class
        );
        $this->app->bind(
            \App\Services\Contracts\JourFerieServiceInterface::class,
            \App\Services\JourFerieService::class
        );
        $this->app->bind(
            \App\Services\Contracts\CalendrierScolaireServiceInterface::class,
            \App\Services\CalendrierScolaireService::class
        );

        $this->app->bind(
            \App\Services\Contracts\PaysServiceInterface::class,
            \App\Services\PaysService::class
        );

        $this->app->bind(
            \App\Services\Contracts\VilleServiceInterface::class,
            \App\Services\VilleService::class
        );

        $this->app->bind(
            \App\Services\Contracts\ModeleSireneServiceInterface::class,
            \App\Services\ModeleSireneService::class
        );

        $this->app->bind(
            \App\Services\Contracts\ProgrammationServiceInterface::class,
            \App\Services\ProgrammationService::class
        );

        $this->app->bind(
            \App\Services\Contracts\PanneServiceInterface::class,
            \App\Services\PanneService::class
        );

        $this->app->bind(
            \App\Services\Contracts\InterventionServiceInterface::class,
            \App\Services\InterventionService::class
        );

        $this->app->bind(
            \App\Services\Contracts\OrdreMissionServiceInterface::class,
            \App\Services\OrdreMissionService::class
        );

        $this->app->bind(
            \App\Services\Contracts\DashboardServiceInterface::class,
            \App\Services\DashboardService::class
        );

        // Repositories
        $this->app->bind(
            \App\Repositories\Contracts\PermissionRepositoryInterface::class,
            \App\Repositories\PermissionRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\RoleRepositoryInterface::class,
            \App\Repositories\RoleRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\UserRepositoryInterface::class,
            \App\Repositories\UserRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\UserInfoRepositoryInterface::class,
            \App\Repositories\UserInfoRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\OtpCodeRepositoryInterface::class,
            \App\Repositories\OtpCodeRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\EcoleRepositoryInterface::class,
            \App\Repositories\EcoleRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\SiteRepositoryInterface::class,
            \App\Repositories\SiteRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\SireneRepositoryInterface::class,
            \App\Repositories\SireneRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\PaiementRepositoryInterface::class,
            \App\Repositories\PaiementRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\AbonnementRepositoryInterface::class,
            \App\Repositories\AbonnementRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\PanneRepositoryInterface::class,
            \App\Repositories\PanneRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\InterventionRepositoryInterface::class,
            \App\Repositories\InterventionRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\RapportInterventionRepositoryInterface::class,
            \App\Repositories\RapportInterventionRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\OrdreMissionRepositoryInterface::class,
            \App\Repositories\OrdreMissionRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\MissionTechnicienRepositoryInterface::class,
            \App\Repositories\MissionTechnicienRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\ProgrammationRepositoryInterface::class,
            \App\Repositories\ProgrammationRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\JourFerieRepositoryInterface::class,
            \App\Repositories\JourFerieRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\CalendrierScolaireRepositoryInterface::class,
            \App\Repositories\CalendrierScolaireRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\TechnicienRepositoryInterface::class,
            \App\Repositories\TechnicienRepository::class
        );
        $this->app->bind(
            \App\Repositories\Contracts\PaiementRepositoryInterface::class,
            \App\Repositories\PaiementRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\PaysRepositoryInterface::class,
            \App\Repositories\PaysRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\VilleRepositoryInterface::class,
            \App\Repositories\VilleRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\ModeleSireneRepositoryInterface::class,
            \App\Repositories\ModeleSireneRepository::class
        );

    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
