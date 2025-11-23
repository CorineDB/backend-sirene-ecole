<?php

namespace App\Services;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\Contracts\RoleServiceInterface;
use App\Traits\FiltersByEcole;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class RoleService extends BaseService implements RoleServiceInterface
{
    use FiltersByEcole;

    protected RoleRepositoryInterface $roleRepository;

    public function __construct(RoleRepositoryInterface $roleRepository)
    {
        parent::__construct($roleRepository);
        $this->roleRepository = $roleRepository;
    }

    /**
     * Override create pour auto-fill roleable_id et roleable_type si école
     */
    public function create(array $data): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Si l'utilisateur connecté est une école, remplir automatiquement roleable_id et roleable_type
            if ($this->isEcoleUser()) {
                $user = auth()->user();
                $data['roleable_type'] = $user->user_account_type_type;
                $data['roleable_id'] = $user->user_account_type_id;
            }

            $model = $this->repository->create($data);
            DB::commit();
            return $this->createdResponse($model);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::create - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Override getAll to support filtering by profilable and exclude defaults
     * profilableType is automatically retrieved from auth()->user()->user_account_type_type
     *
     * @param int $perPage
     * @param array $relations
     * @param string|null $profilableId
     * @param bool $excludeDefaults
     * @return JsonResponse
     */
    public function getAll(
        int $perPage = 15,
        array $relations = [],
        ?string $profilableId = null,
        bool $excludeDefaults = true
    ): JsonResponse {
        try {
            $data = $this->roleRepository->paginate(
                $perPage,
                ['*'],
                $relations,
                $profilableId,
                $excludeDefaults
            );
            return $this->successResponse(null, $data);
        } catch (Exception $e) {
            Log::error("Error in " . get_class($this) . "::getAll - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function findBySlug(string $slug): JsonResponse
    {
        try {
            $role = $this->repository->findBySlug($slug);
            if (!$role) {
                return $this->notFoundResponse();
            }
            return $this->successResponse(null, $role);
        } catch (\Exception $e) {
            \Log::error("Error in " . get_class($this) . "::findBySlug - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getRolesByRoleable(string $roleableId, string $roleableType): JsonResponse
    {
        try {
            $roles = $this->repository->getRolesByRoleable($roleableId, $roleableType);
            return $this->successResponse(null, $roles);
        } catch (\Exception $e) {
            \Log::error("Error in " . get_class($this) . "::getRolesByRoleable - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createRole(array $data): JsonResponse
    {
        try {
            DB::beginTransaction();
            $permissionIds = $data['permission_ids'] ?? [];
            unset($data['permission_ids']); // Remove from data before creating role

            $role = $this->roleRepository->createWithPermissions($data, $permissionIds);
            DB::commit();
            return $this->createdResponse($role);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::createRole - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateRole(string $id, array $data): JsonResponse
    {
        try {
            DB::beginTransaction();
            $role = $this->roleRepository->find($id);
            if (!$role) {
                DB::rollBack();
                return $this->notFoundResponse();
            }

            $permissionIds = $data['permission_ids'] ?? [];
            unset($data['permission_ids']); // Remove from data before updating role

            $this->roleRepository->update($id, $data);
            if (!empty($permissionIds)) {
                $this->roleRepository->syncPermissions($id, $permissionIds);
            }
            DB::commit();
            return $this->successResponse(null, $this->roleRepository->find($id));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::updateRole - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function assignPermissionsToRole(string $roleId, array $permissionIds): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->roleRepository->attachPermissions($roleId, $permissionIds);
            DB::commit();
            return $this->successResponse('Permissions assigned successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::assignPermissionsToRole - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function syncPermissionsToRole(string $roleId, array $permissionIds): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->roleRepository->syncPermissions($roleId, $permissionIds);
            DB::commit();
            return $this->successResponse('Permissions synced successfully.');
        }
        catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::syncPermissionsToRole - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function removePermissionsFromRole(string $roleId, array $permissionIds): JsonResponse
    {
        try {
            DB::beginTransaction();
            $this->roleRepository->detachPermissions($roleId, $permissionIds);
            DB::commit();
            return $this->successResponse('Permissions removed successfully.');
        }
        catch (Exception $e) {
            DB::rollBack();
            Log::error("Error in " . get_class($this) . "::removePermissionsFromRole - " . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}

