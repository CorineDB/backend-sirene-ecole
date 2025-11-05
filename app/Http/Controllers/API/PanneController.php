<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sirene;
use App\Services\Contracts\PanneServiceInterface;
use Illuminate\Http\Request;

class PanneController extends Controller
{
    protected PanneServiceInterface $panneService;

    public function __construct(PanneServiceInterface $panneService)
    {
        $this->panneService = $panneService;
    }

    public function declarer(Request $request, $sireneId)
    {
        $sirene = Sirene::findOrFail($sireneId);

        $validated = $request->validate([
            'description' => 'required|string',
            'priorite' => 'sometimes|string|in:faible,moyenne,haute',
        ]);

        $panne = $sirene->declarerPanne($validated['description'], $validated['priorite'] ?? 'moyenne');

        return response()->json($panne, 201);
    }

    public function valider(Request $request, $panneId)
    {
        $validated = $request->validate([
            'admin_id' => 'required|string|exists:users,id',
            'nombre_techniciens_requis' => 'nullable|integer|min:1',
            'date_debut_candidature' => 'nullable|date',
            'date_fin_candidature' => 'nullable|date|after:date_debut_candidature',
            'commentaire' => 'nullable|string',
        ]);

        $adminId = $validated['admin_id'];
        unset($validated['admin_id']);

        return $this->panneService->validerPanne($panneId, $adminId, $validated);
    }

    public function cloturer($panneId)
    {
        return $this->panneService->cloturerPanne($panneId);
    }
}