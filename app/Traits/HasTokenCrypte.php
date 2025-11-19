<?php

namespace App\Traits;

use App\Models\TokenSirene;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

trait HasTokenCrypte
{
    use HasCryptageESP8266;

    /**
     * Version du protocole de token ESP8266
     */
    protected int $TOKEN_VERSION = 1;
    protected static function bootHasTokenCrypte(): void
    {
        static::updating(function (Model $model) {
            // Génère un nouveau token quand l'abonnement passe à 'actif'
            /* if ($model->isDirty('statut') && $model->statut->value === 'actif') {

                // Désactiver les anciens tokens pour cet abonnement
                TokenSirene::where('abonnement_id', $model->id)
                    ->update(['actif' => false]);

                // Générer un nouveau token
                self::genererTokenCrypte($model);
            } */

            $oldStatut = $model->getOriginal('statut');
            $oldValue = $oldStatut instanceof \BackedEnum ? $oldStatut->value : $oldStatut;
            $newStatut = $model->statut instanceof \BackedEnum
                ? $model->statut->value
                : $model->statut;

            // ✅ On compare les valeurs brutes
            if ($oldValue !== $newStatut && $newStatut === \App\Enums\StatutAbonnement::ACTIF->value) {
                // Avant de créer un nouveau token, désactiver les anciens
                \App\Models\TokenSirene::where('abonnement_id', $model->id)
                    ->update(['actif' => false]);

                self::genererTokenCrypte($model);
            }
        });
    }

    protected static function genererTokenCrypte(Model $model): void
    {
        // $model est l'abonnement
        // Vérifier que l'abonnement a été payé (statut paiement = valide)
        $paiementValide = $model->paiements()
            ->where('statut', 'valide')
            ->exists();

        if (!$paiementValide) {
            return; // Pas de paiement validé, pas de token
        }

        // Vérifier qu'il n'existe pas déjà un token actif pour cet abonnement
        $tokenExistant = TokenSirene::where('abonnement_id', $model->id)
            ->where('actif', true)
            ->exists();

        if ($tokenExistant) {
            return; // Un token existe déjà pour cet abonnement
        }

        // Charger les relations nécessaires
        $model->load(['sirene', 'ecole', 'site']);

        // Générer le token au format Python: VERSION|ECOLE|SERIAL|TIMESTAMP_DEBUT|TIMESTAMP_FIN|CHECKSUM
        $instance = new class {
            use HasCryptageESP8266;
        };

        $parts = [
            $instance->TOKEN_VERSION ?? 1, // VERSION
            $model->ecole_id, // ECOLE (ULID)
            $model->sirene->numero_serie ?? '', // SERIAL
            Carbon::parse($model->date_debut)->timestamp, // TIMESTAMP_DEBUT
            Carbon::parse($model->date_fin)->timestamp, // TIMESTAMP_FIN
        ];

        $data_str = implode('|', $parts);

        // Crypter avec checksum de 16 caractères
        $tokenCrypte = $instance->crypterDonneesESP8266($data_str, true, 16);

        // Hash du token pour vérification rapide
        $tokenHash = hash('sha256', $tokenCrypte);

        // Créer l'enregistrement dans tokens_sirene
        TokenSirene::create([
            'abonnement_id' => $model->id,
            'sirene_id' => $model->sirene_id,
            'site_id' => $model->site_id,
            'token_crypte' => $tokenCrypte,
            'token_hash' => $tokenHash,
            'date_debut' => $model->date_debut,
            'date_fin' => $model->date_fin,
            'date_generation' => Carbon::now(),
            'date_expiration' => Carbon::parse($model->date_fin),
            'actif' => true,
        ]);
    }

    public function tokens()
    {
        return $this->hasMany(TokenSirene::class, 'abonnement_id');
    }

    public function tokenActif()
    {
        return $this->hasOne(TokenSirene::class, 'abonnement_id')
            ->where('actif', true)
            ->where('date_expiration', '>=', now())
            ->latest();
    }

    public function regenererToken(): void
    {
        // Désactiver tous les tokens actifs pour cet abonnement
        TokenSirene::where('abonnement_id', $this->id)
            ->update(['actif' => false]);

        // Générer un nouveau token
        self::genererTokenCrypte($this);
    }

    public function getTokenActif(): ?TokenSirene
    {
        return $this->tokenActif;
    }
}
