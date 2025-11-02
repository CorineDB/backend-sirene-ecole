<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Role\AssignPermissionsRequest;
use App\Http\Requests\API\Role\CreateRoleRequest;
use App\Http\Requests\API\Role\RemovePermissionsRequest;
use App\Http\Requests\API\Role\SyncPermissionsRequest;
use App\Http\Requests\API\Role\UpdateRoleRequest;
use App\Services\Contracts\RoleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Info(
 *     title="Sirene Automatique API Documentation",
 *     version="1.0.0",
 *     description="API Documentation for the Sirene Automatique project"
 * )
 *
 * Class RoleController
 * @package App\Http\Controllers\API
 * @OA\Tag(
 *     name="Roles",
 *     description="API Endpoints of Roles"
 * )
 * @OA\Schema(
 *     schema="Role",
 *     title="Role",
 *     description="Role model",
 *     @OA\Property(
 *         property="id",
 *         type="string",
 *         format="uuid",
 *         description="ID of the permission"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Name of the permission"
 *     )
 * )
 * Controller for managing Roles.
 */
class RoleController extends Controller
{
    protected RoleServiceInterface $roleService;

    public function __construct(RoleServiceInterface $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * @OA\Get(
     *     path="/api/roles",
     *     tags={"Roles"},
     *     summary="Display a listing of roles",
     *     operationId="getRoles",
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of roles per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Role"))
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        return $this->roleService->getAll(15, ['permissions']);
    }

    /**
     * @OA\Post(
     *     path="/api/roles",
     *     tags={"Roles"},
     *     summary="Store a newly created role in storage",
     *     operationId="storeRole",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateRoleRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Role created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Role")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"name": {"The name field is required."}})
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function store(CreateRoleRequest $request): JsonResponse
    {
        return $this->roleService->createRole($request->validated());
    }

    /**
     * @OA\Get(
     *     path="/api/roles/{id}",
     *     tags={"Roles"},
     *     summary="Display the specified role",
     *     operationId="getRoleById",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the role to retrieve",
     *         required=true,
     *         @OA\Schema(type="string", format="ulid", example="01ARZ3NDEKTSV4WS06X8Q1J8Q1")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Role")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Role not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Role not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        return $this->roleService->getById($id, ['permissions']);
    }

    /**
     * @OA\Put(
     *     path="/api/roles/{id}",
     *     tags={"Roles"},
     *     summary="Update the specified role in storage",
     *     operationId="updateRole",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the role to update",
     *         required=true,
     *         @OA\Schema(type="string", format="ulid", example="01ARZ3NDEKTSV4WS06X8Q1J8Q1")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateRoleRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Role")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Role not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Role not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"name": {"The name field is required."}})
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function update(UpdateRoleRequest $request, string $id): JsonResponse
    {
        return $this->roleService->updateRole($id, $request->validated());
    }

    /**
     * @OA\Delete(
     *     path="/api/roles/{id}",
     *     tags={"Roles"},
     *     summary="Remove the specified role from storage",
     *     operationId="deleteRole",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the role to delete",
     *         required=true,
     *         @OA\Schema(type="string", format="ulid", example="01ARZ3NDEKTSV4WS06X8Q1J8Q1")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Role deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Role not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Role not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        return $this->roleService->delete($id);
    }

    /**
     * @OA\Post(
     *     path="/api/roles/{roleId}/permissions/assign",
     *     tags={"Roles"},
     *     summary="Assign permissions to a role",
     *     operationId="assignPermissionsToRole",
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         description="ID of the role to assign permissions to",
     *         required=true,
     *         @OA\Schema(type="string", format="ulid", example="01ARZ3NDEKTSV4WS06X8Q1J8Q1")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/AssignPermissionsRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions assigned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Permissions assigned successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Role not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Role not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"permission_ids": {"The permission ids field is required."}})
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function assignPermissions(AssignPermissionsRequest $request, string $roleId): JsonResponse
    {
        return $this->roleService->assignPermissionsToRole($roleId, $request->input('permission_ids'));
    }

    /**
     * @OA\Post(
     *     path="/api/roles/{roleId}/permissions/sync",
     *     tags={"Roles"},
     *     summary="Sync permissions for a role",
     *     operationId="syncPermissionsForRole",
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         description="ID of the role to sync permissions for",
     *         required=true,
     *         @OA\Schema(type="string", format="ulid", example="01ARZ3NDEKTSV4WS06X8Q1J8Q1")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/SyncPermissionsRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions synced successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Permissions synced successfully.")
     *         )
     *     ),
     *     @OA
     *         response=404,
     *         description="Role not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Role not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"permission_ids": {"The permission ids field is required."}})
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function syncPermissions(SyncPermissionsRequest $request, string $roleId): JsonResponse
    {
        return $this->roleService->syncPermissionsToRole($roleId, $request->input('permission_ids'));
    }

    /**
     * @OA\Post(
     *     path="/api/roles/{roleId}/permissions/remove",
     *     tags={"Roles"},
     *     summary="Remove permissions from a role",
     *     operationId="removePermissionsFromRole",
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         description="ID of the role to remove permissions from",
     *         required=true,
     *         @OA\Schema(type="string", format="ulid", example="01ARZ3NDEKTSV4WS06X8Q1J8Q1")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/RemovePermissionsRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Permissions removed successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Role not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Role not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"permission_ids": {"The permission ids field is required."}})
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function removePermissions(RemovePermissionsRequest $request, string $roleId): JsonResponse
    {
        return $this->roleService->removePermissionsFromRole($roleId, $request->input('permission_ids'));
    }
}
