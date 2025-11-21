<?php

namespace App\Services\Contracts;

use App\Models\JourFerie;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

interface JourFerieServiceInterface extends BaseServiceInterface
{
    /**
     * Get all public holidays.
     *
     * @return JsonResponse
     */
    public function getAllJoursFeries(): JsonResponse;

    /**
     * Check if a given date is a public holiday or within a leave period.
     *
     * @param string $date (format Y-m-d)
     * @param string|null $ecoleId
     * @return JsonResponse
     */
    public function isJourFerie(string $date, ?string $ecoleId = null): JsonResponse;

    /**
     * Get public holidays for a specific school.
     *
     * @param string $ecoleId
     * @return JsonResponse
     */
    public function getJoursFeriesForEcole(string $ecoleId): JsonResponse;

    /**
     * Create multiple public holidays at once.
     *
     * @param array $items
     * @return JsonResponse
     */
    public function createBulk(array $items): JsonResponse;
}