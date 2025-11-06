<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Paiement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class CinetPayService
{
    protected string $apiKey;
    protected string $siteId;
    protected string $apiUrl;
    protected string $notifyUrl;
    protected string $returnUrl;

    public function __construct()
    {
        $this->apiKey = config('services.cinetpay.api_key');
        $this->siteId = config('services.cinetpay.site_id');
        $this->apiUrl = config('services.cinetpay.api_url', 'https://api-checkout.cinetpay.com/v2/payment');
        $this->notifyUrl = config('app.url') . '/api/cinetpay/notify';
        $this->returnUrl = config('app.url') . '/api/cinetpay/return';
    }

    /**
     * Initialiser un paiement pour un abonnement
     *
     * @param Abonnement $abonnement
     * @return array
     * @throws Exception
     */
    public function initierPaiement(Abonnement $abonnement): array
    {
        try {
            // Charger les relations nécessaires
            $abonnement->load(['ecole', 'site.ville.pays', 'sirene']);

            // Générer un identifiant de transaction unique
            $transactionId = $this->generateTransactionId($abonnement->id);

            // Préparer les données du client
            $customerData = $this->prepareCustomerData($abonnement);

            // Préparer les données de la facture
            $invoiceData = $this->prepareInvoiceData($abonnement);

            // Préparer les métadonnées
            $metadata = $this->prepareMetadata($abonnement);

            // Construire la requête
            $payload = [
                'apikey' => $this->apiKey,
                'site_id' => $this->siteId,
                'transaction_id' => $transactionId,
                'amount' => (int) $abonnement->montant,
                'currency' => "GNF",//$abonnement->site->ville->pays->devise ?? 'XOF',
                'alternative_currency' => 'XOF',
                'description' => "Paiement abonnement sirène - {$abonnement->numero_abonnement}",
                'customer_id' => $abonnement->ecole->id,
                'customer_name' => $abonnement->ecole->nom_complet ?? $abonnement->ecole->nom,
                'customer_surname' => $abonnement->site->nom ?? 'Site Principal',
                'customer_email' => $abonnement->ecole->email_contact ?? 'noreply@sirene-ecole.com',
                'customer_phone_number' => $this->formatPhoneNumber($abonnement->ecole->telephone_contact),
                'customer_address' => $abonnement->site->adresse ?? 'N/A',
                'customer_city' => $abonnement->site->ville->nom ?? 'N/A',
                'customer_country' => $abonnement->site->ville->pays->code_iso ?? 'BJ',
                'customer_state' => $abonnement->site->ville->nom ?? 'N/A',
                'customer_zip_code' => $abonnement->site->ville->pays->indicatif_tel ?? '000',
                'notify_url' => $this->notifyUrl,
                'return_url' => $this->returnUrl,
                'channels' => 'ALL',
                'metadata' => json_encode($metadata),
                'lang' => 'FR',
                'invoice_data' => $invoiceData,
                'lock_phone_number' => false, // Laisser l'utilisateur choisir le numéro lors du paiement
            ];

            // Enregistrer le paiement en attente dans la base de données
            //$this->createPendingPaiement($abonnement, $transactionId, $metadata);

            // Envoyer la requête à CinetPay
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, $payload);

            if ($response->failed()) {
                throw new Exception('Erreur lors de la communication avec CinetPay: ' . $response->body());
            }

            $result = $response->json();

            // Vérifier la réponse
            if (!isset($result['code']) || $result['code'] !== '201') {
                throw new Exception(
                    $result['message'] ?? 'Erreur inconnue lors de l\'initialisation du paiement'
                );
            }

            return [
                'success' => true,
                'payment_url' => $result['data']['payment_url'],
                'payment_token' => $result['data']['payment_token'],
                'transaction_id' => $transactionId,
                'metadata' => $metadata,
            ];

        } catch (Exception $e) {
            Log::error('CinetPayService::initierPaiement - ' . $e->getMessage(), [
                'abonnement_id' => $abonnement->id,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Générer un identifiant de transaction unique
     */
    protected function generateTransactionId(string $abonnementId): string
    {
        return 'ABN-' . strtoupper(substr($abonnementId, 0, 8)) . '-' . time();
    }

    /**
     * Formater le numéro de téléphone au format international
     */
    protected function formatPhoneNumber(?string $phone): string
    {
        if (!$phone) {
            return '+2250000000000';
        }

        // Supprimer les espaces et caractères spéciaux
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Ajouter le + si absent
        if (!str_starts_with($phone, '+')) {
            // Supposer qu'il s'agit d'un numéro ivoirien si pas de préfixe
            if (strlen($phone) === 10) {
                $phone = '+225' . $phone;
            } else {
                $phone = '+' . $phone;
            }
        }

        return $phone;
    }

    /**
     * Préparer les données du client
     */
    protected function prepareCustomerData(Abonnement $abonnement): array
    {
        return [
            'id' => $abonnement->ecole->id,
            'name' => $abonnement->ecole->nom_complet ?? $abonnement->ecole->nom,
            'surname' => $abonnement->site->nom ?? 'Site Principal',
            'email' => $abonnement->ecole->email_contact ?? 'noreply@sirene-ecole.com',
            'phone' => $this->formatPhoneNumber($abonnement->ecole->telephone_contact),
            'address' => $abonnement->site->adresse ?? 'N/A',
            'city' => $abonnement->site->ville->nom ?? 'N/A',
            'country' => $abonnement->site->ville->pays->code_iso ?? 'CI',
            'state' => $abonnement->site->ville->nom ?? 'N/A',
            'zip_code' => $abonnement->site->ville->pays->indicatif_tel ?? '000',
        ];
    }

    /**
     * Préparer les données de la facture
     */
    protected function prepareInvoiceData(Abonnement $abonnement): array
    {
        $dateDebut = $abonnement->date_debut ? $abonnement->date_debut->format('d/m/Y') : 'N/A';
        $dateFin = $abonnement->date_fin ? $abonnement->date_fin->format('d/m/Y') : 'N/A';
        $duree = $abonnement->date_debut && $abonnement->date_fin
            ? $abonnement->date_debut->diffInMonths($abonnement->date_fin)
            : 12;

        return [
            //'Numéro Abonnement' => $abonnement->numero_abonnement ?? 'En attente',
            //'École' => $abonnement->ecole->nom ?? 'N/A',
            'Site' => $abonnement->site->nom ?? 'N/A',
            //'Sirène N°' => $abonnement->sirene->numero_serie ?? 'N/A',
            'Période' => "{$dateDebut} - {$dateFin}",
            /*'Durée' => "{$duree} mois",
            'Montant HT' => number_format($abonnement->montant * 0.82, 0, ',', ' ') . ' FCFA',
            'TVA (18%)' => number_format($abonnement->montant * 0.18, 0, ',', ' ') . ' FCFA',
            'Montant TTC' => number_format($abonnement->montant, 0, ',', ' ') . ' FCFA',*/

            "Reste à payer" => number_format($abonnement->montant * 0.82, 0, ',', ' ') . ' FCFA',
            //"Matricule" => "24OPO25",
            //"Annee-scolaire" => "2020-2021"
        ];
    }

    /**
     * Préparer les métadonnées
     */
    protected function prepareMetadata(Abonnement $abonnement): array
    {
        $metadata = [
            'abonnement_id' => $abonnement->id,
            'numero_abonnement' => $abonnement->numero_abonnement,
            'ecole_id' => $abonnement->ecole_id,
            'ecole_nom' => $abonnement->ecole->nom,
            'site_id' => $abonnement->site_id,
            'site_nom' => $abonnement->site->nom,
            'sirene_id' => $abonnement->sirene_id,
            'sirene_numero' => $abonnement->sirene->numero_serie,
            'date_debut' => $abonnement->date_debut?->toDateString(),
            'date_fin' => $abonnement->date_fin?->toDateString(),
            'montant' => (float) $abonnement->montant,
            'devise' => $abonnement->site->ville->pays->devise ?? 'XOF',
            'type_paiement' => 'ABONNEMENT_INITIAL',
            'created_at' => now()->toIso8601String(),
        ];

        // Récupérer le moyen de paiement par défaut du site ou de l'école
        $moyenPaiement = \App\Models\MoyenPaiement::where(function ($query) use ($abonnement) {
            $query->where('paiementable_type', \App\Models\Site::class)
                  ->where('paiementable_id', $abonnement->site_id);
        })->orWhere(function ($query) use ($abonnement) {
            $query->where('paiementable_type', \App\Models\Ecole::class)
                  ->where('paiementable_id', $abonnement->ecole_id);
        })
        ->where('par_defaut', true)
        ->where('actif', true)
        ->first();

        // Ajouter les informations du moyen de paiement dans metadata
        if ($moyenPaiement) {
            $metadata['moyen_paiement'] = [
                'id' => $moyenPaiement->id,
                'type' => $moyenPaiement->type->value ?? null,
                'operateur' => $moyenPaiement->operateur,
                'numero_telephone' => $moyenPaiement->numero_telephone,
                'nom_titulaire' => $moyenPaiement->nom_titulaire,
                'email_wallet' => $moyenPaiement->email_wallet,
            ];
        }

        return $metadata;
    }

    /**
     * Créer un enregistrement de paiement en attente
     */
    protected function createPendingPaiement(Abonnement $abonnement, string $transactionId, array $metadata): Paiement
    {
        return Paiement::create([
            'abonnement_id' => $abonnement->id,
            'ecole_id' => $abonnement->ecole_id,
            'numero_transaction' => $transactionId,
            'montant' => $abonnement->montant,
            'moyen' => \App\Enums\MoyenPaiement::MOBILE_MONEY,
            'statut' => 'en_attente',
            'reference_externe' => null, // Sera rempli par le callback
            'metadata' => $metadata,
            'date_paiement' => null,
            'date_validation' => null,
        ]);
    }

    /**
     * Vérifier le statut d'une transaction
     */
    public function verifierTransaction(string $transactionId): array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://api-checkout.cinetpay.com/v2/payment/check', [
                'apikey' => $this->apiKey,
                'site_id' => $this->siteId,
                'transaction_id' => $transactionId,
            ]);

            if ($response->failed()) {
                throw new Exception('Erreur lors de la vérification: ' . $response->body());
            }

            return $response->json();

        } catch (Exception $e) {
            Log::error('CinetPayService::verifierTransaction - ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Traiter le callback de notification
     */
    public function traiterNotification(array $data): bool
    {
        try {

            Log::error('CinetPayService::verifierTransaction - ' . $data);
            // Vérifier que les données essentielles sont présentes
            if (!isset($data['cpm_trans_id']) || !isset($data['cpm_trans_status'])) {
                throw new Exception('Données de notification incomplètes');
            }

            $transactionId = $data['cpm_trans_id'];
            $statut = $data['cpm_trans_status'];

            // Récupérer le paiement
            $paiement = Paiement::where('numero_transaction', $transactionId)->first();

            if (!$paiement) {
                Log::warning("Paiement non trouvé pour la transaction: {$transactionId}");
                return false;
            }

            // Mettre à jour le paiement selon le statut
            if ($statut === 'ACCEPTED' || $statut === '00') {
                $paiement->update([
                    'statut' => 'valide',
                    'reference_externe' => $data['cpm_payment_token'] ?? null,
                    'date_paiement' => now(),
                    'date_validation' => now(),
                    'metadata' => array_merge($paiement->metadata ?? [], [
                        'cinetpay_response' => $data,
                        'validated_at' => now()->toIso8601String(),
                    ]),
                ]);

                // Activer l'abonnement
                $abonnement = $paiement->abonnement;
                if ($abonnement) {
                    $abonnement->update([
                        'statut' => \App\Enums\StatutAbonnement::ACTIF,
                    ]);

                    Log::info("Abonnement activé: {$abonnement->id}");
                }

                return true;
            } else {
                // Paiement échoué
                $paiement->update([
                    'statut' => 'echoue',
                    'reference_externe' => $data['cpm_payment_token'] ?? null,
                    'metadata' => array_merge($paiement->metadata ?? [], [
                        'cinetpay_response' => $data,
                        'failed_at' => now()->toIso8601String(),
                        'failure_reason' => $data['cpm_error_message'] ?? 'Erreur inconnue',
                    ]),
                ]);

                return false;
            }

        } catch (Exception $e) {
            Log::error('CinetPayService::traiterNotification - ' . $e->getMessage(), [
                'data' => $data,
            ]);
            return false;
        }
    }
}
