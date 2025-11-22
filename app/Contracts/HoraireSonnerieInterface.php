<?php

namespace App\Contracts;

/**
 * Interface stricte pour les horaires de sonnerie
 * Contrat d'implémentation garantissant la cohérence des données
 */
interface HoraireSonnerieInterface
{
    /**
     * Obtenir l'heure de la sonnerie
     *
     * @return int Heure au format 24h (0-23)
     */
    public function getHeure(): int;

    /**
     * Obtenir la minute de la sonnerie
     *
     * @return int Minute (0-59)
     */
    public function getMinute(): int;

    /**
     * Obtenir les jours de la semaine
     *
     * @return array<int> Tableau d'entiers (0=Dimanche...6=Samedi)
     */
    public function getJours(): array;

    /**
     * Obtenir la durée de la sonnerie
     *
     * @return int Durée en secondes (1-30)
     */
    public function getDureeSonnerie(): int;

    /**
     * Obtenir la description de l'horaire
     *
     * @return string|null Description (max 255 caractères)
     */
    public function getDescription(): ?string;

    /**
     * Convertir en tableau associatif
     *
     * @return array Format: ['heure' => int, 'minute' => int, 'jours' => array, ...]
     */
    public function toArray(): array;

    /**
     * Vérifier si l'horaire est valide selon le schéma strict
     *
     * @return bool
     */
    public function isValid(): bool;

    /**
     * Obtenir la signature unique de l'horaire
     * Utilisé pour détecter les doublons
     *
     * @return string Format: "HH:MM:J1,J2,J3..."
     */
    public function getSignature(): string;
}
