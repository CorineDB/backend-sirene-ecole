<?php

namespace App\Models;

use App\Enums\StatutAbonnement;
use App\Traits\HasUlid;
use App\Traits\HasQrCodeAbonnement;
use App\Traits\HasTokenCrypte;
use App\Traits\HasNumeroAbonnement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Abonnement extends Model
{
    use HasUlid, SoftDeletes, HasQrCodeAbonnement, HasTokenCrypte/* , HasNumeroAbonnement */;

    protected $primaryKey = 'id';
    protected $keyType = 'string';

    protected $table = 'abonnements';

    protected $fillable = [
        'ecole_id',
        'site_id',
        'sirene_id',
        'parent_abonnement_id',
        'numero_abonnement',
        'date_debut',
        'date_fin',
        'montant',
        'statut',
        'auto_renouvellement',
        'notes',
        'qr_code_path',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'montant' => 'decimal:2',
        'statut' => StatutAbonnement::class,
        'auto_renouvellement' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relations
    public function ecole(): BelongsTo
    {
        return $this->belongsTo(Ecole::class, 'ecole_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function sirene(): BelongsTo
    {
        return $this->belongsTo(Sirene::class, 'sirene_id');
    }

    public function parentAbonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class, 'parent_abonnement_id');
    }

    public function childAbonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class, 'parent_abonnement_id');
    }

    public function token(): HasOne
    {
        return $this->hasOne(TokenSirene::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->statut === StatutAbonnement::ACTIF
            && $this->date_fin >= now();
    }

    public function isExpired(): bool
    {
        return $this->date_fin < now();
    }

    public function daysRemaining(): int
    {
        return max(0, now()->diffInDays($this->date_fin, false));
    }
}
