# 📱 Guide d'accès aux QR Codes d'abonnement

## Vue d'ensemble

Le système génère automatiquement des QR codes pour chaque abonnement créé. Ce document explique les différentes façons d'accéder à ces QR codes.

## 🔧 Configuration requise

### 1. Créer le lien symbolique de stockage

**Important:** Cette commande doit être exécutée pour que les QR codes soient accessibles publiquement via HTTP.

```bash
php artisan storage:link
```

Cette commande crée un lien symbolique de `public/storage` vers `storage/app/public`, permettant l'accès public aux fichiers.

**Vérification:**
```bash
ls -la public/ | grep storage
# Devrait afficher : lrwxrwxrwx 1 ... storage -> /path/to/storage/app/public
```

### 2. Configuration du .env

Assurez-vous que `APP_URL` est correctement configuré :

```env
APP_URL=https://votre-domaine.com
```

## 📍 Localisation des fichiers

Les QR codes sont stockés dans :
```
storage/app/public/ecoles/{ecole_id}/qrcodes/{sirene_id}/abonnement_{abonnement_id}.png
```

## 🌐 Méthodes d'accès

### Option 1: URL publique directe (Après storage:link)

**Avantage:** Simple et rapide
**Inconvénient:** Aucun contrôle d'accès

```
https://votre-domaine.com/storage/ecoles/{ecole_id}/qrcodes/{sirene_id}/abonnement_{abonnement_id}.png
```

**Utilisation dans le code:**
```php
$abonnement = Abonnement::find($id);
$qrCodeUrl = $abonnement->qr_code_url; // Généré automatiquement via accessor
```

**Réponse JSON automatique:**
```json
{
  "id": "01ABC...",
  "numero_abonnement": "ABO-20251119-A3B7F9",
  "qr_code_path": "ecoles/01ABC.../qrcodes/01DEF.../abonnement_01GHI.png",
  "qr_code_url": "https://votre-domaine.com/storage/ecoles/01ABC.../qrcodes/01DEF.../abonnement_01GHI.png"
}
```

### Option 2: Route API sécurisée (Recommandé) ✅

**Avantage:** Contrôle d'accès, logs, validation
**Inconvénient:** Requête HTTP supplémentaire

#### Télécharger le QR code (Public)

```http
GET /api/abonnements/{id}/qr-code
```

**Exemple:**
```bash
curl https://votre-domaine.com/api/abonnements/01ABC123/qr-code --output qrcode.png
```

**Réponse:** Image PNG du QR code

**Cas d'utilisation:**
- Affichage dans une application mobile
- Téléchargement par l'école
- Impression de factures avec QR code

#### Régénérer le QR code (Authentifié - Admin/ECOLE)

```http
POST /api/admin/abonnements/{id}/regenerer-qr-code
Authorization: Bearer {token}
```

**Restrictions:**
- Uniquement pour les abonnements avec statut `EN_ATTENTE`
- Nécessite authentification

**Réponse:**
```json
{
  "success": true,
  "message": "QR code régénéré avec succès",
  "data": {
    "qr_code_path": "ecoles/.../qrcodes/.../abonnement_xxx.png",
    "qr_code_url": "https://votre-domaine.com/storage/..."
  }
}
```

### Option 3: URL signée temporaire (Recommandé pour production) 🔐✅

**Avantage:** Sécurisé, temporaire, traçable
**Inconvénient:** 2 requêtes HTTP (1 pour obtenir URL, 1 pour télécharger)

#### Obtenir une URL signée (Public)

```http
GET /api/abonnements/{id}/qr-code-url
```

**Réponse:**
```json
{
  "success": true,
  "data": {
    "qr_code_url": "https://votre-domaine.com/api/abonnements/01ABC/qr-code-download?expires=1234567890&signature=abc123...",
    "expires_at": "2025-11-19T13:00:00+00:00"
  }
}
```

**Utilisation:**
```javascript
// Frontend - Récupérer l'URL signée
const response = await fetch('/api/abonnements/01ABC123/qr-code-url');
const { data } = await response.json();

// Afficher le QR code avec l'URL signée
<img src={data.qr_code_url} alt="QR Code" />
```

**Fonctionnement:**
1. Le frontend demande une URL signée temporaire (valide 1 heure)
2. Le backend génère une URL avec signature cryptographique
3. Le frontend utilise cette URL pour afficher/télécharger le QR code
4. L'URL expire automatiquement après 1 heure
5. Toute modification de l'URL invalide la signature

**Sécurité:**
- ✅ URL temporaire (expire après 1 heure)
- ✅ Signature cryptographique (impossible à falsifier)
- ✅ Logs des accès
- ✅ Protection contre les accès non autorisés
- ✅ Révocation automatique après expiration

## 🔐 Sécurité

### Option 1 (Public direct)
- ✅ Rapide et simple
- ❌ Pas de contrôle d'accès
- ❌ Pas de logs
- ⚠️ Toute personne avec l'URL peut accéder au QR code

