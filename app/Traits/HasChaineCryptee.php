<?php

namespace App\Traits;

use Carbon\Carbon;

trait HasChaineCryptee
{
    use HasCryptageESP8266;

    /**
     * Version du protocole de programmation ESP8266
     */
    protected int $PROGRAMMATION_VERSION = 1;

    /**
     * Durée de la sonnerie en secondes (par défaut: 3 secondes)
     */
    protected int $DUREE_SONNERIE = 3;

    /**
     * Générer la chaîne programmée (format lisible pour affichage)
     * Format nouveau: affiche les horaires avec leurs jours spécifiques
     *
     * @return string
     */
    public function genererChaineProgrammee(): string
    {
        $horaires = collect($this->horaires_sonneries)
            ->map(function ($h) {
                $heure = sprintf('%02d:%02d', $h['heure'], $h['minute']);
                $jours = collect($h['jours'])->map(function ($j) {
                    return ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'][$j] ?? '?';
                })->join(',');
                return "{$heure} ({$jours})";
            })
            ->join(' | ');

        return sprintf(
            "Programmation: %s | Horaires: %s | Période: %s au %s",
            $this->nom_programmation,
            $horaires,
            $this->date_debut->format('d/m/Y'),
            $this->date_fin->format('d/m/Y')
        );
    }

    /**
     * Générer la chaîne cryptée pour le module ESP8266
     * Format Python: VERSION|NB_HORAIRES|H:M:MASK:DUREE|...|NB_CONGES|DEBUT:FIN|...|NB_FERIES|Y:M:D|...|CHECKSUM
     *
     * @return string
     */
    public function genererChaineCryptee(): string
    {
        $parts = [];

        // 1. Version (suppression de la durée globale, maintenant par horaire)
        $parts[] = $this->PROGRAMMATION_VERSION;

        // 2. Horaires de sonnerie (avec durée individuelle)
        $horaires = $this->horaires_sonneries ?? [];
        $parts[] = count($horaires);

        foreach ($horaires as $horaire) {
            $heure = $horaire['heure'] ?? 0;
            $minute = $horaire['minute'] ?? 0;
            $jours = $horaire['jours'] ?? [];
            $duree = $horaire['duree_sonnerie'] ?? $this->DUREE_SONNERIE; // Fallback sur valeur par défaut

            // Convertir le tableau de jours en masque binaire
            $mask = $this->joursVersIntMasque($jours);

            $parts[] = "{$heure}:{$minute}:{$mask}:{$duree}";
        }

        // 3. Périodes de congés (vacances)
        $conges = $this->extraireConges();
        $parts[] = count($conges);

        foreach ($conges as $conge) {
            $parts[] = "{$conge['debut']}:{$conge['fin']}";
        }

        // 4. Jours fériés spécifiques
        $feries = $this->extraireJoursFeries();
        $parts[] = count($feries);

        foreach ($feries as $ferie) {
            $parts[] = "{$ferie['annee']}:{$ferie['mois']}:{$ferie['jour']}";
        }

        // 5. Assembler la chaîne et crypter avec checksum
        $data_str = implode('|', $parts);

        return $this->crypterDonneesESP8266($data_str, true, 8);
    }

    /**
     * Extraire les périodes de congés (vacances) à partir du calendrier ou des données
     *
     * @return array Tableau de congés avec format ['debut' => timestamp, 'fin' => timestamp]
     */
    protected function extraireConges(): array
    {
        $conges = [];

        // 1. Si un calendrier scolaire est associé, utiliser ses vacances
        if ($this->calendrier_id && $this->calendrier) {
            $vacances = $this->calendrier->vacances ?? [];

            foreach ($vacances as $vacance) {
                if (isset($vacance['date_debut']) && isset($vacance['date_fin'])) {
                    $conges[] = [
                        'debut' => Carbon::parse($vacance['date_debut'])->timestamp,
                        'fin' => Carbon::parse($vacance['date_fin'])->timestamp,
                    ];
                }
            }
        }

        // 2. Si le champ vacances existe directement sur la programmation
        if (isset($this->vacances) && is_array($this->vacances)) {
            foreach ($this->vacances as $vacance) {
                if (isset($vacance['date_debut']) && isset($vacance['date_fin'])) {
                    $conges[] = [
                        'debut' => Carbon::parse($vacance['date_debut'])->timestamp,
                        'fin' => Carbon::parse($vacance['date_fin'])->timestamp,
                    ];
                }
            }
        }

        return $conges;
    }

