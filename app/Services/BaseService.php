<?php

namespace App\Services;

use App\Repositories\Contracts\BaseRepositoryInterface;
use App\Services\Contracts\BaseServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

abstract class BaseService implements BaseServiceInterface
{
    use JsonResponseTrait;

    protected BaseRepositoryInterface $repository;

    public function __construct(BaseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get the repository instance.
     */
    public function getRepository(): BaseRepositoryInterface{
        return $this->repository;
    }

    public function getAll(int $perPage = 15, array $relations = []): JsonResponse
    {
        try {
            $data = $this->repository->paginate($perPage, relations: $relations);
            return $this->successResponse(null, $data);
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::getAll - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getById(string $id, array $columns = ['*'], array $relations = []): JsonResponse
    {
        try {
            $data = $this->repository->find($id, $columns, $relations);
            if (!$data) {
                return $this->notFoundResponse();
            }
            return $this->successResponse(null, $data);
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::getById - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function create(array $data): JsonResponse
    {
        try {
            DB::beginTransaction();
            $model = $this->repository->create($data);
            DB::commit();
            return $this->createdResponse($model);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::create - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(string $id, array $data): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->repository->update($id, $data);
            DB::commit();
            $model = $this->repository->find($id);
            return $this->successResponse(null, $model);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::update - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function delete(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->repository->delete($id);
            DB::commit();
            return $this->noContentResponse();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::delete - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function forceDelete(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->repository->forceDelete($id);
            DB::commit();
            return $this->noContentResponse();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::forceDelete - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function restore(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->repository->restore($id);
            DB::commit();
            return $this->successResponse();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::restore - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function findBy(array $criteria, array $relations = []): JsonResponse
    {
        try {
            $data = $this->repository->findBy($criteria, ['*'], $relations);
            if (!$data) {
                return $this->notFoundResponse();
            }
            return $this->successResponse(null, $data);
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::findBy - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function findAllBy(array $criteria, array $relations = []): JsonResponse
    {
        try {
            $data = $this->repository->findAllBy($criteria, ['*'], $relations);
            return $this->successResponse(null, $data);
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::findAllBy - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function exists(array $criteria): JsonResponse
    {
        try {
            $exists = $this->repository->exists($criteria);
            return $this->successResponse(null, ['exists' => $exists]);
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::exists - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function count(array $criteria = []): JsonResponse
    {
        try {
            $count = $this->repository->count($criteria);
            return $this->successResponse(null, ['count' => $count]);
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::count - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
