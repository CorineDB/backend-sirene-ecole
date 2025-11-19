<?php

namespace App\Repositories;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseRepository implements BaseRepositoryInterface
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function all(array $columns = ['*'], array $relations = []): Collection
    {
        return $this->model->with($relations)->orderBy("created_at", "desc")->get($columns);
    }

    public function find(string $id, array $columns = ['*'], array $relations = []): ?Model
    {
        return $this->model->with($relations)->findOrFail($id, $columns);
    }

    public function findBy(array $criteria, array $columns = ['*'], array $relations = []): ?Model
    {
        return $this->model->with($relations)->where($criteria)->first($columns);
    }

    public function findAllBy(array $criteria, array $columns = ['*'], array $relations = []): Collection
    {
        return $this->model->with($relations)->where($criteria)->get($columns);
    }

    public function findByRelation(string $relation, string $column, $value, array $columns = ['*'], array $relations = []): ?Model
    {
        return $this->model->with($relations)->whereHas($relation, function ($query) use ($column, $value) {
            $query->where($column, $value);
        })->first($columns);
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(string $id, array $data): bool
    {
        $record = $this->find($id);

        if (!$record) {
            return false;
        }

        return $record->update($data);
    }


    /**
     * Supprimer partiellement un enregistrement
     */
    public function delete(string $id): bool
    {
        $record = $this->find($id);

        if (!$record) {
            return false;
        }

        return $record->delete();
    }


    /**
     * Restaurer un enregistrement supprimé partiellement
     */
    public function restore(string $id): bool
    {
        $record = $this->find($id);

        if (!$record) {
            return false;
        }

        return $record->restore();
    }

    /**
     * Supprimer definitivement un enregistrement
     */
    public function forceDelete(string $id): bool
    {
        $record = $this->find($id);

        if (!$record) {
            return false;
        }

        return $record->forceDelete();
    }

    /**
     * Paginer les résultats
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], array $relations = []): LengthAwarePaginator
    {
        return $this->model/* ->withTrashed() */->with($relations)->orderBy("created_at", "desc")->paginate($perPage);
    }

    /**
     * Vérifier si un enregistrement existe
     */
    public function exists(array $criteria): bool
    {
        return $this->model->where($criteria)->exists();
    }

    /**
     * Compter les enregistrements
     */
    public function count(array $criteria = []): int
    {
        if (empty($criteria)) {
            return $this->model->count();
        }

        return $this->model->where($criteria)->count();
    }

    /**
     * Récupérer le modèle
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Définir le modèle
     */
    public function setModel(Model $model): self
    {
        $this->model = $model;
        return $this;
    }
}
