<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ville extends Model
{
    use HasUlid, SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'villes';

    protected $fillable = [
        'pays_id',
        'nom',
        'code',
        'latitude',
        'longitude',
        'actif',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'actif' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'nom_pays',
        'nom_complet',
    ];

    // Accesseurs
    /**
     * Obtenir le nom du pays de la ville
     *
     * @return string|null
     */
    public function getNomPaysAttribute(): ?string
    {
        return $this->pays?->nom;
    }

    /**
     * Obtenir le nom complet de la ville avec le pays
     * Format: "Ville - Pays" (ex: "Dakar - Sénégal")
     *
     * @return string
     */
    public function getNomCompletAttribute(): string
    {
        if ($this->nom_pays) {
            return $this->nom . ' - ' . $this->nom_pays;
        }

        return $this->nom;
    }

    // Relations
    public function pays(): BelongsTo
    {
        return $this->belongsTo(Pays::class, 'pays_id');
    }

    public function ecoles(): HasMany
    {
        return $this->hasMany(Ecole::class);
    }

    public function techniciens(): HasMany
    {
        return $this->hasMany(Technicien::class);
    }
}
