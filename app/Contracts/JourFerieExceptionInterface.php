<?php

namespace App\Contracts;

/**
 * Interface contractuelle pour les exceptions de jours fériés
 *
 * Définit le contrat que toute implémentation d'exception de jour férié doit respecter.
 * Cette interface garantit la cohérence et la compatibilité entre les différentes
 * implémentations (DTO, modèles, etc.).
 */
interface JourFerieExceptionInterface
{
    /**
     * Obtenir la date de l'exception
     *
     * @return string Format YYYY-MM-DD (ex: "2025-12-25")
     */
    public function getDate(): string;

    /**
     * Obtenir l'action de l'exception
     *
     * @return string "include" ou "exclude"
     */
    public function getAction(): string;

    /**
     * Vérifier si l'action est "include"
     *
     * @return bool True si l'action est "include", false sinon
     */
    public function isInclude(): bool;

    /**
     * Vérifier si l'action est "exclude"
     *
     * @return bool True si l'action est "exclude", false sinon
     */
    public function isExclude(): bool;

    /**
     * Convertir en tableau associatif
     *
     * @return array ['date' => string, 'action' => string]
     */
    public function toArray(): array;

    /**
     * Valider que l'exception respecte le schéma
     *
     * @return bool True si valide, false sinon
     */
    public function isValid(): bool;

    /**
     * Générer une signature unique pour cette exception
     *
     * Format: "YYYY-MM-DD:action"
     * Exemple: "2025-12-25:include"
     *
     * @return string La signature unique
     */
    public function getSignature(): string;
}
