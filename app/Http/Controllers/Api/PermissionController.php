<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\PermissionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

/**
 *
 * Class PermissionController
 * @package App\Http\Controllers\API
 * @OA\Tag(
 *     name="Permissions",
 *     description="API Endpoints of Permissions"
 * )
 * @OA\Schema(
 *     schema="Permission",
 *     title="Permission",
 *     description="Permission model",
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
 * Controller for managing permissions.
 */
class PermissionController extends Controller implements HasMiddleware
{
    protected PermissionServiceInterface $permissionService;

    public function __construct(PermissionServiceInterface $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
            new Middleware('can:voir_les_permissions', only: ['index', 'showByRole']),
            new Middleware('can:voir_permission', only: ['show', 'showBySlug']),
        ];
    }

    /**
     * Display a listing of the permissions.
     * @OA\Get(
     *     path="/api/permissions",
     *     tags={"Permissions"},
     *     summary="Get list of permissions",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array",
     *             @OA\Items(ref="#/components/schemas/Permission"))
     *     )
     * )
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('voir_les_permissions');
        $perPage = $request->get('per_page', 15);
        return $this->permissionService->getAll($perPage);
    }

    /**
     * Display the specified permission.
     * @OA\Get(
     *     path="/api/permissions/{id}",
     *     tags={"Permissions"},
     *     summary="Get a specific permission by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the permission to retrieve",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Permission")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Permission not found"
     *     )
     * )
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        Gate::authorize('voir_permission');
        return $this->permissionService->getById($id);
    }

    /**
     * Display the specified permission by slug.
     * @OA\Get(
     *     path="/api/permissions/slug/{slug}",
     *     tags={"Permissions"},
     *     summary="Get a specific permission by slug",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Slug of the permission to retrieve",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Permission")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Permission not found"
     *     )
     * )
     * @param string $slug
     * @return JsonResponse
     */
    public function showBySlug(string $slug): JsonResponse
    {
        Gate::authorize('voir_permission');
        return $this->permissionService->findBySlug($slug);
    }

    /**
     * Display permissions by role ID.
     * @OA\Get(
     *     path="/api/roles/{roleId}/permissions",
     *     tags={"Permissions"},
     *     summary="Get permissions by role ID",
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         required=true,
     *         description="ID of the role to retrieve permissions for",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array",
     *             @OA\Items(ref="#/components/schemas/Permission"))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Role not found"
     *     )
     * )
     * @param string $roleId
     * @return JsonResponse
     */
    public function showByRole(string $roleId): JsonResponse
    {
        Gate::authorize('voir_les_permissions');
        return $this->permissionService->getByRole($roleId);
    }
}
