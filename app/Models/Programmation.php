<?php

namespace App\Models;

use App\Traits\HasUlid;
use App\Traits\HasChaineCryptee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Programmation extends Model
{
    use HasUlid, SoftDeletes, HasChaineCryptee;

    protected $table = 'programmations';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ecole_id',
        'site_id',
        'sirene_id',
        'abonnement_id',
        'calendrier_id',
        'nom_programmation',
        'horaires_sonneries',
        'jours_feries_inclus',
        'jours_feries_exceptions',
        'chaine_programmee',
        'chaine_cryptee',
        'date_debut',
        'date_fin',
        'actif',
        'cree_par',
    ];

    protected $casts = [
        'horaires_sonneries' => 'array',
        'jours_feries_inclus' => 'boolean',
        'jours_feries_exceptions' => 'array',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'actif' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Accessors
    /**
     * Calculer dynamiquement les jours de la semaine à partir de horaires_sonneries
     *
     * @return array Tableau des jours uniques (0-6)
     */
    public function getJourSemaineAttribute(): array
    {
        $joursUniques = [];

        if (is_array($this->horaires_sonneries)) {
            foreach ($this->horaires_sonneries as $horaire) {
                if (isset($horaire['jours']) && is_array($horaire['jours'])) {
                    foreach ($horaire['jours'] as $jour) {
                        if (!in_array($jour, $joursUniques)) {
                            $joursUniques[] = $jour;
                        }
                    }
                }
            }
        }

        sort($joursUniques);
        return $joursUniques;
    }

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

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class, 'abonnement_id');
    }

    public function calendrier(): BelongsTo
    {
        return $this->belongsTo(CalendrierScolaire::class, 'calendrier_id');
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
