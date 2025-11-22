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
        // Récupérer le token depuis le header X-Sirene-Token
        $tokenCrypte = $request->header('X-Sirene-Token');

        // Si aucun token fourni, retourner une erreur
        if (!$tokenCrypte) {
            Log::warning('Sirene request without token', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token d\'authentification requis. Veuillez fournir le header X-Sirene-Token.'
            ], 401);
        }

        // Hash du token pour recherche en base de données
        $tokenHash = hash('sha256', $tokenCrypte);

        // Rechercher le token actif avec son abonnement et sa sirène
        $tokenActif = \App\Models\AbonnementToken::where('token_hash', $tokenHash)
            ->where('actif', true)
            ->with([
                'abonnement' => function ($query) {
                    $query->where('statut', StatutAbonnement::ACTIF->value)
                        ->where('date_debut', '<=', now())
                        ->where('date_fin', '>=', now())
                        ->with(['sirene']);
                }
            ])
            ->first();

        if (!$tokenActif) {
            Log::warning('Sirene request with invalid token', [
                'token_hash' => $tokenHash,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token d\'authentification invalide.'
            ], 401);
        }

        // Vérifier que le token n'est pas expiré
        if ($tokenActif->date_expiration < now()) {
            Log::warning('Sirene request with expired token', [
                'token_id' => $tokenActif->id,
                'date_expiration' => $tokenActif->date_expiration->toIso8601String(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token expiré. Veuillez renouveler votre abonnement.'
            ], 401);
        }

        // Vérifier l'abonnement actif
        $abonnementActif = $tokenActif->abonnement;

        if (!$abonnementActif) {
            Log::warning('Sirene request without active subscription', [
                'token_id' => $tokenActif->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Aucun abonnement actif associé à ce token.'
            ], 401);
        }

        // Récupérer la sirène depuis l'abonnement
        $sirene = $abonnementActif->sirene;

        if (!$sirene) {
            Log::warning('Sirene request without associated sirene', [
                'abonnement_id' => $abonnementActif->id,
                'token_id' => $tokenActif->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Aucune sirène associée à cet abonnement.'
            ], 404);
        }

        // Authentification réussie - Ajouter les infos au request pour utilisation ultérieure
        $request->merge([
            'authenticated_sirene' => $sirene,
            'authenticated_abonnement' => $abonnementActif,
            'authenticated_token' => $tokenActif,
        ]);

        Log::info('Sirene authenticated successfully', [
            'sirene_id' => $sirene->id,
            'numero_serie' => $sirene->numero_serie,
            'abonnement_id' => $abonnementActif->id,
            'token_id' => $tokenActif->id,
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }
}
