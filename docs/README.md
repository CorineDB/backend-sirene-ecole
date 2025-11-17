# Documentation - Backend Sirene École

Bienvenue dans la documentation complète du projet **Backend Sirene École** ! 🎓

Cette documentation a été créée pour aider les développeurs juniors et seniors à comprendre et travailler efficacement avec l'architecture SOLID de notre API REST.

---

## 📚 Table des matières

### 1. [Architecture SOLID](ARCHITECTURE.md)
**Comprendre l'architecture du projet**

Ce document explique en détail :
- 🏗️ Vue d'ensemble de l'architecture en 3 couches
- 🎯 Les 5 principes SOLID (avec exemples concrets)
- 📦 Patterns de conception (Repository, Service, DI)
- 🔄 Flow complet d'une requête HTTP
- ✅ Bonnes pratiques à suivre et erreurs à éviter

**À lire en premier !** Ce document pose les fondations pour comprendre le reste du projet.

---

### 2. [Guide de Développement](DEV_GUIDE.md)
**Guide pratique étape par étape**

Ce document vous guide pour :
- ✅ Créer une nouvelle fonctionnalité API complète (15 étapes détaillées)
- 🗂️ Comprendre chaque composant (Migration, Model, Repository, Service, Controller, etc.)
- 🔧 Configurer les routes, permissions, et middleware
- 🧪 Tester votre API (cURL, Postman)
- 📝 Documenter avec Swagger

**Exemple concret :** Création d'une API "Départements" de A à Z

**À utiliser comme checklist** lors du développement de nouvelles fonctionnalités.

---

### 3. [Exemples Concrets](EXAMPLES.md)
**Patterns et cas d'usage réels**

Ce document présente des exemples tirés du projet réel :
- 🔍 Repository avec recherche avancée et filtres
- 💼 Service avec logique métier complexe
- 🔗 Relations Eloquent (polymorphisme, Many-to-Many)
- ✅ Validation avancée et règles custom
- 🔄 Transactions et gestion d'erreurs
- 📄 Pagination avec métadonnées
- 📁 Upload de fichiers sécurisés
- 📧 Notifications multi-canal (email, BDD)
- 🛡️ Middleware personnalisés

**À consulter** quand vous cherchez comment implémenter un pattern spécifique.

---

### 4. [Bonnes Pratiques](BEST_PRACTICES.md)
**Conventions et standards du projet**

Ce document couvre :
- 📝 Conventions de nommage (classes, variables, routes, BDD)
- 🏗️ Structure du code et organisation
- 🔖 Typage et documentation (PHPDoc, type hints)
- ⚠️ Gestion des erreurs et exceptions
- 🔒 Sécurité (validation, SQL injection, autorisation)
- ⚡ Performance (N+1, pagination, cache)
- 🧪 Tests (nommage, structure AAA)
- 📋 Git et versioning (commits, branches)
- ✅ Code Review Checklist complète

**À réviser régulièrement** pour maintenir la qualité du code.

---

### 5. [FAQ - Questions Fréquentes](FAQ.md)
**Réponses aux questions courantes**

Ce document répond aux questions importantes :
- ❓ **Pourquoi le formatage JSON est dans le Controller et pas dans le Service ?** (avec exemples détaillés)
- ❓ Quand utiliser un Repository vs Eloquent direct ?
- ❓ Dois-je toujours créer une interface ?
- ❓ Où mettre la validation métier ?

**À consulter** quand vous avez des doutes sur l'architecture ou les bonnes pratiques.

---

## 🚀 Par où commencer ?

### Pour un nouveau développeur junior :

1. **Jour 1-2 : Comprendre l'architecture**
   - Lire [ARCHITECTURE.md](ARCHITECTURE.md) en entier
   - Comprendre les principes SOLID
   - Visualiser le flow d'une requête HTTP

2. **Jour 3-5 : Pratiquer avec un exemple**
   - Suivre le [Guide de Développement](DEV_GUIDE.md)
   - Créer une fonctionnalité simple (ex: CRUD Départements)
   - Tester avec Postman ou cURL

3. **Semaine 2 : Explorer le code existant**
   - Lire le code de `EcoleController`, `EcoleService`, `EcoleRepository`
   - Comparer avec les [Exemples Concrets](EXAMPLES.md)
   - Comprendre les relations entre les modèles

4. **Semaine 3+ : Développement autonome**
   - Utiliser [BEST_PRACTICES.md](BEST_PRACTICES.md) comme référence
   - Participer aux code reviews
   - Contribuer aux nouvelles fonctionnalités

---

## 📋 Checklist avant de commencer à coder

Avant de développer une nouvelle fonctionnalité, assurez-vous de :

- [ ] Avoir lu et compris [ARCHITECTURE.md](ARCHITECTURE.md)
- [ ] Connaître la checklist de développement dans [DEV_GUIDE.md](DEV_GUIDE.md)
- [ ] Avoir configuré votre environnement de développement
- [ ] Connaître les conventions de nommage ([BEST_PRACTICES.md](BEST_PRACTICES.md))
- [ ] Savoir comment tester votre code

