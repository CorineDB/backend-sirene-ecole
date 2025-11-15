<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\Contracts\UserServiceInterface;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller implements HasMiddleware
{
    protected UserServiceInterface $userService;

    public function __construct(UserServiceInterface $userService)
    {
        $this->userService = $userService;
    }

    public static function middleware(): array
    {
        return [
            // Les middlewares sont déjà appliqués dans les routes
            // On les laisse commentés ici pour éviter la duplication
            /*new Middleware('can:voir_les_utilisateurs', only: ['index']),
            new Middleware('can:voir_utilisateur', only: ['show']),
            new Middleware('can:creer_utilisateur', only: ['store']),
            new Middleware('can:modifier_utilisateur', only: ['update']),
            new Middleware('can:supprimer_utilisateur', only: ['destroy']),*/
        ];
    }

    public function index()
    {
        Gate::authorize('voir_les_utilisateurs');
        return $this->userService->getAll(15, relations: ["userInfo"]);
    }

    public function show(string $id)
    {
        Gate::authorize('voir_utilisateur');
        return $this->userService->getById($id, relations:["userInfo"]);
    }

    public function store(StoreUserRequest $request)
    {
        Gate::authorize('creer_utilisateur');
        return $this->userService->create($request->validated());
    }

    public function update(UpdateUserRequest $request, string $id)
    {
        Gate::authorize('modifier_utilisateur');
        return $this->userService->update($id, $request->validated());
    }

    public function destroy(string $id)
    {
        Gate::authorize('supprimer_utilisateur');
        return $this->userService->delete($id);
    }
}
