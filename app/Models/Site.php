<?php

namespace App\Models;

use App\Traits\HasUlid;
use App\Traits\SoftDeletesUniqueFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use HasUlid, SoftDeletes, SoftDeletesUniqueFields;

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

            // Si l'utilisateur est une école, filtrer par école
            if ($user->isEcoleUser()) {
                $ecole = $user->userAccount;
                if ($ecole) {
                    $builder->where('ecole_principale_id', $ecole->id);
                }
            }

            // Si admin ou technicien, pas de filtre (retourne tous les sites)
        });
    }

    protected $table = 'sites';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ecole_principale_id',
        'nom',
        'types_etablissement',
        'responsable',
        'est_principale',
        'adresse',
        'ville_id',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'types_etablissement' => 'array',
        'est_principale' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected $appends = [
        'nom_ville'
    ];

    /**
     * Get the unique fields that should be updated on soft delete.
     *
     * @return array
     */
    protected function getUniqueSoftDeleteFields(): array
    {
        return ['nom'];
    }

    // Relations
    public function ecolePrincipale(): BelongsTo
    {
        return $this->belongsTo(Ecole::class, 'ecole_principale_id');
    }

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'ville_id');
    }

    public function sirene(): ?HasOne
    {
        return $this->hasOne(Sirene::class, 'site_id');
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class, 'site_id');
    }

    public function programmations(): HasMany
    {
        return $this->hasMany(Programmation::class, 'site_id');
    }

    public function pannes(): HasMany
    {
        return $this->hasMany(Panne::class, 'site_id');
    }

    // Accesseurs
    /**
     * Obtenir le nom de la ville de l'utilisateur
     *
     * @return string|null
     */
    public function getNomVilleAttribute(): ?string
    {
        return $this->ville?->nom_complet;
    }
}
