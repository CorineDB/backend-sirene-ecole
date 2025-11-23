<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\RolePermission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create Admin Role
        $adminRole = Role::firstOrNew(['slug' => 'admin']);
        if (!$adminRole->exists) {
            $adminRole->id = Role::generateUlid();
            $adminRole->nom = 'Admin';
            $adminRole->save();
        }

        $permissions = Permission::all()->pluck('id');

        $adminRole->permissions()->sync(
            $permissions->mapWithKeys(function ($permissionId) {
                return [
                    $permissionId => [
                        'created_at' => now(),
                        'updated_at' => now(),
                        'id' => RolePermission::generateUlid(), // si tu as une colonne supplémentaire
                    ]
                ];
            })->toArray()
        );

        //adminRole->permissions()->sync(Permission::all());

        // Create User Role
        $userRole = Role::firstOrNew(['slug' => 'user']);
        if (!$userRole->exists) {
            $userRole->id = Role::generateUlid();
            $userRole->nom = 'User';
            $userRole->save();
        }
        $userPermissions = Permission::whereIn('slug', [
            'voir_tableau_de_bord',
            'voir_les_abonnements',
            'voir_abonnement',
            'initier_paiement_abonnement',
            'voir_les_notifications',
            'voir_notification',
            'modifier_notification',
            'voir_les_paiements',
            'voir_paiement',
            'voir_les_informations_utilisateur',
            'modifier_informations_utilisateur',
        ])->get()->pluck('id');

        $userRole->permissions()->sync(
            $userPermissions->mapWithKeys(function ($permissionId) {
                return [
                    $permissionId => [
                        'created_at' => now(),
                        'updated_at' => now(),
                        'id' => RolePermission::generateUlid(), // si tu as une colonne supplémentaire
                    ]
                ];
            })->toArray()
        );
        //$userRole->permissions()->sync($userPermissions);

        // Create Ecole Role
        $ecoleRole = Role::firstOrNew(['slug' => 'ecole']);
        if (!$ecoleRole->exists) {
            $ecoleRole->id = Role::generateUlid();
            $ecoleRole->nom = 'ecole';
            $ecoleRole->save();
        }
        $ecolePermissions = Permission::whereIn('slug', [
            'voir_tableau_de_bord',
            'voir_ecole',
            'modifier_ecole',
            'gerer_sirenes_ecole',
            'gerer_abonnements_ecole',
            'voir_les_abonnements',
            'voir_abonnement',
            'creer_abonnement',
            'modifier_abonnement',
            'initier_paiement_abonnement',
            'voir_les_sirenes',
            'voir_sirene',
            'voir_les_sites',
            'voir_site',
            'creer_site',
            'modifier_site',
            'gerer_sirenes_site',
            'voir_les_pannes',
            'creer_sirene',
            'modifier_sirene',
            'activer_sirene',
            'desactiver_sirene',
            'tester_sirene',
            'configurer_sirene',
            'voir_panne',
            'creer_panne',
            'modifier_panne',
            'voir_les_interventions',
            'voir_intervention',
            'creer_intervention',
            'creer_avis_intervention',
            'voir_les_rapports_intervention',
            'voir_rapport_intervention',
            'voir_les_notifications',
            'voir_notification',
            'modifier_notification',
            'voir_les_paiements',
            'voir_paiement',
            'voir_les_calendriers_scolaires',
            'voir_calendrier_scolaire',
            'voir_les_jours_feries',
            'voir_jour_ferie',
            'creer_jour_ferie',
            'modifier_jour_ferie',
            'supprimer_jour_ferie',
            'voir_les_programmations',
            'voir_programmation',
            'creer_programmation',
            'modifier_programmation',
            'supprimer_programmation',
            'executer_programmation',
            'voir_les_fichiers',
            'voir_fichier',
            'creer_fichier',
            'telecharger_fichier',
            'voir_son_propre_profil',
            'changer_son_propre_mot_de_passe',
            'voir_informations_utilisateur',

            // Permissions Générales
           'gerer_user',
            'gerer_utilisateurs',
            'gerer_roles',
            'gerer_permissions',
            'voir_les_pays',
            'voir_pays',

            // Permission
            'voir_les_permissions',
            'voir_permission',


            // Role
            'voir_les_roles',
            'voir_role',
            'creer_role',
            'modifier_role',
            'supprimer_role',
            'assigner_permissions_role',

            // RolePermission
            'voir_les_permissions_role',
            'voir_role_permission',
            'creer_role_permission',
            'supprimer_role_permission',


            // Utilisateur (User)
            'voir_les_utilisateurs',
            'voir_utilisateur',
            'creer_utilisateur',
            'modifier_utilisateur',
            'supprimer_utilisateur',
            'assigner_roles_utilisateur',
            'reinitialiser_mot_de_passe_utilisateur',

            // Informations Utilisateur (UserInfo)
            'voir_les_informations_utilisateur',
            'voir_informations_utilisateur',
            'creer_informations_utilisateur',
            'modifier_informations_utilisateur',
            'supprimer_informations_utilisateur',

        ])->get()->pluck('id');

        $ecoleRole->permissions()->sync(
            $ecolePermissions->mapWithKeys(function ($permissionId) {
                return [
                    $permissionId => [
                        'created_at' => now(),
                        'updated_at' => now(),
                        'id' => RolePermission::generateUlid(), // si tu as une colonne supplémentaire
                    ]
                ];
            })->toArray()
        );
        //$ecoleRole->permissions()->sync($ecolePermissions);

        // Create Technicien Role
        $technicienRole = Role::firstOrNew(['slug' => 'technicien']);
        if (!$technicienRole->exists) {
            $technicienRole->id = Role::generateUlid();
            $technicienRole->nom = 'technicien';
            $technicienRole->save();
        }
        $technicienPermissions = Permission::whereIn('slug', [
            'voir_tableau_de_bord',
            'voir_les_interventions',
            'voir_intervention',
            'modifier_intervention',
            'cloturer_intervention',
            'creer_avis_intervention',
            'voir_les_missions_technicien',
            'voir_mission_technicien',
            'modifier_mission_technicien',
            'completer_mission_technicien',
            'voir_les_ordres_mission',
            'voir_ordre_mission',
            'voir_les_pannes',
            'voir_panne',
            'creer_panne',
            'modifier_panne',
            'resoudre_panne',
            'voir_les_rapports_intervention',
            'voir_rapport_intervention',
            'creer_rapport_intervention',
            'modifier_rapport_intervention',
            'creer_avis_rapport',
            'voir_les_sirenes',
            'voir_sirene',
            'creer_sirene',
            'modifier_sirene',
            'activer_sirene',
            'desactiver_sirene',
            'tester_sirene',
            'configurer_sirene',
            'voir_les_programmations',
            'voir_programmation',
            'executer_programmation',
            'voir_les_ecoles',
            'voir_ecole',
            'voir_les_sites',
            'voir_site',

            'creer_sirene',
            'modifier_sirene',
            'activer_sirene',
            'desactiver_sirene',
            'tester_sirene',
            'configurer_sirene',
            'voir_les_notifications',
            'voir_notification',
            'modifier_notification',
            'voir_les_fichiers',
            'voir_fichier',
            'creer_fichier',
            'telecharger_fichier',
            'voir_son_propre_profil',
            'changer_son_propre_mot_de_passe',
            'voir_informations_utilisateur',
        ])->get()->pluck('id');

        $technicienRole->permissions()->sync(
            $technicienPermissions->mapWithKeys(function ($permissionId) {
                return [
                    $permissionId => [
                        'created_at' => now(),
                        'updated_at' => now(),
                        'id' => RolePermission::generateUlid(), // si tu as une colonne supplémentaire
                    ]
                ];
            })->toArray()
        );
        $technicienRole->permissions()->sync($technicienPermissions);
    }
}
