# Documentation - Authentification Sirène avec Token dans les Headers

## 🎯 Vue d'ensemble

Le système d'authentification des sirènes ESP8266 utilise un **token crypté** passé dans les **headers HTTP** pour sécuriser les communications entre les modules physiques et le backend.

**Header utilisé** : `X-Sirene-Token`

**Avantage** : Le token identifie automatiquement la sirène, plus besoin de spécifier le numéro de série dans l'URL.

---

## 🔐 Architecture d'Authentification

### Workflow Complet

```
┌─────────────────────────────────────────────────────────────┐
│ 1. INITIALISATION (Sans authentification)                   │
│    ESP8266 démarre et appelle /config/{numeroSerie}         │
│    → Récupère son token crypté + programmations             │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│ 2. STOCKAGE                                                  │
│    ESP8266 stocke le token dans l'EEPROM/SPIFFS             │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│ 3. REQUÊTES AUTHENTIFIÉES                                   │
│    Toutes les requêtes suivantes incluent le header:        │
│    X-Sirene-Token: {token_crypte}                           │
│    → Middleware vérifie le token                            │
│    → Middleware identifie automatiquement la sirène         │
│    → Backend valide et autorise                             │
└─────────────────────────────────────────────────────────────┘
```

### Composants

1. **Middleware** : `AuthenticateEsp8266`
   - Lit le token depuis le header `X-Sirene-Token`
   - Recherche le token dans la base de données
   - Identifie automatiquement la sirène via le token
   - Vérifie l'abonnement actif
   - Vérifie la date d'expiration
   - Injecte la sirène authentifiée dans la requête

2. **Routes publiques Sirène** :
   - `GET /api/sirenes/config/{numeroSerie}` → Sans authentification (init)
   - `GET /api/sirenes/programmation` → Avec authentification (token identifie la sirène)

---

## 📡 Endpoints Disponibles

### 1️⃣ Configuration Initiale (Public - Sans Token)

**Endpoint** :
```http
GET /api/sirenes/config/{numeroSerie}
```

**Headers** :
```http
Accept: application/json
```

**Exemple cURL** :
```bash
curl -X GET "http://localhost:8000/api/sirenes/config/SRN12345" \
  -H "Accept: application/json"
```

**Réponse Succès (200)** :
```json
{
  "success": true,
  "message": "Configuration ESP8266 récupérée avec succès.",
  "data": {
    "numero_serie": "SRN12345",
    "ecole": {
      "id": "01ABC123...",
      "nom": "École Primaire Exemple"
    },
    "site": {
      "id": "01SITE123...",
      "nom": "Site Principal"
    },
    "token_crypte": "a1b2c3d4e5f6g7h8i9j0...",
    "token_valide_jusqu_au": "2025-12-31T23:59:59.000000Z",
    "programmations": [
      {
        "id": "01PROG123...",
        "nom": "Horaires Septembre-Décembre",
        "chaine_cryptee": "eyJhbGciOiJIUzI1NiIsInR5cCI6...",
        "date_debut": "2024-09-01",
        "date_fin": "2024-12-20"
      }
    ]
  }
}
```

**Réponse Erreur (404)** :
```json
{
  "success": false,
  "message": "Sirène non trouvée avec ce numéro de série."
}
```

```json
{
  "success": false,
  "message": "Aucun abonnement actif trouvé pour cette sirène."
}
```

---

### 2️⃣ Récupérer la Programmation (Authentifié)

**Endpoint** :
```http
GET /api/sirenes/programmation
```

**Headers** :
```http
Accept: application/json
X-Sirene-Token: {votre_token_crypte}
```

**Exemple cURL** :
```bash
curl -X GET "http://localhost:8000/api/sirenes/programmation" \
  -H "Accept: application/json" \
  -H "X-Sirene-Token: a1b2c3d4e5f6g7h8i9j0..."
```

**Note** : Le token identifie automatiquement la sirène, pas besoin de spécifier le numéro de série dans l'URL.

**Réponse Succès (200)** :
```json
{
  "success": true,
  "data": {
    "chaine_cryptee": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "chaine_programmee": "Programmation: Horaires Septembre-Décembre | Jours: Monday, Tuesday, Wednesday, Thursday, Friday | Horaires: 07:30, 12:00, 15:00 | Période: 01/09/2024 au 20/12/2024",
    "version": "01",
    "date_generation": "2024-11-22 10:30:00",
    "date_debut": "2024-09-01",
    "date_fin": "2024-12-20"
  }
}
```

**Réponse Erreur (401 - Token manquant)** :
```json
{
  "success": false,
  "message": "Token d'authentification requis. Veuillez fournir le header X-Sirene-Token."
}
```

