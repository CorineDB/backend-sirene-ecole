# Documentation - Implémentation Calendrier Scolaire & Programmation des Sonneries

## 📋 Table des matières
1. [Vue d'ensemble](#vue-densemble)
2. [Flux Routes → Controller → Service → Repository → Model → Migration](#flux-complet)
3. [Routes API](#1-routes-api)
4. [Contrôleurs](#2-contrôleurs)
5. [Services (Logique Métier)](#3-services)
6. [Repositories (Accès Données)](#4-repositories)
7. [Modèles Eloquent](#5-modèles-eloquent)
8. [Migrations](#6-migrations)
9. [Traits Spécialisés](#7-traits-spécialisés)
10. [Diagramme de relations](#diagramme-de-relations)
11. [Cas d'utilisation](#cas-dutilisation)

---

## 🎯 Vue d'ensemble

### Objectif du système

Le système de **Calendrier Scolaire et Programmation des Sonneries** permet de :

1. **Gérer les calendriers scolaires** :
   - Définir les années scolaires (date de rentrée, fin d'année)
   - Configurer les périodes de vacances
   - Gérer les jours fériés nationaux et spécifiques aux écoles

2. **Programmer les sonneries automatiques** :
   - Créer des programmations hebdomadaires (jours et horaires)
   - Intégrer le calendrier scolaire pour respecter les jours fériés
   - Générer des chaînes cryptées pour les modules physiques
   - Gérer les exceptions de jours fériés

### Architecture multicouche

```
┌─────────────────────────────────────────────────────────────┐
│ ROUTES (api.php)                                            │
│ - /api/calendrier-scolaire/*                                │
│ - /api/sirenes/{sirene}/programmations/*                    │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│ CONTROLLERS                                                  │
│ - CalendrierScolaireController.php                          │
│ - ProgrammationController.php                               │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│ SERVICES (Business Logic)                                   │
│ - CalendrierScolaireService.php                             │
│ - ProgrammationService.php                                  │
│ - JourFerieService.php                                      │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│ REPOSITORIES (Data Access)                                  │
│ - CalendrierScolaireRepository.php                          │
│ - ProgrammationRepository.php                               │
│ - JourFerieRepository.php                                   │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│ MODELS (Eloquent ORM)                                       │
│ - CalendrierScolaire.php                                    │
│ - Programmation.php (+ trait HasChaineCryptee)              │
│ - JourFerie.php                                             │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│ DATABASE (PostgreSQL)                                       │
│ - calendriers_scolaires                                     │
│ - programmations                                            │
│ - jours_feries                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Flux complet

### Exemple : Créer une programmation

```
1. Client envoie → POST /api/sirenes/{sirene_id}/programmations
   Body: {
     "nom_programmation": "Horaires Septembre-Décembre",
     "horaires_sonneries": ["07:30", "12:00", "15:00"],
     "jour_semaine": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
     "jours_feries_inclus": false,
     "date_debut": "2024-09-01",
     "date_fin": "2024-12-20"
   }

2. Routes (api.php) → Dirige vers ProgrammationController::store()

3. ProgrammationController::store()
   - Valide avec StoreProgrammationRequest
   - Ajoute sirene_id au payload
   - Appelle ProgrammationService::create()

4. ProgrammationService::create()
   - Récupère la sirène et vérifie l'école associée
   - Vérifie l'abonnement actif
   - Auto-remplit: ecole_id, site_id, abonnement_id, cree_par
   - Appelle ProgrammationRepository::create()
   - Génère les chaînes cryptées via Programmation::sauvegarderChainesCryptees()
   - Retourne JsonResponse

5. ProgrammationRepository::create()
   - Utilise Eloquent Model pour créer l'enregistrement
   - INSERT INTO programmations...

6. Programmation Model
   - Trait HasChaineCryptee génère:
     * chaine_programmee (format lisible)
     * chaine_cryptee (pour module physique)

7. Database
   - Enregistrement créé dans table programmations
   - Champs JSON stockés: horaires_sonneries, jour_semaine, etc.

8. Réponse JSON renvoyée au client avec l'objet Programmation complet
```

---

## 1. Routes API

### Fichier : `routes/api.php`

#### Routes Calendrier Scolaire

```php
Route::prefix('calendrier-scolaire')->middleware('auth:api')->group(function () {
    // CRUD basique
    Route::get('/', [CalendrierScolaireController::class, 'index']);
    Route::post('/', [CalendrierScolaireController::class, 'store']);
    Route::get('{id}', [CalendrierScolaireController::class, 'show']);
    Route::put('{id}', [CalendrierScolaireController::class, 'update']);
    Route::delete('{id}', [CalendrierScolaireController::class, 'destroy']);

    // Jours fériés
    Route::get('{id}/jours-feries', [CalendrierScolaireController::class, 'getJoursFeries']);
    Route::post('{id}/jours-feries/bulk', [CalendrierScolaireController::class, 'storeMultipleJoursFeries']);
    Route::put('{id}/jours-feries/bulk', [CalendrierScolaireController::class, 'updateMultipleJoursFeries']);

    // Calculs
    Route::get('{id}/calculate-school-days', [CalendrierScolaireController::class, 'calculateSchoolDays']);
});
```

**Permissions requises** :
- `voir_les_calendriers_scolaires` : Lister
- `voir_calendrier_scolaire` : Voir détails
- `creer_calendrier_scolaire` : Créer
- `modifier_calendrier_scolaire` : Modifier
- `supprimer_calendrier_scolaire` : Supprimer

#### Routes Programmation

```php
Route::prefix('sirenes')->middleware('auth:api')->group(function () {
    // Programmations imbriquées dans sirenes
    Route::apiResource('{sirene}/programmations', ProgrammationController::class);
    // Génère automatiquement :
    // GET    /sirenes/{sirene}/programmations           → index
    // POST   /sirenes/{sirene}/programmations           → store
    // GET    /sirenes/{sirene}/programmations/{prog}    → show
    // PUT    /sirenes/{sirene}/programmations/{prog}    → update
    // DELETE /sirenes/{sirene}/programmations/{prog}    → destroy
});
```

**Permissions requises** :
- `voir_les_programmations` : Lister
- `creer_programmation` : Créer
- `voir_programmation` : Voir détails
- `modifier_programmation` : Modifier
- `supprimer_programmation` : Supprimer

#### Route Calendrier pour Écoles

```php
Route::prefix('ecoles')->group(function () {
    Route::middleware('auth:api')->group(function () {
        // Calendrier avec jours fériés mergés (globaux + école)
        Route::get('me/calendrier-scolaire/with-ecole-holidays',
            [EcoleController::class, 'getCalendrierScolaireWithJoursFeries'])
            ->middleware('can:voir_ecole');
    });
});
```

---

## 2. Contrôleurs

### CalendrierScolaireController

**Fichier** : `app/Http/Controllers/Api/CalendrierScolaireController.php`

#### Responsabilités
- Validation des requêtes (Request classes)
- Autorisation via Gates
- Délégation vers CalendrierScolaireService
- Retour de réponses JSON standardisées

#### Méthodes principales

| Méthode | Route | Description |
|---------|-------|-------------|
| `index(Request)` | GET /calendrier-scolaire | Liste paginée des calendriers |
| `store(CreateRequest)` | POST /calendrier-scolaire | Créer un calendrier |
| `show(string $id)` | GET /calendrier-scolaire/{id} | Détails d'un calendrier |
| `update(UpdateRequest, $id)` | PUT /calendrier-scolaire/{id} | Modifier un calendrier |
| `destroy(string $id)` | DELETE /calendrier-scolaire/{id} | Supprimer (soft delete) |
| `getJoursFeries(string $id)` | GET /{id}/jours-feries | Jours fériés du calendrier |
| `calculateSchoolDays(Request, $id)` | GET /{id}/calculate-school-days | Calculer jours de classe |
| `storeMultipleJoursFeries(Request, $id)` | POST /{id}/jours-feries/bulk | Créer jours fériés en masse |
| `updateMultipleJoursFeries(Request, $id)` | PUT /{id}/jours-feries/bulk | Modifier jours fériés en masse |

#### Exemple : index()

```php
public function index(Request $request): JsonResponse
{
    Gate::authorize('voir_les_calendriers_scolaires');
    $perPage = $request->get('per_page', 15);
    return $this->calendrierScolaireService->getAll($perPage);
}
```

**Workflow** :
1. Vérification permission via Gate
2. Récupération paramètre pagination
3. Délégation au service
4. Retour JsonResponse

---

### ProgrammationController

**Fichier** : `app/Http/Controllers/Api/ProgrammationController.php`

#### Responsabilités
- CRUD pour les programmations de sirènes
- Route binding automatique (Sirene, Programmation)
- Filtrage par date pour programmations effectives
- Validation et autorisation

#### Méthodes principales

| Méthode | Route | Description |
|---------|-------|-------------|
| `index(Sirene, Request)` | GET /sirenes/{sirene}/programmations | Liste programmations d'une sirène |
| `store(StoreRequest, Sirene)` | POST /sirenes/{sirene}/programmations | Créer programmation |
| `show(Sirene, Programmation)` | GET /sirenes/{sirene}/programmations/{prog} | Détails programmation |
| `update(UpdateRequest, Sirene, Prog)` | PUT /sirenes/{sirene}/programmations/{prog} | Modifier programmation |
| `destroy(Sirene, Programmation)` | DELETE /sirenes/{sirene}/programmations/{prog} | Supprimer programmation |

#### Exemple : index() avec filtrage par date

```php
public function index(Sirene $sirene, Request $request): JsonResponse
{
    $date = $request->query('date');

    if ($date) {
        // Retourne programmations effectives pour une date donnée
        // (considère jours fériés, exceptions, jours de semaine)
        return $this->programmationService->getEffectiveProgrammationsForSirene(
            $sirene->id,
            $date
        );
    }

    // Retourne toutes les programmations
    return $this->programmationService->getBySireneId($sirene->id);
}
```

**Usage** :
```http
GET /api/sirenes/{sirene_id}/programmations?date=2024-12-25
```
Retourne uniquement les programmations actives le 25 décembre 2024, en tenant compte des jours fériés.

---

## 3. Services

### CalendrierScolaireService

**Fichier** : `app/Services/CalendrierScolaireService.php`

#### Dépendances
```php
protected CalendrierScolaireRepositoryInterface $repository;
protected JourFerieRepositoryInterface $jourFerieRepository;
```

#### Méthodes métier

##### 1. create(array $data): JsonResponse

Crée un calendrier avec jours fériés par défaut.

```php
public function create(array $data): JsonResponse
{
    DB::beginTransaction();
    try {
        // 1. Extraire jours_feries_defaut du payload
        $joursFeriesData = $data['jours_feries_defaut'] ?? [];
        unset($data['jours_feries_defaut']);

        // 2. Créer le calendrier
        $calendrierScolaire = $this->repository->create($data);

        // 3. Créer les jours fériés associés
        if (!empty($joursFeriesData)) {
            foreach ($joursFeriesData as $jourFerieData) {
                $jourFerieData['calendrier_id'] = $calendrierScolaire->id;
                $jourFerieData['intitule_journee'] = $jourFerieData['nom'];
                unset($jourFerieData['nom']);
                $this->jourFerieRepository->create($jourFerieData);
            }
        }

        DB::commit();
        return $this->createdResponse($calendrierScolaire->load('joursFeries'));
    } catch (Exception $e) {
        DB::rollBack();
        Log::error("Error creating calendrier scolaire: " . $e->getMessage());
        return $this->errorResponse($e->getMessage(), 500);
    }
}
```

##### 2. calculateSchoolDays(string $id, ?string $ecoleId): JsonResponse

Calcule le nombre de jours de classe en excluant :
- Weekends (samedi/dimanche)
- Jours fériés
- Périodes de vacances
- Jours fériés spécifiques à l'école (si `ecoleId` fourni)

**Algorithme** :
```php
public function calculateSchoolDays(string $calendrierScolaireId, string $ecoleId = null): JsonResponse
{
    // 1. Charger le calendrier et jours fériés
    $calendrierScolaire = $this->repository->find($calendrierScolaireId, relations: ['joursFeries']);

    $startDate = $calendrierScolaire->date_rentree;
    $endDate = $calendrierScolaire->date_fin_annee;
    $vacances = $calendrierScolaire->periodes_vacances;
    $joursFeries = $calendrierScolaire->joursFeries->pluck('date_ferie')->map(...)->toArray();

    // 2. Merger avec jours fériés de l'école si fourni
    if ($ecoleId) {
        $ecole = Ecole::with('joursFeries')->find($ecoleId);
        if ($ecole) {
            foreach ($ecole->joursFeries as $jourFerie) {
                $date = $jourFerie->date_ferie->format('Y-m-d');
                if ($jourFerie->actif) {
                    // Ajouter jour férié école
                    if (!in_array($date, $joursFeries)) {
                        $joursFeries[] = $date;
                    }
                } else {
                    // Retirer jour férié global (surcharge)
                    $joursFeries = array_diff($joursFeries, [$date]);
                }
            }
        }
    }

    // 3. Compter les jours ouvrés
    $schoolDays = 0;
    $currentDate = clone $startDate;

    while ($currentDate->lte($endDate)) {
        // Vérifier si c'est un jour de semaine
        if ($currentDate->isWeekday()) {
            $isHoliday = false;

            // Vérifier jour férié
            if (in_array($currentDate->format('Y-m-d'), $joursFeries)) {
                $isHoliday = true;
            }

            // Vérifier période de vacances
            if (!$isHoliday) {
                foreach ($vacances as $vacance) {
                    $vacanceStart = Carbon::parse($vacance['date_debut']);
                    $vacanceEnd = Carbon::parse($vacance['date_fin']);
                    if ($currentDate->between($vacanceStart, $vacanceEnd)) {
                        $isHoliday = true;
                        break;
                    }
                }
            }

            // Compter si pas férié/vacances
            if (!$isHoliday) {
                $schoolDays++;
            }
        }
        $currentDate->addDay();
    }

    return $this->successResponse(null, ['school_days' => $schoolDays]);
}
```

**Exemple d'utilisation** :
```http
GET /api/calendrier-scolaire/{id}/calculate-school-days?ecole_id={ecole_id}
```

**Réponse** :
```json
{
  "success": true,
  "data": {
    "school_days": 180
  }
}
```

##### 3. getCalendrierScolaireWithJoursFeries(array $filtres): JsonResponse

Fusionne les jours fériés nationaux et spécifiques à une école.

**Logique de fusion** :
- Jours fériés globaux (du calendrier)
- Jours fériés de l'école peuvent **surcharger** les globaux (actif/inactif)
- Résultat : liste fusionnée avec priorité aux jours école

```php
public function getCalendrierScolaireWithJoursFeries(array $filtres = []): JsonResponse
{
    $anneeScolaire = $filtres['annee_scolaire'];
    $ecoleId = $filtres['ecoleId'] ?? null;

    // Charger calendrier avec jours fériés nationaux
    $calendrierScolaire = $this->repository->findBy(
        ['annee_scolaire' => $anneeScolaire'],
        relations: ['joursFeries']
    );

    $globalJoursFeries = collect();
    if ($filtres['avec_jours_feries_nationaux'] ?? false) {
        $globalJoursFeries = $calendrierScolaire->joursFeries->keyBy('date');
    }

    $mergedJoursFeries = $globalJoursFeries;

    // Merger avec jours fériés école
    if ($ecoleId && ($filtres['avec_jours_feries_ecole'] ?? false)) {
        $ecole = Ecole::with('joursFeries')->find($ecoleId);
        if ($ecole) {
            $ecoleJoursFeries = $ecole->joursFeries->keyBy('date');
            // Merge : jours école surchargent globaux
            $mergedJoursFeries = $globalJoursFeries->merge($ecoleJoursFeries);
        }
    }

    $calendrierScolaireArray = $calendrierScolaire->toArray();
    $calendrierScolaireArray['jours_feries_merged'] = $mergedJoursFeries->values()->toArray();

    return $this->successResponse(null, $calendrierScolaireArray);
}
```

---

### ProgrammationService

**Fichier** : `app/Services/ProgrammationService.php`

#### Dépendances
```php
protected ProgrammationRepositoryInterface $repository;
protected JourFerieServiceInterface $jourFerieService;
```

#### Méthodes métier

##### 1. create(array $data): JsonResponse

Crée une programmation avec génération automatique des chaînes cryptées.

**Workflow complet** :
```php
public function create(array $data): JsonResponse
{
    DB::beginTransaction();
    try {
        // 1. Récupérer la sirène
        $sirene = Sirene::find($data['sirene_id']);
        if (!$sirene) {
            DB::rollBack();
            return $this->errorResponse('Sirène introuvable.', 404);
        }

        // 2. Récupérer l'école associée
        $ecole = $sirene->ecole;
        if (!$ecole) {
            DB::rollBack();
            return $this->errorResponse('École introuvable pour cette sirène.', 404);
        }

        // 3. Vérifier abonnement actif
        $abonnementActif = $ecole->abonnementActif;
        if (!$abonnementActif) {
            DB::rollBack();
            return $this->errorResponse('Aucun abonnement actif trouvé.', 403);
        }

        // 4. Auto-remplir champs système
        $data['ecole_id'] = $ecole->id;
        $data['site_id'] = $sirene->site_id;
        $data['abonnement_id'] = $abonnementActif->id;
        $data['cree_par'] = auth()->id();
        $data['actif'] = $data['actif'] ?? true;

        // 5. Créer la programmation
        $programmation = $this->repository->create($data);

        // 6. Générer chaînes cryptées (trait HasChaineCryptee)
        $programmation->sauvegarderChainesCryptees();

        // 7. Recharger avec relations
        $programmation->load(['ecole', 'site', 'sirene', 'abonnement', 'calendrier', 'creePar']);

        DB::commit();

        Log::info("Programmation créée", [
            'programmation_id' => $programmation->id,
            'nom' => $programmation->nom_programmation,
            'sirene_id' => $programmation->sirene_id,
            'horaires' => $programmation->horaires_sonneries,
            'jours' => $programmation->jour_semaine,
        ]);

        return $this->createdResponse($programmation, 'Programmation créée avec succès.');

    } catch (Exception $e) {
        DB::rollBack();
        Log::error("Error creating programmation: " . $e->getMessage());
        return $this->errorResponse($e->getMessage(), 500);
    }
}
```

##### 2. update(string $id, array $data): JsonResponse

Met à jour une programmation et régénère les chaînes cryptées si nécessaire.

**Détection des changements** :
```php
public function update(string $id, array $data): JsonResponse
{
    DB::beginTransaction();
    try {
        // 1. Récupérer programmation existante
        $programmation = $this->repository->find($id);

        // 2. Détecter champs critiques modifiés
        $horairesDirty = isset($data['horaires_sonneries'])
            && json_encode($data['horaires_sonneries']) !== json_encode($programmation->horaires_sonneries);

        $joursSemaineDirty = isset($data['jour_semaine'])
            && json_encode($data['jour_semaine']) !== json_encode($programmation->jour_semaine);

        $nomDirty = isset($data['nom_programmation'])
            && $data['nom_programmation'] !== $programmation->nom_programmation;

        $datesDirty = (isset($data['date_debut']) && ...)
            || (isset($data['date_fin']) && ...);

        $joursFeriesDirty = isset($data['jours_feries_inclus'])
            && $data['jours_feries_inclus'] !== $programmation->jours_feries_inclus;

        $needsRegeneration = $horairesDirty || $joursSemaineDirty || $nomDirty
                           || $datesDirty || $joursFeriesDirty;

        // 3. Mettre à jour
        $updated = $this->repository->update($id, $data);
        $programmation->refresh();

        // 4. Régénérer chaînes si nécessaire
        if ($needsRegeneration) {
            $programmation->regenererChainesCryptees();

            Log::info("Chaînes cryptées régénérées", [
                'programmation_id' => $programmation->id,
                'raison' => [
                    'horaires_modifies' => $horairesDirty,
                    'jours_modifies' => $joursSemaineDirty,
                    'nom_modifie' => $nomDirty,
                    'dates_modifiees' => $datesDirty,
                    'jours_feries_modifies' => $joursFeriesDirty,
                ],
            ]);
        }

        // 5. Recharger avec relations
        $programmation->load(['ecole', 'site', 'sirene', 'abonnement', 'calendrier', 'creePar']);

        DB::commit();
        return $this->successResponse('Programmation mise à jour.', $programmation);

    } catch (Exception $e) {
        DB::rollBack();
        Log::error("Error updating programmation: " . $e->getMessage());
        return $this->errorResponse($e->getMessage(), 500);
    }
}
```

##### 3. getEffectiveProgrammationsForSirene(string $sireneId, string $date): JsonResponse

Retourne les programmations **réellement actives** pour une date donnée.

**Algorithme de filtrage** :
```php
public function getEffectiveProgrammationsForSirene(string $sireneId, string $date): JsonResponse
{
    try {
        // 1. Charger toutes les programmations de la sirène
        $programmations = $this->repository->getBySireneId($sireneId);
        $carbonDate = Carbon::parse($date);
        $dayOfWeek = $carbonDate->dayName; // 'Monday', 'Tuesday', etc.

        // 2. Vérifier si la date est un jour férié
        $isHoliday = $this->jourFerieService->isJourFerie($date);

        // 3. Filtrer les programmations
        $effectiveProgrammations = $programmations->filter(function (Programmation $programmation)
            use ($isHoliday, $dayOfWeek, $date) {

            // a) Vérifier jour de la semaine
            if (!in_array($dayOfWeek, $programmation->jour_semaine)) {
                return false; // Pas actif ce jour
            }

            // b) Décision initiale : inclure jours fériés ?
            $shouldIncludeHoliday = $programmation->jours_feries_inclus;

            // c) Vérifier exceptions spécifiques
            if (is_array($programmation->jours_feries_exceptions)) {
                foreach ($programmation->jours_feries_exceptions as $exception) {
                    if (isset($exception['date']) && $exception['date'] === $date) {
                        if (isset($exception['action'])) {
                            // Exception surcharge la décision
                            $shouldIncludeHoliday = ($exception['action'] === 'include');
                        }
                        break;
                    }
                }
            }

            // d) Filtre final : si jour férié ET on ne l'inclut pas
            if ($isHoliday && !$shouldIncludeHoliday) {
                return false;
            }

            // Autres vérifications possibles : date_debut, date_fin, vacances...

            return true; // Programmation active
        });

        return $this->successResponse('Effective programmations retrieved.',
            $effectiveProgrammations->values());

    } catch (Exception $e) {
        Log::error("Error getting effective programmations: " . $e->getMessage());
        return $this->errorResponse($e->getMessage(), 500);
    }
}
```

**Cas d'usage** :
```http
GET /api/sirenes/01ABC123/programmations?date=2024-12-25
```

**Scénarios** :
- Si 25/12/2024 est un jour férié ET programmation a `jours_feries_inclus = false` → **Filtrée**
- Si programmation a une exception pour 25/12/2024 avec `action = 'include'` → **Incluse**
- Si 25/12/2024 est un samedi et pas dans `jour_semaine` → **Filtrée**

---

## 4. Repositories

### CalendrierScolaireRepository

**Fichier** : `app/Repositories/CalendrierScolaireRepository.php`

```php
class CalendrierScolaireRepository extends BaseRepository
    implements CalendrierScolaireRepositoryInterface
{
    public function __construct(CalendrierScolaire $model)
    {
        parent::__construct($model);
    }

    // Hérite de BaseRepository :
    // - find($id, $relations = [])
    // - findBy($criteria, $relations = [])
    // - create($data)
    // - update($id, $data)
    // - delete($id)
    // - getAll($perPage = 15, $relations = [])
}
```

Aucune méthode spécifique : utilise les méthodes héritées de `BaseRepository`.

---

### ProgrammationRepository

**Fichier** : `app/Repositories/ProgrammationRepository.php`

```php
class ProgrammationRepository extends BaseRepository
    implements ProgrammationRepositoryInterface
{
    public function __construct(Programmation $model)
    {
        parent::__construct($model);
    }

    /**
     * Récupère toutes les programmations d'une sirène
     */
    public function getBySireneId(string $sireneId): Collection
    {
        return $this->model->where('sirene_id', $sireneId)->get();
    }
}
```

**Méthodes** :
- Hérite de `BaseRepository` : CRUD standard
- **`getBySireneId(string $sireneId)`** : Méthode spécifique pour filtrer par sirène

---

## 5. Modèles Eloquent

### CalendrierScolaire

**Fichier** : `app/Models/CalendrierScolaire.php`

```php
class CalendrierScolaire extends Model
{
    use HasUlid, SoftDeletes;

    protected $table = 'calendriers_scolaires';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'pays_id',
        'annee_scolaire',      // Ex: "2024-2025"
        'description',
        'date_rentree',
        'date_fin_annee',
        'periodes_vacances',   // JSON : [{"date_debut": "...", "date_fin": "..."}]
        'jours_feries_defaut', // JSON : liste jours fériés
        'actif',
    ];

    protected $casts = [
        'date_rentree' => 'date',
        'date_fin_annee' => 'date',
        'periodes_vacances' => 'array',     // Cast JSON → PHP array
        'jours_feries_defaut' => 'array',   // Cast JSON → PHP array
        'actif' => 'boolean',
    ];

    // Relations
    public function pays(): BelongsTo
    {
        return $this->belongsTo(Pays::class, 'pays_id');
    }

    public function joursFeries(): HasMany
    {
        return $this->hasMany(JourFerie::class, 'calendrier_id');
    }

    public function programmations(): HasMany
    {
        return $this->hasMany(Programmation::class, 'calendrier_id');
    }
}
```

**Champs importants** :

| Champ | Type | Description |
|-------|------|-------------|
| `annee_scolaire` | string | Ex: "2024-2025" |
| `date_rentree` | date | Date de rentrée scolaire |
| `date_fin_annee` | date | Date de fin d'année |
| `periodes_vacances` | JSON array | `[{"date_debut": "2024-12-20", "date_fin": "2025-01-05"}, ...]` |
| `jours_feries_defaut` | JSON array | Jours fériés par défaut pour le pays |

---

### Programmation

**Fichier** : `app/Models/Programmation.php`

```php
class Programmation extends Model
{
    use HasUlid, SoftDeletes, HasChaineCryptee;

    protected $table = 'programmations';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ecole_id',
        'site_id',
        'sirene_id',
        'abonnement_id',
        'calendrier_id',
        'nom_programmation',
        'horaires_sonneries',       // JSON array
        'jour_semaine',             // JSON array
        'jours_feries_inclus',      // boolean
        'jours_feries_exceptions',  // JSON array
        'chaine_programmee',        // string généré
        'chaine_cryptee',           // text généré crypté
        'date_debut',
        'date_fin',
        'actif',
        'cree_par',
    ];

    protected $casts = [
        'horaires_sonneries' => 'array',         // ["07:30", "12:00", "15:00"]
        'jour_semaine' => 'array',               // ["Monday", "Tuesday", ...]
        'jours_feries_inclus' => 'boolean',
        'jours_feries_exceptions' => 'array',    // [{"date": "2024-12-25", "action": "include"}]
        'date_debut' => 'date',
        'date_fin' => 'date',
        'actif' => 'boolean',
    ];

    // Relations
    public function ecole(): BelongsTo { ... }
    public function site(): BelongsTo { ... }
    public function sirene(): BelongsTo { ... }
    public function abonnement(): BelongsTo { ... }
    public function calendrier(): BelongsTo { ... }
    public function creePar(): BelongsTo { ... }
}
```

**Champs importants** :

| Champ | Type | Description | Exemple |
|-------|------|-------------|---------|
| `horaires_sonneries` | JSON array | Horaires de sonnerie | `["07:30", "12:00", "15:00"]` |
| `jour_semaine` | JSON array | Jours actifs | `["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"]` |
| `jours_feries_inclus` | boolean | Sonner les jours fériés ? | `false` |
| `jours_feries_exceptions` | JSON array | Exceptions spécifiques | `[{"date": "2024-12-25", "action": "include"}]` |
| `chaine_programmee` | string | Chaîne lisible | "Programmation: Trimestre 1 | Jours: Monday, Tuesday, Wednesday..." |
| `chaine_cryptee` | text | Chaîne cryptée pour module | "eyJhbGciOiJIUzI1NiIsInR5cCI6..." |

---

### JourFerie

**Fichier** : `app/Models/JourFerie.php`

```php
class JourFerie extends Model
{
    use HasUlid, SoftDeletes;

    protected $table = 'jours_feries';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'calendrier_id',
        'ecole_id',
        'pays_id',
        'intitule_journee',
        'date',
        'recurrent',
        'actif',
        'est_national',
    ];

    protected $casts = [
        'date' => 'date',
        'recurrent' => 'boolean',
        'actif' => 'boolean',
        'est_national' => 'boolean',
    ];

    // Relations
    public function ecole(): BelongsTo { ... }
    public function calendrier(): BelongsTo { ... }
    public function pays(): BelongsTo { ... }
}
```

**Champs importants** :

| Champ | Type | Description |
|-------|------|-------------|
| `calendrier_id` | ULID | Calendrier scolaire associé |
| `ecole_id` | ULID | École (si spécifique) ou null (si national) |
| `intitule_journee` | string | Ex: "Jour de l'an", "Fête du travail" |
| `date` | date | Date du jour férié |
| `recurrent` | boolean | Se répète chaque année ? |
| `actif` | boolean | Actif ou désactivé |
| `est_national` | boolean | National (pays) ou personnalisé (école) |

**Logique** :
- Si `ecole_id` est NULL → Jour férié national (pays)
- Si `ecole_id` est renseigné → Jour férié spécifique à l'école
- `actif = false` permet à une école de **désactiver** un jour férié national

---

## 6. Migrations

### calendriers_scolaires

**Fichier** : `database/migrations/2025_10_31_072400_create_calendriers_scolaires_table.php`

```php
Schema::create('calendriers_scolaires', function (Blueprint $table) {
    $table->string('id', 26)->primary(); // ULID
    $table->string('pays_id', 26);
    $table->string('annee_scolaire', 9); // Ex: "2024-2025"
    $table->text('description')->nullable();
    $table->date('date_rentree');
    $table->date('date_fin_annee');
    $table->json('periodes_vacances')->nullable();
    $table->json('jours_feries_defaut')->nullable();
    $table->boolean('actif')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('pays_id')->references('id')->on('pays')->onDelete('restrict');
});
```

**Contraintes** :
- FK `pays_id` → `pays.id` (restrict)
- Soft deletes activé

---

### programmations

**Fichier** : `database/migrations/2025_10_31_072401_create_programmations_table.php`

```php
Schema::create('programmations', function (Blueprint $table) {
    $table->string('id', 26)->primary(); // ULID
    $table->string('ecole_id', 26);
    $table->string('site_id', 26)->nullable();
    $table->string('sirene_id', 26);
    $table->string('abonnement_id', 26)->nullable();
    $table->string('calendrier_id', 26)->nullable();
    $table->string('nom_programmation')->nullable();

    // Champs JSON
    $table->json('horaire_json')->nullable();
    $table->json('horaires_sonneries')->nullable();
    $table->json('jour_semaine');
    $table->json('vacances')->nullable();
    $table->json('types_etablissement');

    // Horaires (MCD)
    $table->time('horaire_debut');
    $table->time('horaire_fin');

    // Jours fériés
    $table->boolean('jours_feries_inclus')->default(false);

    // Chaînes
    $table->string('chaine_programmee');
    $table->text('chaine_cryptee')->nullable();

    // Dates
    $table->date('date_debut')->nullable();
    $table->date('date_fin')->nullable();

    $table->boolean('actif')->default(true);
    $table->timestamp('date_creation')->useCurrent();
    $table->string('cree_par', 26)->nullable();
    $table->timestamps();
    $table->softDeletes();

    // Foreign keys
    $table->foreign('ecole_id')->references('id')->on('ecoles')->onDelete('restrict');
    $table->foreign('site_id')->references('id')->on('sites')->onDelete('restrict');
    $table->foreign('sirene_id')->references('id')->on('sirenes')->onDelete('restrict');
    $table->foreign('abonnement_id')->references('id')->on('abonnements')->onDelete('restrict');
    $table->foreign('calendrier_id')->references('id')->on('calendriers_scolaires')->onDelete('restrict');
    $table->foreign('cree_par')->references('id')->on('users')->onDelete('restrict');
});
```

**Contraintes** :
- 6 FK avec `onDelete('restrict')`
- Soft deletes activé
- Plusieurs champs JSON pour flexibilité

**Migration complémentaire** : `2025_11_04_083824_add_jours_feries_exceptions_to_programmations_table.php`
- Ajoute `jours_feries_exceptions` (JSON)

---

### jours_feries

**Fichier** : `database/migrations/2025_10_31_072404_create_jours_feries_table.php`

```php
Schema::create('jours_feries', function (Blueprint $table) {
    $table->string('id', 26)->primary(); // ULID
    $table->string('calendrier_id', 26);
    $table->string('ecole_id', 26)->nullable();
    $table->string('pays_id', 26)->nullable();
    $table->string('libelle'); // MCD
    $table->string('nom')->nullable();
    $table->date('date_ferie'); // MCD
    $table->date('date')->nullable();
    $table->enum('type', ['national', 'personnalise'])->default('national');
    $table->boolean('recurrent')->default(false);
    $table->boolean('actif')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('calendrier_id')->references('id')->on('calendriers_scolaires')->onDelete('restrict');
    $table->foreign('ecole_id')->references('id')->on('ecoles')->onDelete('restrict');
    $table->foreign('pays_id')->references('id')->on('pays')->onDelete('restrict');
});
```

**Migration complémentaire** : `2025_11_04_091734_update_jours_feries_table_for_types_and_dates.php`
- Ajoute `est_national` (boolean)
- Renomme/ajoute champs pour compatibilité

---

## 7. Traits spécialisés

### HasChaineCryptee

**Fichier** : `app/Traits/HasChaineCryptee.php`

#### Responsabilité
Génère et gère les chaînes de programmation (lisible et cryptée) pour les modules physiques de sirènes.

#### Méthodes

##### 1. genererChaineProgrammee(): string

Génère une chaîne **lisible** pour affichage.

```php
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
```

**Exemple de sortie** :
```
"Programmation: Trimestre 1 | Jours: Monday, Tuesday, Wednesday, Thursday, Friday | Horaires: 07:30, 12:00, 15:00 | Période: 01/09/2024 au 20/12/2024"
```

##### 2. genererChaineCryptee(): string

Génère une chaîne **cryptée** pour le module physique.

```php
public function genererChaineCryptee(): string
{
    $tokenService = app(TokenEncryptionService::class);

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
        'signature' => Str::random(32), // Anti-duplication
    ];

    return $tokenService->encryptToken($data);
}
```

**Exemple de sortie** :
```
"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJwcm9ncmFtbWF0aW9uX2lkIjoiMDFBQkMxMjMi..."
```

##### 3. sauvegarderChainesCryptees(): void

Génère et sauvegarde les deux chaînes en base.

```php
public function sauvegarderChainesCryptees(): void
{
    $this->update([
        'chaine_programmee' => $this->genererChaineProgrammee(),
        'chaine_cryptee' => $this->genererChaineCryptee(),
    ]);
}
```

**Appelé automatiquement** :
- Lors de la création d'une programmation
- Lors de la modification (si champs critiques changent)

##### 4. regenererChainesCryptees(): void

Alias de `sauvegarderChainesCryptees()` + refresh du modèle.

```php
public function regenererChainesCryptees(): void
{
    $this->sauvegarderChainesCryptees();
    $this->refresh();
}
```

##### 5. decrypterChaineCryptee(): ?array

Décrypte la chaîne cryptée (pour vérification/débogage).

```php
public function decrypterChaineCryptee(): ?array
{
    if (!$this->chaine_cryptee) {
        return null;
    }

    try {
        $tokenService = app(TokenEncryptionService::class);
        return $tokenService->decryptToken($this->chaine_cryptee);
    } catch (\Exception $e) {
        Log::error("Erreur décryptage: " . $e->getMessage());
        return null;
    }
}
```

**Usage** :
```php
$programmation = Programmation::find($id);
$decrypted = $programmation->decrypterChaineCryptee();

// Résultat :
// [
//   'programmation_id' => '01ABC123',
//   'horaires' => ['07:30', '12:00', '15:00'],
//   'jours' => ['Monday', 'Tuesday', ...],
//   ...
// ]
```

---

## Diagramme de relations

```
┌──────────────────┐
│      Pays        │
│ - id (ULID)      │
│ - nom            │
└────────┬─────────┘
         │ 1
         │ N
┌────────▼─────────────────────────────────┐
│       CalendrierScolaire                 │
│ - id (ULID)                              │
│ - pays_id (FK)                           │
│ - annee_scolaire (Ex: 2024-2025)         │
│ - date_rentree                           │
│ - date_fin_annee                         │
│ - periodes_vacances (JSON)               │
│ - jours_feries_defaut (JSON)             │
└────────┬─────────────────────────────────┘
         │ 1
         │ N
┌────────▼──────────┐      ┌────────────────────────┐
│   JourFerie       │      │   Programmation        │
│ - id (ULID)       │      │ - id (ULID)            │
│ - calendrier_id FK├──────┤ - calendrier_id FK     │
│ - ecole_id FK     │  N   │ - sirene_id FK         │
│ - pays_id FK      │  1   │ - ecole_id FK          │
│ - intitule_journee│      │ - site_id FK           │
│ - date            │      │ - abonnement_id FK     │
│ - recurrent       │      │ - horaires_sonneries   │
│ - actif           │      │ - jour_semaine         │
│ - est_national    │      │ - jours_feries_inclus  │
└───────────────────┘      │ - jours_feries_exceptions│
         │                 │ - chaine_programmee    │
         │ N               │ - chaine_cryptee       │
         │                 │ - date_debut/date_fin  │
         │                 └────────────────────────┘
         │ 1                        │ N
┌────────▼─────────┐                │ 1
│     Ecole        │◄───────────────┘
│ - id (ULID)      │
│ - nom_etablissement│
│ - code_etablissement│
└──────────────────┘
         │ 1
         │ N
┌────────▼─────────┐
│      Site        │
│ - id (ULID)      │
│ - ecole_id FK    │
│ - nom_site       │
└──────────────────┘
         │ 1
         │ N
┌────────▼─────────┐
│     Sirene       │
│ - id (ULID)      │
│ - site_id FK     │
│ - ecole_id FK    │
│ - numero_serie   │
│ - statut         │
└──────────────────┘
```

---

## Cas d'utilisation

### Cas 1 : Créer un calendrier scolaire national

**Requête** :
```http
POST /api/calendrier-scolaire
Authorization: Bearer {token}
Content-Type: application/json

{
  "pays_id": "01HJKM2VW3XYZ9ABCDEFGH1234",
  "annee_scolaire": "2024-2025",
  "description": "Calendrier scolaire Sénégal 2024-2025",
  "date_rentree": "2024-09-02",
  "date_fin_annee": "2025-06-30",
  "periodes_vacances": [
    {
      "nom": "Vacances de Noël",
      "date_debut": "2024-12-20",
      "date_fin": "2025-01-05"
    },
    {
      "nom": "Vacances de Pâques",
      "date_debut": "2025-04-14",
      "date_fin": "2025-04-28"
    }
  ],
  "jours_feries_defaut": [
    {
      "nom": "Jour de l'an",
      "date": "2025-01-01",
      "recurrent": true
    },
    {
      "nom": "Fête de l'indépendance",
      "date": "2025-04-04",
      "recurrent": true
    },
    {
      "nom": "Fête du travail",
      "date": "2025-05-01",
      "recurrent": true
    }
  ],
  "actif": true
}
```

**Workflow** :
1. CalendrierScolaireController::store() valide la requête
2. CalendrierScolaireService::create() crée le calendrier
3. Boucle sur `jours_feries_defaut` et crée des JourFerie associés
4. Transaction DB commit
5. Retourne le calendrier avec `joursFeries` chargés

---

### Cas 2 : École ajoute un jour férié spécifique

**Requête** :
```http
POST /api/ecoles/{ecoleId}/jours-feries
Authorization: Bearer {token}
Content-Type: application/json

{
  "calendrier_id": "01CALENDRIER123",
  "intitule_journee": "Journée Portes Ouvertes",
  "date": "2024-11-15",
  "recurrent": false,
  "actif": true,
  "est_national": false
}
```

**Résultat** :
- Jour férié créé avec `ecole_id` renseigné
- Sera pris en compte lors du calcul des jours de classe pour cette école
- Visible uniquement pour cette école (pas national)

---

### Cas 3 : École désactive un jour férié national

**Requête** :
```http
POST /api/ecoles/{ecoleId}/jours-feries
Authorization: Bearer {token}
Content-Type: application/json

{
  "calendrier_id": "01CALENDRIER123",
  "intitule_journee": "Fête du travail",
  "date": "2025-05-01",
  "actif": false,  ← Désactive pour cette école
  "est_national": true
}
```

**Résultat** :
- Surcharge le jour férié national
- Lors du calcul des jours de classe pour cette école, le 1er mai sera compté

---

### Cas 4 : Créer une programmation hebdomadaire

**Requête** :
```http
POST /api/sirenes/01SIRENE123/programmations
Authorization: Bearer {token}
Content-Type: application/json

{
  "nom_programmation": "Horaires Septembre-Décembre 2024",
  "horaires_sonneries": ["07:30", "12:00", "15:00", "17:00"],
  "jour_semaine": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
  "jours_feries_inclus": false,
  "jours_feries_exceptions": [],
  "calendrier_id": "01CALENDRIER123",
  "date_debut": "2024-09-02",
  "date_fin": "2024-12-20",
  "actif": true
}
```

**Workflow** :
1. ProgrammationController::store() valide et ajoute `sirene_id`
2. ProgrammationService::create() :
   - Récupère sirène → école → abonnement actif
   - Auto-remplit `ecole_id`, `site_id`, `abonnement_id`, `cree_par`
   - Crée la programmation
   - Appelle `Programmation::sauvegarderChainesCryptees()`
3. Trait HasChaineCryptee génère :
   - `chaine_programmee` : "Programmation: Horaires Septembre-Décembre 2024 | Jours: Monday, Tuesday, Wednesday, Thursday, Friday | Horaires: 07:30, 12:00, 15:00, 17:00 | Période: 02/09/2024 au 20/12/2024"
   - `chaine_cryptee` : Token JWT crypté avec toutes les données
4. Programmation sauvegardée avec les chaînes
5. Retourne objet Programmation complet

**Réponse** :
```json
{
  "success": true,
  "message": "Programmation créée avec succès.",
  "data": {
    "id": "01PROG123",
    "ecole_id": "01ECOLE123",
    "site_id": "01SITE123",
    "sirene_id": "01SIRENE123",
    "abonnement_id": "01ABON123",
    "calendrier_id": "01CALENDRIER123",
    "nom_programmation": "Horaires Septembre-Décembre 2024",
    "horaires_sonneries": ["07:30", "12:00", "15:00", "17:00"],
    "jour_semaine": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
    "jours_feries_inclus": false,
    "jours_feries_exceptions": [],
    "chaine_programmee": "Programmation: Horaires Septembre-Décembre 2024 | Jours: Monday, Tuesday, Wednesday, Thursday, Friday | Horaires: 07:30, 12:00, 15:00, 17:00 | Période: 02/09/2024 au 20/12/2024",
    "chaine_cryptee": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "date_debut": "2024-09-02",
    "date_fin": "2024-12-20",
    "actif": true,
    "cree_par": "01USER123",
    "created_at": "2024-11-15T10:30:00.000000Z",
    "updated_at": "2024-11-15T10:30:00.000000Z"
  }
}
```

---

### Cas 5 : Ajouter une exception de jour férié

**Scénario** : L'école veut sonner le 25 décembre 2024 pour une cérémonie spéciale.

**Requête** :
```http
PUT /api/sirenes/01SIRENE123/programmations/01PROG123
Authorization: Bearer {token}
Content-Type: application/json

{
  "jours_feries_exceptions": [
    {
      "date": "2024-12-25",
      "action": "include",
      "raison": "Cérémonie de Noël"
    }
  ]
}
```

**Résultat** :
- Le champ `jours_feries_exceptions` est mis à jour
- `ProgrammationService::update()` détecte le changement
- Les chaînes cryptées sont régénérées
- Le 25 décembre, la programmation sera active malgré `jours_feries_inclus = false`

---

### Cas 6 : Récupérer les programmations effectives pour une date

**Requête** :
```http
GET /api/sirenes/01SIRENE123/programmations?date=2024-12-25
Authorization: Bearer {token}
```

**Workflow** :
1. ProgrammationController::index() détecte le paramètre `date`
2. Appelle `ProgrammationService::getEffectiveProgrammationsForSirene()`
3. Service :
   - Charge toutes les programmations de la sirène
   - Détermine le jour de la semaine (Wednesday)
   - Vérifie si 25/12/2024 est un jour férié (oui)
   - Filtre les programmations :
     - Vérifie `jour_semaine` contient "Wednesday"
     - Vérifie `jours_feries_inclus` ou exceptions
     - Filtre celles qui ne correspondent pas
4. Retourne uniquement les programmations actives ce jour

**Réponse** :
```json
{
  "success": true,
  "message": "Effective programmations for sirene retrieved successfully.",
  "data": [
    {
      "id": "01PROG123",
      "nom_programmation": "Horaires Septembre-Décembre 2024",
      "horaires_sonneries": ["07:30", "12:00", "15:00", "17:00"],
      "jour_semaine": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
      "jours_feries_inclus": false,
      "jours_feries_exceptions": [
        {
          "date": "2024-12-25",
          "action": "include",
          "raison": "Cérémonie de Noël"
        }
      ]
    }
  ]
}
```

---

### Cas 7 : Calculer les jours de classe

**Requête** :
```http
GET /api/calendrier-scolaire/01CALENDRIER123/calculate-school-days?ecole_id=01ECOLE123
Authorization: Bearer {token}
```

**Workflow** :
1. CalendrierScolaireController::calculateSchoolDays()
2. CalendrierScolaireService::calculateSchoolDays() :
   - Charge calendrier avec jours fériés nationaux
   - Charge jours fériés spécifiques de l'école
   - Merge les deux listes (école surcharge national)
   - Parcourt chaque jour entre `date_rentree` et `date_fin_annee`
   - Exclut : weekends, jours fériés, périodes de vacances
   - Compte les jours restants
3. Retourne le nombre

**Réponse** :
```json
{
  "success": true,
  "data": {
    "school_days": 180
  }
}
```

---

### Cas 8 : Récupérer le calendrier avec jours fériés mergés

**Requête** :
```http
GET /api/ecoles/me/calendrier-scolaire/with-ecole-holidays?annee_scolaire=2024-2025
Authorization: Bearer {token}
```

**Workflow** :
1. EcoleController::getCalendrierScolaireWithJoursFeries()
2. CalendrierScolaireService::getCalendrierScolaireWithJoursFeries() :
   - Charge calendrier pour l'année scolaire
   - Charge jours fériés nationaux
   - Charge jours fériés de l'école connectée
   - Merge les deux (école surcharge national)
   - Retourne calendrier avec `jours_feries_merged`

**Réponse** :
```json
{
  "success": true,
  "data": {
    "id": "01CALENDRIER123",
    "annee_scolaire": "2024-2025",
    "date_rentree": "2024-09-02",
    "date_fin_annee": "2025-06-30",
    "periodes_vacances": [...],
    "jours_feries_merged": [
      {
        "id": "01JF001",
        "nom": "Jour de l'an",
        "date": "2025-01-01",
        "actif": true,
        "type": "national",
        "recurrent": true
      },
      {
        "id": "01JF002",
        "nom": "Journée Portes Ouvertes",
        "date": "2024-11-15",
        "actif": true,
        "type": "personnalise",
        "recurrent": false
      },
      {
        "id": "01JF003",
        "nom": "Fête du travail",
        "date": "2025-05-01",
        "actif": false,  ← Désactivé par l'école
        "type": "national",
        "recurrent": true
      }
    ]
  }
}
```

---

## 🔐 Sécurité et bonnes pratiques

### 1. Permissions granulaires
- Chaque action est protégée par un Gate spécifique
- Exemple : `voir_les_calendriers_scolaires`, `creer_programmation`

### 2. Validation stricte
- Form Request classes pour validation
- Rules spécifiques par endpoint (Create vs Update)

### 3. Transactions DB
- Toutes les opérations multi-tables utilisent `DB::beginTransaction()`
- Rollback automatique en cas d'erreur

### 4. Logging complet
- Création/modification de programmations loggées
- Régénération de chaînes cryptées loggée avec raison
- Erreurs capturées et loggées

### 5. Soft deletes
- Tous les modèles utilisent soft deletes
- Récupération possible en cas d'erreur

### 6. Cryptage sécurisé
- TokenEncryptionService pour chaînes cryptées
- Signature unique anti-duplication
- Timestamp de génération

### 7. Auto-remplissage intelligent
- `ProgrammationService::create()` auto-remplit :
  - `ecole_id`, `site_id`, `abonnement_id` depuis la sirène
  - `cree_par` depuis l'utilisateur authentifié
- Évite les erreurs de saisie manuelle

---

## 📊 Résumé des endpoints

### Calendrier Scolaire

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/calendrier-scolaire` | Liste calendriers |
| POST | `/api/calendrier-scolaire` | Créer calendrier |
| GET | `/api/calendrier-scolaire/{id}` | Détails calendrier |
| PUT | `/api/calendrier-scolaire/{id}` | Modifier calendrier |
| DELETE | `/api/calendrier-scolaire/{id}` | Supprimer calendrier |
| GET | `/api/calendrier-scolaire/{id}/jours-feries` | Jours fériés |
| POST | `/api/calendrier-scolaire/{id}/jours-feries/bulk` | Créer jours fériés en masse |
| PUT | `/api/calendrier-scolaire/{id}/jours-feries/bulk` | Modifier jours fériés en masse |
| GET | `/api/calendrier-scolaire/{id}/calculate-school-days` | Calculer jours de classe |

### Programmation

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/sirenes/{sirene}/programmations` | Liste programmations |
| GET | `/api/sirenes/{sirene}/programmations?date=...` | Programmations effectives |
| POST | `/api/sirenes/{sirene}/programmations` | Créer programmation |
| GET | `/api/sirenes/{sirene}/programmations/{prog}` | Détails programmation |
| PUT | `/api/sirenes/{sirene}/programmations/{prog}` | Modifier programmation |
| DELETE | `/api/sirenes/{sirene}/programmations/{prog}` | Supprimer programmation |

### Écoles

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/ecoles/me/calendrier-scolaire/with-ecole-holidays` | Calendrier + jours fériés mergés |

---

**Version** : 1.0.0
**Date** : 15 novembre 2024
**Auteur** : Équipe Backend Sirene d'Ecole
