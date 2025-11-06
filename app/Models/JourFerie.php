<?php

namespace App\Models;

use App\Traits\HasUlid;
use App\Traits\SoftDeletesUniqueFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JourFerie extends Model
{
    use HasUlid, SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'jours_feries';

    protected $fillable = [
        'calendrier_id',
        'ecole_id',
        'pays_id',
        'intitule_journee',
        'date',
        'recurrent',
        'actif',
        'est_national',
    ];

    protected $casts = [
        'date' => 'date',
        'recurrent' => 'boolean',
        'actif' => 'boolean',
        'est_national' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the unique fields that should be updated on soft delete.
     * @return array
     */
    protected function getUniqueSoftDeleteFields(): array
    {
        return ['intitule_journee']; // Assuming intitule_journee is now the primary unique identifier
    }

    // Relations
    public function ecole(): BelongsTo
    {
        return $this->belongsTo(Ecole::class, 'ecole_id');
    }

    public function calendrier(): BelongsTo
    {
        return $this->belongsTo(CalendrierScolaire::class, 'calendrier_id');
    }

    public function pays(): BelongsTo
    {
        return $this->belongsTo(Pays::class, 'pays_id');
    }
}