### Option 2 (Route API)
- ✅ Logs des accès
- ✅ Validation de l'existence de l'abonnement
- ✅ Gestion des erreurs
- ⚠️ Toujours public (pas d'authentification requise pour GET)

### Option 3 (URL signée) - NOUVEAU ✨
- ✅ URL temporaire (expire après 1 heure)
- ✅ Signature cryptographique
- ✅ Logs des accès
- ✅ Validation de l'existence
- ✅ Protection contre la falsification
- ✅ Révocation automatique

### 📊 Tableau comparatif

| Critère | Option 1 (Direct) | Option 2 (API) | Option 3 (Signée) ✅ |
|---------|-------------------|----------------|----------------------|
| **Sécurité** | ⚠️ Faible | ✓ Moyenne | ✅ Élevée |
| **Expiration** | ❌ Non | ❌ Non | ✅ 1 heure |
| **Falsification** | ❌ Possible | ⚠️ Risque | ✅ Impossible |
| **Logs** | ❌ Non | ✅ Oui | ✅ Oui |
| **Requêtes HTTP** | 1 | 1 | 2 |
| **Complexité** | Simple | Simple | Moyenne |
| **Production** | ❌ Déconseillé | ⚠️ Acceptable | ✅ Recommandé |

## 🚀 Intégration Frontend

### React/Vue.js (Option 1 - Direct)

```javascript
// Afficher le QR code dans une image
<img
  src={abonnement.qr_code_url}
  alt={`QR Code ${abonnement.numero_abonnement}`}
  onError={(e) => {
    // Fallback vers l'API si le lien direct ne fonctionne pas
    e.target.src = `/api/abonnements/${abonnement.id}/qr-code`;
  }}
/>
```

### React/Vue.js (Option 3 - URL signée) ✅ RECOMMANDÉ

```javascript
import { ref, onMounted } from 'vue';

const qrCodeUrl = ref(null);
const loading = ref(true);

onMounted(async () => {
  try {
    // Récupérer l'URL signée
    const response = await fetch(`/api/abonnements/${abonnementId}/qr-code-url`);
    const { data } = await response.json();

    if (data && data.qr_code_url) {
      qrCodeUrl.value = data.qr_code_url;
    }
  } catch (error) {
    console.error('Erreur chargement QR code:', error);
  } finally {
    loading.value = false;
  }
});
```

```vue
<template>
  <div v-if="loading" class="animate-pulse bg-gray-200 h-64 w-64"></div>
  <img
    v-else-if="qrCodeUrl"
    :src="qrCodeUrl"
    alt="QR Code"
    class="w-64 h-64"
  />
  <div v-else class="text-red-500">QR Code non disponible</div>
</template>
```

### Mobile (React Native)

```javascript
import { Image } from 'react-native';

<Image
  source={{ uri: abonnement.qr_code_url }}
  style={{ width: 300, height: 300 }}
/>
```

### Téléchargement via JavaScript

```javascript
async function downloadQRCode(abonnementId) {
  const response = await fetch(`/api/abonnements/${abonnementId}/qr-code`);
  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `qrcode-${abonnementId}.png`;
  a.click();
}
```

## 🧪 Tests

### Test 1: Vérifier le lien symbolique
```bash
ls -la public/storage
```
✅ Doit pointer vers `../storage/app/public`

### Test 2: Créer un abonnement et vérifier le QR code
```bash
# Via API ou Tinker
php artisan tinker
> $abonnement = Abonnement::first();
> $abonnement->qr_code_url;
# Doit retourner une URL complète
```

### Test 3: Télécharger via l'API
```bash
curl -I https://votre-domaine.com/api/abonnements/01ABC123/qr-code
# Doit retourner 200 OK avec Content-Type: image/png
```

### Test 4: Vérifier l'accessor
```bash
php artisan tinker
> $abonnement = Abonnement::with('ecole')->first();
> $abonnement->toArray();
# La clé 'qr_code_url' doit être présente automatiquement
```

## 📝 Changelog

### v2.0 - 2025-11-19
- ✅ Ajout de l'accessor `qr_code_url` au modèle Abonnement
- ✅ Ajout de `qr_code_url` dans les attributs `$appends`
- ✅ Documentation complète des méthodes d'accès

### v1.0 - Initial
- ✅ Génération automatique des QR codes (trait HasQrCodeAbonnement)
- ✅ Routes API pour téléchargement et régénération
- ✅ Stockage dans storage/app/public

## 🐛 Troubleshooting

### Problème: QR code non accessible via URL publique

**Solution:**
```bash
php artisan storage:link
php artisan cache:clear
php artisan config:clear
```

### Problème: qr_code_url retourne null

**Causes possibles:**
1. Le QR code n'a pas été généré
2. Le chemin `qr_code_path` est vide

**Solution:**
```bash
php artisan tinker
> $abonnement = Abonnement::find('01ABC...');
> $abonnement->regenererQrCode();
```

### Problème: Route API retourne 404

**Vérifications:**
1. L'abonnement existe
2. Le fichier existe physiquement
3. Les permissions du dossier sont correctes

```bash
# Vérifier les permissions
ls -la storage/app/public/ecoles/
# Doit être accessible en lecture

# Corriger si nécessaire
chmod -R 755 storage/app/public/
```

## 📚 Ressources

- [Laravel File Storage](https://laravel.com/docs/filesystem)
- [Laravel Accessors & Mutators](https://laravel.com/docs/eloquent-mutators)
- [SimpleSoftwareIO QR Code](https://www.simplesoftware.io/#/docs/simple-qrcode)
