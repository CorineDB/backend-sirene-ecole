# Guide de correction des signatures getById() dans les services

## 🚨 Problème identifié

Plusieurs services ont une signature incorrecte de `getById()` dans certains environnements locaux:

```php
// ❌ SIGNATURE INCORRECTE
public function getById(string $id, array $relations = []): JsonResponse

// ✅ SIGNATURE CORRECTE
public function getById(string $id, array $columns = ['*'], array $relations = []): JsonResponse
```

Cette incompatibilité cause l'erreur PHP:
```
Declaration of App\Services\XXXService::getById(string $id, array $relations = [])
must be compatible with App\Services\BaseService::getById(string $id, array $columns = [...], array $relations = [])
```

## 📋 Services concernés par les erreurs

1. **CalendrierScolaireService** ❌ Ne doit PAS surcharger `getById()`
2. **AbonnementService** ❌ Ne doit PAS surcharger `getById()`
3. **PanneService** ✅ Déjà corrigé avec la bonne signature
4. **SireneService** ✅ Déjà corrigé avec la bonne signature

## 🔧 Solution recommandée

### Option 1: Pull depuis Git (RECOMMANDÉ)

Synchronisez votre environnement local avec le dépôt Git:

```bash
cd "/home/pc1/workspace/sirene d'ecole/sirenedecolebackend"

# Récupérer les dernières modifications
git fetch origin
git pull origin claude/review-school-subscription-013gBUzgPFrDVfb4yvwLwqsr-01QSeEchDJzFpWz2mAQSsCP4

# Vider tous les caches Laravel
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
composer dump-autoload

# Redémarrer le serveur de développement
php artisan serve
```

### Option 2: Correction manuelle

Si vous avez des modifications locales importantes, corrigez manuellement:

#### Pour CalendrierScolaireService.php et AbonnementService.php

**Si ces fichiers contiennent une méthode `getById()`, SUPPRIMEZ-LA complètement.**

Ces services n'ont pas besoin de logique de filtrage personnalisée, ils doivent simplement hériter de `BaseService::getById()`.

#### Pour d'autres services qui ont `getById()` avec une mauvaise signature

Remplacez:
```php
public function getById(string $id, array $relations = []): JsonResponse
{
    // votre code
}
```

Par:
```php
public function getById(string $id, array $columns = ['*'], array $relations = []): JsonResponse
{
    // votre code
}
```

## ✅ Services qui DOIVENT surcharger getById()

Seuls ces services ont besoin d'une surcharge de `getById()` avec filtrage spécifique:

| Service | Raison | Utilisateur filtré |
|---------|--------|-------------------|
| **OrdreMissionService** | Filtrage par zone géographique | Techniciens |
| **InterventionService** | Filtrage par zone ou assignation | Techniciens |
| **PanneService** | Filtrage par école propriétaire | Écoles |
| **SireneService** | Filtrage par école propriétaire | Écoles |

## 🔍 Vérification

Après correction, vérifiez qu'il n'y a plus d'erreurs:

```bash
# Rechercher les signatures incorrectes
grep -rn "function getById.*string.*id.*array.*relations" app/Services/*.php | grep -v "columns"

# Si aucun résultat, c'est bon! ✅
```

## 📚 Référence

La signature correcte est définie dans `BaseServiceInterface`:

```php
// app/Services/Contracts/BaseServiceInterface.php
public function getById(string $id, array $columns = ['*'], array $relations = []): JsonResponse;
```

Tous les services qui implémentent cette interface doivent respecter cette signature.

## 💡 Principe

**Règle d'or:** Ne surchargez `getById()` que si vous avez besoin d'une logique de filtrage spécifique basée sur les permissions utilisateur. Sinon, laissez la classe parent (`BaseService`) gérer la méthode.
