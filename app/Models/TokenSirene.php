<?php

namespace App\Models;

use App\Traits\HasUlid;
use App\Traits\HasCryptageESP8266;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TokenSirene extends Model
{
    use HasUlid, SoftDeletes, HasCryptageESP8266;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'tokens_sirene';

    protected $fillable = [
        'abonnement_id',
        'sirene_id',
        'site_id',
        'token_crypte',
        'token_hash',
        'date_debut',
        'date_fin',
        'actif',
        'date_generation',
        'date_expiration',
        'date_activation',
        'genere_par',
    ];

    protected $casts = [
        'date_debut' => 'date:Y-m-d',
        'date_fin' => 'date:Y-m-d',
        'actif' => 'boolean',
        'date_generation' => 'datetime:Y-m-d H:i:s',
        'date_expiration' => 'datetime:Y-m-d H:i:s',
        'date_activation' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
    ];

    // Relations
    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class, 'abonnement_id');
    }

    public function sirene(): BelongsTo
    {
        return $this->belongsTo(Sirene::class, 'sirene_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    // Helpers
    public function isValid(): bool
    {
        return $this->actif
            && $this->date_debut <= now()
            && $this->date_fin >= now();
    }

    /**
     * Décrypte le token et retourne les données au format Python
     * Format: VERSION|ECOLE|SERIAL|TIMESTAMP_DEBUT|TIMESTAMP_FIN|CHECKSUM
     *
     * @return array|null Données décryptées ['version', 'ecole_id', 'numero_serie', 'timestamp_debut', 'timestamp_fin']
     */
    public function decrypterToken(): ?array
    {
        if (!$this->token_crypte) {
            return null;
        }

        try {
            // Décrypter avec checksum de 16 caractères
            $data_str = $this->decrypterDonneesESP8266($this->token_crypte, true, 16);

            if (!$data_str) {
                return null;
            }

            // Parser le format pipe-separated
            return $this->parserTokenCrypte($data_str);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erreur décryptage token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parser une chaîne décryptée au format Python
     * Format: VERSION|ECOLE|SERIAL|TIMESTAMP_DEBUT|TIMESTAMP_FIN
     *
     * @param string $data_str Chaîne décryptée
     * @return array|null Données parsées
     */
    protected function parserTokenCrypte(string $data_str): ?array
    {
        try {
            $parts = explode('|', $data_str);

            if (count($parts) < 5) {
                \Illuminate\Support\Facades\Log::warning('Token format invalide: nombre de champs insuffisant', [
                    'parts_count' => count($parts),
                    'expected' => 5,
                ]);
                return null;
            }

            return [
                'version' => (int) $parts[0],
                'ecole_id' => $parts[1],
                'numero_serie' => $parts[2],
                'timestamp_debut' => (int) $parts[3],
                'timestamp_fin' => (int) $parts[4],
            ];

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erreur parsing token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Vérifie l'intégrité du token au format Python
     */
    public function verifierToken(): bool
    {
        $tokenData = $this->decrypterToken();

        if (!$tokenData) {
            return false;
        }

        // Vérifier l'expiration via timestamp
        if (isset($tokenData['timestamp_fin'])) {
            $timestampFin = $tokenData['timestamp_fin'];
            if ($timestampFin < now()->timestamp) {
                return false;
            }
        }

        // Vérifier la correspondance des données
        // Charger la sirène pour vérifier le numéro de série
        $sirene = $this->sirene;
        if (!$sirene) {
            return false;
        }

        return $tokenData['numero_serie'] === $sirene->numero_serie
            && $tokenData['ecole_id'] === $this->abonnement->ecole_id;
    }

    /**
     * Formatte le token pour affichage (segments de 4 caractères)
     */
    public function getTokenFormatted(): ?string
    {
        if (!$this->token_crypte) {
            return null;
        }

        $token = base64_encode($this->token_crypte);
        return rtrim(chunk_split($token, 4, '-'), '-');
    }

    /**
     * Vérifie le hash du token
     */
    public function verifierHash(string $tokenCrypte): bool
    {
        return hash('sha256', $tokenCrypte) === $this->token_hash;
    }
}
