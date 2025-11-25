<?php

namespace App\Models;

use App\Enums\StatutCandidature;
use App\Enums\StatutMission;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MissionTechnicien extends Model
{
    use HasUlid, SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'missions_techniciens';

    protected $fillable = [
        'ordre_mission_id',
        'technicien_id',
        'statut_candidature',
        'statut',
        'date_acceptation',
        'date_cloture',
        'date_retrait',
        'motif_retrait',
        'is_suspended',
        'motif_suspension',
        'date_suspension',
    ];

    protected $casts = [
        'statut' => StatutMission::class,
        'statut_candidature' => StatutCandidature::class,
        'date_acceptation' => 'datetime',
        'date_cloture' => 'datetime',
        'date_retrait' => 'datetime',
        'is_suspended' => 'boolean',
        'date_suspension' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relations
    public function ordreMission(): BelongsTo
    {
        return $this->belongsTo(OrdreMission::class, 'ordre_mission_id');
    }

    public function technicien(): BelongsTo
    {
        return $this->belongsTo(Technicien::class, 'technicien_id');
    }
}
