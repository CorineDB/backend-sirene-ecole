<?php

namespace App\Models;

use App\Enums\StatutRapportIntervention; // Assuming this enum exists or will be created
use App\Enums\ResultatIntervention; // Assuming this enum exists or will be created
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RapportIntervention extends Model
{
    use HasUlid, SoftDeletes;

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

            // Si l'utilisateur est un technicien, filtrer ses rapports
            if ($user->isTechnicienUser()) {
                if ($user->user_account_type_id) {
                    $builder->where(function ($q) use ($user) {
                        // Rapports individuels du technicien
                        $q->where('technicien_id', $user->user_account_type_id)
                          // OU rapports collectifs des interventions où il participe
                          ->orWhere(function ($collectifQ) use ($user) {
                              $collectifQ->whereNull('technicien_id')
                                         ->whereHas('intervention', function ($interventionQ) use ($user) {
                                             $interventionQ->whereHas('techniciens', function ($techQ) use ($user) {
                                                 $techQ->where('techniciens.id', $user->user_account_type_id);
                                             });
                                         });
                          });
                    });
                }
                return;
            }

            // Si l'utilisateur est une école, filtrer par école via intervention->panne
            if ($user->isEcoleUser()) {
                if ($user->user_account_type_id) {
                    $builder->whereHas('intervention', function ($q) use ($user) {
                        $q->whereHas('panne', function ($panneQ) use ($user) {
                            $panneQ->where('ecole_id', $user->user_account_type_id);
                        });
                    });
                }
            }

            // Si admin, pas de filtre (retourne tous les rapports)
        });
    }

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'rapports_intervention';

    protected $fillable = [
        'intervention_id',
        'technicien_id',
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

    /**
     * Technicien qui a rédigé ce rapport
     * Si null, c'est un rapport collectif pour toute l'équipe
     */
    public function technicien(): BelongsTo
    {
        return $this->belongsTo(Technicien::class, 'technicien_id');
    }

    public function avis(): HasMany
    {
        return $this->hasMany(AvisRapport::class, 'rapport_intervention_id');
    }

    // Helper methods
    /**
     * Vérifier si c'est un rapport collectif (pas de technicien spécifique)
     */
    public function estRapportCollectif(): bool
    {
        return $this->technicien_id === null;
    }

    /**
     * Vérifier si c'est un rapport individuel
     */
    public function estRapportIndividuel(): bool
    {
        return $this->technicien_id !== null;
    }
}
