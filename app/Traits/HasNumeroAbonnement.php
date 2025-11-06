<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasNumeroAbonnement
{
    protected static function bootHasNumeroAbonnement(): void
    {
        static::creating(function (Model $model) {
            // Générer le numéro d'abonnement avant la création si non fourni
            if (empty($model->numero_abonnement)) {
                $model->numero_abonnement = self::generateNumeroAbonnement();
            }
        });
    }

    /**
     * Générer un numéro d'abonnement unique
     * Format: ABO-YYYYMMDD-XXXXXX
     * Exemple: ABO-20251105-A3B7F9
     */
    protected static function generateNumeroAbonnement(): string
    {
        $prefix = 'ABO';
        $date = date('Ymd');

        do {
            $randomCode = strtoupper(Str::random(6));
            $numero = "{$prefix}-{$date}-{$randomCode}";

            // Vérifier l'unicité dans la base de données
            $exists = static::where('numero_abonnement', $numero)->exists();

        } while ($exists);

        return $numero;
    }

    /**
     * Générer un nouveau numéro d'abonnement manuellement
     * Utile pour les renouvellements
     */
    public function regenererNumeroAbonnement(): string
    {
        $numero = self::generateNumeroAbonnement();
        $this->numero_abonnement = $numero;
        $this->save();

        return $numero;
    }
}
