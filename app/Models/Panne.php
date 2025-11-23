<?php

namespace App\Models;

use App\Enums\PrioritePanne;
use App\Enums\StatutPanne;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Panne extends Model
{
    use HasUlid, SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'pannes';

    protected $fillable = [
        'ecole_id',
        'sirene_id',
        'site_id',
        'numero_panne',
        'description',
        'date_signalement',
        'priorite',
        'statut',
        'date_declaration',
        'date_validation',
        'valide_par',
        'est_cloture',
    ];

    protected $casts = [
        'priorite' => PrioritePanne::class,
        'statut' => StatutPanne::class,
        'date_signalement' => 'datetime',
        'date_declaration' => 'datetime',
        'date_validation' => 'datetime',
        'est_cloture' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relations
    public function ecole(): BelongsTo
    {
        return $this->belongsTo(Ecole::class, 'ecole_id');
    }

    public function sirene(): BelongsTo
    {
        return $this->belongsTo(Sirene::class, 'sirene_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    public function ordresMission(): HasMany
    {
        return $this->hasMany(OrdreMission::class, 'panne_id');
    }

    /**
     * Retourne le dernier ordre de mission actif pour cette panne
     */
    public function ordreMission(): HasOne
    {
        return $this->hasOne(OrdreMission::class, 'panne_id')->latestOfMany('date_generation');
    }

    /**
     * Alias pour ordreMission (pour compatibilité)
     */
    public function ordreMissionActif(): HasOne
    {
        return $this->ordreMission();
    }
}
