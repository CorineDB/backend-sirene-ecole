<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\SoftDeletesUniqueFields;

class UserInfo extends Model
{
    use HasFactory, HasUlid, SoftDeletes, SoftDeletesUniqueFields;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'user_infos';

    protected $fillable = [
        'user_id',
        'nom',
        'prenom',
        'telephone',
        'email',
        'ville_id',
        'adresse',
    ];

    /**
     * Get the unique fields that should be updated on soft delete.
     *
     * @return array
     */
    protected function getUniqueSoftDeleteFields(): array
    {
        return ['telephone', 'email'];
    }

    protected $appends = [
        'nom_ville',
        'nom_complet',
    ];

    // Accesseurs
    /**
     * Obtenir le nom de la ville de l'utilisateur
     *
     * @return string|null
     */
    public function getNomVilleAttribute(): ?string
    {
        return $this->ville?->nom;
    }

    /**
     * Obtenir le nom complet de l'utilisateur (prénom + nom)
     * Format: "Prénom Nom" (ex: "John Doe")
     *
     * @return string
     */
    public function getNomCompletAttribute(): string
    {
        $parts = array_filter([$this->prenom, $this->nom]);
        return implode(' ', $parts) ?: '';
    }

    // User relationship
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Ville relationship
    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'ville_id');
    }
}