    /**
     * Extraire les jours fériés spécifiques à partir des exceptions
     *
     * @return array Tableau de jours fériés avec format ['annee' => int, 'mois' => int, 'jour' => int]
     */
    protected function extraireJoursFeries(): array
    {
        $feries = [];

        // Traiter les exceptions de jours fériés
        if (isset($this->jours_feries_exceptions) && is_array($this->jours_feries_exceptions)) {
            foreach ($this->jours_feries_exceptions as $exception) {
                // Si jours_feries_inclus est false, on envoie les exclusions
                // Si jours_feries_inclus est true, on envoie les inclusions
                // Pour simplifier, on envoie toutes les dates avec action "include"
                if (isset($exception['date']) && ($exception['action'] ?? '') === 'include') {
                    $date = Carbon::parse($exception['date']);
                    $feries[] = [
                        'annee' => $date->year,
                        'mois' => $date->month,
                        'jour' => $date->day,
                    ];
                }
            }
        }

        return $feries;
    }

    /**
     * Générer et sauvegarder les chaînes (programmée et cryptée)
     *
     * @return void
     */
    public function sauvegarderChainesCryptees(): void
    {
        $this->update([
            'chaine_programmee' => $this->genererChaineProgrammee(),
            'chaine_cryptee' => $this->genererChaineCryptee(),
        ]);
    }

    /**
     * Régénérer les chaînes (utile après modification des horaires)
     *
     * @return void
     */
    public function regenererChainesCryptees(): void
    {
        $this->sauvegarderChainesCryptees();
        $this->refresh();
    }

    /**
     * Décrypter la chaîne cryptée (pour vérification)
     * Retourne la chaîne décryptée au format brut (pipe-separated)
     *
     * @return string|null
     */
    public function decrypterChaineCryptee(): ?string
    {
        if (!$this->chaine_cryptee) {
            return null;
        }

        try {
            return $this->decrypterDonneesESP8266($this->chaine_cryptee, true, 8);
        } catch (\Exception $e) {
            \Log::error("Erreur lors du décryptage de la chaîne cryptée: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Parser une chaîne décryptée pour obtenir un tableau structuré
     *
     * @param string $data_str Chaîne décryptée au format pipe-separated
     * @return array|null Données parsées ou null en cas d'erreur
     */
    public function parserChaineCryptee(string $data_str): ?array
    {
        try {
            $parts = explode('|', $data_str);
            $index = 0;

            $version = (int) ($parts[$index++] ?? 1);
            $nb_horaires = (int) ($parts[$index++] ?? 0);

            $horaires = [];
            for ($i = 0; $i < $nb_horaires; $i++) {
                if (!isset($parts[$index])) break;

                // Format: H:M:MASK:DUREE (nouvelle version avec durée par horaire)
                $horaireData = explode(':', $parts[$index++]);
                $h = (int) ($horaireData[0] ?? 0);
                $m = (int) ($horaireData[1] ?? 0);
                $mask = (int) ($horaireData[2] ?? 0);
                $duree = isset($horaireData[3]) ? (int) $horaireData[3] : 3; // Fallback sur 3s

                $horaires[] = [
                    'heure' => $h,
                    'minute' => $m,
                    'jours' => $this->intMasqueVersJours($mask),
                    'duree_sonnerie' => $duree,
                ];
            }

            $nb_conges = (int) ($parts[$index++] ?? 0);
            $conges = [];
            for ($i = 0; $i < $nb_conges; $i++) {
                if (!isset($parts[$index])) break;
                [$debut, $fin] = explode(':', $parts[$index++]);
                $conges[] = [
                    'debut' => (int) $debut,
                    'fin' => (int) $fin,
                ];
            }

            $nb_feries = (int) ($parts[$index++] ?? 0);
            $feries = [];
            for ($i = 0; $i < $nb_feries; $i++) {
                if (!isset($parts[$index])) break;
                [$annee, $mois, $jour] = explode(':', $parts[$index++]);
                $feries[] = [
                    'annee' => (int) $annee,
                    'mois' => (int) $mois,
                    'jour' => (int) $jour,
                ];
            }

            return [
                'version' => $version,
                'horaires' => $horaires,
                'conges' => $conges,
                'feries' => $feries,
            ];
        } catch (\Exception $e) {
            \Log::error("Erreur lors du parsing de la chaîne cryptée: " . $e->getMessage());
            return null;
        }
    }
}
