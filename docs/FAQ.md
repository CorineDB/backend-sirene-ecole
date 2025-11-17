# Questions Fréquentes (FAQ)

## 📚 Table des matières

1. [Pourquoi le formatage JSON est dans le Controller et pas dans le Service ?](#pourquoi-le-formatage-json-est-dans-le-controller-et-pas-dans-le-service)
2. [Quand utiliser un Repository vs Eloquent direct ?](#quand-utiliser-un-repository-vs-eloquent-direct)
3. [Dois-je toujours créer une interface ?](#dois-je-toujours-créer-une-interface)
4. [Où mettre la validation métier ?](#où-mettre-la-validation-métier)

---

## Pourquoi le formatage JSON est dans le Controller et pas dans le Service ?

### ❓ La Question

> "Pourquoi dans votre documentation, la réponse JSON est créée dans le Controller avec `JsonResponseTrait` et `Resource`, et non directement dans le Service ?"

### ✅ La Réponse : Séparation des Responsabilités

C'est une question **fondamentale** qui touche au cœur de l'architecture SOLID ! Voici pourquoi :

---

### 🎯 Principe 1 : Single Responsibility (SOLID)

Chaque couche a **une seule responsabilité** :

```
┌─────────────────────────────────────┐
│         CONTROLLER                  │
│  Responsabilité : PRÉSENTATION      │
│  - Recevoir requête HTTP            │
│  - Appeler le service               │
│  - FORMATER la réponse (JSON)       │  ← ICI
│  - Retourner réponse HTTP           │
└─────────────────────────────────────┘
              ▼
┌─────────────────────────────────────┐
│         SERVICE                     │
│  Responsabilité : LOGIQUE MÉTIER    │
│  - Règles de gestion                │
│  - Orchestration                    │
│  - Transactions                     │
│  - RETOURNER des objets métier      │  ← ICI
└─────────────────────────────────────┘
              ▼
┌─────────────────────────────────────┐
│         REPOSITORY                  │
│  Responsabilité : ACCÈS DONNÉES     │
│  - Requêtes SQL/Eloquent            │
│  - RETOURNER des Models             │  ← ICI
└─────────────────────────────────────┘
```

---

### 🔄 Exemple concret : Mauvaise vs Bonne pratique

#### ❌ MAUVAIS : Service qui retourne du JSON

```php
// ❌ Service qui retourne du JSON
class EcoleService
{
    public function create(array $data): JsonResponse  // ⚠️ Problème !
    {
        $ecole = $this->ecoleRepository->create($data);

        // ⚠️ Le Service connaît HTTP et JSON !
        return response()->json([
            'success' => true,
            'message' => 'École créée',
            'data' => [
                'id' => $ecole->id,
                'nom' => $ecole->nom,
                // ...
            ]
        ], 201);
    }
}

// Controller
class EcoleController
{
    public function store(CreateEcoleRequest $request)
    {
        // Le Controller ne fait rien, juste passer la réponse
        return $this->ecoleService->create($request->validated());
    }
}
```

**Problèmes :**
1. ❌ Le Service **dépend de HTTP** (`JsonResponse`)
2. ❌ Impossible de réutiliser le Service ailleurs (CLI, Queue, GraphQL)
3. ❌ Difficile à tester (doit parser du JSON dans les tests)
4. ❌ Violation du principe de responsabilité unique
5. ❌ Couplage fort avec la couche de présentation

---

#### ✅ BON : Service qui retourne un objet métier

```php
// ✅ Service qui retourne un objet métier
class EcoleService implements EcoleServiceInterface
{
    public function create(array $data): Ecole  // ✅ Retourne un objet métier
    {
        DB::beginTransaction();

        try {
            // Logique métier pure
            $ecole = $this->ecoleRepository->create($data);
            $ecole->generateCodeEtablissement();
            $this->abonnementService->createForEcole($ecole);

            DB::commit();

            return $ecole;  // ✅ Objet métier, pas JSON

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

// Controller
class EcoleController extends Controller
{
    use JsonResponseTrait;

    public function store(CreateEcoleRequest $request): JsonResponse
    {
        // 1. Appeler le service (logique métier)
        $ecole = $this->ecoleService->create($request->validated());

        // 2. Formater pour HTTP/JSON (présentation)
        return $this->createdResponse(
            new EcoleResource($ecole),
            'École créée avec succès'
        );
    }
}
```

**Avantages :**
1. ✅ Le Service est **indépendant de HTTP**
2. ✅ Réutilisable partout (API REST, GraphQL, CLI, Jobs)
3. ✅ Facile à tester (on teste un objet `Ecole`)
4. ✅ Respect du principe de responsabilité unique
5. ✅ Découplage entre logique métier et présentation

---

### 🚀 Avantage : Réutilisabilité

Imaginez ces scénarios où vous devez **réutiliser** la même logique métier :

#### Scénario 1 : API REST (JSON)

```php
// API REST Controller
class EcoleController
{
    public function store(CreateEcoleRequest $request): JsonResponse
    {
        $ecole = $this->ecoleService->create($request->validated());

        // Format JSON pour API REST
        return $this->createdResponse(
            new EcoleResource($ecole),
            'École créée avec succès'
        );
    }
}
```

#### Scénario 2 : API GraphQL

```php
// GraphQL Resolver
class EcoleResolver
{
    public function createEcole($root, array $args): array
    {
        $ecole = $this->ecoleService->create($args['input']);

        // Format GraphQL
        return [
            'ecole' => $ecole,
            'success' => true,
        ];
    }
}
```

#### Scénario 3 : Commande Artisan (CLI)

```php
// Commande CLI
class ImportEcolesCommand extends Command
{
    public function handle()
    {
        $data = $this->readCsvFile('ecoles.csv');

        foreach ($data as $row) {
            $ecole = $this->ecoleService->create($row);

            // Format texte pour CLI
            $this->info("École créée : {$ecole->nom} ({$ecole->code_etablissement})");
        }
    }
}
```

#### Scénario 4 : Job en Queue

```php
// Job asynchrone
class CreateEcoleJob implements ShouldQueue
{
    public function handle(EcoleServiceInterface $ecoleService)
    {
        $ecole = $ecoleService->create($this->data);

        // Pas de réponse HTTP, juste enregistrer
        Log::info("École créée en arrière-plan : {$ecole->id}");
    }
}
```

#### Scénario 5 : Export XML

```php
// Export XML Controller
class XmlExportController
{
    public function export()
    {
        $ecoles = $this->ecoleService->getAll();

        // Format XML
        return response()->xml([
            'ecoles' => $ecoles->map(fn($e) => [
                'id' => $e->id,
                'nom' => $e->nom,
            ])
        ]);
    }
}
```

**🎯 Le même Service pour 5 contextes différents !**

Si le Service retournait du JSON, il serait **inutilisable** dans les scénarios 2, 3, 4 et 5.

---

### 🧪 Avantage : Testabilité

#### ❌ Test difficile avec Service retournant JSON

```php
public function test_can_create_ecole()
{
    $data = ['nom' => 'École Test', 'email' => 'test@ecole.fr'];

    // ❌ Le service retourne JsonResponse
    $response = $this->ecoleService->create($data);

    // ❌ Doit parser JSON, vérifier status HTTP...
    $this->assertEquals(201, $response->getStatusCode());
    $content = json_decode($response->getContent(), true);
    $this->assertEquals('École Test', $content['data']['nom']);

    // ❌ Complexe et fragile
}
```

#### ✅ Test facile avec Service retournant un objet

```php
public function test_can_create_ecole()
{
    $data = ['nom' => 'École Test', 'email' => 'test@ecole.fr'];

    // ✅ Le service retourne un objet Ecole
    $ecole = $this->ecoleService->create($data);

    // ✅ Test simple et clair
    $this->assertInstanceOf(Ecole::class, $ecole);
    $this->assertEquals('École Test', $ecole->nom);
    $this->assertNotNull($ecole->code_etablissement);

    // ✅ Simple et robuste
}
```

---

### 📋 Règle d'or : Qui fait quoi ?

| Composant | Responsabilité | Retourne | Ne retourne JAMAIS |
|-----------|----------------|----------|-------------------|
| **Repository** | Accès données | `Model`, `Collection` | ❌ JSON, Array associatif |
| **Service** | Logique métier | `Model`, `Collection`, primitives | ❌ `JsonResponse`, `array` formaté pour API |
| **Controller** | HTTP & Présentation | `JsonResponse` | ❌ Logique métier |
| **Resource** | Formatage JSON | `array` | ❌ Logique métier |

---

### 🎨 Où formater la réponse JSON ?

Le formatage JSON se fait à **deux endroits** dans le Controller :

#### 1️⃣ API Resource (formatage des données)

```php
namespace App\Http\Resources;

class EcoleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'email' => $this->email,
            'code_etablissement' => $this->code_etablissement,
            'ville' => new VilleResource($this->whenLoaded('ville')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

**Responsabilité :** Transformer un objet `Ecole` en tableau pour JSON

#### 2️⃣ JsonResponseTrait (structure de la réponse)

```php
namespace App\Traits;

trait JsonResponseTrait
{
    protected function createdResponse($data, string $message = 'Created')
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], 201);
    }
}
```

**Responsabilité :** Standardiser la structure des réponses HTTP

#### 3️⃣ Controller (assembler le tout)

```php
class EcoleController extends Controller
{
    use JsonResponseTrait;

    public function store(CreateEcoleRequest $request): JsonResponse
    {
        // 1. Logique métier (Service)
        $ecole = $this->ecoleService->create($request->validated());

        // 2. Formatage données (Resource)
        $resource = new EcoleResource($ecole);

        // 3. Structure réponse (Trait)
        return $this->createdResponse($resource, 'École créée avec succès');
    }
}
```

---

### 💡 En résumé

| ❌ Service retourne JSON | ✅ Service retourne objet métier |
|-------------------------|----------------------------------|
| Couplage fort avec HTTP | Indépendant de HTTP |
| Non réutilisable | Réutilisable partout |
| Difficile à tester | Facile à tester |
| Violation SOLID | Respect SOLID |
| Un seul usage (API REST) | Multiples usages (API, CLI, Jobs, GraphQL) |

---

### 🎯 Architecture en pratique

```
┌─────────────────────────────────────────────────────────────┐
│  CLIENT (Postman, Frontend, Mobile App)                     │
└─────────────────────────┬───────────────────────────────────┘
                          │ HTTP Request
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  CONTROLLER (Couche Présentation)                           │
│  ┌────────────────────────────────────────────────────┐     │
│  │ 1. Recevoir Request                                │     │
│  │ 2. Appeler Service → obtenir Ecole (objet)        │     │
│  │ 3. Transformer Ecole → EcoleResource (array)      │     │
│  │ 4. Créer JsonResponse                             │     │
│  │ 5. Retourner HTTP Response                        │     │
│  └────────────────────────────────────────────────────┘     │
└─────────────────────────┬───────────────────────────────────┘
                          │ Ecole (objet)
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  SERVICE (Couche Métier)                                    │
│  ┌────────────────────────────────────────────────────┐     │
│  │ 1. Valider règles métier                          │     │
│  │ 2. Orchestrer Repositories                        │     │
│  │ 3. Gérer transactions                             │     │
│  │ 4. Retourner Ecole (objet métier)                │     │  ← PAS JSON !
│  └────────────────────────────────────────────────────┘     │
└─────────────────────────┬───────────────────────────────────┘
                          │ Ecole (objet)
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  REPOSITORY (Couche Données)                                │
│  ┌────────────────────────────────────────────────────┐     │
│  │ 1. Requête Eloquent                               │     │
│  │ 2. Retourner Model Ecole                          │     │  ← PAS JSON !
│  └────────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

---

### 📚 Lectures complémentaires

- [ARCHITECTURE.md - Principe de responsabilité unique](ARCHITECTURE.md#s---single-responsibility-principle-responsabilité-unique)
- [DEV_GUIDE.md - API Resources](DEV_GUIDE.md#étape-10--créer-lapi-resource)
- [BEST_PRACTICES.md - Structure du code](BEST_PRACTICES.md#structure-du-code)

---

## Quand utiliser un Repository vs Eloquent direct ?

### ✅ Utiliser un Repository

```php
// ✅ Dans un Service
class EcoleService
{
    public function __construct(
        private EcoleRepositoryInterface $ecoleRepository
    ) {}

    public function create(array $data): Ecole
    {
        // ✅ Toujours via Repository
        return $this->ecoleRepository->create($data);
    }
}
```

### ❌ NE PAS utiliser Eloquent direct dans un Service

```php
// ❌ MAUVAIS
class EcoleService
{
    public function create(array $data): Ecole
    {
        // ❌ Ne jamais faire ça dans un Service
        return Ecole::create($data);
    }
}
```

### 💡 Pourquoi ?

1. **Testabilité** : Peut mocker le Repository facilement
2. **Réutilisabilité** : Centralise les requêtes
3. **Maintenabilité** : Changement de BDD facile
4. **SOLID** : Respect de l'inversion de dépendances

---

## Dois-je toujours créer une interface ?

### ✅ OUI pour les Services et Repositories

```php
// ✅ Toujours créer une interface
interface EcoleServiceInterface { }
class EcoleService implements EcoleServiceInterface { }

interface EcoleRepositoryInterface { }
class EcoleRepository implements EcoleRepositoryInterface { }
```

### 💡 Pourquoi ?

1. **Inversion de dépendances (SOLID - D)**
2. **Testabilité** : Permet de créer des mocks
3. **Flexibilité** : Changement d'implémentation facile
4. **Contrat** : Définit clairement les méthodes disponibles

### ⚠️ NON pour les autres classes

```php
// ⚠️ Pas besoin d'interface
class CreateEcoleRequest extends FormRequest { }
class EcoleResource extends JsonResource { }
class EcoleController extends Controller { }
class Ecole extends Model { }
```

---

## Où mettre la validation métier ?

### 🎯 Deux types de validation

#### 1️⃣ Validation HTTP (FormRequest)

```php
// ✅ Dans FormRequest
class CreateEcoleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:255',
            'email' => 'required|email|unique:ecoles',
        ];
    }
}
```

**Utilisation :** Validation des **données entrantes HTTP**

#### 2️⃣ Validation Métier (Service)

```php
// ✅ Dans Service
class EcoleService
{
    public function create(array $data): Ecole
    {
        // Validation métier
        if ($this->ecoleRepository->emailExists($data['email'])) {
            throw ValidationException::withMessages([
                'email' => 'Cet email est déjà utilisé.'
            ]);
        }

        // Règle métier : une ville ne peut avoir plus de 10 écoles
        if ($this->ecoleRepository->countByVille($data['ville_id']) >= 10) {
            throw new BusinessException(
                'Cette ville a déjà atteint le nombre maximum d\'écoles.'
            );
        }

        return $this->ecoleRepository->create($data);
    }
}
```

**Utilisation :** Validation des **règles de gestion métier**

### 📋 Règle simple

| Type | Où ? | Exemple |
|------|------|---------|
| Validation **format** | FormRequest | `email`, `max:255`, `regex` |
| Validation **existence** | FormRequest | `exists:villes,id`, `unique:ecoles,email` |
| Validation **métier** | Service | Limites, règles de gestion, cohérence |

---

Vous avez d'autres questions ? N'hésitez pas ! 🚀
