<?php

namespace App\Http\Requests\JourFerie;

use App\Models\CalendrierScolaire;
use App\Models\Ecole;
use App\Models\JourFerie;
use Illuminate\Foundation\Http\FormRequest;

class BulkCreateJourFerieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jours_feries' => ['required', 'array', 'min:1'],
            'jours_feries.*.calendrier_id' => ['nullable', 'string', 'exists:calendriers_scolaires,id'],
            'jours_feries.*.ecole_id' => ['nullable', 'string', 'exists:ecoles,id'],
            'jours_feries.*.intitule_journee' => ['required', 'string', 'max:100'],
            'jours_feries.*.date' => ['required', 'date'],
            'jours_feries.*.recurrent' => ['boolean'],
            'jours_feries.*.actif' => ['boolean'],
            'jours_feries.*.est_national' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $joursFeries = $this->jours_feries ?? [];

            foreach ($joursFeries as $index => $jour) {
                $calendrierId = $jour['calendrier_id'] ?? null;
                $ecoleId = $jour['ecole_id'] ?? null;
                $date = $jour['date'] ?? null;
                $estNational = $jour['est_national'] ?? false;

                if (!$calendrierId) continue;

                $calendrier = CalendrierScolaire::find($calendrierId);
                if (!$calendrier) continue;

                // Vérifier que ecole appartient au même pays
                if ($ecoleId) {
                    $ecole = Ecole::with('sitePrincipal.ville')->find($ecoleId);
                    if ($ecole && $ecole->sitePrincipal && $ecole->sitePrincipal->ville) {
                        if ($ecole->sitePrincipal->ville->pays_id !== $calendrier->pays_id) {
                            $validator->errors()->add(
                                "jours_feries.{$index}.ecole_id",
                                "L'école n'appartient pas au même pays que le calendrier."
                            );
                        }
                    }
                }

                // Vérifier que si est_national = true, ecole_id doit être null
                if ($estNational && $ecoleId) {
                    $validator->errors()->add(
                        "jours_feries.{$index}.ecole_id",
                        "Un jour férié national ne peut pas être spécifique à une école."
                    );
                }

                // Vérifier l'unicité
                if ($date) {
                    $exists = JourFerie::where('calendrier_id', $calendrierId)
                        ->where('date', $date)
                        ->where('ecole_id', $ecoleId)
                        ->exists();

                    if ($exists) {
                        $validator->errors()->add(
                            "jours_feries.{$index}.date",
                            "Un jour férié existe déjà pour cette date."
                        );
                    }
                }
            }
        });
    }
}