**Réponse Erreur (401 - Token invalide)** :
```json
{
  "success": false,
  "message": "Token d'authentification invalide."
}
```

**Réponse Erreur (401 - Token expiré)** :
```json
{
  "success": false,
  "message": "Token expiré. Veuillez renouveler votre abonnement."
}
```

**Réponse Erreur (404 - Aucune programmation)** :
```json
{
  "success": false,
  "message": "Aucune programmation active trouvée pour cette sirène."
}
```

---

## 🔧 Code ESP8266/Arduino

### Bibliothèques Requises

```cpp
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>
#include <ArduinoJson.h>
#include <EEPROM.h>
```

### Configuration

```cpp
// WiFi credentials
const char* WIFI_SSID = "VOTRE_WIFI";
const char* WIFI_PASSWORD = "VOTRE_PASSWORD_WIFI";

// API Configuration
const char* API_BASE_URL = "http://votre-domaine.com/api/sirenes";
const char* NUMERO_SERIE = "SRN12345";  // Unique pour chaque sirène

// EEPROM addresses
#define EEPROM_SIZE 512
#define EEPROM_TOKEN_ADDR 0
#define EEPROM_TOKEN_SIZE 256
```

### Fonctions Utilitaires

```cpp
// Sauvegarder le token dans l'EEPROM
void saveTokenToEEPROM(String token) {
  EEPROM.begin(EEPROM_SIZE);

  // Écrire la longueur du token
  int tokenLength = token.length();
  EEPROM.write(EEPROM_TOKEN_ADDR, tokenLength);

  // Écrire le token
  for (int i = 0; i < tokenLength && i < EEPROM_TOKEN_SIZE; i++) {
    EEPROM.write(EEPROM_TOKEN_ADDR + 1 + i, token[i]);
  }

  EEPROM.commit();
  EEPROM.end();

  Serial.println("✅ Token sauvegardé dans l'EEPROM");
}

// Charger le token depuis l'EEPROM
String loadTokenFromEEPROM() {
  EEPROM.begin(EEPROM_SIZE);

  // Lire la longueur du token
  int tokenLength = EEPROM.read(EEPROM_TOKEN_ADDR);

  if (tokenLength == 0 || tokenLength > EEPROM_TOKEN_SIZE) {
    EEPROM.end();
    return "";
  }

  // Lire le token
  String token = "";
  for (int i = 0; i < tokenLength; i++) {
    token += char(EEPROM.read(EEPROM_TOKEN_ADDR + 1 + i));
  }

  EEPROM.end();
  Serial.println("✅ Token chargé depuis l'EEPROM");

  return token;
}
```

### 1. Récupérer la Configuration Initiale

```cpp
String getInitialConfiguration() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("❌ WiFi non connecté");
    return "";
  }

  HTTPClient http;
  WiFiClient client;

  // Construire l'URL
  String url = String(API_BASE_URL) + "/config/" + String(NUMERO_SERIE);

  Serial.println("🔄 Récupération de la configuration...");
  Serial.println("URL: " + url);

  http.begin(client, url);
  http.addHeader("Accept", "application/json");

  int httpCode = http.GET();

  if (httpCode == HTTP_CODE_OK) {
    String payload = http.getString();
    Serial.println("✅ Configuration reçue");

    // Parser le JSON
    DynamicJsonDocument doc(4096);
    DeserializationError error = deserializeJson(doc, payload);

    if (error) {
      Serial.print("❌ Erreur parsing JSON: ");
      Serial.println(error.c_str());
      http.end();
      return "";
    }

    // Extraire et sauvegarder le token
    String tokenCrypte = doc["data"]["token_crypte"].as<String>();
    if (tokenCrypte.length() > 0) {
      saveTokenToEEPROM(tokenCrypte);
      Serial.println("📝 Token: " + tokenCrypte.substring(0, 20) + "...");
    }

    // Afficher les programmations
    JsonArray programmations = doc["data"]["programmations"];
    Serial.println("📋 Programmations actives: " + String(programmations.size()));

    http.end();
    return tokenCrypte;
  } else {
    Serial.printf("❌ Erreur HTTP: %d\n", httpCode);
    String errorPayload = http.getString();
    Serial.println("Réponse: " + errorPayload);
    http.end();
    return "";
  }
}
```

### 2. Récupérer la Programmation (Avec Token)

