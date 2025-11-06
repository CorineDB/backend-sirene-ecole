<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CinetPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CinetPayController extends Controller
{
    protected CinetPayService $cinetpayService;

    public function __construct(CinetPayService $cinetpayService)
    {
        $this->cinetpayService = $cinetpayService;
    }

    /**
     * Callback de notification de CinetPay
     * Appelé par CinetPay pour notifier du statut du paiement
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function notify(Request $request): JsonResponse
    {
        try {
            Log::info('CinetPay Notification reçue', [
                'data' => $request->all(),
                'ip' => $request->ip(),
            ]);

            $data = $request->all();

            // Traiter la notification
            $success = $this->cinetpayService->traiterNotification($data);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Notification traitée avec succès',
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Échec du traitement de la notification',
            ], 400);

        } catch (\Exception $e) {
            Log::error('Erreur traitement notification CinetPay: ' . $e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Page de retour après paiement
     * L'utilisateur est redirigé ici après avoir effectué le paiement
     *
     * @param Request $request
     * @return JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function return(Request $request)
    {
        try {
            Log::info('CinetPay Return reçu', [
                'data' => $request->all(),
            ]);

            $transactionId = $request->input('transaction_id');

            if (!$transactionId) {
                return $this->redirectToFrontend('error', 'Transaction ID manquant');
            }

            // Vérifier le statut de la transaction
            $result = $this->cinetpayService->verifierTransaction($transactionId);

            Log::info('Statut transaction vérifié', [
                'transaction_id' => $transactionId,
                'result' => $result,
            ]);

            // Vérifier le statut
            if (isset($result['data']['status']) && in_array($result['data']['status'], ['ACCEPTED', '00'])) {
                // Paiement réussi
                return $this->redirectToFrontend('success', 'Paiement effectué avec succès', $transactionId);
            } else {
                // Paiement échoué ou en attente
                $message = $result['data']['payment_method'] ?? 'Paiement non validé';
                return $this->redirectToFrontend('pending', $message, $transactionId);
            }

        } catch (\Exception $e) {
            Log::error('Erreur traitement retour CinetPay: ' . $e->getMessage(), [
                'data' => $request->all(),
            ]);

            return $this->redirectToFrontend('error', 'Erreur lors de la vérification du paiement');
        }
    }

    /**
     * Rediriger vers le frontend avec le statut
     */
    protected function redirectToFrontend(string $status, string $message, ?string $transactionId = null)
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $url = "{$frontendUrl}/paiement/callback?status={$status}&message=" . urlencode($message);

        if ($transactionId) {
            $url .= "&transaction_id={$transactionId}";
        }

        return redirect($url);
    }

    /**
     * Vérifier manuellement le statut d'une transaction
     * Endpoint pour le frontend pour vérifier le statut d'un paiement
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'transaction_id' => 'required|string',
            ]);

            $transactionId = $request->input('transaction_id');

            $result = $this->cinetpayService->verifierTransaction($transactionId);

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur vérification statut: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
