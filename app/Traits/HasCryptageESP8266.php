<?php

namespace App\Traits;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Trait HasCryptageESP8266
 *
 * Fournit les méthodes de cryptage/décryptage compatibles avec le script Python
 * sirene_crypto.py utilisé par les modules ESP8266.
 *
 * Utilise AES-256-CBC avec:
 * - Clé de 32 bytes (256 bits) fixe
 * - IV aléatoire de 16 bytes pour chaque cryptage
 * - Padding PKCS7
 * - Checksum SHA-256 (8 ou 16 caractères)
 * - Format de sortie: base64(IV + données cryptées)
 */
trait HasCryptageESP8266
{
    /**
     * Clé de cryptage AES-256 (32 bytes en hexadécimal)
     * Doit correspondre exactement à la clé dans sirene_crypto.py
     */
    protected static string $ESP8266_ENCRYPTION_KEY = '2b7e151628aed2a6abf7158809cf4f3c2b7e151628aed2a6abf7158809cf4f3c';

    /**
     * Crypte des données au format compatible ESP8266/Python
     *
     * @param string $data_str Données à crypter (format: "FIELD1|FIELD2|...")
     * @param bool $use_checksum Ajouter un checksum SHA-256 (par défaut: true)
     * @param int $checksum_length Longueur du checksum (8 ou 16 caractères)
     * @return string Données cryptées en base64
     * @throws Exception
     */
    protected function crypterDonneesESP8266(string $data_str, bool $use_checksum = true, int $checksum_length = 8): string
    {
        try {
            // 1. Ajouter le checksum si nécessaire
            if ($use_checksum) {
                $checksum = substr(hash('sha256', $data_str), 0, $checksum_length);
                $data_str = "{$data_str}|{$checksum}";
            }

            // 2. Padding PKCS7
            $block_size = 16;
            $padding_length = $block_size - (strlen($data_str) % $block_size);
            $padded_data = $data_str . str_repeat(chr($padding_length), $padding_length);

            // 3. Générer IV aléatoire de 16 bytes
            $iv = openssl_random_pseudo_bytes(16);

            // 4. Convertir la clé hexadécimale en bytes
            $key_bytes = hex2bin(self::$ESP8266_ENCRYPTION_KEY);

            // 5. Chiffrement AES-256-CBC
            $encrypted = openssl_encrypt(
                $padded_data,
                'AES-256-CBC',
                $key_bytes,
                OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, // Pas de padding auto car on gère PKCS7 manuellement
                $iv
            );

            if ($encrypted === false) {
                throw new Exception('Échec du cryptage AES-256-CBC');
            }

            // 6. Retourner IV + données chiffrées en base64
            $result = base64_encode($iv . $encrypted);

            Log::debug('Cryptage ESP8266 réussi', [
                'data_length' => strlen($data_str),
                'encrypted_length' => strlen($result),
                'checksum_used' => $use_checksum,
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Erreur lors du cryptage ESP8266', [
                'error' => $e->getMessage(),
                'data_length' => strlen($data_str),
            ]);
            throw $e;
        }
    }

