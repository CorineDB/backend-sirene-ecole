<?php

namespace App\Models;

use App\Enums\StatutOrdreMission;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrdreMission extends Model
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

            // Si l'utilisateur est un technicien, filtrer par ville
            if ($user->isTechnicienUser()) {
                $technicien = $user->userAccount;
                if ($technicien && $technicien->ville_id) {
                    $builder->where('ville_id', $technicien->ville_id);
                }
                return;
            }

            // Si l'utilisateur est une école, filtrer par école via la panne
            if ($user->isEcoleUser()) {
                $ecole = $user->userAccount;
                if ($ecole) {
                    $builder->whereHas('panne', function ($q) use ($ecole) {
                        $q->where('ecole_id', $ecole->id);
                    });
                }
            }

            // Si admin, pas de filtre (retourne tous les ordres de mission)
        });
    }

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'ordres_mission';

    protected $fillable = [
        'panne_id',
        'ville_id',
        'date_generation',
        'date_debut_candidature',
        'date_fin_candidature',
        'nombre_techniciens_requis',
        'nombre_techniciens_acceptes',
        'candidature_cloturee',
        'date_cloture_candidature',
        'cloture_par',
        'valide_par',
        'statut',
        'commentaire',
        'numero_ordre',
    ];

    protected $casts = [
        'statut' => StatutOrdreMission::class,
        'date_generation' => 'datetime',
        'date_debut_candidature' => 'datetime',
        'date_fin_candidature' => 'datetime',
        'nombre_techniciens_requis' => 'integer',
        'nombre_techniciens_acceptes' => 'integer',
        'candidature_cloturee' => 'boolean',
        'date_cloture_candidature' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relations
    public function panne(): BelongsTo
    {
        return $this->belongsTo(Panne::class, 'panne_id');
    }

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'ville_id');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function cloturePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cloture_par');
    }

    public function techniciens(): HasMany
    {
        return $this->hasMany(MissionTechnicien::class, 'ordre_mission_id')
            ->where('statut_candidature', 'acceptee');
    }

    public function missionsTechniciens(): HasMany
    {
        return $this->hasMany(MissionTechnicien::class, 'ordre_mission_id');
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class, 'ordre_mission_id');
    }

    // Helper methods
    public function candidatureOuverte(): bool
    {
        // Vérifier si l'admin a clôturé manuellement les candidatures
        if ($this->candidature_cloturee) {
            return false;
        }

        // Vérifier si le quota est atteint (clôture automatique)
        if ($this->nombreTechniciensAtteint()) {
            return false;
        }

        // Si des dates sont définies, vérifier la période
        if ($this->date_debut_candidature || $this->date_fin_candidature) {
            $now = now();
            $apresDebut = !$this->date_debut_candidature || $now->greaterThanOrEqualTo($this->date_debut_candidature);
            $avantFin = !$this->date_fin_candidature || $now->lessThanOrEqualTo($this->date_fin_candidature);

            return $apresDebut && $avantFin;
        }

        // Si pas de dates et quota non atteint, candidature ouverte
        return true;
    }

    public function nombreTechniciensAtteint(): bool
    {
        return $this->nombre_techniciens_acceptes >= $this->nombre_techniciens_requis;
    }

    public function peutAccepterTechnicien(): bool
    {
        return $this->nombre_techniciens_acceptes < $this->nombre_techniciens_requis;
    }
}
