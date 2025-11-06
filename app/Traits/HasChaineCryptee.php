<?php

namespace App\Traits;

use App\Services\TokenEncryptionService;
use Carbon\Carbon;
use Illuminate\Support\Str;

trait HasChaineCryptee
{
    /**
     * Générer la chaîne programmée (format lisible pour affichage)
     *
     * @return string
     */
    public function genererChaineProgrammee(): string
    {
        $horaires = collect($this->horaires_sonneries)
            ->map(fn($h) => Carbon::parse($h)->format('H:i'))
            ->join(', ');

        $jours = collect($this->jour_semaine)->join(', ');

        return sprintf(
            "Programmation: %s | Jours: %s | Horaires: %s | Période: %s au %s",
            $this->nom_programmation,
            $jours,
            $horaires,
            $this->date_debut->format('d/m/Y'),
            $this->date_fin->format('d/m/Y')
        );
    }

    /**
     * Générer la chaîne cryptée pour le module physique de la sirène
     *
     * @return string
     */
    public function genererChaineCryptee(): string
    {
        $tokenService = app(TokenEncryptionService::class);

        // Données à encoder pour le module physique
        $data = [
            'programmation_id' => $this->id,
            'sirene_id' => $this->sirene_id,
            'ecole_id' => $this->ecole_id,
            'site_id' => $this->site_id,
            'nom' => $this->nom_programmation,
            'horaires' => $this->horaires_sonneries,
            'jours' => $this->jour_semaine,
            'date_debut' => $this->date_debut->format('Y-m-d'),
            'date_fin' => $this->date_fin->format('Y-m-d'),
            'jours_feries_inclus' => $this->jours_feries_inclus,
            'jours_feries_exceptions' => $this->jours_feries_exceptions,
            'actif' => $this->actif,
            'generated_at' => now()->toIso8601String(),
            'signature' => Str::random(32), // Signature unique pour éviter la duplication
        ];

        return $tokenService->encryptToken($data);
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
     *
     * @return array|null
     */
    public function decrypterChaineCryptee(): ?array
    {
        if (!$this->chaine_cryptee) {
            return null;
        }

        try {
            $tokenService = app(TokenEncryptionService::class);
            return $tokenService->decryptToken($this->chaine_cryptee);
        } catch (\Exception $e) {
            \Log::error("Erreur lors du décryptage de la chaîne cryptée: " . $e->getMessage());
            return null;
        }
    }
}