    /**
     * Décrypte des données au format compatible ESP8266/Python
     *
     * @param string $encrypted_b64 Données cryptées en base64
     * @param bool $use_checksum Vérifier le checksum SHA-256 (par défaut: true)
     * @param int $checksum_length Longueur du checksum (8 ou 16 caractères)
     * @return string|null Données décryptées ou null en cas d'erreur
     */
    protected function decrypterDonneesESP8266(string $encrypted_b64, bool $use_checksum = true, int $checksum_length = 8): ?string
    {
        try {
            // 1. Décoder le base64
            $encrypted_data = base64_decode($encrypted_b64, true);
            if ($encrypted_data === false) {
                Log::warning('Échec du décodage base64');
                return null;
            }

            // 2. Extraire IV (16 premiers bytes) et données cryptées
            if (strlen($encrypted_data) < 16) {
                Log::warning('Données cryptées trop courtes');
                return null;
            }

            $iv = substr($encrypted_data, 0, 16);
            $encrypted = substr($encrypted_data, 16);

            // 3. Convertir la clé hexadécimale en bytes
            $key_bytes = hex2bin(self::$ESP8266_ENCRYPTION_KEY);

            // 4. Déchiffrement AES-256-CBC
            $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                $key_bytes,
                OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, // Pas de padding auto car on gère PKCS7 manuellement
                $iv
            );

            if ($decrypted === false) {
                Log::warning('Échec du décryptage AES-256-CBC');
                return null;
            }

            // 5. Retirer le padding PKCS7
            $padding_length = ord($decrypted[strlen($decrypted) - 1]);
            if ($padding_length < 1 || $padding_length > 16) {
                Log::warning('Padding PKCS7 invalide', ['padding_length' => $padding_length]);
                return null;
            }

            $data_str = substr($decrypted, 0, -$padding_length);

            // 6. Vérifier le checksum si nécessaire
            if ($use_checksum) {
                $parts = explode('|', $data_str);
                if (count($parts) < 2) {
                    Log::warning('Format de données invalide (checksum manquant)');
                    return null;
                }

                // Le checksum est le dernier champ
                $checksum = array_pop($parts);
                $data_str = implode('|', $parts);

                $expected_checksum = substr(hash('sha256', $data_str), 0, $checksum_length);
                if ($checksum !== $expected_checksum) {
                    Log::warning('Checksum invalide', [
                        'expected' => $expected_checksum,
                        'received' => $checksum,
                    ]);
                    return null;
                }
            }

            Log::debug('Décryptage ESP8266 réussi', [
                'data_length' => strlen($data_str),
                'checksum_used' => $use_checksum,
            ]);

            return $data_str;

        } catch (Exception $e) {
            Log::error('Erreur lors du décryptage ESP8266', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Génère un checksum SHA-256
     *
     * @param string $data Données à hasher
     * @param int $length Longueur du checksum (8 ou 16 caractères)
     * @return string Checksum tronqué
     */
    protected function genererChecksum(string $data, int $length = 8): string
    {
        return substr(hash('sha256', $data), 0, $length);
    }

    /**
     * Convertit un jour français en masque binaire Python
     * Python: 0=Dimanche, 1=Lundi, 2=Mardi, 3=Mercredi, 4=Jeudi, 5=Vendredi, 6=Samedi
     *
     * @param string $jour Nom du jour en français (Lundi, Mardi, etc.)
     * @return int Masque binaire (0-6)
     */
    protected function jourVersMasque(string $jour): int
    {
        $mapping = [
            'Dimanche' => 0,
            'Lundi' => 1,
            'Mardi' => 2,
            'Mercredi' => 3,
            'Jeudi' => 4,
            'Vendredi' => 5,
            'Samedi' => 6,
        ];

        return $mapping[$jour] ?? -1;
    }

    /**
     * Convertit un tableau de jours en masque binaire
     * Exemple: [1, 2, 3, 4, 5] (Lun-Ven) -> 0b0111110 = 62
     *
     * @param array $jours Tableau de masques binaires (0-6)
     * @return int Masque binaire combiné
     */
    protected function joursVersIntMasque(array $jours): int
    {
        $mask = 0;
        foreach ($jours as $jour) {
            if ($jour >= 0 && $jour <= 6) {
                $mask |= (1 << $jour);
            }
        }
        return $mask;
    }

    /**
     * Convertit un masque binaire en tableau de jours
     * Exemple: 62 (0b0111110) -> [1, 2, 3, 4, 5]
     *
     * @param int $mask Masque binaire
     * @return array Tableau de masques binaires (0-6)
     */
    protected function intMasqueVersJours(int $mask): array
    {
        $jours = [];
        for ($i = 0; $i <= 6; $i++) {
            if ($mask & (1 << $i)) {
                $jours[] = $i;
            }
        }
        return $jours;
    }
}
