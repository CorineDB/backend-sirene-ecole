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

            // Si l'abonnement est actif, pointer vers la page de détails
            if ($model->statut->value === 'actif') {
                $qrContent = $frontendUrl . '/abonnements/' . $model->id;
            } else {
                //

                //$frontendUrl . '/paiement/' . $model->id;

                try {
                    $cinetpayService = app(CinetPayService::class);
                    $paiementData = $cinetpayService->initierPaiement($model);

                    if (isset($paiementData['payment_url']) && isset($paiementData['success']) && $paiementData['success']) {
                        // Encoder les métadonnées CinetPay en JSON dans le QR code
                        $qrData = [
                            'type' => 'paiement',
                            'abonnement_id' => $model->id,
                            'url' => $frontendUrl . '/paiement/' . $model->id,
                            'metadata' => array_merge([
                                'cinetpay_payment_url' => $paiementData['payment_url'],
                                'cinetpay_payment_token' => $paiementData['payment_token'],
                                'cinetpay_api_response_id' => $paiementData['api_response_id'] ?? null,
                                'transaction_id' => $paiementData['transaction_id'],
                            ], $paiementData["metadata"]),
                        ];

                        $checkoutUrl = rtrim($frontendUrl, '/') . '/checkout-page.html';
                        $encodedData = base64_encode(json_encode($qrData));
                        $qrContent = $checkoutUrl . '#' . $encodedData;

                        // Sauvegarder les infos dans les notes de l'abonnement
                        $noteDetails = [
                            "Transaction ID: {$paiementData['transaction_id']}",
                            "Payment Token: {$paiementData['payment_token']}",
                            "Payment URL: {$paiementData['payment_url']}",
                        ];

                        if (isset($paiementData['api_response_id'])) {
                            $noteDetails[] = "API Response ID: {$paiementData['api_response_id']}";
                        }

                        $model->updateQuietly([
                            'notes' => ($model->notes ?? '') . "\n" .
                                "[" . now()->format('Y-m-d H:i:s') . "] Lien CinetPay généré:\n  - " .
                                implode("\n  - ", $noteDetails)
                        ]);

                        Log::info('QR code généré avec métadonnées CinetPay', [
                            'cinetpay_payment_url' => $paiementData['payment_url'],
                            'cinetpay_payment_token' => $paiementData['payment_token'],
                            'abonnement_id' => $model->id,
                            'transaction_id' => $paiementData['transaction_id'],
                        ]);
                    } else {
                        throw new \Exception('Données CinetPay invalides');
                    }
                } catch (\Exception $e) {
                    Log::error('Erreur génération lien CinetPay: ' . $e->getMessage(), [
                        'abonnement_id' => $model->id,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw new \Exception($e->getMessage());

                    // Fallback: pointer vers la page frontend
                    $qrData = [
                        'type' => 'paiement_fallback',
                        'abonnement_id' => $model->id,
                        'url' => $frontendUrl . '/paiement/' . $model->id,
                    ];
                    $checkoutUrl = rtrim($frontendUrl, '/') . '/checkout-page.html';
                    $encodedData = base64_encode(json_encode($qrData));
                    $qrContent = $checkoutUrl . '#' . $encodedData;
                }
            }

            // Générer le QR code en PNG
            $qrCode = QrCode::format('png')
                ->size(300)
                ->errorCorrection('H')
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
