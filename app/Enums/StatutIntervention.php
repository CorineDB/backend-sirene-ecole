<?php

namespace App\Enums;

enum StatutIntervention: string
{
    case PLANIFIEE = 'planifiee';
    case ASSIGNEE = 'assignee';
    case ACCEPTEE = 'acceptee';
    case EN_COURS = 'en_cours';
    case TERMINÉE = 'terminee';
    case ANNULEE = 'annulee';

    public function label(): string
    {
        return match($this) {
            self::PLANIFIEE => 'Planifiée',
            self::ASSIGNEE => 'Assignée',
            self::ACCEPTEE => 'Acceptée',
            self::EN_COURS => 'En cours',
            self::TERMINÉE => 'Terminée',
            self::ANNULEE => 'Annulée',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}