<?php

namespace App\Models;

use App\Enums\StatutIntervention;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'technicien_id',
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relations
    public function panne(): BelongsTo
    {
        return $this->belongsTo(Panne::class, 'panne_id');
    }

    public function technicien(): BelongsTo
    {
        return $this->belongsTo(Technicien::class, 'technicien_id');
    }

    public function ordreMission(): BelongsTo
    {
        return $this->belongsTo(OrdreMission::class, 'ordre_mission_id');
    }

    public function rapport(): HasOne
    {
        return $this->hasOne(RapportIntervention::class, 'intervention_id');
    }

    public function avis(): HasMany
    {
        return $this->hasMany(AvisIntervention::class, 'intervention_id');
    }
}
