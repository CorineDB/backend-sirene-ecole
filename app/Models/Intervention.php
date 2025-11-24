<?php

namespace App\Models;

use App\Enums\StatutIntervention;
use App\Enums\TypeIntervention;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Intervention extends Model
{
    use HasUlid, SoftDeletes;

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

            // Si l'utilisateur est un technicien, filtrer ses interventions
            if ($user->isTechnicienUser()) {
                if ($user->user_account_type_id) {
                    $builder->whereHas('techniciens', function ($q) use ($user) {
                        $q->where('techniciens.id', $user->user_account_type_id);
                    });
                }
                return;
            }

            // Si l'utilisateur est une école, filtrer par école
            if ($user->isEcoleUser()) {
                if ($user->user_account_type_id) {
                    $builder->whereHas('panne', function ($q) use ($user) {
                        $q->whereHas('site', function ($siteQ) use ($user) {
                            $siteQ->where('ecole_principale_id', $user->user_account_type_id);
                        });
                    });
                }
            }

            // Si admin, pas de filtre (retourne toutes les interventions)
        });
    }

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'interventions';

    protected $fillable = [
        'panne_id',
        'ordre_mission_id',
        'type_intervention',
        'nombre_techniciens_requis',
        'date_intervention',
        'date_affectation',
        'date_assignation',
        'date_acceptation',
        'date_debut',
        'date_fin',
        'statut',
        'old_statut',
        'note_ecole',
        'commentaire_ecole',
        'observations',
        'instructions',
        'lieu_rdv',
        'heure_rdv',
    ];

    protected $casts = [
        'date_intervention' => 'datetime',
        'date_affectation' => 'datetime',
        'date_assignation' => 'datetime',
        'date_acceptation' => 'datetime',
        'date_debut' => 'datetime',
        'date_fin' => 'datetime',
        'type_intervention' => TypeIntervention::class,
        'old_statut' => StatutIntervention::class,
        'nombre_techniciens_requis' => 'integer',
        'note_ecole' => 'integer',
        'heure_rdv' => 'datetime:H:i',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relations
    public function panne(): BelongsTo
    {
        return $this->belongsTo(Panne::class, 'panne_id');
    }

    public function ordreMission(): BelongsTo
    {
        return $this->belongsTo(OrdreMission::class, 'ordre_mission_id');
    }

    /**
     * Techniciens assignés à cette intervention (équipe)
     */
    public function techniciens(): BelongsToMany
    {
        return $this->belongsToMany(Technicien::class, 'intervention_technicien', 'intervention_id', 'technicien_id')
            ->using(InterventionTechnicien::class)
            ->withPivot(['date_assignation', 'role', 'notes'])
            ->withTimestamps();
    }

    /**
     * Rapports d'intervention (peut y en avoir plusieurs, un par technicien ou un collectif)
     */
    public function rapports(): HasMany
    {
        return $this->hasMany(RapportIntervention::class, 'intervention_id');
    }

    /**
     * Alias pour compatibilité - retourne le premier rapport
     */
    public function rapport()
    {
        return $this->hasOne(RapportIntervention::class, 'intervention_id')->latestOfMany();
    }

    public function avis(): HasMany
    {
        return $this->hasMany(AvisIntervention::class, 'intervention_id');
    }

    // Helper methods
    /**
     * Obtenir le site de l'intervention via la panne
     */
    public function getSiteAttribute()
    {
        return $this->panne?->site;
    }

    /**
     * Obtenir l'école de l'intervention via la panne
     */
    public function getEcoleAttribute()
    {
        return $this->panne?->ecole;
    }
}
