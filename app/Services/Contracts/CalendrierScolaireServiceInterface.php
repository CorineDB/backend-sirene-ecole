<?php

namespace App\Services\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CalendrierScolaireServiceInterface extends BaseServiceInterface
{

    /**
     * Get all public holidays associated with a specific school calendar.
     *
     * @param string $calendrierScolaireId The ID of the school calendar.
     * @param \App\Http\Requests\JoursFeriesFiltreRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getJoursFeries(string $calendrierScolaireId, array $filters = []): \Illuminate\Http\JsonResponse;

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

    /**
     * Find calendriers scolaires by country ISO code and school year.
     *
     * @param string $codeIso
     * @param string|null $anneeScolaire
     * @param array $filters Additional optional filters
     * @return \Illuminate\Http\JsonResponse
     */
    public function findByCodeIsoAndAnneeScolaire(string $codeIso, ?string $anneeScolaire = null, array $filters = []): \Illuminate\Http\JsonResponse;

    // Add specific methods for CalendrierScolaireService here if needed
}