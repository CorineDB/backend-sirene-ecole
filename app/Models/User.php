<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use App\Traits\SoftDeletesUniqueFields;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUlid, SoftDeletes, HasApiTokens, SoftDeletesUniqueFields;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'users';

    protected $fillable = [
        'nom_utilisateur',
        'identifiant',
        'mot_de_passe',
        'doit_changer_mot_de_passe',
        'mot_de_passe_change',
        'type',
        'user_account_type_id',
        'user_account_type_type',
        'role_id',
        'actif',
        'statut',
    ];

    /**
     * Get the unique fields that should be updated on soft delete.
     *
     * @return array
     */
    protected function getUniqueSoftDeleteFields(): array
    {
        return ['nom_utilisateur', 'identifiant'];
    }

    protected $hidden = [
        'mot_de_passe',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'doit_changer_mot_de_passe' => 'boolean',
            'mot_de_passe_change' => 'boolean',
            'statut' => 'integer',
        ];
    }

    // Polymorphic relationship to user account (Ecole, Technicien, Admin)
    public function userAccount()
    {
        return $this->morphTo('user_account_type');
    }

    // Role relationship
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // User info relationship
    public function userInfo()
    {
        return $this->hasOne(UserInfo::class);
    }

    // OTP codes relationship
    public function otpCodes()
    {
        return $this->hasMany(OtpCode::class);
    }

    // Notifications morphed
    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    /**
     * Check if the user is an admin.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role && $this->role->slug === 'admin';
    }

    /**
     * Check if the user is a technicien.
     *
     * @return bool
     */
    public function isTechnicien(): bool
    {
        return $this->type === 'technicien' ||
               ($this->role && $this->role->slug === 'technicien');
    }

    /**
     * Get the technicien account if user is a technicien.
     *
     * @return \App\Models\Technicien|null
     */
    public function getTechnicien(): ?Technicien
    {
        if ($this->isTechnicien() && $this->user_account_type_type === 'App\\Models\\Technicien') {
            return $this->userAccount;
        }
        return null;
    }

    /**
     * Check if the user is an ecole.
     *
     * @return bool
     */
    public function isEcole(): bool
    {
        return $this->type === 'ecole' ||
               ($this->role && $this->role->slug === 'ecole');
    }

    /**
     * Get the ecole account if user is an ecole.
     *
     * @return \App\Models\Ecole|null
     */
    public function getEcole(): ?Ecole
    {
        if ($this->isEcole() && $this->user_account_type_type === 'App\\Models\\Ecole') {
            return $this->userAccount;
        }
        return null;
    }

    /**
     * Vérifier si l'utilisateur connecté est une école (vérifie le type de compte polymorphique)
     *
     * @return bool
     */
    public function isEcoleUser(): bool
    {
        return $this->user_account_type_type === Ecole::class;
    }

    /**
     * Vérifier si l'utilisateur connecté est un technicien (vérifie le type de compte polymorphique)
     *
     * @return bool
     */
    public function isTechnicienUser(): bool
    {
        return $this->user_account_type_type === Technicien::class;
    }
}
