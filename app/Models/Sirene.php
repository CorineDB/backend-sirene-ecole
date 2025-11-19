<?php

namespace App\Models;

use App\Enums\StatutSirene;
use App\Traits\HasNumeroSerie;
use App\Traits\HasUlid;
use App\Traits\HasAbonnementAnnuel;
use App\Traits\HasPannes;
use App\Traits\SoftDeletesUniqueFields;
use Illuminate\Database\Eloquent\Model;
use App\Enums\StatutAbonnement;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sirene extends Model
{
    use HasUlid, HasNumeroSerie, HasAbonnementAnnuel, HasPannes, SoftDeletes, SoftDeletesUniqueFields;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'sirenes';

    protected $fillable = [
        'modele_id',
        'ecole_id',
        'site_id',
        'numero_serie',
        'date_installation',
        'date_fin',
        'date_fabrication',
        'etat',
        'statut',
        'old_statut',
        'notes',
    ];

    protected $casts = [
        'date_fabrication' => 'date',
        'date_installation' => 'date',
        'date_fin' => 'date',
        'old_statut' => StatutSirene::class,
        'statut' =>     StatutSirene::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = ['modele'];

    // Relations
    public function modeleSirene(): BelongsTo
    {
        return $this->belongsTo(ModeleSirene::class, 'modele_id');
    }

    // Accessor pour compatibilité avec le frontend
    public function getModeleAttribute()
    {
        return $this->modeleSirene;
    }

    public function ecole(): BelongsTo
    {
        return $this->belongsTo(Ecole::class, 'ecole_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }

    public function abonnementActif(): HasOne
    {
        return $this->hasOne(Abonnement::class)
            ->where('statut', StatutAbonnement::ACTIF)
            ->where('date_fin', '>=', now())
            ->latest('date_debut')
            ->latest('created_at');
    }

    public function abonnementEnAttente(): HasOne
    {
        return $this->hasOne(Abonnement::class)
            ->where('statut', StatutAbonnement::EN_ATTENTE)
            ->where('date_fin', '>=', now())
            ->latest('date_debut')
            ->latest('created_at');
    }

    public function programmations(): HasMany
    {
        return $this->hasMany(Programmation::class);
    }

    public function pannes(): HasMany
    {
        return $this->hasMany(Panne::class);
    }

    // Helpers
    public function isInstalled(): bool
    {
        return $this->statut === StatutSirene::INSTALLE;
    }

    public function isInStock(): bool
    {
        return $this->statut === StatutSirene::EN_STOCK;
    }

    /**
     * Get the unique fields that should be updated on soft delete.
     *
     * @return array
     */
    protected function getUniqueSoftDeleteFields(): array
    {
        return ['numero_serie'];
    }
}
