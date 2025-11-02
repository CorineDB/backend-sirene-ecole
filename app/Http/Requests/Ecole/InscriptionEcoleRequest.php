<?php

namespace App\Http\Requests\Ecole;

use Illuminate\Foundation\Http\FormRequest;

class InscriptionEcoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Informations de l'école (basées sur la migration)
            'nom' => ['required', 'string', 'max:100'],
            'nom_complet' => ['required', 'string'],
            'telephone_contact' => ['required', 'string', 'max:20', 'unique:ecoles,telephone_contact'],
            'email_contact' => ['nullable', 'email', 'max:100', 'unique:ecoles,email_contact'],
            'types_etablissement' => ['required', 'array'],
            'types_etablissement.*' => ['string', 'exists:types_etablissement,id'],

            // Informations du responsable
            'responsable_nom' => ['required', 'string', 'max:255'],
            'responsable_prenom' => ['required', 'string', 'max:255'],
            'responsable_telephone' => ['required', 'string', 'max:20'],

            // Site principal (obligatoire avec une sirène)
            'site_principal' => ['required', 'array'],
            'site_principal.nom' => ['nullable', 'string', 'max:255'],
            'site_principal.adresse' => ['required', 'string', 'max:500'],
            'site_principal.ville_id' => ['required', 'string', 'exists:villes,id'],
            'site_principal.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'site_principal.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'site_principal.sirene' => ['required', 'array'],
            'site_principal.sirene.numero_serie' => ['required', 'string', 'exists:sirenes,numero_serie'],

            // Sites annexes (optionnel pour multi-sites)
            'sites_annexe' => ['nullable', 'array'],
            'sites_annexe.*.nom' => ['required', 'string', 'max:255'],
            'sites_annexe.*.adresse' => ['nullable', 'string', 'max:500'],
            'sites_annexe.*.ville_id' => ['nullable', 'string', 'exists:villes,id'],
            'sites_annexe.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'sites_annexe.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'sites_annexe.*.sirene' => ['required', 'array'],
            'sites_annexe.*.sirene.numero_serie' => ['required', 'string', 'exists:sirenes,numero_serie']
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom de l\'école est requis.',
            'telephone.required' => 'Le numéro de téléphone est requis.',
            'telephone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email.unique' => 'Cet email est déjà utilisé.',
            'types_etablissement.required' => 'Le type d\'établissement est requis.',
            'responsable_nom.required' => 'Le nom du responsable est requis.',
            'responsable_prenom.required' => 'Le prénom du responsable est requis.',
            'responsable_telephone.required' => 'Le téléphone du responsable est requis.',
            'site_principal.required' => 'Le site principal est requis.',
            'site_principal.sirene.required' => 'La sirène du site principal est requise.',
            'site_principal.sirene.numero_serie.required' => 'Le numéro de série de la sirène du site principal est requis.',
            'site_principal.sirene.numero_serie.exists' => 'Le numéro de série fourni n\'existe pas.',
            'sites_annexe.*.nom.required' => 'Le nom du site annexe est requis.',
            'sites_annexe.*.sirene.numero_serie.required' => 'Le numéro de série de la sirène est requis pour chaque site annexe.',
            'sites_annexe.*.sirene.numero_serie.exists' => 'Le numéro de série fourni n\'existe pas.',
        ];
    }
}
