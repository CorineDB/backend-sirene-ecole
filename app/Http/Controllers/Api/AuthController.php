<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Services\Contracts\AuthServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthServiceInterface $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Demander un code OTP pour connexion
     */
    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        return $this->authService->requestOtp($request->telephone);
    }

    /**
     * Vérifier l'OTP et se connecter
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        return $this->authService->verifyOtpAndLogin($request->telephone, $request->otp);
    }

    /**
     * Connexion classique avec identifiant et mot de passe
     */
    public function login(LoginRequest $request): JsonResponse
    {
        return $this->authService->login($request->identifiant, $request->mot_de_passe);
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request): JsonResponse
    {
        return $this->authService->logout($request->user());
    }

    /**
     * Obtenir les informations de l'utilisateur connecté
     */
    public function me(Request $request): JsonResponse
    {
        return $this->authService->me($request->user());
    }
}
