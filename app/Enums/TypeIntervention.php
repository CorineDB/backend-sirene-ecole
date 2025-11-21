<?php

namespace App\Enums;

enum TypeIntervention: string
{
    case INSPECTION = 'inspection';
    case CONSTAT = 'constat';
    case REPARATION = 'reparation';
    case INSTALLATION = 'installation';
    case MAINTENANCE = 'maintenance';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match($this) {
            self::INSPECTION => 'Inspection sur site',
            self::CONSTAT => 'Constat de panne',
            self::REPARATION => 'Réparation',
            self::INSTALLATION => 'Installation',
            self::MAINTENANCE => 'Maintenance',
            self::AUTRE => 'Autre',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
