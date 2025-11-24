<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\CinetPayService;

trait HasQrCodeAbonnement
{
    protected static function bootHasQrCodeAbonnement(): void
    {
        static::created(function (Model $model) {
            // Génère le QR code après la création de l'abonnement
            self::genererQrCode($model);
        });

        static::updated(function (Model $model) {
            // Régénère le QR code si le statut change
            if ($model->isDirty('statut')) {
                self::genererQrCode($model);
            }
        });
    }

    protected static function genererQrCode(Model $model): void
    {
        try {
            // Charger les relations nécessaires
            $model->load(['sirene.ecole', 'ecole', 'site.ville.pays']);

            $ecole = $model->ecole ?? $model->sirene?->ecole;

            if (!$ecole) {
                return; // Pas d'école, pas de QR code
            }

            $frontendUrl = config('app.frontend_url', config('app.url'));

            // Préparer les données essentielles de l'abonnement pour le QR code
            // Utilisation de clés courtes pour réduire la taille
            $qrData = [
                'a' => $model->id, // abonnement_id
                'e' => $ecole->id, // ecole_id
                'n' => $model->numero_abonnement, // numero_abonnement
                'm' => (int) $model->montant, // montant
                's' => $model->statut->value, // statut
                'ec' => $ecole->nom, // nom école (version courte)
                'si' => [
                    'n' => $model->site->nom ?? 'N/A', // nom site
                    'v' => $model->site->ville->nom ?? 'N/A', // ville
                ],
                'sr' => $model->sirene->numero_serie ?? 'N/A', // numero_serie sirène
            ];

            // Construire l'URL de checkout avec les données encodées
            $checkoutUrl = rtrim($frontendUrl, '/') . '/checkout/' . $ecole->id . '/' . $model->id;
            $encodedData = base64_encode(json_encode($qrData));
            $qrContent = $checkoutUrl . '?d=' . $encodedData;

            Log::info('QR code généré pour abonnement', [
                'abonnement_id' => $model->id,
                'ecole_id' => $ecole->id,
                'frontend_url' => $frontendUrl,
                'checkout_url' => $checkoutUrl,
                'statut' => $model->statut->value,
                'qr_data_size' => strlen($qrContent),
            ]);

            // Générer le QR code en PNG avec correction d'erreur moyenne
            $qrCode = QrCode::format('png')
                ->size(400)
                ->errorCorrection('M')
                ->generate($qrContent);

            // Sauvegarder le QR code
            $filename = "ecoles/{$ecole->id}/qrcodes/{$model->sirene->id}/abonnement_" . $model->id . '.png';
            Storage::disk('public')->put($filename, $qrCode);

            // Mettre à jour le modèle avec le chemin du QR code
            $model->qr_code_path = $filename;
            $model->saveQuietly(); // Évite de déclencher les events à nouveau

        } catch (\Exception $e) {
            Log::error('Erreur génération QR code abonnement: ' . $e->getMessage(), [
                'abonnement_id' => $model->id ?? 'unknown',
            ]);
        }
    }

    public function getQrCodeUrl(): ?string
    {
        if (!$this->qr_code_path) {
            return null;
        }

        return Storage::disk('public')->url($this->qr_code_path);
    }

    public function regenererQrCode(): void
    {
        self::genererQrCode($this);
    }
}
