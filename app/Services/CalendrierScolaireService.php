<?php

namespace App\Services;

use App\Repositories\Contracts\CalendrierScolaireRepositoryInterface;
use App\Repositories\Contracts\JourFerieRepositoryInterface;
use App\Services\Contracts\CalendrierScolaireServiceInterface;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class CalendrierScolaireService extends BaseService implements CalendrierScolaireServiceInterface
{
    protected $jourFerieRepository;

    public function __construct(CalendrierScolaireRepositoryInterface $repository, JourFerieRepositoryInterface $jourFerieRepository)
    {
        parent::__construct($repository);
        $this->jourFerieRepository = $jourFerieRepository;
    }

    public function create(array $data): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Extraire les jours fériés du payload pour les créer dans la table jours_feries
            $joursFeriesData = $data['jours_feries_defaut'] ?? [];
            unset($data['jours_feries_defaut']);

            // Créer le calendrier scolaire d'abord (sans jours_feries_defaut)
            $calendrierScolaire = $this->repository->create($data);

            // Créer les jours fériés fournis dans le payload
            $createdJoursFeries = [];
            if (!empty($joursFeriesData)) {
                foreach ($joursFeriesData as $jourFerieData) {
                    $jourFerieData['calendrier_id'] = $calendrierScolaire->id;
                    $jourFerieData['pays_id'] = $data['pays_id'] ?? null;
                    $jourFerieData['intitule_journee'] = $jourFerieData['nom'] ?? $jourFerieData['intitule_journee'];
                    $jourFerieData['est_national'] = $jourFerieData['est_national'] ?? true;
                    $jourFerieData['actif'] = $jourFerieData['actif'] ?? true;
                    $jourFerieData['date'] = $jourFerieData['date'];
                    unset($jourFerieData['nom']);
                    $created = $this->jourFerieRepository->create($jourFerieData);
                    $createdJoursFeries[] = [
                        'intitule_journee' => $created->intitule_journee,
                        'date' => $created->date->format('Y-m-d'),
                        'recurrent' => $created->recurrent ?? false,
                        'est_national' => $created->est_national,
                    ];
                }
            }

            // Récupérer les jours fériés nationaux existants depuis la table jours_feries
            $joursFeriesNationaux = [];
            if (isset($data['pays_id'])) {
                $joursFeriesNationaux = \App\Models\JourFerie::where('pays_id', $data['pays_id'])
                    ->where('est_national', true)
                    ->where('actif', true)
                    ->where('calendrier_id', '!=', $calendrierScolaire->id) // Exclure ceux qu'on vient de créer
                    ->get(['intitule_journee', 'date', 'recurrent', 'est_national'])
                    ->map(function ($item) {
                        return [
                            'intitule_journee' => $item->intitule_journee,
                            'date' => $item->date->format('Y-m-d'),
                            'recurrent' => $item->recurrent,
                            'est_national' => $item->est_national,
                        ];
                    })
                    ->toArray();
            }

            // Fusionner les jours fériés créés avec les nationaux existants
            $allJoursFeries = array_merge($createdJoursFeries, $joursFeriesNationaux);

            // Mettre à jour le champ jours_feries_defaut
            $calendrierScolaire->update(['jours_feries_defaut' => $allJoursFeries]);

            DB::commit();
            return $this->createdResponse($calendrierScolaire->load('joursFeries'));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::create - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store multiple public holidays for a specific school calendar.
     *
     * @param string $calendrierScolaireId
     * @param array $joursFeriesData
     * @return JsonResponse
     */
    public function storeMultipleJoursFeries(string $calendrierScolaireId, array $joursFeriesData): JsonResponse
    {
        try {
            $processedJoursFeries = collect();

            foreach ($joursFeriesData as $data) {
                $data['calendrier_id'] = $calendrierScolaireId;
                // Ensure 'est_national' is set if not provided
                if (!isset($data['est_national'])) {
                    $data['est_national'] = false;
                }

                if (isset($data['id'])) {
                    // Attempt to update if ID is provided
                    $jourFerie = $this->jourFerieRepository->update($data['id'], $data);
                } else {
                    // Create new if no ID
                    $jourFerie = $this->jourFerieRepository->create($data);
                }
                $processedJoursFeries->push($jourFerie);
            }

            return $this->successResponse(null, $processedJoursFeries);
        } catch (\Exception $e) {
            Log::error("Error in " . get_class($this) . "::storeMultipleJoursFeries - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update multiple public holidays for a specific school calendar.
     *
     * @param string $calendrierScolaireId
     * @param array $joursFeriesData
     * @return JsonResponse
     */
    public function updateMultipleJoursFeries(string $calendrierScolaireId, array $joursFeriesData): JsonResponse
    {
        try {
            $processedJoursFeries = collect();

            foreach ($joursFeriesData as $data) {
                if (!isset($data['id'])) {
                    return $this->errorResponse('ID is required for updating public holidays.', 422);
                }
                $data['calendrier_id'] = $calendrierScolaireId;
                // Ensure 'est_national' is set if not provided
                if (!isset($data['est_national'])) {
                    $data['est_national'] = false;
                }

                $jourFerie = $this->jourFerieRepository->update($data['id'], $data);
                $processedJoursFeries->push($jourFerie);
            }

            return $this->successResponse(null, $processedJoursFeries);
        } catch (\Exception $e) {
            Log::error("Error in " . get_class($this) . "::updateMultipleJoursFeries - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get all public holidays associated with a specific school calendar.
     *
     * @param string $calendrierScolaireId The ID of the school calendar.
     * @return JsonResponse
     */
    public function getJoursFeries(string $calendrierScolaireId, array $filters = []): JsonResponse
    {
        try {
            $calendrierScolaire = $this->repository->find($calendrierScolaireId);

            if (!$calendrierScolaire) {
                return $this->notFoundResponse('School calendar not found.');
            }

            // Construire la requête avec filtres
            $query = $calendrierScolaire->joursFeries();

            if (isset($filters['est_national'])) {
                $query->where('est_national', $filters['est_national']);
            }

            if (isset($filters['ecole_id'])) {
                $query->where('ecole_id', $filters['ecole_id']);
            }

            if (isset($filters['date_debut'])) {
                $query->where('date', '>=', $filters['date_debut']);
            }

            if (isset($filters['date_fin'])) {
                $query->where('date', '<=', $filters['date_fin']);
            }

            return $this->successResponse(null, $query->get());
        } catch (\Exception $e) {
            Log::error("Error in " . get_class($this) . "::getJoursFeries - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Calculate the number of school days for a given school calendar, excluding weekends, holidays, and vacation periods.
     *
     * @param string $calendrierScolaireId The ID of the school calendar.
     * @param string|null $ecoleId The ID of the school (optional).
     * @return JsonResponse
     */
    public function calculateSchoolDays(string $calendrierScolaireId, string $ecoleId = null): JsonResponse
    {
        try {
            $calendrierScolaire = $this->repository->find($calendrierScolaireId, relations: ['joursFeries']);

            if (!$calendrierScolaire) {
                return $this->notFoundResponse('School calendar not found.');
            }

            $startDate = $calendrierScolaire->date_rentree;
            $endDate = $calendrierScolaire->date_fin_annee;
            $vacances = $calendrierScolaire->periodes_vacances;
            $joursFeries = $calendrierScolaire->joursFeries->pluck('date_ferie')->map(fn ($date) => $date->format('Y-m-d'))->toArray();

            if ($ecoleId) {
                $ecole = \App\Models\Ecole::with('joursFeries')->find($ecoleId);
                if ($ecole) {
                    $ecoleJoursFeries = $ecole->joursFeries;
                    foreach ($ecoleJoursFeries as $jourFerie) {
                        $date = $jourFerie->date_ferie->format('Y-m-d');
                        if ($jourFerie->actif) {
                            // Add holiday if not already in the list
                            if (!in_array($date, $joursFeries)) {
                                $joursFeries[] = $date;
                            }
                        } else {
                            // Remove holiday if it exists in the list
                            if (($key = array_search($date, $joursFeries)) !== false) {
                                unset($joursFeries[$key]);
                            }
                        }
                    }
                }
            }

            $schoolDays = 0;
            $currentDate = clone $startDate;

            while ($currentDate->lte($endDate)) {
                // Check if it's a weekend
                if ($currentDate->isWeekday()) {
                    $isHoliday = false;

                    // Check if it's a public holiday
                    if (in_array($currentDate->format('Y-m-d'), $joursFeries)) {
                        $isHoliday = true;
                    }

                    // Check if it's a vacation period
                    if (!$isHoliday) {
                        foreach ($vacances as $vacance) {
                            $vacanceStart = \Carbon\Carbon::parse($vacance['date_debut']);
                            $vacanceEnd = \Carbon\Carbon::parse($vacance['date_fin']);
                            if ($currentDate->between($vacanceStart, $vacanceEnd)) {
                                $isHoliday = true;
                                break;
                            }
                        }
                    }

                    if (!$isHoliday) {
                        $schoolDays++;
                    }
                }
                $currentDate->addDay();
            }

            return $this->successResponse(null, ['school_days' => $schoolDays]);
        } catch (\Exception $e) {
            Log::error("Error in " . get_class($this) . "::calculateSchoolDays - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Load the school calendar for a specific school, including global and school-specific holidays.
     *
     * @param string $calendrierScolaireId The ID of the school calendar.
     * @param string|null $ecoleId The ID of the school (optional).
     * @return JsonResponse
     */
    public function getCalendrierScolaireWithJoursFeries(array $filtres = []): JsonResponse
    {
        try {
            $anneeScolaire = $filtres['annee_scolaire'];
            $ecoleId = $filtres['ecoleId'] ?? null;

            $calendrierScolaire = $this->repository->findBy(['annee_scolaire' => $anneeScolaire], relations: ['joursFeries']);

            if (!$calendrierScolaire) {
                return $this->notFoundResponse('School calendar not found.');
            }

            $globalJoursFeries = collect();
            if (isset($filtres['avec_jours_feries_nationaux']) && $filtres['avec_jours_feries_nationaux']) {
                $globalJoursFeries = $calendrierScolaire->joursFeries->map(function ($jourFerie) {
                    return [
                        'id' => $jourFerie->id,
                        'nom' => $jourFerie->nom,
                        'date' => $jourFerie->date_ferie->format('Y-m-d'),
                        'actif' => $jourFerie->actif,
                        'type' => $jourFerie->type,
                        'recurrent' => $jourFerie->recurrent,
                    ];
                })->keyBy('date'); // Key by date for easy merging
            }

            $mergedJoursFeries = $globalJoursFeries;

            if ($ecoleId && isset($filtres['avec_jours_feries_ecole']) && $filtres['avec_jours_feries_ecole']) {
                $ecole = \App\Models\Ecole::with('joursFeries')->find($ecoleId);
                if ($ecole) {
                    $ecoleJoursFeries = $ecole->joursFeries->map(function ($jourFerie) {
                        return [
                            'id' => $jourFerie->id,
                            'nom' => $jourFerie->nom,
                            'date' => $jourFerie->date_ferie->format('Y-m-d'),
                            'actif' => $jourFerie->actif,
                            'type' => $jourFerie->type,
                            'recurrent' => $jourFerie->recurrent,
                        ];
                    })->keyBy('date');

                    // Merge school-specific holidays, overriding global ones
                    $mergedJoursFeries = $globalJoursFeries->merge($ecoleJoursFeries);
                }
            }

            $calendrierScolaireArray = $calendrierScolaire->toArray();
            $calendrierScolaireArray['jours_feries_merged'] = $mergedJoursFeries->values()->toArray(); // Convert back to indexed array

            return $this->successResponse(null, $calendrierScolaireArray);
        } catch (\Exception $e) {
            Log::error("Error in " . get_class($this) . "::getCalendrierScolaireWithJoursFeries - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Find calendriers scolaires by country ISO code and school year.
     *
     * @param string $codeIso
     * @param string $anneeScolaire
     * @param array $filters Additional optional filters
     * @return \Illuminate\Http\JsonResponse
     */
    public function findByCodeIsoAndAnneeScolaire(string $codeIso, string $anneeScolaire, array $filters = []): \Illuminate\Http\JsonResponse
    {
        try {
            // Récupérer le pays via code_iso
            $pays = \App\Models\Pays::where('code_iso', $codeIso)->first();

            if (!$pays) {
                return $this->notFoundResponse('Pays non trouvé avec le code ISO fourni.');
            }

            // Construire les critères de recherche avec pays_id
            $criteria = [
                'pays_id' => $pays->id,
                'annee_scolaire' => $anneeScolaire,
            ];

            // Ajouter les filtres optionnels
            if (isset($filters['actif'])) {
                $criteria['actif'] = $filters['actif'];
            }

            // Rechercher les calendriers
            $calendriers = $this->repository->findAllBy($criteria, relations: ['joursFeries', 'pays']);

            return $this->successResponse(null, $calendriers);

        } catch (\Exception $e) {
            Log::error("Error in " . get_class($this) . "::findByCodeIsoAndAnneeScolaire - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update a school calendar entry.
     *
     * @param string $id
     * @param array $data
     * @return JsonResponse
     */
    public function update(string $id, array $data): JsonResponse
    {
        try {
            DB::beginTransaction();

            $calendrierScolaire = $this->repository->find($id);
            if (!$calendrierScolaire) {
                return $this->notFoundResponse('Calendrier scolaire non trouvé.');
            }

            // Extraire les jours fériés du payload
            $joursFeriesData = $data['jours_feries_defaut'] ?? null;
            unset($data['jours_feries_defaut']);

            // Mettre à jour le calendrier scolaire
            $this->repository->update($id, $data);

            // Gérer les jours fériés si fournis
            if ($joursFeriesData !== null) {
                // Supprimer les anciens jours fériés liés à ce calendrier
                \App\Models\JourFerie::where('calendrier_id', $id)->delete();

                // Créer les nouveaux jours fériés
                $createdJoursFeries = [];
                foreach ($joursFeriesData as $jourFerieData) {
                    $jourFerieData['calendrier_id'] = $id;
                    $jourFerieData['pays_id'] = $data['pays_id'] ?? $calendrierScolaire->pays_id;
                    $jourFerieData['intitule_journee'] = $jourFerieData['nom'] ?? $jourFerieData['intitule_journee'];
                    $jourFerieData['est_national'] = $jourFerieData['est_national'] ?? true;
                    $jourFerieData['actif'] = $jourFerieData['actif'] ?? true;
                    $jourFerieData['date'] = $jourFerieData['date'];
                    unset($jourFerieData['nom']);
                    $created = $this->jourFerieRepository->create($jourFerieData);
                    $createdJoursFeries[] = [
                        'intitule_journee' => $created->intitule_journee,
                        'date' => $created->date->format('Y-m-d'),
                        'recurrent' => $created->recurrent ?? false,
                        'est_national' => $created->est_national,
                    ];
                }

                // Récupérer les jours fériés nationaux existants
                $paysId = $data['pays_id'] ?? $calendrierScolaire->pays_id;
                $joursFeriesNationaux = [];
                if ($paysId) {
                    $joursFeriesNationaux = \App\Models\JourFerie::where('pays_id', $paysId)
                        ->where('est_national', true)
                        ->where('actif', true)
                        ->where('calendrier_id', '!=', $id)
                        ->get(['intitule_journee', 'date', 'recurrent', 'est_national'])
                        ->map(function ($item) {
                            return [
                                'intitule_journee' => $item->intitule_journee,
                                'date' => $item->date->format('Y-m-d'),
                                'recurrent' => $item->recurrent,
                                'est_national' => $item->est_national,
                            ];
                        })
                        ->toArray();
                }

                // Fusionner et mettre à jour
                $allJoursFeries = array_merge($createdJoursFeries, $joursFeriesNationaux);
                $calendrierScolaire->update(['jours_feries_defaut' => $allJoursFeries]);
            }

            DB::commit();
            return $this->successResponse(null, $this->repository->find($id)->load('joursFeries'));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::update - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
