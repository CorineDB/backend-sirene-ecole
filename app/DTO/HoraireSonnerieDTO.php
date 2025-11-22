<?php

namespace App\DTO;

use App\Contracts\HoraireSonnerieInterface;

/**
 * Data Transfer Object pour un horaire de sonnerie
 * Schéma strict à respecter pour chaque horaire
 *
 * @property int $heure Heure de la sonnerie (0-23)
 * @property int $minute Minute de la sonnerie (0-59)
 * @property array $jours Jours de la semaine (0=Dimanche...6=Samedi)
 * @property int|null $duree_sonnerie Durée en secondes (1-30, défaut: 3)
 * @property string|null $description Description de l'horaire
 */
class HoraireSonnerieDTO implements HoraireSonnerieInterface
{
    /**
     * Heure de la sonnerie (format 24h)
     * @var int 0-23
     */
    public int $heure;

    /**
     * Minute de la sonnerie
     * @var int 0-59
     */
    public int $minute;

    /**
     * Jours de la semaine où la sonnerie sonne
     * @var array<int> Tableau de 0 (Dimanche) à 6 (Samedi)
     */
    public array $jours;

    /**
     * Durée de la sonnerie en secondes
     * @var int|null 1-30 secondes (défaut: 3)
     */
    public ?int $duree_sonnerie;

    /**
     * Description de l'horaire
     * @var string|null Max 255 caractères
     */
    public ?string $description;

    /**
     * Constructeur avec validation stricte
     *
     * @param array $data Données brutes de l'horaire
     * @throws \InvalidArgumentException Si les données ne respectent pas le schéma
     */
    public function __construct(array $data)
    {
        $this->validate($data);

        $this->heure = $data['heure'];
        $this->minute = $data['minute'];
        $this->jours = $data['jours'];
        $this->duree_sonnerie = $data['duree_sonnerie'] ?? 3;
        $this->description = $data['description'] ?? null;
    }

    /**
     * Valider les données selon le schéma strict
     *
     * @param array $data
     * @throws \InvalidArgumentException
     */
    private function validate(array $data): void
    {
        // 1. Vérifier la présence des champs requis
        $required = ['heure', 'minute', 'jours'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Le champ '{$field}' est obligatoire.");
            }
        }

        // 2. Valider heure (0-23)
        if (!is_int($data['heure']) || $data['heure'] < 0 || $data['heure'] > 23) {
            throw new \InvalidArgumentException("L'heure doit être un entier entre 0 et 23.");
        }

        // 3. Valider minute (0-59)
        if (!is_int($data['minute']) || $data['minute'] < 0 || $data['minute'] > 59) {
            throw new \InvalidArgumentException("La minute doit être un entier entre 0 et 59.");
        }

        // 4. Valider jours (tableau non vide de 0-6)
        if (!is_array($data['jours']) || empty($data['jours'])) {
            throw new \InvalidArgumentException("Les jours doivent être un tableau non vide.");
        }

        foreach ($data['jours'] as $jour) {
            if (!is_int($jour) || $jour < 0 || $jour > 6) {
                throw new \InvalidArgumentException("Chaque jour doit être un entier entre 0 (Dimanche) et 6 (Samedi).");
            }
        }

        // Vérifier les doublons dans les jours
        if (count($data['jours']) !== count(array_unique($data['jours']))) {
            throw new \InvalidArgumentException("Les jours ne doivent pas contenir de doublons.");
        }

        // 5. Valider duree_sonnerie (optionnel, 1-30)
        if (isset($data['duree_sonnerie'])) {
            if (!is_int($data['duree_sonnerie']) || $data['duree_sonnerie'] < 1 || $data['duree_sonnerie'] > 30) {
                throw new \InvalidArgumentException("La durée de sonnerie doit être un entier entre 1 et 30 secondes.");
            }
        }

        // 6. Valider description (optionnel, max 255)
        if (isset($data['description'])) {
            if (!is_string($data['description'])) {
                throw new \InvalidArgumentException("La description doit être une chaîne de caractères.");
            }
            if (strlen($data['description']) > 255) {
                throw new \InvalidArgumentException("La description ne peut pas dépasser 255 caractères.");
            }
        }
    }

    /**
     * Convertir en tableau
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'heure' => $this->heure,
            'minute' => $this->minute,
            'jours' => $this->jours,
            'duree_sonnerie' => $this->duree_sonnerie,
            'description' => $this->description,
        ];
    }

    /**
     * Créer depuis un tableau avec validation
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Convertir en format horaire lisible (HH:MM)
     *
     * @return string
     */
    public function toTimeString(): string
    {
        return sprintf('%02d:%02d', $this->heure, $this->minute);
    }

    /**
     * Obtenir les noms des jours en français
     *
     * @return array
     */
    public function getJoursNoms(): array
    {
        $noms = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        return array_map(fn($j) => $noms[$j] ?? '?', $this->jours);
    }

    /**
     * Convertir en représentation JSON
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Vérifier si la sonnerie sonne un jour donné
     *
     * @param int $jour 0 (Dimanche) à 6 (Samedi)
     * @return bool
     */
    public function sonneLeJour(int $jour): bool
    {
        return in_array($jour, $this->jours);
    }

    /**
     * Obtenir la signature unique de l'horaire (pour détecter les doublons)
     *
     * @return string
     */
    public function getSignature(): string
    {
        return sprintf(
            '%02d:%02d:%s',
            $this->heure,
            $this->minute,
            implode(',', $this->jours)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getHeure(): int
    {
        return $this->heure;
    }

    /**
     * {@inheritdoc}
     */
    public function getMinute(): int
    {
        return $this->minute;
    }

    /**
     * {@inheritdoc}
     */
    public function getJours(): array
    {
        return $this->jours;
    }

    /**
     * {@inheritdoc}
     */
    public function getDureeSonnerie(): int
    {
        return $this->duree_sonnerie ?? 3;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): ?string
    {
        return $this->description;
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
     * Obtenir l'heure formatée (alias de toTimeString pour cohérence avec JourFerieExceptionDTO)
     *
     * @param string $format Format d'affichage (par défaut: "H:i")
     * @return string
     */
    public function getFormattedTime(string $format = 'H:i'): string
    {
        // Pour compatibilité, on retourne toujours au format HH:MM
        return $this->toTimeString();
    }
}
