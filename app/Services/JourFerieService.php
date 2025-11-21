<?php

namespace App\Services;

use App\Models\CalendrierScolaire;
use App\Models\JourFerie;
use App\Repositories\Contracts\JourFerieRepositoryInterface;
use App\Services\Contracts\JourFerieServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class JourFerieService extends BaseService implements JourFerieServiceInterface
{
    use JsonResponseTrait;

    /**
     * @param JourFerieRepositoryInterface $repository
     */
    public function __construct(JourFerieRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Create a new JourFerie entry.
     * Automatically sets pays_id from the calendrier.
     *
     * @param array $data
     * @return JsonResponse
     */
    public function create(array $data): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Récupérer pays_id depuis le calendrier
            if (isset($data['calendrier_id']) && !isset($data['pays_id'])) {
                $calendrier = CalendrierScolaire::find($data['calendrier_id']);
                if ($calendrier) {
                    $data['pays_id'] = $calendrier->pays_id;
                }
            }

            $jourFerie = $this->repository->create($data);

            DB::commit();
            return $this->createdResponse($jourFerie);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::create - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Create multiple JourFerie entries at once.
     *
     * @param array $items Array of jour ferie data
     * @return JsonResponse
     */
    public function createBulk(array $items): JsonResponse
    {
        try {
            DB::beginTransaction();

            $created = [];
            foreach ($items as $data) {
                // Récupérer pays_id depuis le calendrier
                if (isset($data['calendrier_id']) && !isset($data['pays_id'])) {
                    $calendrier = CalendrierScolaire::find($data['calendrier_id']);
                    if ($calendrier) {
                        $data['pays_id'] = $calendrier->pays_id;
                    }
                }
                $created[] = $this->repository->create($data);
            }

            DB::commit();
            return $this->createdResponse($created);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::createBulk - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get all public holidays.
     *
     * @return JsonResponse
     */
    public function getAllJoursFeries(): JsonResponse
    {
        $joursFeries = $this->repository->all();
        return $this->success($joursFeries);
    }

    /**
     * Check if a given date is a public holiday or within a leave period.
     *
     * @param string $date (format Y-m-d)
     * @param string|null $ecoleId
     * @return JsonResponse
     */
    public function isJourFerie(string $date, ?string $ecoleId = null): JsonResponse
    {
        $query = $this->repository->where('date_debut', '<=', $date)
                                  ->where(function ($q) use ($date) {
                                      $q->whereNull('date_fin')
                                        ->orWhere('date_fin', '>=', $date);
                                  });

        if ($ecoleId) {
            $query->where(function ($q) use ($ecoleId) {
                $q->where('ecole_id', $ecoleId)
                  ->orWhereNull('ecole_id'); // National holidays
            });
        } else {
            $query->whereNull('ecole_id'); // Only national holidays if no ecoleId is provided
        }

        return $this->success($query->exists());
    }

    /**
     * Get public holidays for a specific school.
     *
     * @param string $ecoleId
     * @return JsonResponse
     */
    public function getJoursFeriesForEcole(string $ecoleId): JsonResponse
    {
        $joursFeries = $this->repository->where('ecole_id', $ecoleId)
                                ->orWhereNull('ecole_id') // Include national holidays
                                ->get();
        return $this->success($joursFeries);
    }
}
