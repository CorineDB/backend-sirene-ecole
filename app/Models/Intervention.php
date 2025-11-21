<?php

namespace App\Models;

use App\Enums\StatutIntervention;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Intervention extends Model
{
    use HasUlid, SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'interventions';

    protected $fillable = [
        'panne_id',
        'ordre_mission_id',
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
        'old_statut' => StatutIntervention::class,
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
