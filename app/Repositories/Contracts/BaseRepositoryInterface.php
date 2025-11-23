<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface BaseRepositoryInterface
{
    /**
     * Récupérer tous les enregistrements
     */
    public function all(array $columns = ['*'], array $relations = []): Collection;

    /**
     * Récupérer un enregistrement par ID
     */
    public function find(string $id, array $columns = ['*'], array $relations = []): ?Model;

    /**
     * Récupérer un enregistrement par critère
     */
    public function findBy(array $criteria, array $columns = ['*'], array $relations = []): ?Model;

    /**
     * Récupérer plusieurs enregistrements par critère
     */
    public function findAllBy(array $criteria, array $columns = ['*'], array $relations = []): Collection;

    /**
     * Récupérer un enregistrement en filtrant par une relation
     */
    public function findByRelation(string $relation, string $column, $value, array $columns = ['*'], array $relations = []): ?Model;

    /**
     * Créer un nouvel enregistrement
     */
    public function create(array $data): Model;

    /**
     * Mettre à jour un enregistrement
     */
    public function update(string $id, array $data): bool;

    /**
     * Supprimer partiellement un enregistrement
     */
    public function delete(string $id): bool;

    /**
     * Supprimer definitivement un enregistrement
     */
    public function forceDelete(string $id): bool;

    /**
     * Restaurer un enregistrement supprimé
     */
    public function restore(string $id): bool;

    /**
     * Paginer les résultats
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], array $relations = []): LengthAwarePaginator;

    /**
     * Vérifier si un enregistrement existe
     */
    public function exists(array $criteria): bool;

    /**
     * Compter les enregistrements
     */
    public function count(array $criteria = []): int;

    /**
     * Récupérer le modèle
     */
    public function getModel(): Model;

    /**
     * Définir le modèle
     */
    public function setModel(Model $model): self;

    /**
     * Récupérer une instance du query builder
     */
    public function query();
}
