<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalendrierFiltreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // L'autorisation est gérée par le middleware et la logique du contrôleur.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'annee_scolaire' => 'required|string|regex:/^\d{4}-\d{4}$/',
            'avec_jours_feries_ecole' => 'sometimes|boolean',
            'avec_jours_feries_nationaux' => 'sometimes|boolean',
            'ecoleId' => [
                'sometimes',
                'string',
                'exists:ecoles,id',
                function ($attribute, $value, $fail) {
                    if (auth()->user()->user_account_type_type == \App\Models\Ecole::class) {
                        if ($value != auth()->user()->user_account_type_id) {
                            $fail('You are not authorized to access this school calendar.');
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Récupère les filtres de la requête.
     *
     * @return array
     */
    public function getFiltres(): array
    {
        return $this->validated();
    }
}