```cpp
bool getProgrammation(String token) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("❌ WiFi non connecté");
    return false;
  }

  if (token.length() == 0) {
    Serial.println("❌ Token vide");
    return false;
  }

  HTTPClient http;
  WiFiClient client;

  // Construire l'URL - Pas besoin du numéro de série, le token identifie la sirène
  String url = String(API_BASE_URL) + "/programmation";

  Serial.println("🔄 Récupération de la programmation...");
  Serial.println("URL: " + url);

  http.begin(client, url);
  http.addHeader("Accept", "application/json");
  http.addHeader("X-Sirene-Token", token);  // 🔑 Header d'authentification (identifie la sirène)

  int httpCode = http.GET();

  if (httpCode == HTTP_CODE_OK) {
    String payload = http.getString();
    Serial.println("✅ Programmation reçue");

    // Parser le JSON
    DynamicJsonDocument doc(4096);
    DeserializationError error = deserializeJson(doc, payload);

    if (error) {
      Serial.print("❌ Erreur parsing JSON: ");
      Serial.println(error.c_str());
      http.end();
      return false;
    }

    // Extraire les données
    String chaineCryptee = doc["data"]["chaine_cryptee"].as<String>();
    String chaineProgrammee = doc["data"]["chaine_programmee"].as<String>();
    String version = doc["data"]["version"].as<String>();
    String dateGeneration = doc["data"]["date_generation"].as<String>();
    String dateDebut = doc["data"]["date_debut"].as<String>();
    String dateFin = doc["data"]["date_fin"].as<String>();

    Serial.println("📝 Chaîne programmée: " + chaineProgrammee);
    Serial.println("🔐 Chaîne cryptée (20 premiers car): " + chaineCryptee.substring(0, 20) + "...");
    Serial.println("📅 Période: " + dateDebut + " → " + dateFin);
    Serial.println("🔢 Version: " + version);

    // TODO: Sauvegarder la programmation et l'exécuter
    // saveProgrammation(chaineCryptee, version);

    http.end();
    return true;

  } else if (httpCode == HTTP_CODE_UNAUTHORIZED) {
    Serial.println("❌ Authentification échouée - Token invalide ou expiré");
    String errorPayload = http.getString();
    Serial.println("Réponse: " + errorPayload);
    http.end();
    return false;

  } else if (httpCode == HTTP_CODE_NOT_FOUND) {
    Serial.println("⚠️ Aucune programmation active trouvée");
    http.end();
    return false;

  } else {
    Serial.printf("❌ Erreur HTTP: %d\n", httpCode);
    String errorPayload = http.getString();
    Serial.println("Réponse: " + errorPayload);
    http.end();
    return false;
  }
}
```

### 3. Setup Initial

```cpp
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n\n================================");
  Serial.println("🚀 ESP8266 Sirène - Démarrage");
  Serial.println("================================");
  Serial.println("Numéro de série: " + String(NUMERO_SERIE));

  // Connexion WiFi
  Serial.println("\n🔌 Connexion WiFi...");
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✅ WiFi connecté!");
    Serial.print("📡 IP: ");
    Serial.println(WiFi.localIP());

    // Essayer de charger le token depuis l'EEPROM
    String savedToken = loadTokenFromEEPROM();

    if (savedToken.length() > 0) {
      Serial.println("✅ Token trouvé dans l'EEPROM");

      // Tester le token
      if (!getProgrammation(savedToken)) {
        Serial.println("⚠️ Token invalide/expiré, récupération d'un nouveau...");
        savedToken = getInitialConfiguration();
      }
    } else {
      Serial.println("ℹ️ Aucun token en mémoire, récupération...");
      savedToken = getInitialConfiguration();
    }

    // Récupérer la programmation
    if (savedToken.length() > 0) {
      getProgrammation(savedToken);
    } else {
      Serial.println("❌ Impossible de récupérer le token");
    }

  } else {
    Serial.println("\n❌ Échec de la connexion WiFi");
  }

  Serial.println("\n================================");
  Serial.println("✅ Initialisation terminée");
  Serial.println("================================\n");
}
```

### 4. Loop avec Vérification Périodique

```cpp
void loop() {
  // Vérifier s'il y a une nouvelle programmation toutes les heures
  static unsigned long lastCheck = 0;
  unsigned long currentMillis = millis();

  // 1 heure = 3600000 ms
  if (currentMillis - lastCheck >= 3600000) {
    lastCheck = currentMillis;

    Serial.println("\n🔄 Vérification de mise à jour de programmation...");

    String token = loadTokenFromEEPROM();
    if (token.length() > 0) {
      getProgrammation(token);
    } else {
      Serial.println("❌ Aucun token disponible");
    }
  }

  // TODO: Exécuter les sonneries selon la programmation stockée
  // executeProgrammation();

  delay(1000);
}
```

---

## 🧪 Tests avec cURL

### Test 1 : Configuration Initiale (Sans Token)

