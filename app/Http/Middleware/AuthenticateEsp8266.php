<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Sirene;
use App\Enums\StatutAbonnement;
use Illuminate\Support\Facades\Log;

class AuthenticateEsp8266
{
    /**
     * Handle an incoming request from ESP8266 device.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Récupérer le token depuis le header X-ESP8266-Token
        $tokenCrypte = $request->header('X-ESP8266-Token');

        // Si aucun token fourni, on laisse passer (mode non sécurisé)
        if (!$tokenCrypte) {
            Log::warning('ESP8266 request without token', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            // Vous pouvez forcer l'authentification en décommentant cette ligne :
            // return response()->json([
            //     'success' => false,
            //     'message' => 'Token d\'authentification requis. Veuillez fournir le header X-ESP8266-Token.'
            // ], 401);

            // Pour l'instant, on laisse passer sans token
            return $next($request);
        }

        // Récupérer le numéro de série depuis la route
        $numeroSerie = $request->route('numeroSerie');

        if (!$numeroSerie) {
            return response()->json([
                'success' => false,
                'message' => 'Numéro de série manquant.'
            ], 400);
        }

        // Rechercher la sirène avec son abonnement actif
        $sirene = Sirene::where('numero_serie', $numeroSerie)
            ->with([
                'abonnements' => function ($query) {
                    $query->where('statut', StatutAbonnement::ACTIF->value)
                        ->where('date_debut', '<=', now())
                        ->where('date_fin', '>=', now())
                        ->with(['tokenActif']);
                }
            ])
            ->first();

        if (!$sirene) {
            return response()->json([
                'success' => false,
                'message' => 'Sirène non trouvée pour ce numéro de série.'
            ], 404);
        }

        // Vérifier l'abonnement actif
        $abonnementActif = $sirene->abonnements->first();

        if (!$abonnementActif) {
            Log::warning('ESP8266 request without active subscription', [
                'numero_serie' => $numeroSerie,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Aucun abonnement actif pour cette sirène.'
            ], 401);
        }

        // Vérifier le token actif
        $tokenActif = $abonnementActif->tokenActif;

        if (!$tokenActif) {
            Log::warning('ESP8266 request without active token', [
                'numero_serie' => $numeroSerie,
                'abonnement_id' => $abonnementActif->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Aucun token actif trouvé pour cet abonnement.'
            ], 401);
        }

        // Vérifier que le token correspond
        $tokenHash = hash('sha256', $tokenCrypte);
        if ($tokenHash !== $tokenActif->token_hash) {
            Log::warning('ESP8266 invalid token', [
                'numero_serie' => $numeroSerie,
                'token_hash_fourni' => $tokenHash,
                'token_hash_attendu' => $tokenActif->token_hash,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token d\'authentification invalide.'
            ], 401);
        }

        // Vérifier que le token n'est pas expiré
        if ($tokenActif->date_expiration < now()) {
            Log::warning('ESP8266 expired token', [
                'numero_serie' => $numeroSerie,
                'date_expiration' => $tokenActif->date_expiration->toIso8601String(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token expiré. Veuillez renouveler votre abonnement.'
            ], 401);
        }

        // Authentification réussie - Ajouter les infos au request pour utilisation ultérieure
        $request->merge([
            'authenticated_sirene' => $sirene,
            'authenticated_abonnement' => $abonnementActif,
            'authenticated_token' => $tokenActif,
        ]);

        Log::info('ESP8266 authenticated successfully', [
            'numero_serie' => $numeroSerie,
            'abonnement_id' => $abonnementActif->id,
            'token_id' => $tokenActif->id,
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }
}
