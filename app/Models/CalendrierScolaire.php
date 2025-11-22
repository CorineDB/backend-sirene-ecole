<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendrierScolaire extends Model
{
    use HasUlid, SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'calendriers_scolaires';

    protected $fillable = [
        'pays_id',
        'annee_scolaire',
        'description',
        'date_rentree',
        'date_fin_annee',
        'periodes_vacances',
        'jours_feries_defaut',
        'actif',
    ];

    protected $casts = [
        'date_rentree'          => 'date:Y-m-d',
        'date_fin_annee'        => 'date:Y-m-d',
        'periodes_vacances'     => 'array',
        'jours_feries_defaut'   => 'array',
        'actif'                 => 'boolean',
        'created_at'            => 'datetime:Y-m-d H:i:s',
        'updated_at'            => 'datetime:Y-m-d H:i:s',
        'deleted_at'            => 'datetime:Y-m-d H:i:s',
    ];

    // Relations
    public function pays(): BelongsTo
    {
        return $this->belongsTo(Pays::class, 'pays_id');
    }

    public function joursFeries(): HasMany
    {
        return $this->hasMany(JourFerie::class, 'calendrier_id');
    }

    public function programmations(): HasMany
    {
        return $this->hasMany(Programmation::class, 'calendrier_id');
    }
}
