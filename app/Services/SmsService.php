<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SmsService
{
    private string $provider;
    private string $apiKey;
    private string $apiSecret;
    private string $fromNumber;
    private string $apiUrl;
    private string $account_id;
    private string $account_password;
    private string $username;
    private int $alertThreshold;
    private string $alertEmails;
    private string $alertPhones;

    public function __construct()
    {
        $this->provider = (string) config('services.sms.provider', 'custom_api');
        $this->apiKey = (string) config('services.sms.api_key', '');
        $this->apiSecret = (string) config('services.sms.api_secret', '');
        $this->fromNumber = (string) config('services.sms.from_number', 'SIRENE');
        $this->apiUrl = (string) config('services.sms.url', '');
        $this->account_id = (string) config('services.sms.account_id', '');
        $this->account_password = (string) config('services.sms.account_password', '');
        $this->username = (string) config('services.sms.username', '');
    }

    /**
     * Envoie un SMS
     */
    public function sendSms(string $to, string $message): bool
    {
        try {
            switch ($this->provider) {
                case 'twilio':
                    return $this->sendViaTwilio($to, $message);

                case 'africas_talking':
                    return $this->sendViaAfricasTalking($to, $message);

                case 'custom_api':
                    return $this->sendViaCustomApi($to, $message);

                default:
                    Log::warning("SMS not sent (no provider configured): {$to}");
                    return true; // En mode dev, on simule l'envoi
            }
        } catch (Exception $e) {
            Log::error('SMS sending failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Envoie un SMS OTP (alias de sendSms pour compatibilité)
     */
    public function sendOtpSms(string $to, string $code): bool
    {
        $message = "Votre code de vérification Sirène d'École est: {$code}. Valide pendant " . config('services.otp.expiration_minutes', 5) . " minutes.";
        return $this->sendSms($to, $message);
    }

    /**
     * Envoie via Twilio
     */
    private function sendViaTwilio(string $to, string $message): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::warning("Twilio credentials not configured. SMS not sent to: {$to}");
            Log::info("SMS Content: {$message}");
            return true;
        }

        $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->apiKey}/Messages.json", [
                'To' => $to,
                'From' => $this->fromNumber,
                'Body' => $message,
            ]);

        if ($response->successful()) {
            Log::info("SMS sent successfully to {$to}");
            return true;
        }

        throw new Exception('Twilio SMS failed: ' . $response->body());
    }

    /**
     * Envoie via Africa's Talking
     */
    private function sendViaAfricasTalking(string $to, string $message): bool
    {
        if (empty($this->apiKey)) {
            Log::warning("Africa's Talking API key not configured. SMS not sent to: {$to}");
            Log::info("SMS Content: {$message}");
            return true;
        }

        $response = Http::withHeaders([
            'apiKey' => $this->apiKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post('https://api.africastalking.com/version1/messaging', [
            'username' => config('services.sms.username', 'sandbox'),
            'to' => $to,
            'message' => $message,
            'from' => $this->fromNumber,
        ]);

        if ($response->successful()) {
            Log::info("SMS sent successfully to {$to}");
            return true;
        }

        throw new Exception('Africa\'s Talking SMS failed: ' . $response->body());
    }

    /**
     * Envoie via l'API personnalisée
     */
    private function sendViaCustomApi(string $to, string $message): bool
    {
        if (empty($this->apiKey) || empty($this->apiUrl)) {
            Log::warning("Custom API credentials not configured. SMS not sent to: {$to}");
            Log::info("SMS Content: {$message}");
            return true;
        }

        $endpoint = $this->apiUrl . '/sendbatch';

        $headers = [
            'Authorization' => "Basic {$this->apiKey}",
            'Content-Type' => 'application/json',
        ];

        $phoneNumbers = is_array($to) ? $to : [$to];
        Log::info("SMS sending to: " . json_encode($phoneNumbers));
        Log::info("SMS sending to: " . $message);

        $requestBody = [
            "globals" => [
                "from" => $this->fromNumber
            ],
            "messages" => [
                [
                    "to" => $phoneNumbers,
                    "content" => $message
                ]
            ]
        ];

        try {
            $response = Http::withHeaders($headers)->post($endpoint, $requestBody);
            $responseBody = $response->json();

            if ($response->successful()) {
                Log::info("SMS sent successfully via Custom API: " . json_encode($responseBody));
                return true;
            }

            throw new Exception("Custom API SMS failed: " . json_encode($responseBody));
        } catch (Exception $e) {
            Log::error('Error sending SMS via Custom API: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtenir le solde SMS de l'API personnalisée
     */
    public function getSmsBalance(): ?float
    {
        if ($this->provider !== 'custom_api' || empty($this->apiKey) || empty($this->apiUrl)) {
            Log::warning("SMS balance check not available for provider: {$this->provider}");
            return null;
        }

        $endpoint = $this->apiUrl . '/account/balance';

        try {
            $response = Http::withHeaders([
                'Authorization' => "Basic {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->get($endpoint);

            if ($response->successful()) {
                $data = $response->json()['data'] ?? [];
                $balance = $data['balance'] ?? 0;

                Log::info("Current SMS balance: {$balance}");
                return (float) $balance;
            }

            Log::error('Failed to fetch SMS balance: ' . $response->body());
            return null;
        } catch (Exception $e) {
            Log::error('Error checking SMS balance: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Vérifier le solde et envoyer des alertes si le seuil est atteint
     */
    public function checkBalanceAndAlert(): void
    {
        $balance = $this->getSmsBalance();

        if ($balance === null) {
            return;
        }

        // Si le solde est en dessous du seuil
        if ($balance < $this->alertThreshold) {
            $this->sendLowBalanceAlert($balance);
        }
    }

    /**
     * Envoyer une alerte de solde faible
     */
    private function sendLowBalanceAlert(float $balance): void
    {
        $message = "ALERTE: Le solde SMS est faible ({$balance} crédits restants). Seuil d'alerte: {$this->alertThreshold}.";

        // Envoyer par email
        if (!empty($this->alertEmails)) {
            $emails = explode(',', $this->alertEmails);
            foreach ($emails as $email) {
                $email = trim($email);
                if (!empty($email)) {
                    try {
                        Mail::raw($message, function ($mail) use ($email) {
                            $mail->to($email)
                                ->subject("Alerte Solde SMS - Sirène d'École");
                        });
                        Log::info("Low balance email alert sent to: {$email}");
                    } catch (Exception $e) {
                        Log::error("Failed to send email alert to {$email}: " . $e->getMessage());
                    }
                }
            }
        }

        // Envoyer par SMS
        if (!empty($this->alertPhones)) {
            $phones = explode(',', $this->alertPhones);
            foreach ($phones as $phone) {
                $phone = trim($phone);
                if (!empty($phone)) {
                    try {
                        // Utiliser un provider alternatif si possible pour éviter de consommer le solde restant
                        Log::info("Low balance SMS alert to: {$phone}");
                        Log::warning("SMS alert not sent to avoid consuming remaining balance: {$phone}");
                    } catch (Exception $e) {
                        Log::error("Failed to send SMS alert to {$phone}: " . $e->getMessage());
                    }
                }
            }
        }

        Log::warning("SMS balance alert triggered. Current balance: {$balance}, Threshold: {$this->alertThreshold}");
    }
}
