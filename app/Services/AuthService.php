<?php

namespace App\Services;

use App\Services\Contracts\AuthServiceInterface;
use App\Services\OtpService;
use App\Services\SmsService;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService implements AuthServiceInterface
{
    protected $otpService;
    protected $smsService;
    protected $userRepository;

    public function __construct(
        OtpService $otpService,
        SmsService $smsService,
        UserRepositoryInterface $userRepository
    ) {
        $this->otpService = $otpService;
        $this->smsService = $smsService;
        $this->userRepository = $userRepository;
    }

    /**
     * Demander un code OTP pour connexion
     */
    public function requestOtp(string $telephone): JsonResponse
    {
        try {
            // Vérifier que l'utilisateur existe avec ce numéro
            $user = $this->userRepository->findByRelation('userInfo', 'telephone', $telephone);

            if (!$user) {
                throw ValidationException::withMessages([
                    'telephone' => ['Aucun compte associé à ce numéro de téléphone.'],
                ]);
            }

            // Générer l'OTP
            $otp = $this->otpService->generateOtp($telephone);

            // Envoyer l'OTP par SMS
            $this->smsService->sendOtpSms($telephone, $otp);

            return response()->json([
                'success' => true,
                'message' => 'Code OTP envoyé avec succès.',
                'expires_in' => config('otp.expiration', 5) . ' minutes',
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Error in " . get_class($this) . "::requestOtp - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Vérifier l'OTP et se connecter
     */
    public function verifyOtpAndLogin(string $telephone, string $otp): JsonResponse
    {
        try {
            // Vérifier l'OTP
            if (!$this->otpService->verifyOtp($telephone, $otp)) {
                throw ValidationException::withMessages([
                    'otp' => ['Code OTP invalide ou expiré.'],
                ]);
            }

            // Récupérer l'utilisateur
            $user = $this->userRepository->findByRelation('userInfo', 'telephone', $telephone);

            if (!$user) {
                throw ValidationException::withMessages([
                    'telephone' => ['Aucun compte associé à ce numéro de téléphone.'],
                ]);
            }

            // Créer le token d'accès
            $token = $user->createToken('auth_token')->accessToken;

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie.',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'nom_utilisateur' => $user->nom_utilisateur,
                    'type' => $user->type,
                    'telephone' => $user->userInfo->telephone ?? null,
                    'email' => $user->userInfo->email ?? null,
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Error in " . get_class($this) . "::verifyOtpAndLogin - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Connexion classique avec identifiant et mot de passe
     */
    public function login(string $identifiant, string $motDePasse): JsonResponse
    {
        try {
            $user = $this->userRepository->findBy('identifiant', $identifiant);

            if (!$user || !Hash::check($motDePasse, $user->mot_de_passe)) {
                throw ValidationException::withMessages([
                    'identifiant' => ['Identifiants incorrects.'],
                ]);
            }

            // Créer le token d'accès
            $token = $user->createToken('auth_token')->accessToken;

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie.',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'nom_utilisateur' => $user->nom_utilisateur,
                    'type' => $user->type,
                    'telephone' => $user->userInfo->telephone ?? null,
                    'email' => $user->userInfo->email ?? null,
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Error in " . get_class($this) . "::login - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Déconnexion
     */
    public function logout($user): JsonResponse
    {
        try {
            $user->token()->revoke();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie.',
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Error in " . get_class($this) . "::logout - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtenir les informations de l'utilisateur connecté
     */
    public function me($user): JsonResponse
    {
        try {
            $user->load(['userInfo', 'role.permissions']);

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'nom_utilisateur' => $user->nom_utilisateur,
                    'identifiant' => $user->identifiant,
                    'type' => $user->type,
                    'telephone' => $user->userInfo->telephone ?? null,
                    'email' => $user->userInfo->email ?? null,
                    'role' => $user->role,
                    'permissions' => $user->role->permissions ?? [],
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Error in " . get_class($this) . "::me - " . $e->getMessage());
            throw $e;
        }
    }
}
