<?php

namespace App\Services\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CalendrierScolaireServiceInterface extends BaseServiceInterface
{
    /**
     * Create a new school calendar entry with its default holidays.
     *
     * @param array $data
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(array $data): \Illuminate\Http\JsonResponse;

    /**
     * Get all public holidays associated with a specific school calendar.
     *
     * @param string $calendrierScolaireId The ID of the school calendar.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getJoursFeries(string $calendrierScolaireId): \Illuminate\Http\JsonResponse;

    /**
     * Calculate the number of school days for a given school calendar, excluding weekends, holidays, and vacation periods.
     *
     * @param string $calendrierScolaireId The ID of the school calendar.
     * @param string|null $ecoleId The ID of the school (optional).
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateSchoolDays(string $calendrierScolaireId, string $ecoleId = null): \Illuminate\Http\JsonResponse;

    /**
     * Load the school calendar for a specific school, including global and school-specific holidays.
     *
     * @param string $calendrierScolaireId The ID of the school calendar.
     * @param string|null $ecoleId The ID of the school (optional).
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCalendrierScolaireWithJoursFeries(array $filtres = []): \Illuminate\Http\JsonResponse;

    /**
     * Store multiple public holidays for a specific school calendar.
     *
     * @param string $calendrierScolaireId
     * @param array $joursFeriesData
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeMultipleJoursFeries(string $calendrierScolaireId, array $joursFeriesData): \Illuminate\Http\JsonResponse;

    /**
     * Update multiple public holidays for a specific school calendar.
     *
     * @param string $calendrierScolaireId
     * @param array $joursFeriesData
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMultipleJoursFeries(string $calendrierScolaireId, array $joursFeriesData): \Illuminate\Http\JsonResponse;

    // Add specific methods for CalendrierScolaireService here if needed
}