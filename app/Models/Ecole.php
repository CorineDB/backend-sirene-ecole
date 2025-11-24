<?php

namespace App\Models;

use App\Enums\StatutAbonnement;
use App\Traits\HasCodeEtablissement;
use App\Traits\HasUlid;
use App\Traits\SoftDeletesUniqueFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class Ecole extends Model
{
    use HasUlid, HasCodeEtablissement, SoftDeletes, SoftDeletesUniqueFields, Notifiable;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('userAccess', function (Builder $builder) {
            $user = auth()->user();

            if (!$user) {
                return;
            }

            // Si l'utilisateur est une école, ne voir que sa propre école
            if ($user->isEcoleUser()) {
                $ecole = $user->userAccount;
                if ($ecole) {
                    $builder->where('id', $ecole->id);
                }
            }

            // Si admin ou technicien, pas de filtre (retourne toutes les écoles)
        });
    }

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'ecoles';

    protected $fillable = [
        'reference',
        'nom_complet',
        'nom',
        'telephone_contact',
        'email_contact',
        'est_prive',
        'code_etablissement',
        'responsable_nom',
        'responsable_prenom',
        'responsable_telephone',
        'statut',
        'date_inscription',
    ];

    protected $casts = [
        'est_prive' => 'boolean',
        'date_inscription' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relations

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class, 'ecole_principale_id');
    }

    public function sitesAnnexe(): HasMany
    {
        return $this->hasMany(Site::class, 'ecole_principale_id')->where('est_principale', FALSE);
    }

    public function sitePrincipal(): HasOne
    {
        return $this->hasOne(Site::class, 'ecole_principale_id')->where('est_principale', true);
    }

    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'user_account_type');
    }

    public function sirenes(): HasMany
    {
        return $this->hasMany(Sirene::class);
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }

    public function abonnementActif(): HasOne
    {
        return $this->hasOne(Abonnement::class)
            ->where('statut', StatutAbonnement::ACTIF)
            ->where('date_fin', '>=', now())
            ->latest('date_debut')
            ->latest('created_at');
    }

    public function abonnementEnAttente(): HasOne
    {
        return $this->hasOne(Abonnement::class)
            ->where('statut', StatutAbonnement::EN_ATTENTE)
            ->where('date_fin', '>=', now())
            ->latest('date_debut')
            ->latest('created_at');
    }

    public function programmations(): HasMany
    {
        return $this->hasMany(Programmation::class);
    }

    public function pannes(): HasMany
    {
        return $this->hasMany(Panne::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function joursFeries(): HasMany
    {
        return $this->hasMany(JourFerie::class);
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    public function moyensPaiement(): MorphMany
    {
        return $this->morphMany(MoyenPaiement::class, 'paiementable');
    }

    public function moyenPaiementDefaut()
    {
        return $this->morphOne(MoyenPaiement::class, 'paiementable')->where('par_defaut', true)->where('actif', true);
    }

    // Helpers
    public function hasActiveSubscription(): bool
    {
        return $this->abonnements()
            ->where('statut', 'actif')
            ->where('date_fin', '>=', now())
            ->exists();
    }

    /**
     * Get the unique fields that should be updated on soft delete.
     *
     * @return array
     */
    protected function getUniqueSoftDeleteFields(): array
    {
        return ['code_etablissement', 'nom_complet', 'telephone_contact', 'email_contact'];
    }
}
