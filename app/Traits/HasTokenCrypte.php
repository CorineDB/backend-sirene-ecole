<?php

namespace App\Traits;

use App\Models\TokenSirene;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
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
        try {
            Log::info('HasTokenCrypte::genererTokenCrypte - Début génération token', [
                'abonnement_id' => $model->id,
                'statut' => $model->statut->value ?? $model->statut,
            ]);

            // $model est l'abonnement
            // Vérifier que l'abonnement a été payé (statut paiement = valide)
            $paiementValide = $model->paiements()
                ->where('statut', 'valide')
                ->exists();

            if (!$paiementValide) {
                Log::warning('HasTokenCrypte::genererTokenCrypte - Pas de paiement validé', [
                    'abonnement_id' => $model->id,
                ]);
                return; // Pas de paiement validé, pas de token
            }

            // Vérifier qu'il n'existe pas déjà un token actif pour cet abonnement
            $tokenExistant = TokenSirene::where('abonnement_id', $model->id)
                ->where('actif', true)
                ->exists();

            if ($tokenExistant) {
                Log::info('HasTokenCrypte::genererTokenCrypte - Token actif existe déjà', [
                    'abonnement_id' => $model->id,
                ]);
                return; // Un token existe déjà pour cet abonnement
            }

            // Charger les relations nécessaires
            $model->load(['sirene', 'ecole', 'site']);

            Log::info('HasTokenCrypte::genererTokenCrypte - Relations chargées', [
                'abonnement_id' => $model->id,
                'sirene_id' => $model->sirene_id,
                'ecole_id' => $model->ecole_id,
                'site_id' => $model->site_id,
                'numero_serie' => $model->sirene->numero_serie ?? 'NULL',
            ]);

            // Générer le token au format Python: VERSION|ECOLE|SERIAL|TIMESTAMP_DEBUT|TIMESTAMP_FIN|CHECKSUM
            $parts = [
                1, // VERSION
                $model->ecole_id, // ECOLE (ULID)
                $model->sirene->numero_serie ?? '', // SERIAL
                Carbon::parse($model->date_debut)->timestamp, // TIMESTAMP_DEBUT
                Carbon::parse($model->date_fin)->timestamp, // TIMESTAMP_FIN
            ];

            $data_str = implode('|', $parts);

            Log::info('HasTokenCrypte::genererTokenCrypte - Données token préparées', [
                'abonnement_id' => $model->id,
                'data_str' => $data_str,
            ]);

            // Crypter avec checksum de 16 caractères
            // On utilise directement $model car il utilise HasTokenCrypte qui utilise HasCryptageESP8266
            $tokenCrypte = $model->crypterDonneesESP8266($data_str, true, 16);

            Log::info('HasTokenCrypte::genererTokenCrypte - Token crypté', [
                'abonnement_id' => $model->id,
                'token_length' => strlen($tokenCrypte),
            ]);

            // Hash du token pour vérification rapide
            $tokenHash = hash('sha256', $tokenCrypte);

            // Créer l'enregistrement dans tokens_sirene
            $tokenSirene = TokenSirene::create([
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

            Log::info('HasTokenCrypte::genererTokenCrypte - Token créé avec succès', [
                'abonnement_id' => $model->id,
                'token_id' => $tokenSirene->id,
            ]);

        } catch (\Exception $e) {
            Log::error('HasTokenCrypte::genererTokenCrypte - Erreur lors de la génération du token', [
                'abonnement_id' => $model->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // NE PAS relancer l'exception pour ne pas bloquer la mise à jour de l'abonnement
            // On préfère avoir un abonnement actif sans token plutôt qu'un abonnement bloqué
        }
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
