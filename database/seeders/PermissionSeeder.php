<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            // Permissions Générales
           // ['nom' => 'Gérer les users', 'slug' => 'gerer_user'],
            ['nom' => 'Gérer les utilisateurs', 'slug' => 'gerer_utilisateurs'],
            ['nom' => 'Gérer les rôles', 'slug' => 'gerer_roles'],
            ['nom' => 'Gérer les permissions', 'slug' => 'gerer_permissions'],
            ['nom' => 'Voir le tableau de bord', 'slug' => 'voir_tableau_de_bord'],

            // Abonnement
            ['nom' => 'Voir les abonnements', 'slug' => 'voir_les_abonnements'],
            ['nom' => 'Voir un abonnement', 'slug' => 'voir_abonnement'],
            ['nom' => 'Créer un abonnement', 'slug' => 'creer_abonnement'],
            ['nom' => 'Modifier un abonnement', 'slug' => 'modifier_abonnement'],
            ['nom' => 'Supprimer un abonnement', 'slug' => 'supprimer_abonnement'],
            ['nom' => 'Gérer le QR code d\'abonnement', 'slug' => 'gerer_qrcode_abonnement'],
            ['nom' => 'Initier le paiement d\'un abonnement', 'slug' => 'initier_paiement_abonnement'],

            // AvisIntervention
            ['nom' => 'Voir les avis d\'intervention', 'slug' => 'voir_les_avis_intervention'],
            ['nom' => 'Voir un avis d\'intervention', 'slug' => 'voir_avis_intervention'],
            ['nom' => 'Créer un avis d\'intervention', 'slug' => 'creer_avis_intervention'],
            ['nom' => 'Modifier un avis d\'intervention', 'slug' => 'modifier_avis_intervention'],
            ['nom' => 'Supprimer un avis d\'intervention', 'slug' => 'supprimer_avis_intervention'],

            // AvisRapport
            ['nom' => 'Voir les avis de rapport', 'slug' => 'voir_les_avis_rapport'],
            ['nom' => 'Voir un avis de rapport', 'slug' => 'voir_avis_rapport'],
            ['nom' => 'Créer un avis de rapport', 'slug' => 'creer_avis_rapport'],
            ['nom' => 'Modifier un avis de rapport', 'slug' => 'modifier_avis_rapport'],
            ['nom' => 'Supprimer un avis de rapport', 'slug' => 'supprimer_avis_rapport'],

            // CalendrierScolaire
            ['nom' => 'Voir les calendriers scolaires', 'slug' => 'voir_les_calendriers_scolaires'],
            ['nom' => 'Voir un calendrier scolaire', 'slug' => 'voir_calendrier_scolaire'],
            ['nom' => 'Créer un calendrier scolaire', 'slug' => 'creer_calendrier_scolaire'],
            ['nom' => 'Modifier un calendrier scolaire', 'slug' => 'modifier_calendrier_scolaire'],
            ['nom' => 'Supprimer un calendrier scolaire', 'slug' => 'supprimer_calendrier_scolaire'],

            // Ecole
            ['nom' => 'Voir les écoles', 'slug' => 'voir_les_ecoles'],
            ['nom' => 'Voir une école', 'slug' => 'voir_ecole'],
            ['nom' => 'Créer une école', 'slug' => 'creer_ecole'],
            ['nom' => 'Modifier une école', 'slug' => 'modifier_ecole'],
            ['nom' => 'Supprimer une école', 'slug' => 'supprimer_ecole'],
            ['nom' => 'Gérer les sirènes d\'école', 'slug' => 'gerer_sirenes_ecole'],
            ['nom' => 'Gérer les abonnements d\'école', 'slug' => 'gerer_abonnements_ecole'],

            // Fichier
            ['nom' => 'Voir les fichiers', 'slug' => 'voir_les_fichiers'],
            ['nom' => 'Voir un fichier', 'slug' => 'voir_fichier'],
            ['nom' => 'Créer un fichier', 'slug' => 'creer_fichier'],
            ['nom' => 'Télécharger un fichier', 'slug' => 'telecharger_fichier'],
            ['nom' => 'Supprimer un fichier', 'slug' => 'supprimer_fichier'],

            // Intervention
            ['nom' => 'Voir les interventions', 'slug' => 'voir_les_interventions'],
            ['nom' => 'Voir une intervention', 'slug' => 'voir_intervention'],
            ['nom' => 'Créer une intervention', 'slug' => 'creer_intervention'],
            ['nom' => 'Modifier une intervention', 'slug' => 'modifier_intervention'],
            ['nom' => 'Supprimer une intervention', 'slug' => 'supprimer_intervention'],
            ['nom' => 'Assigner un technicien à une intervention', 'slug' => 'assigner_technicien_intervention'],
            ['nom' => 'Clôturer une intervention', 'slug' => 'cloturer_intervention'],

            // JourFerie
            ['nom' => 'Voir les jours fériés', 'slug' => 'voir_les_jours_feries'],
            ['nom' => 'Voir un jour férié', 'slug' => 'voir_jour_ferie'],
            ['nom' => 'Créer un jour férié', 'slug' => 'creer_jour_ferie'],
            ['nom' => 'Modifier un jour férié', 'slug' => 'modifier_jour_ferie'],
            ['nom' => 'Supprimer un jour férié', 'slug' => 'supprimer_jour_ferie'],

            // MissionTechnicien
            ['nom' => 'Voir les missions technicien', 'slug' => 'voir_les_missions_technicien'],
            ['nom' => 'Voir une mission technicien', 'slug' => 'voir_mission_technicien'],
            ['nom' => 'Créer une mission technicien', 'slug' => 'creer_mission_technicien'],
            ['nom' => 'Modifier une mission technicien', 'slug' => 'modifier_mission_technicien'],
            ['nom' => 'Supprimer une mission technicien', 'slug' => 'supprimer_mission_technicien'],
            ['nom' => 'Compléter une mission technicien', 'slug' => 'completer_mission_technicien'],

            // ModeleSirene
            ['nom' => 'Voir les modèles de sirène', 'slug' => 'voir_les_modeles_sirene'],
            ['nom' => 'Voir un modèle de sirène', 'slug' => 'voir_modele_sirene'],
            ['nom' => 'Créer un modèle de sirène', 'slug' => 'creer_modele_sirene'],
            ['nom' => 'Modifier un modèle de sirène', 'slug' => 'modifier_modele_sirene'],
            ['nom' => 'Supprimer un modèle de sirène', 'slug' => 'supprimer_modele_sirene'],

            // MoyenPaiement
            ['nom' => 'Voir les moyens de paiement', 'slug' => 'voir_les_moyens_paiement'],
            ['nom' => 'Voir un moyen de paiement', 'slug' => 'voir_moyen_paiement'],
            ['nom' => 'Créer un moyen de paiement', 'slug' => 'creer_moyen_paiement'],
            ['nom' => 'Modifier un moyen de paiement', 'slug' => 'modifier_moyen_paiement'],
            ['nom' => 'Supprimer un moyen de paiement', 'slug' => 'supprimer_moyen_paiement'],

            // Notification
            ['nom' => 'Voir les notifications', 'slug' => 'voir_les_notifications'],
            ['nom' => 'Voir une notification', 'slug' => 'voir_notification'],
            ['nom' => 'Créer une notification', 'slug' => 'creer_notification'],
            ['nom' => 'Modifier une notification', 'slug' => 'modifier_notification'],
            ['nom' => 'Supprimer une notification', 'slug' => 'supprimer_notification'],
            ['nom' => 'Envoyer une notification', 'slug' => 'envoyer_notification'],

            // OrdreMission
            ['nom' => 'Voir les ordres de mission', 'slug' => 'voir_les_ordres_mission'],
            ['nom' => 'Voir un ordre de mission', 'slug' => 'voir_ordre_mission'],
            ['nom' => 'Créer un ordre de mission', 'slug' => 'creer_ordre_mission'],
            ['nom' => 'Modifier un ordre de mission', 'slug' => 'modifier_ordre_mission'],
            ['nom' => 'Supprimer un ordre de mission', 'slug' => 'supprimer_ordre_mission'],
            ['nom' => 'Approuver un ordre de mission', 'slug' => 'approuver_ordre_mission'],

            // OtpCode
            ['nom' => 'Voir les codes OTP', 'slug' => 'voir_les_codes_otp'],
            ['nom' => 'Voir un code OTP', 'slug' => 'voir_otp_code'],
            ['nom' => 'Créer un code OTP', 'slug' => 'creer_otp_code'],
            ['nom' => 'Valider un code OTP', 'slug' => 'valider_otp_code'],

            // Paiement
            ['nom' => 'Voir les paiements', 'slug' => 'voir_les_paiements'],
            ['nom' => 'Voir un paiement', 'slug' => 'voir_paiement'],
            ['nom' => 'Créer un paiement', 'slug' => 'creer_paiement'],
            ['nom' => 'Modifier un paiement', 'slug' => 'modifier_paiement'],
            ['nom' => 'Supprimer un paiement', 'slug' => 'supprimer_paiement'],
            ['nom' => 'Rembourser un paiement', 'slug' => 'rembourser_paiement'],

            // Panne
            ['nom' => 'Voir les pannes', 'slug' => 'voir_les_pannes'],
            ['nom' => 'Voir une panne', 'slug' => 'voir_panne'],
            ['nom' => 'Créer une panne', 'slug' => 'creer_panne'],
            ['nom' => 'Modifier une panne', 'slug' => 'modifier_panne'],
            ['nom' => 'Supprimer une panne', 'slug' => 'supprimer_panne'],
            ['nom' => 'Résoudre une panne', 'slug' => 'resoudre_panne'],

            // Pays
            ['nom' => 'Voir les pays', 'slug' => 'voir_les_pays'],
            ['nom' => 'Voir un pays', 'slug' => 'voir_pays'],
            ['nom' => 'Créer un pays', 'slug' => 'creer_pays'],
            ['nom' => 'Modifier un pays', 'slug' => 'modifier_pays'],
            ['nom' => 'Supprimer un pays', 'slug' => 'supprimer_pays'],

            // Permission
            ['nom' => 'Voir les permissions', 'slug' => 'voir_les_permissions'],
            ['nom' => 'Voir une permission', 'slug' => 'voir_permission'],
            ['nom' => 'Créer une permission', 'slug' => 'creer_permission'],
            ['nom' => 'Modifier une permission', 'slug' => 'modifier_permission'],
            ['nom' => 'Supprimer une permission', 'slug' => 'supprimer_permission'],

            // Programmation
            ['nom' => 'Voir les programmations', 'slug' => 'voir_les_programmations'],
            ['nom' => 'Voir une programmation', 'slug' => 'voir_programmation'],
            ['nom' => 'Créer une programmation', 'slug' => 'creer_programmation'],
            ['nom' => 'Modifier une programmation', 'slug' => 'modifier_programmation'],
            ['nom' => 'Supprimer une programmation', 'slug' => 'supprimer_programmation'],
            ['nom' => 'Exécuter une programmation', 'slug' => 'executer_programmation'],

            // RapportIntervention
            ['nom' => 'Voir les rapports d\'intervention', 'slug' => 'voir_les_rapports_intervention'],
            ['nom' => 'Voir un rapport d\'intervention', 'slug' => 'voir_rapport_intervention'],
            ['nom' => 'Créer un rapport d\'intervention', 'slug' => 'creer_rapport_intervention'],
            ['nom' => 'Modifier un rapport d\'intervention', 'slug' => 'modifier_rapport_intervention'],
            ['nom' => 'Supprimer un rapport d\'intervention', 'slug' => 'supprimer_rapport_intervention'],
            ['nom' => 'Approuver un rapport d\'intervention', 'slug' => 'approuver_rapport_intervention'],

            // Role
            ['nom' => 'Voir les rôles', 'slug' => 'voir_les_roles'],
            ['nom' => 'Voir un rôle', 'slug' => 'voir_role'],
            ['nom' => 'Créer un rôle', 'slug' => 'creer_role'],
            ['nom' => 'Modifier un rôle', 'slug' => 'modifier_role'],
            ['nom' => 'Supprimer un rôle', 'slug' => 'supprimer_role'],
            ['nom' => 'Assigner des permissions à un rôle', 'slug' => 'assigner_permissions_role'],

            // RolePermission
            ['nom' => 'Voir les permissions de rôle', 'slug' => 'voir_les_permissions_role'],
            ['nom' => 'Voir une permission de rôle', 'slug' => 'voir_role_permission'],
            ['nom' => 'Créer une permission de rôle', 'slug' => 'creer_role_permission'],
            ['nom' => 'Supprimer une permission de rôle', 'slug' => 'supprimer_role_permission'],

            // Sirene
            ['nom' => 'Voir les sirènes', 'slug' => 'voir_les_sirenes'],
            ['nom' => 'Voir une sirène', 'slug' => 'voir_sirene'],
            ['nom' => 'Créer une sirène', 'slug' => 'creer_sirene'],
            ['nom' => 'Modifier une sirène', 'slug' => 'modifier_sirene'],
            ['nom' => 'Supprimer une sirène', 'slug' => 'supprimer_sirene'],
            ['nom' => 'Activer une sirène', 'slug' => 'activer_sirene'],
            ['nom' => 'Désactiver une sirène', 'slug' => 'desactiver_sirene'],
            ['nom' => 'Tester une sirène', 'slug' => 'tester_sirene'],
            ['nom' => 'Configurer une sirène', 'slug' => 'configurer_sirene'],

            // Site
            ['nom' => 'Voir les sites', 'slug' => 'voir_les_sites'],
            ['nom' => 'Voir un site', 'slug' => 'voir_site'],
            ['nom' => 'Créer un site', 'slug' => 'creer_site'],
            ['nom' => 'Modifier un site', 'slug' => 'modifier_site'],
            ['nom' => 'Supprimer un site', 'slug' => 'supprimer_site'],
            ['nom' => 'Gérer les sirènes de site', 'slug' => 'gerer_sirenes_site'],

            // Technicien
            ['nom' => 'Voir les techniciens', 'slug' => 'voir_les_techniciens'],
            ['nom' => 'Voir un technicien', 'slug' => 'voir_technicien'],
            ['nom' => 'Créer un technicien', 'slug' => 'creer_technicien'],
            ['nom' => 'Modifier un technicien', 'slug' => 'modifier_technicien'],
            ['nom' => 'Supprimer un technicien', 'slug' => 'supprimer_technicien'],
            ['nom' => 'Assigner des missions à un technicien', 'slug' => 'assigner_missions_technicien'],

            // TokenSirene
            ['nom' => 'Voir les tokens de sirène', 'slug' => 'voir_les_tokens_sirene'],
            ['nom' => 'Voir un token de sirène', 'slug' => 'voir_token_sirene'],
            ['nom' => 'Créer un token de sirène', 'slug' => 'creer_token_sirene'],
            ['nom' => 'Modifier un token de sirène', 'slug' => 'modifier_token_sirene'],
            ['nom' => 'Supprimer un token de sirène', 'slug' => 'supprimer_token_sirene'],
            ['nom' => 'Révoquer un token de sirène', 'slug' => 'revoquer_token_sirene'],

            // TypeEtablissement
            ['nom' => 'Voir les types d\'établissement', 'slug' => 'voir_les_types_etablissement'],
            ['nom' => 'Voir un type d\'établissement', 'slug' => 'voir_type_etablissement'],
            ['nom' => 'Créer un type d\'établissement', 'slug' => 'creer_type_etablissement'],
            ['nom' => 'Modifier un type d\'établissement', 'slug' => 'modifier_type_etablissement'],
            ['nom' => 'Supprimer un type d\'établissement', 'slug' => 'supprimer_type_etablissement'],

            // Utilisateur (User)
            ['nom' => 'Voir les utilisateurs', 'slug' => 'voir_les_utilisateurs'],
            ['nom' => 'Voir un utilisateur', 'slug' => 'voir_utilisateur'],
            ['nom' => 'Créer un utilisateur', 'slug' => 'creer_utilisateur'],
            ['nom' => 'Modifier un utilisateur', 'slug' => 'modifier_utilisateur'],
            ['nom' => 'Supprimer un utilisateur', 'slug' => 'supprimer_utilisateur'],
            ['nom' => 'Assigner des rôles à un utilisateur', 'slug' => 'assigner_roles_utilisateur'],
            ['nom' => 'Réinitialiser le mot de passe d\'un utilisateur', 'slug' => 'reinitialiser_mot_de_passe_utilisateur'],
            ['nom' => 'Voir son propre profil', 'slug' => 'voir_son_propre_profil'],
            ['nom' => 'Changer son propre mot de passe', 'slug' => 'changer_son_propre_mot_de_passe'],

            // Informations Utilisateur (UserInfo)
            ['nom' => 'Voir les informations utilisateur', 'slug' => 'voir_les_informations_utilisateur'],
            ['nom' => 'Voir les details d\'un utilisateur', 'slug' => 'voir_informations_utilisateur'],
            ['nom' => 'Créer des informations utilisateur', 'slug' => 'creer_informations_utilisateur'],
            ['nom' => 'Modifier des informations utilisateur', 'slug' => 'modifier_informations_utilisateur'],
            ['nom' => 'Supprimer des informations utilisateur', 'slug' => 'supprimer_informations_utilisateur'],

            // Ville
            ['nom' => 'Voir les villes', 'slug' => 'voir_les_villes'],
            ['nom' => 'Voir une ville', 'slug' => 'voir_ville'],
            ['nom' => 'Créer une ville', 'slug' => 'creer_ville'],
            ['nom' => 'Modifier une ville', 'slug' => 'modifier_ville'],
            ['nom' => 'Supprimer une ville', 'slug' => 'supprimer_ville'],
        ];

        foreach ($permissions as $permissionData) {
            $permission = Permission::firstOrNew(['slug' => $permissionData['slug']], $permissionData);

            if ($permission->id == null) {

                $permission->id = Permission::generateUlid();
                $permission->fill($permissionData);
                $permission->save();
            }
        }
    }
}