```bash
# Récupérer la configuration et le token
curl -X GET "http://localhost:8000/api/sirenes/SRN12345/config" \
  -H "Accept: application/json" \
  -v
```

### Test 2 : Programmation (Avec Token)

```bash
# Remplacer TOKEN_ICI par le token reçu de l'étape 1
curl -X GET "http://localhost:8000/api/sirenes/programmation" \
  -H "Accept: application/json" \
  -H "X-Sirene-Token: TOKEN_ICI" \
  -v
```

**Note** : Pas besoin du numéro de série dans l'URL, le token identifie automatiquement la sirène.

### Test 3 : Programmation (Sans Token - Doit échouer)

```bash
curl -X GET "http://localhost:8000/api/sirenes/programmation" \
  -H "Accept: application/json" \
  -v
```

Devrait retourner une erreur 401 : "Token d'authentification requis. Veuillez fournir le header X-Sirene-Token."

### Test 4 : Programmation (Token Invalide - Doit échouer)

```bash
curl -X GET "http://localhost:8000/api/sirenes/programmation" \
  -H "Accept: application/json" \
  -H "X-Sirene-Token: TOKEN_INVALIDE" \
  -v
```

Devrait retourner une erreur 401 : "Token d'authentification invalide."

---

## 🛡️ Sécurité

### Points de Sécurité Implémentés

1. ✅ **Token dans les Headers** : Plus sécurisé que dans l'URL
2. ✅ **Identification Automatique** : Le token identifie la sirène, impossible d'usurper l'identité d'une autre sirène
3. ✅ **Validation par Hash SHA-256** : Le token est hashé avant comparaison
4. ✅ **Vérification de l'Expiration** : Les tokens expirés sont rejetés
5. ✅ **Vérification de l'Abonnement** : Seuls les abonnements actifs sont acceptés
6. ✅ **Logging Complet** : Toutes les tentatives sont loggées avec l'IP
7. ✅ **Middleware Dédié** : Séparation des responsabilités
8. ✅ **Pas de Numéro de Série dans l'URL** : Empêche les tentatives d'accès non autorisé

### Recommandations Production

1. **HTTPS Obligatoire** : Ne JAMAIS utiliser HTTP en production
2. **Rotation des Tokens** : Régénérer les tokens lors du renouvellement d'abonnement
3. **Rate Limiting** : Limiter le nombre de requêtes par IP/token
4. **Monitoring** : Surveiller les tentatives d'authentification échouées
5. **Stockage Sécurisé** : Utiliser EEPROM avec chiffrement si possible

---

## 📊 Diagramme de Séquence

```
ESP8266                 Backend (Laravel)              Base de Données
   │                            │                              │
   │  1. GET /config/SRN12345   │                              │
   ├───────────────────────────>│                              │
   │                            │  Recherche sirène + abonnement
   │                            ├─────────────────────────────>│
   │                            │<─────────────────────────────┤
   │  token_crypte + prog       │                              │
   │<───────────────────────────┤                              │
   │                            │                              │
   │  Stocke token EEPROM       │                              │
   │                            │                              │
   │  2. GET /programmation     │                              │
   │     X-Sirene-Token: xxx    │                              │
   ├───────────────────────────>│                              │
   │                            │  Middleware vérifie token     │
   │                            ├─────────────────────────────>│
   │                            │  Hash token + recherche       │
   │                            │  Token → Abonnement → Sirène  │
   │                            │<─────────────────────────────┤
   │                            │  Token valide + Sirène OK     │
   │                            │  Récupère programmation       │
   │                            ├─────────────────────────────>│
   │  chaine_cryptee + data     │<─────────────────────────────┤
   │<───────────────────────────┤                              │
   │                            │                              │
   │  Exécute programmation     │                              │
   │                            │                              │
```

---

## 📝 Notes Importantes

1. **Premier Démarrage** : L'ESP8266 appelle d'abord `/config/{numeroSerie}` pour obtenir son token
2. **Token Persistant** : Le token est stocké dans l'EEPROM et réutilisé
3. **Identification Automatique** : Le token identifie la sirène, pas besoin du numéro de série dans l'URL `/programmation`
4. **Sécurité Renforcée** : Impossible pour une sirène d'accéder aux données d'une autre sirène
5. **Gestion d'Erreur** : Si le token expire, redemander la config
6. **Mise à Jour** : Vérifier périodiquement les nouvelles programmations avec le même token
7. **Fallback** : Si pas de connexion, utiliser la programmation en mémoire

---

**Version** : 1.0.0
**Date** : 22 novembre 2024
**Auteur** : Équipe Backend Sirene d'École
