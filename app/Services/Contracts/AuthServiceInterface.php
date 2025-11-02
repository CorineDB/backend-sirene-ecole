<?php

namespace App\Services\Contracts;

use Illuminate\Http\JsonResponse;

interface AuthServiceInterface
{
    public function requestOtp(string $telephone): JsonResponse;
    public function verifyOtpAndLogin(string $telephone, string $otp): JsonResponse;
    public function login(string $identifiant, string $motDePasse): JsonResponse;
    public function logout($user): JsonResponse;
    public function me($user): JsonResponse;
}
