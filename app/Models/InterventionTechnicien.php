<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class InterventionTechnicien extends Pivot
{

    use HasUlid, SoftDeletes;

    protected $table = 'intervention_technicien';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'intervention_id',
        'technicien_id',
        'date_assignation',
        'role',
        'notes',
    ];

    protected $casts = [
        'date_assignation' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relations
    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class, 'intervention_id');
    }

    public function technicien(): BelongsTo
    {
        return $this->belongsTo(Technicien::class, 'technicien_id');
    }
}
