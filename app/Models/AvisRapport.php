<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AvisRapport extends Model
{
    use HasUlid, SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'avis_rapports';

    protected $fillable = [
        'rapport_intervention_id',
        'admin_id',
        'note',
        'review',
        'type_evaluation',
        'approuve',
        'points_forts',
        'points_amelioration',
        'date_evaluation',
    ];

    protected $casts = [
        'note' => 'integer',
        'approuve' => 'boolean',
        'date_evaluation' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relations
    public function rapportIntervention(): BelongsTo
    {
        return $this->belongsTo(RapportIntervention::class, 'rapport_intervention_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
