<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\BaseService;
use App\Services\Contracts\UserServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserService extends BaseService implements UserServiceInterface
{
    public function __construct(UserRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function create(array $data): JsonResponse
    {
        try {
            DB::beginTransaction();
            $userInfoData = $data['userInfoData'] ?? [];
            unset($data['userInfoData']);
            $model = $this->repository->create($data, $userInfoData);
            DB::commit();
            return $this->createdResponse($model);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::create - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(string $id, array $data): JsonResponse
    {
        try {
            DB::beginTransaction();
            $userInfoData = $data['userInfoData'] ?? [];
            unset($data['userInfoData']);
            $this->repository->update($id, $data, $userInfoData);
            DB::commit();
            $model = $this->repository->find($id);
            return $this->successResponse(null, $model);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::update - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function findByIdentifier(string $identifier): JsonResponse
    {
        try {
            $user = $this->repository->findByIdentifier($identifier);
            if (!$user) {
                return $this->notFoundResponse();
            }
            return $this->successResponse(null, $user);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function findByEmail(string $email): JsonResponse
    {
        try {
            $user = $this->repository->findByEmail($email);
            if (!$user) {
                return $this->notFoundResponse();
            }
            return $this->successResponse(null, $user);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function findByPhone(string $phone): JsonResponse
    {
        try {
            $user = $this->repository->findByPhone($phone);
            if (!$user) {
                return $this->notFoundResponse();
            }
            return $this->successResponse(null, $user);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function findByUserAccount(string $accountType, string $accountId): JsonResponse
    {
        try {
            $user = $this->repository->findByUserAccount($accountType, $accountId);
            if (!$user) {
                return $this->notFoundResponse();
            }
            return $this->successResponse(null, $user);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function userExists(string $identifier): JsonResponse
    {
        try {
            $exists = $this->repository->userExists($identifier);
            return $this->successResponse(null, ['exists' => $exists]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateStatus(string $id, int $status): JsonResponse
    {
        try {
            $user = $this->repository->updateStatus($id, $status);
            if (!$user) {
                return $this->notFoundResponse();
            }
            return $this->successResponse(null, $user);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function activateUser(string $id): JsonResponse
    {
        try {
            $user = $this->repository->activateUser($id);
            if (!$user) {
                return $this->notFoundResponse();
            }
            return $this->successResponse(null, $user);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function deactivateUser(string $id): JsonResponse
    {
        try {
            $user = $this->repository->deactivateUser($id);
            if (!$user) {
                return $this->notFoundResponse();
            }
            return $this->successResponse(null, $user);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updatePassword(string $id, string $newPassword): JsonResponse
    {
        try {
            $user = $this->repository->updatePassword($id, $newPassword);
            if (!$user) {
                return $this->notFoundResponse();
            }
            return $this->successResponse(null, $user);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
