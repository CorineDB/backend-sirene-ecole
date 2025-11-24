<?php

namespace App\Enums;

enum StatutAbonnement: string
{
    case ACTIF = 'actif';
    case EXPIRE = 'expire';
    case ANNULE = 'annule';
    case SUSPENDU = 'suspendu';
    case EN_ATTENTE = 'en_attente';

    public function label(): string
    {
        return match($this) {
            self::ACTIF => 'Actif',
            self::EXPIRE => 'Expiré',
            self::ANNULE => 'Annulé',
            self::SUSPENDU => 'Suspendu',
            self::EN_ATTENTE => 'En attente',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}