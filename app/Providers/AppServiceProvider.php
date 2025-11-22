<?php

namespace App\Providers;

use App\Models\Permission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Définir dynamiquement les Gates pour toutes les permissions
        try {
            Permission::all()->each(function ($permission) {
                Gate::define($permission->slug, function ($user) use ($permission) {
                    // Charger la relation role.permissions si elle n'est pas déjà chargée
                    if (!$user->relationLoaded('role')) {
                        $user->load('role.permissions');
                    } elseif ($user->role && !$user->role->relationLoaded('permissions')) {
                        $user->role->load('permissions');
                    }

                    return $user->role && $user->role->permissions->contains('id', $permission->id);
                });
            });
        } catch (\Exception $e) {
            // Si la table permissions n'existe pas encore (migration en cours)
        }
    }
}