---

## 🎯 Principes clés à retenir

### 1. Séparation des responsabilités
```
Controller  → Gère HTTP (requête/réponse)
Service     → Contient la logique métier
Repository  → Accède aux données
```

### 2. Injection de dépendances
```php
// ✅ Toujours injecter les dépendances
public function __construct(
    private EcoleServiceInterface $ecoleService
) {}
```

### 3. Validation
```php
// ✅ FormRequest pour la validation
public function store(CreateEcoleRequest $request)
{
    $ecole = $this->ecoleService->create($request->validated());
}
```

### 4. Typage
```php
// ✅ Typer tous les paramètres et retours
public function create(array $data): Ecole
{
    // ...
}
```

---

## 🛠️ Outils de développement

### Commandes utiles

```bash
# Lancer le serveur de développement
php artisan serve

# Créer une migration
php artisan make:migration create_departments_table

# Créer un modèle
php artisan make:model Department

# Créer un controller
php artisan make:controller Api/DepartmentController

# Exécuter les migrations
php artisan migrate

# Exécuter les tests
php artisan test

# Formater le code
./vendor/bin/pint

# Générer la documentation Swagger
php artisan l5-swagger:generate
```

### Configuration IDE recommandée

**VS Code Extensions :**
- PHP Intelephense
- Laravel Extension Pack
- PHP Debug
- GitLens
- Better Comments

---

## 📖 Structure du projet

```
backend-sirene-ecole/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/     → Contrôleurs REST
│   │   ├── Requests/            → Form Requests (validation)
│   │   └── Resources/           → API Resources (JSON)
│   ├── Models/                  → Modèles Eloquent
│   ├── Repositories/            → Pattern Repository
│   │   ├── Contracts/           → Interfaces
│   │   └── [Implementations]    → Implémentations
│   ├── Services/                → Pattern Service
│   │   ├── Contracts/           → Interfaces
│   │   └── [Implementations]    → Implémentations
│   ├── Enums/                   → Énumérations PHP 8.1+
│   └── Traits/                  → Traits réutilisables
├── database/
│   ├── migrations/              → Migrations BDD
│   ├── seeders/                 → Seeders
│   └── factories/               → Factories pour tests
├── routes/
│   └── api.php                  → Routes API
├── tests/
│   ├── Unit/                    → Tests unitaires
│   └── Feature/                 → Tests d'intégration
└── docs/                        → Cette documentation !
```

---

## 💡 Ressources complémentaires

### Documentation Laravel
- [Laravel 12 Documentation](https://laravel.com/docs/12.x)
- [Eloquent ORM](https://laravel.com/docs/12.x/eloquent)
- [Validation](https://laravel.com/docs/12.x/validation)
- [API Resources](https://laravel.com/docs/12.x/eloquent-resources)

### Bonnes pratiques
- [Laravel Best Practices](https://github.com/alexeymezenin/laravel-best-practices)
- [PHP The Right Way](https://phptherightway.com/)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)

---

## ❓ Questions fréquentes

**📖 Pour des réponses détaillées avec exemples, consultez [FAQ.md](FAQ.md)**

### Q: Où dois-je mettre ma logique métier ?
**R:** Toujours dans le **Service**, jamais dans le Controller.

### Q: Pourquoi le formatage JSON est dans le Controller et pas dans le Service ?
**R:** Pour respecter le principe de responsabilité unique (SOLID). Le Service retourne des **objets métier** (réutilisables partout), le Controller gère la **présentation HTTP/JSON**. [Voir explication détaillée →](FAQ.md#pourquoi-le-formatage-json-est-dans-le-controller-et-pas-dans-le-service)

### Q: Comment accéder aux données ?
**R:** Via le **Repository**, jamais directement avec `Model::find()` dans le Service.

### Q: Comment valider les données ?
**R:** Avec un **FormRequest** pour la validation HTTP, et dans le **Service** pour les règles métier.

### Q: Dois-je toujours créer une interface ?
**R:** Oui, pour les Services et Repositories, afin de respecter le principe d'inversion de dépendances (SOLID).

---

## 🤝 Contribution

Avant de soumettre votre code :

1. ✅ Vérifier que les tests passent : `php artisan test`
2. ✅ Formater le code : `./vendor/bin/pint`
3. ✅ Suivre la checklist dans [BEST_PRACTICES.md](BEST_PRACTICES.md)
4. ✅ Créer une pull request avec une description claire

---

## 📞 Support

Si vous avez des questions ou besoin d'aide :

1. Consultez d'abord cette documentation
2. Regardez le code existant pour des exemples similaires
3. Demandez à votre mentor ou lead developer
4. Créez une issue sur le repository

---

**Bonne lecture et bon développement ! 🚀**

*Cette documentation est maintenue à jour régulièrement. N'hésitez pas à suggérer des améliorations.*
