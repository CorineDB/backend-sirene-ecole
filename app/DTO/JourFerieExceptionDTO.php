<?php

namespace App\DTO;

use App\Contracts\JourFerieExceptionInterface;
use Carbon\Carbon;

/**
 * Data Transfer Object pour les exceptions de jours fériés
 *
 * Fournit une validation stricte et des méthodes helper pour gérer
 * les exceptions de jours fériés dans les programmations.
 */
class JourFerieExceptionDTO implements JourFerieExceptionInterface
{
    public string $date;
    public string $action;
    public ?bool $est_national;
    public ?bool $recurrent;

    /**
     * @param array $data
     * @throws \InvalidArgumentException Si les données ne respectent pas le schéma
     */
    public function __construct(array $data)
    {
        $this->validate($data);

        $this->date = $data['date'];
        $this->action = $data['action'];
        $this->est_national = $data['est_national'] ?? null;
        $this->recurrent = $data['recurrent'] ?? null;
    }

    /**
     * Valide les données selon le schéma strict
     *
     * @param array $data
     * @throws \InvalidArgumentException
     */
    private function validate(array $data): void
    {
        // Vérifier que les champs requis sont présents
        if (!isset($data['date'])) {
            throw new \InvalidArgumentException('Le champ "date" est obligatoire pour une exception de jour férié.');
        }

        if (!isset($data['action'])) {
            throw new \InvalidArgumentException('Le champ "action" est obligatoire pour une exception de jour férié.');
        }

        // Valider le format de la date
        if (!is_string($data['date'])) {
            throw new \InvalidArgumentException('La date doit être une chaîne de caractères.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
            throw new \InvalidArgumentException('La date doit être au format YYYY-MM-DD (ex: 2025-12-25).');
        }

        // Valider que la date est valide (pas de 2025-02-30 par exemple)
        try {
            Carbon::createFromFormat('Y-m-d', $data['date']);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("La date '{$data['date']}' n'est pas une date valide.");
        }

        // Valider l'action
        if (!is_string($data['action'])) {
            throw new \InvalidArgumentException('L\'action doit être une chaîne de caractères.');
        }

        if (!in_array($data['action'], ['include', 'exclude'], true)) {
            throw new \InvalidArgumentException('L\'action doit être "include" ou "exclude".');
        }

        // Valider est_national (optionnel)
        if (isset($data['est_national']) && !is_bool($data['est_national'])) {
            throw new \InvalidArgumentException('Le champ "est_national" doit être un booléen (true ou false).');
        }

        // Valider recurrent (optionnel)
        if (isset($data['recurrent']) && !is_bool($data['recurrent'])) {
            throw new \InvalidArgumentException('Le champ "recurrent" doit être un booléen (true ou false).');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDate(): string
    {
        return $this->date;
    }

    /**
     * {@inheritdoc}
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * {@inheritdoc}
     */
    public function isInclude(): bool
    {
        return $this->action === 'include';
    }

    /**
     * {@inheritdoc}
     */
    public function isExclude(): bool
    {
        return $this->action === 'exclude';
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'action' => $this->action,
            'est_national' => $this->est_national,
            'recurrent' => $this->recurrent,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(): bool
    {
        try {
            $this->validate($this->toArray());
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSignature(): string
    {
        return "{$this->date}:{$this->action}";
    }

    /**
     * Obtenir la date au format Carbon
     *
     * @return Carbon
     */
    public function getCarbonDate(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->date);
    }

    /**
     * Obtenir la date formatée en français
     *
     * @param string $format Format Carbon (ex: 'd/m/Y', 'l d F Y')
     * @return string
     */
    public function getFormattedDate(string $format = 'd/m/Y'): string
    {
        return $this->getCarbonDate()->locale('fr')->translatedFormat($format);
    }

    /**
     * Obtenir l'action en français
     *
     * @return string "Inclure" ou "Exclure"
     */
    public function getActionLabel(): string
    {
        return $this->action === 'include' ? 'Inclure' : 'Exclure';
    }

    /**
     * Vérifier si la date est dans le futur
     *
     * @return bool
     */
    public function isFuture(): bool
    {
        return $this->getCarbonDate()->isFuture();
    }

    /**
     * Vérifier si la date est dans le passé
     *
     * @return bool
     */
    public function isPast(): bool
    {
        return $this->getCarbonDate()->isPast();
    }

    /**
     * Vérifier si la date est aujourd'hui
     *
     * @return bool
     */
    public function isToday(): bool
    {
        return $this->getCarbonDate()->isToday();
    }

    /**
     * Vérifier si cette exception s'applique à une date donnée
     *
     * @param string|Carbon $date
     * @return bool
     */
    public function appliesTo($date): bool
    {
        $compareDate = $date instanceof Carbon ? $date->format('Y-m-d') : $date;
        return $this->date === $compareDate;
    }

    /**
     * Obtenir une description complète de l'exception
     *
     * @return string
     */
    public function getDescription(): string
    {
        $dateFormatted = $this->getFormattedDate('l d F Y');
        $action = $this->getActionLabel();

        $description = "{$action} le {$dateFormatted}";

        // Ajouter des détails supplémentaires si disponibles
        $details = [];
        if ($this->est_national === true) {
            $details[] = 'jour férié national';
        } elseif ($this->est_national === false) {
            $details[] = 'jour férié local';
        }

        if ($this->recurrent === true) {
            $details[] = 'récurrent';
        } elseif ($this->recurrent === false) {
            $details[] = 'exceptionnel';
        }

        if (!empty($details)) {
            $description .= ' (' . implode(', ', $details) . ')';
        }

        return $description;
    }

    /**
     * Vérifier si c'est un jour férié national
     *
     * @return bool|null null si non spécifié
     */
    public function getEstNational(): ?bool
    {
        return $this->est_national;
    }

    /**
     * Vérifier si c'est un jour férié récurrent
     *
     * @return bool|null null si non spécifié
     */
    public function getRecurrent(): ?bool
    {
        return $this->recurrent;
    }

    /**
     * Vérifier si c'est un jour férié national
     *
     * @return bool
     */
    public function isNational(): bool
    {
        return $this->est_national === true;
    }

    /**
     * Vérifier si c'est un jour férié local/régional
     *
     * @return bool
     */
    public function isLocal(): bool
    {
        return $this->est_national === false;
    }

    /**
     * Vérifier si c'est un jour férié récurrent (annuel)
     *
     * @return bool
     */
    public function isRecurrent(): bool
    {
        return $this->recurrent === true;
    }

    /**
     * Vérifier si c'est un jour férié exceptionnel (non récurrent)
     *
     * @return bool
     */
    public function isExceptionnel(): bool
    {
        return $this->recurrent === false;
    }
}
