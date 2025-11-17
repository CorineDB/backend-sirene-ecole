# Architecture SOLID - Backend Sirene École

## 📚 Table des matières

1. [Introduction](#introduction)
2. [Vue d'ensemble de l'architecture](#vue-densemble-de-larchitecture)
3. [Principes SOLID](#principes-solid)
4. [Architecture en couches](#architecture-en-couches)
5. [Patterns de conception](#patterns-de-conception)
6. [Structure des dossiers](#structure-des-dossiers)
7. [Flow de requête HTTP](#flow-de-requête-http)

---

## Introduction

Ce document explique l'architecture du projet **Backend Sirene École**, une API REST développée avec **Laravel 12** suivant les principes **SOLID** et les meilleures pratiques de développement.

### Objectifs de l'architecture

- ✅ **Séparation des responsabilités** : Chaque classe a une seule responsabilité
- ✅ **Maintenabilité** : Code facile à modifier et à étendre
- ✅ **Testabilité** : Chaque composant peut être testé indépendamment
- ✅ **Réutilisabilité** : Code modulaire et réutilisable
- ✅ **Lisibilité** : Code clair et bien organisé

---

## Vue d'ensemble de l'architecture

Notre application suit une **architecture en 3 couches** (3-Tier Architecture) :

```
┌─────────────────────────────────────────────────────────┐
│                    COUCHE PRÉSENTATION                   │
│                                                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │ Controllers  │  │Form Requests │  │  Resources   │  │
│  │              │  │              │  │  (JSON API)  │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
│                                                          │
│  Responsabilité : Gestion HTTP, Validation, Formatage   │
└────────────────────────┬─────────────────────────────────┘
                         │ Injection de dépendances
                         ▼
┌─────────────────────────────────────────────────────────┐
│                   COUCHE MÉTIER (BUSINESS)               │
│                                                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Services   │  │    Enums     │  │    Traits    │  │
│  │              │  │              │  │              │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
│                                                          │
│  Responsabilité : Logique métier, Règles de gestion     │
└────────────────────────┬─────────────────────────────────┘
                         │ Injection de dépendances
                         ▼
┌─────────────────────────────────────────────────────────┐
│                  COUCHE ACCÈS AUX DONNÉES                │
│                                                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │ Repositories │  │    Models    │  │  Migrations  │  │
│  │              │  │  (Eloquent)  │  │              │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
│                                                          │
│  Responsabilité : Accès BDD, Persistance des données    │
└────────────────────────┬─────────────────────────────────┘
                         │
                         ▼
                   ┌──────────┐
                   │ Database │
                   │PostgreSQL│
                   └──────────┘
```

---

## Principes SOLID

### 🔹 S - Single Responsibility Principle (Responsabilité unique)

> Une classe ne devrait avoir qu'une seule raison de changer.

**Dans notre projet :**

- **Controller** : Gère uniquement les requêtes/réponses HTTP
- **Service** : Contient uniquement la logique métier
- **Repository** : Gère uniquement l'accès aux données
- **FormRequest** : Gère uniquement la validation des données

> 💡 **Question fréquente :** Pourquoi le formatage JSON est dans le Controller et pas dans le Service ?
> **Réponse :** Le Service doit retourner des objets métier (réutilisables partout : API, CLI, Jobs), tandis que le Controller gère la présentation HTTP/JSON.
> 📖 [Voir l'explication détaillée dans la FAQ →](FAQ.md#pourquoi-le-formatage-json-est-dans-le-controller-et-pas-dans-le-service)

**Exemple :**

```php
// ❌ MAUVAIS : Controller avec trop de responsabilités
class EcoleController {
    public function store(Request $request) {
        // Validation
        $validated = $request->validate([...]);

        // Logique métier
        $ecole = new Ecole($validated);
        $ecole->code_etablissement = 'ECO-'.rand(1000,9999);

        // Accès BDD
        $ecole->save();

        // Email
        Mail::to($ecole->email)->send(new WelcomeEmail());

        return response()->json($ecole);
    }
}

// ✅ BON : Responsabilités séparées
class EcoleController {
    public function __construct(
        private EcoleServiceInterface $ecoleService
    ) {}

    public function store(CreateEcoleRequest $request) {
        $ecole = $this->ecoleService->create($request->validated());
        return new EcoleResource($ecole);
    }
}
```

---

### 🔹 O - Open/Closed Principle (Ouvert/Fermé)

> Les entités logicielles doivent être ouvertes à l'extension mais fermées à la modification.

**Dans notre projet :**

- Utilisation d'**interfaces** pour permettre l'extension
- **Traits** pour ajouter des fonctionnalités sans modifier les classes existantes
- **Events & Listeners** pour ajouter des comportements

**Exemple :**

```php
// Interface ouverte à l'extension
interface EcoleServiceInterface {
    public function create(array $data): Ecole;
}

// Implémentation de base
class EcoleService implements EcoleServiceInterface {
    public function create(array $data): Ecole {
        // Logique de création
    }
}

// Extension sans modification (nouveau service si besoin)
class EcoleServiceWithNotification extends EcoleService {
    public function create(array $data): Ecole {
        $ecole = parent::create($data);
        // Ajouter notification
        return $ecole;
    }
}
```

---

### 🔹 L - Liskov Substitution Principle (Substitution de Liskov)

> Les objets d'une classe dérivée doivent pouvoir remplacer les objets de la classe de base.

**Dans notre projet :**

- Toutes les implémentations respectent leur interface
- On peut remplacer une implémentation par une autre sans casser le code

**Exemple :**

```php
// Interface du contrat
interface SmsServiceInterface {
    public function send(string $phone, string $message): bool;
}

// Implémentation 1
class TwilioSmsService implements SmsServiceInterface {
    public function send(string $phone, string $message): bool {
        // Envoi via Twilio
    }
}

// Implémentation 2
class NexmoSmsService implements SmsServiceInterface {
    public function send(string $phone, string $message): bool {
        // Envoi via Nexmo
    }
}

// Les deux peuvent être utilisées de façon interchangeable
class OtpService {
    public function __construct(
        private SmsServiceInterface $smsService // Peut être Twilio ou Nexmo
    ) {}
}
```

---

### 🔹 I - Interface Segregation Principle (Ségrégation des interfaces)

> Aucun client ne devrait être forcé de dépendre de méthodes qu'il n'utilise pas.

**Dans notre projet :**

- Interfaces petites et spécialisées
- Pas d'interfaces "fourre-tout"

**Exemple :**

```php
// ❌ MAUVAIS : Interface trop grande
interface RepositoryInterface {
    public function find($id);
    public function all();
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
    public function findWithTrashed($id);
    public function restore($id);
    public function forceDelete($id);
}

// ✅ BON : Interfaces ségrégées
interface FindableInterface {
    public function find($id);
}

interface CreatableInterface {
    public function create(array $data);
}

interface SoftDeletableInterface {
    public function findWithTrashed($id);
    public function restore($id);
}

// Utilisation sélective
class EcoleRepository implements FindableInterface, CreatableInterface {
    // Implémente seulement ce dont on a besoin
}
```

---

### 🔹 D - Dependency Inversion Principle (Inversion de dépendances)

> Les modules de haut niveau ne doivent pas dépendre de modules de bas niveau. Les deux doivent dépendre d'abstractions.

**Dans notre projet :**

- **Injection de dépendances** via le constructeur
- Dépendance sur des **interfaces**, pas sur des classes concrètes
- Configuration dans **ServiceProvider**

**Exemple :**

```php
// ❌ MAUVAIS : Dépendance directe
class EcoleService {
    private $repository;

    public function __construct() {
        $this->repository = new EcoleRepository(); // ⚠️ Couplage fort
    }
}

// ✅ BON : Dépendance sur l'abstraction
class EcoleService implements EcoleServiceInterface {
    public function __construct(
        private EcoleRepositoryInterface $ecoleRepository // Interface
    ) {}
}

// Configuration dans ServiceProvider
class ServiceLayerServiceProvider extends ServiceProvider {
    public function register() {
        $this->app->bind(
            EcoleRepositoryInterface::class,
            EcoleRepository::class
        );
    }
}
```

---

## Architecture en couches

### 1️⃣ Couche Présentation (HTTP Layer)

**Responsabilité :** Gérer les requêtes/réponses HTTP

#### Controllers

```php
namespace App\Http\Controllers\Api;

class EcoleController extends Controller
{
    public function __construct(
        private EcoleServiceInterface $ecoleService
    ) {}

    public function index(Request $request)
    {
        $ecoles = $this->ecoleService->getAll($request->query());
        return EcoleResource::collection($ecoles);
    }

    public function store(CreateEcoleRequest $request)
    {
        $ecole = $this->ecoleService->create($request->validated());
        return new EcoleResource($ecole);
    }
}
```

**Responsabilités :**
- Recevoir les requêtes HTTP
- Appeler le service approprié
- Retourner une réponse formatée (JSON)

#### Form Requests (Validation)

```php
namespace App\Http\Requests;

class CreateEcoleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:255',
            'email' => 'required|email|unique:ecoles',
            'telephone' => 'required|string',
            'ville_id' => 'required|exists:villes,id',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom de l\'école est obligatoire',
            'email.unique' => 'Cet email est déjà utilisé',
        ];
    }
}
```

**Responsabilités :**
- Valider les données entrantes
- Autoriser la requête (`authorize()`)
- Formater les messages d'erreur

#### API Resources (Formatage JSON)

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

**Responsabilités :**
- Formater les données pour l'API
- Masquer/transformer certains champs
- Gérer les relations (eager loading)

---

### 2️⃣ Couche Métier (Business Layer)

**Responsabilité :** Contenir la logique métier

#### Services

```php
namespace App\Services;

class EcoleService implements EcoleServiceInterface
{
    public function __construct(
        private EcoleRepositoryInterface $ecoleRepository,
        private AbonnementServiceInterface $abonnementService,
    ) {}

    public function create(array $data): Ecole
    {
        DB::beginTransaction();

        try {
            // 1. Créer l'école
            $ecole = $this->ecoleRepository->create($data);

            // 2. Générer le code établissement
            $ecole->generateCodeEtablissement();

            // 3. Créer l'abonnement initial
            $this->abonnementService->createForEcole($ecole);

            DB::commit();
            return $ecole;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

**Responsabilités :**
- Logique métier complexe
- Orchestration entre plusieurs repositories
- Gestion des transactions
- Validation métier (règles de gestion)

---

### 3️⃣ Couche Accès aux Données (Data Layer)

**Responsabilité :** Interagir avec la base de données

#### Repositories

```php
namespace App\Repositories;

class EcoleRepository extends BaseRepository implements EcoleRepositoryInterface
{
    public function __construct(Ecole $model)
    {
        parent::__construct($model);
    }

    public function findByCodeEtablissement(string $code): ?Ecole
    {
        return $this->model
            ->where('code_etablissement', $code)
            ->first();
    }

    public function getActiveEcoles()
    {
        return $this->model
            ->where('statut', StatutEcole::ACTIVE)
            ->with('ville', 'abonnements')
            ->get();
    }
}
```

**Responsabilités :**
- Requêtes Eloquent
- Filtrage, tri, pagination
- Relations (eager loading)
- Pas de logique métier !

#### Models (Eloquent)

```php
namespace App\Models;

class Ecole extends Model
{
    use HasFactory, SoftDeletes, HasUlid, HasCodeEtablissement;

    protected $fillable = [
        'nom', 'email', 'telephone', 'ville_id'
    ];

    protected $casts = [
        'statut' => StatutEcole::class,
    ];

    // Relations
    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class);
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }
}
```

**Responsabilités :**
- Définir la structure de la table
- Déclarer les relations
- Accessors/Mutators simples
- Pas de requêtes SQL !

---

## Patterns de conception

### 🎯 Repository Pattern

**Objectif :** Abstraire l'accès aux données

**Structure :**

```
app/Repositories/
├── Contracts/
│   ├── EcoleRepositoryInterface.php
│   ├── SireneRepositoryInterface.php
│   └── BaseRepositoryInterface.php
└── EcoleRepository.php
    SireneRepository.php
    BaseRepository.php
```

**Avantages :**
- ✅ Facilite les tests (mock facile)
- ✅ Centralise les requêtes
- ✅ Permet de changer de ORM sans impacter le reste du code

---

### 🎯 Service Pattern

**Objectif :** Encapsuler la logique métier

**Structure :**

```
app/Services/
├── Contracts/
│   ├── EcoleServiceInterface.php
│   └── SireneServiceInterface.php
└── EcoleService.php
    SireneService.php
```

**Avantages :**
- ✅ Code réutilisable
- ✅ Logique métier isolée
- ✅ Facilite les tests

---

### 🎯 Dependency Injection

**Objectif :** Inverser les dépendances

**Configuration (ServiceProvider) :**

```php
// app/Providers/ServiceLayerServiceProvider.php
class ServiceLayerServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Repositories
        $this->app->bind(
            EcoleRepositoryInterface::class,
            EcoleRepository::class
        );

        // Services
        $this->app->bind(
            EcoleServiceInterface::class,
            EcoleService::class
        );
    }
}
```

**Utilisation :**

```php
class EcoleController extends Controller
{
    // Laravel injecte automatiquement l'implémentation
    public function __construct(
        private EcoleServiceInterface $ecoleService
    ) {}
}
```

**Avantages :**
- ✅ Couplage faible
- ✅ Tests faciles (injection de mocks)
- ✅ Flexibilité (changement d'implémentation)

---

## Structure des dossiers

```
app/
├── Console/
│   └── Commands/              # Commandes Artisan personnalisées
├── DTO/                       # Data Transfer Objects
├── Enums/                     # Types énumérés (PHP 8.1+)
├── Http/
│   ├── Controllers/
│   │   └── Api/               # Contrôleurs API REST
│   ├── Middleware/            # Middleware personnalisés
│   └── Requests/              # Form Requests (validation)
├── Models/                    # Modèles Eloquent
├── Notifications/             # Notifications (email, SMS)
├── Providers/                 # Service Providers
├── Repositories/              # Pattern Repository
│   ├── Contracts/             # Interfaces des repositories
│   └── [Implementations]      # Implémentations concrètes
├── Services/                  # Pattern Service (logique métier)
│   ├── Contracts/             # Interfaces des services
│   └── [Implementations]      # Implémentations concrètes
└── Traits/                    # Traits réutilisables
```

---

## Flow de requête HTTP

Voici le parcours complet d'une requête API :

```
1. 🌐 Requête HTTP
   POST /api/ecoles
   {
     "nom": "École Primaire de Paris",
     "email": "contact@ecole-paris.fr",
     "telephone": "+33123456789",
     "ville_id": 1
   }
        │
        ▼
2. 🛣️ Routes (routes/api.php)
   Route::post('/ecoles', [EcoleController::class, 'store']);
        │
        ▼
3. 🛡️ Middleware
   - ForceJsonResponse
   - auth:api (si route protégée)
   - can:create-ecole (permission)
        │
        ▼
4. ✅ Form Request Validation
   CreateEcoleRequest->rules()
   - Validation des données
   - Messages d'erreur personnalisés
        │
        ▼
5. 🎮 Controller
   EcoleController->store(CreateEcoleRequest $request)
   - Récupère les données validées
   - Appelle le service
        │
        ▼
6. 💼 Service (Logique métier)
   EcoleService->create($data)
   - Orchestration
   - Règles métier
   - Transactions DB
        │
        ▼
7. 💾 Repository (Accès données)
   EcoleRepository->create($data)
   - Requête Eloquent
   - Sauvegarde en BDD
        │
        ▼
8. 🗄️ Model & Database
   Ecole::create($data)
   - Insertion PostgreSQL
        │
        ▼
9. 📦 Resource (Formatage)
   new EcoleResource($ecole)
   - Transformation en JSON
   - Masquage de champs sensibles
        │
        ▼
10. ✉️ Réponse HTTP
    {
      "data": {
        "id": "01HQ...",
        "nom": "École Primaire de Paris",
        "code_etablissement": "ECO-2024-001",
        ...
      }
    }
```

---

## Résumé des bonnes pratiques

### ✅ À FAIRE

1. **Toujours utiliser l'injection de dépendances**
2. **Une classe = une responsabilité**
3. **Dépendre des interfaces, pas des implémentations**
4. **Valider avec FormRequest**
5. **Formater avec Resource**
6. **Logique métier dans Service**
7. **Requêtes SQL dans Repository**
8. **Utiliser les Enums pour les constantes**
9. **Utiliser les Traits pour le code réutilisable**
10. **Toujours typer (type hints + return types)**

### ❌ À ÉVITER

1. ❌ Logique métier dans les Controllers
2. ❌ Requêtes SQL dans les Controllers
3. ❌ `new Class()` au lieu de l'injection
4. ❌ Validation dans le Controller
5. ❌ Classes avec trop de responsabilités
6. ❌ Dépendances circulaires
7. ❌ Code dupliqué
8. ❌ Magic strings (utiliser des Enums)

---

## Prochaines étapes

📖 Lisez ensuite :
- [Guide de développement pratique](DEV_GUIDE.md)
- [Exemples concrets](EXAMPLES.md)
- [Bonnes pratiques et conventions](BEST_PRACTICES.md)
