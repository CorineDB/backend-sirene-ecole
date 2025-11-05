<?php

namespace App\Models;

use App\Enums\StatutRapportIntervention; // Assuming this enum exists or will be created
use App\Enums\ResultatIntervention; // Assuming this enum exists or will be created
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RapportIntervention extends Model
{
    use HasUlid, SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'rapports_intervention';

    protected $fillable = [
        'intervention_id',
        'rapport',
        'date_soumission',
        'statut',
        'photo_url',
        'review_note',
        'review_admin',
        'diagnostic',
        'travaux_effectues',
        'pieces_utilisees',
        'resultat',
        'recommandations',
        'photos',
        'date_rapport',
    ];

    protected $casts = [
        'date_soumission' => 'datetime',
        'statut' => StatutRapportIntervention::class,
        'photo_url' => 'array',
        'review_note' => 'integer',
        'resultat' => ResultatIntervention::class,
        'photos' => 'array',
        'date_rapport' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relations
    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class, 'intervention_id');
    }

    public function avis(): HasMany
    {
        return $this->hasMany(AvisRapport::class, 'rapport_intervention_id');
    }
}
