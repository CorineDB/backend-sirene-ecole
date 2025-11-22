<?php

namespace App\Traits;

use App\Models\Ecole;

trait FiltersByEcole
{
    /**
     * Vérifier si l'utilisateur connecté est une école
     */
    protected function isEcoleUser(): bool
    {
        $user = auth()->user();
        return $user && $user->user_account_type_type === Ecole::class;
    }

    /**
     * Récupérer l'ID de l'école de l'utilisateur connecté
     */
    protected function getEcoleId(): ?string
    {
        $user = auth()->user();
        if ($this->isEcoleUser()) {
            return $user->user_account_type_id;
        }
        return null;
    }

    /**
     * Appliquer le filtre école sur une query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $ecoleColumn Le nom de la colonne qui contient l'ecole_id (par défaut 'ecole_id')
     * @param string|null $relationship Si l'ecole_id est dans une relation (ex: 'site')
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyEcoleFilter($query, string $ecoleColumn = 'ecole_id', ?string $relationship = null)
    {
        if ($this->isEcoleUser()) {
            $ecoleId = $this->getEcoleId();

            if ($relationship) {
                // Si l'ecole_id est dans une relation (ex: pannes via site)
                $query->whereHas($relationship, function ($q) use ($ecoleId, $ecoleColumn) {
                    $q->where($ecoleColumn, $ecoleId);
                });
            } else {
                // Si l'ecole_id est directement dans la table
                $query->where($ecoleColumn, $ecoleId);
            }
        }

        return $query;
    }

    /**
     * Appliquer le filtre école sur les sites
     */
    protected function applyEcoleFilterForSites($query)
    {
        return $this->applyEcoleFilter($query, 'ecole_id');
    }

    /**
     * Appliquer le filtre école sur les sirènes (via relation site)
     */
    protected function applyEcoleFilterForSirenes($query)
    {
        return $this->applyEcoleFilter($query, 'ecole_id', 'site');
    }

    /**
     * Appliquer le filtre école sur les pannes (via relation site)
     */
    protected function applyEcoleFilterForPannes($query)
    {
        return $this->applyEcoleFilter($query, 'ecole_id', 'site');
    }

    /**
     * Appliquer le filtre école sur les interventions (via panne.site)
     */
    protected function applyEcoleFilterForInterventions($query)
    {
        if ($this->isEcoleUser()) {
            $ecoleId = $this->getEcoleId();
            $query->whereHas('panne.site', function ($q) use ($ecoleId) {
                $q->where('ecole_id', $ecoleId);
            });
        }
        return $query;
    }

    /**
     * Appliquer le filtre école sur les calendriers scolaires
     */
    protected function applyEcoleFilterForCalendriers($query)
    {
        return $this->applyEcoleFilter($query, 'ecole_id');
    }

    /**
     * Appliquer le filtre école sur les jours fériés
     * Note: On filtre uniquement ceux qui ont un ecole_id (jours fériés spécifiques à une école)
     */
    protected function applyEcoleFilterForJoursFeries($query)
    {
        if ($this->isEcoleUser()) {
            $ecoleId = $this->getEcoleId();
            $query->where(function($q) use ($ecoleId) {
                $q->where('ecole_id', $ecoleId)
                  ->orWhereNull('ecole_id'); // Inclure aussi les jours fériés nationaux
            });
        }
        return $query;
    }

    /**
     * Appliquer le filtre école sur les programmations (via sirène.site)
     */
    protected function applyEcoleFilterForProgrammations($query)
    {
        if ($this->isEcoleUser()) {
            $ecoleId = $this->getEcoleId();
            $query->whereHas('sirene.site', function ($q) use ($ecoleId) {
                $q->where('ecole_id', $ecoleId);
            });
        }
        return $query;
    }

    /**
     * Appliquer le filtre école sur les abonnements
     */
    protected function applyEcoleFilterForAbonnements($query)
    {
        return $this->applyEcoleFilter($query, 'ecole_id');
    }

    /**
     * Appliquer le filtre école sur les utilisateurs
     * Filtre uniquement les utilisateurs dont le compte est lié à l'école
     */
    protected function applyEcoleFilterForUsers($query)
    {
        if ($this->isEcoleUser()) {
            $ecoleId = $this->getEcoleId();
            $query->where('user_account_type_type', Ecole::class)
                  ->where('user_account_type_id', $ecoleId);
        }
        return $query;
    }
}
