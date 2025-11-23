<?php

namespace App\Services;

use App\Enums\TypeOtp;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\OtpService;
use App\Services\SmsService;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Traits\JsonResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpParser\Node\Expr\Throw_;

class AuthService implements AuthServiceInterface
{
    use JsonResponseTrait;

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

            // Générer l'OTP de type LOGIN
            $result = $this->otpService->generate($telephone, $user->id, TypeOtp::LOGIN);

            if (!$result['success']) {
                throw new Exception($result['message']);
            }

            return $this->successResponse(
                $result['message'],
                ['expires_in' => $result['expires_in'] . ' minutes']
            );

        } catch (\Exception $e) {
            Log::error("Error in " . get_class($this) . "::requestOtp - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Vérifier l'OTP et se connecter
     */
    public function verifyOtpAndLogin(string $telephone, string $otp): JsonResponse
    {
        try {
            // Vérifier l'OTP de type LOGIN
            $result = $this->otpService->verify($telephone, $otp, TypeOtp::LOGIN);

            if (!$result['success']) {
                throw ValidationException::withMessages([
                    'otp' => [$result['message']],
                ]);
            }

            // Récupérer l'utilisateur
            $user = $this->userRepository->findByRelation('userInfo', 'telephone', $telephone);

            if (!$user) {
                throw ValidationException::withMessages([
                    'telephone' => ['Aucun compte associé à ce numéro de téléphone.'],
                ]);
            }

            // Apply the user's requested logic
            if ($user->doit_changer_mot_de_passe === true && $user->mot_de_passe_change === false) {
                $user->actif = false;
                $user->statut = 0;
                $user->save(); // Save the updated user
            }

            // Créer le token d'accès
            $token = $user->createToken('auth_token')->accessToken;

            return $this->successResponse(
                'Connexion réussie.',
                [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $user->id,
                        'nom_utilisateur' => $user->nom_utilisateur,
                        'type' => $user->type,
                        'telephone' => $user->userInfo->telephone ?? null,
                        'email' => $user->userInfo->email ?? null,
                        'doit_changer_mot_de_passe' => $user->doit_changer_mot_de_passe,
                        'mot_de_passe_change' => $user->mot_de_passe_change,
                    ],
                ]
            );

        } catch (\Exception $e) {
            Log::error("Error in " . get_class($this) . "::verifyOtpAndLogin - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Connexion classique avec identifiant et mot de passe
     */
    public function login(string $identifiant, string $motDePasse): JsonResponse
    {
        try {
            $user = $this->userRepository->findBy(['identifiant' => $identifiant]);

            if(!$user){

                throw new Exception("User undefined", 403);
            }

            if($user->actif == false && $user->statut == -1)
            {
                throw new \Exception("Veuillez activer votre compte", 403);
            }

            if($user->actif == false && $user->statut !== 1)
            {
                //throw new \Exception("Veuillez changer le mot de passe temporaire", 206);
            }

            if (!$user || !Hash::check($motDePasse, $user->mot_de_passe)) {
                throw ValidationException::withMessages([
                    'identifiant' => ['Identifiants incorrects.'],
                ]);
            }

            // Créer le token d'accès
            $token = $user->createToken('auth_token')->accessToken;

            return $this->successResponse(
                'Connexion réussie.',
                [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $user->id,
                        'nom_utilisateur' => $user->nom_utilisateur,
                        'type' => $user->type,
                        'telephone' => $user->userInfo->telephone ?? null,
                        'email' => $user->userInfo->email ?? null,
                        'doit_changer_mot_de_passe' => $user->doit_changer_mot_de_passe,
                        'mot_de_passe_change' => $user->mot_de_passe_change,
                    ],
                ]
            );

        } catch (\Exception $e) {
            Log::error("Error in " . get_class($this) . "::login - " . $e->getMessage());
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

            return $this->successResponse('Déconnexion réussie.');

        } catch (\Exception $e) {
            Log::error("Error in " . get_class($this) . "::logout - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtenir les informations de l'utilisateur connecté
     */
    public function me($user): JsonResponse
    {
        try {
            $user->load(['userInfo', 'role.permissions', 'userAccount']);

            return $this->successResponse(
                null,
                [
                    'user' => [
                        'id' => $user->id,
                        'nom_utilisateur' => $user->nom_utilisateur,
                        'identifiant' => $user->identifiant,
                        'type' => $user->type,
                        'user_account_type_type' => $user->user_account_type_type,
                        'user_account_type_id' => $user->user_account_type_id,
                        'telephone' => $user->userInfo->telephone ?? null,
                        'email' => $user->userInfo->email ?? null,
                        'role' => $user->role,
                        'permissions' => $user->role->permissions ?? [],
                        'userAccount' => $user->userAccount,
                    ],
                ]
            );

        } catch (\Exception $e) {
            Log::error("Error in " . get_class($this) . "::me - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Changer le mot de passe de l'utilisateur
     */
    public function changerMotDePasse($user, string $nouveauMotDePasse, ?string $ancienMotDePasse = null): JsonResponse
    {
        try {
            // Si l'ancien mot de passe est fourni, le vérifier
            if ($ancienMotDePasse !== null) {
                if (!Hash::check($ancienMotDePasse, $user->mot_de_passe)) {
                    throw ValidationException::withMessages([
                        'ancien_mot_de_passe' => ['L\'ancien mot de passe est incorrect.'],
                    ]);
                }
            }

            // Activer le compte si c'est le premier changement de mot de passe
            if ($user->doit_changer_mot_de_passe === true && $user->mot_de_passe_change === false) {
                $user->actif = true;
                $user->statut = 1;
            }

            // Mettre à jour le mot de passe
            $user->mot_de_passe = Hash::make($nouveauMotDePasse);
            $user->mot_de_passe_change = true;
            $user->doit_changer_mot_de_passe = false;
            $user->save();

            return $this->successResponse('Mot de passe changé avec succès.');

        } catch (\Exception $e) {
            Log::error("Error in " . get_class($this) . "::changerMotDePasse - " . $e->getMessage());
            throw $e;
        }
    }
}
