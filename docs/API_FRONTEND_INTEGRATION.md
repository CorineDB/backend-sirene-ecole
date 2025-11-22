# Documentation API - Intégration Frontend

## Table des matières

1. [Authentification](#authentification)
2. [Programmations](#programmations)
   - [Liste paginée](#liste-paginée-des-programmations)
   - [Créer une programmation](#créer-une-programmation)
   - [Voir une programmation](#voir-une-programmation)
   - [Modifier une programmation](#modifier-une-programmation)
   - [Supprimer une programmation](#supprimer-une-programmation)
3. [Schémas de données](#schémas-de-données)
4. [Gestion des erreurs](#gestion-des-erreurs)

---

## Authentification

Toutes les requêtes API nécessitent un token d'authentification Bearer:

```http
Authorization: Bearer {votre_token}
```

---

## Programmations

### Liste paginée des programmations

**Endpoint:** `GET /api/sirenes/{sirene_id}/programmations`

#### Paramètres de requête (Query params)

| Paramètre | Type | Requis | Défaut | Description |
|-----------|------|--------|--------|-------------|
| `page` | integer | Non | 1 | Numéro de la page |
| `per_page` | integer | Non | 15 | Nombre d'éléments par page (max: 100) |
| `date` | string (Y-m-d) | Non | - | Filtrer par date (retourne liste non paginée) |

#### Exemple de requête

```javascript
// Avec axios
const response = await axios.get('/api/sirenes/01ABC123/programmations', {
  params: {
    page: 1,
    per_page: 20
  },
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

// Avec fetch
const response = await fetch('/api/sirenes/01ABC123/programmations?page=1&per_page=20', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

#### Réponse (200 OK)

```json
{
  "success": true,
  "message": "Programmations paginées récupérées avec succès.",
  "data": [
    {
      "id": "01HN5KZQR7WYX9G3V8M2TCBJ4P",
      "ecole_id": "01HN5KZQ00000000000000000",
      "site_id": "01HN5KZQ11111111111111111",
      "sirene_id": "01ABC123",
      "abonnement_id": "01HN5KZQ22222222222222222",
      "calendrier_id": null,
      "nom_programmation": "Programmation normale",
      "horaires_sonneries": [
        {
          "heure": 8,
          "minute": 0,
          "jours": [1, 2, 3, 4, 5],
          "duree_sonnerie": 3,
          "description": "Début des cours"
        },
        {
          "heure": 10,
          "minute": 0,
          "jours": [1, 2, 3, 4, 5],
          "duree_sonnerie": 5,
          "description": "Récréation"
        }
      ],
      "jour_semaine": [1, 2, 3, 4, 5],
      "jours_feries_inclus": false,
      "jours_feries_exceptions": [
        {
          "date": "2025-12-25",
          "action": "exclude",
          "est_national": true,
          "recurrent": true
        }
      ],
      "date_debut": "2025-09-01",
      "date_fin": "2026-06-30",
      "actif": true,
      "cree_par": "01HN5KZQ33333333333333333",
      "created_at": "2025-11-22T10:30:00.000000Z",
      "updated_at": "2025-11-22T10:30:00.000000Z"
    }
    // ... autres programmations
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "last_page": 3,
    "from": 1,
    "to": 20,
    "has_more_pages": true
  },
  "links": {
    "first": "http://api.example.com/api/sirenes/01ABC123/programmations?page=1",
    "last": "http://api.example.com/api/sirenes/01ABC123/programmations?page=3",
    "prev": null,
    "next": "http://api.example.com/api/sirenes/01ABC123/programmations?page=2"
  }
}
```

#### Composant React exemple

```jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

function ProgrammationsList({ sireneId }) {
  const [programmations, setProgrammations] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [loading, setLoading] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);

  const fetchProgrammations = async (page = 1) => {
    setLoading(true);
    try {
      const response = await axios.get(`/api/sirenes/${sireneId}/programmations`, {
        params: { page, per_page: 20 }
      });

      setProgrammations(response.data.data);
      setPagination(response.data.pagination);
      setCurrentPage(page);
    } catch (error) {
      console.error('Erreur chargement programmations:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProgrammations();
  }, [sireneId]);

  return (
    <div>
      {loading ? (
        <div>Chargement...</div>
      ) : (
        <>
          <ul>
            {programmations.map(prog => (
              <li key={prog.id}>
                {prog.nom_programmation}
                {/* ... afficher autres détails ... */}
              </li>
            ))}
          </ul>

          {/* Pagination */}
          {pagination && (
            <div className="pagination">
              <button
                disabled={!pagination.prev}
                onClick={() => fetchProgrammations(currentPage - 1)}
              >
                Précédent
              </button>

              <span>
                Page {pagination.current_page} / {pagination.last_page}
                ({pagination.total} total)
              </span>

              <button
                disabled={!pagination.has_more_pages}
                onClick={() => fetchProgrammations(currentPage + 1)}
              >
                Suivant
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
```

---

### Créer une programmation

**Endpoint:** `POST /api/sirenes/{sirene_id}/programmations`

#### Champs auto-assignés par le système

Les champs suivants sont **automatiquement assignés** et ne doivent **PAS** être fournis dans le payload:
- `ecole_id` - Déduit de la sirène
- `site_id` - Déduit de la sirène
- `abonnement_id` - Automatiquement assigné à l'abonnement actif de l'école
- `cree_par` - Utilisateur authentifié
- `chaine_programmee` / `chaine_cryptee` - Générées automatiquement

#### Règles de chevauchement (Overlap)

**IMPORTANT**: Une seule programmation active par période est autorisée pour une même sirène.

- ✅ **AUTORISÉ**: Plusieurs programmations actives avec des périodes qui ne se chevauchent PAS
  - Exemple: Programmation Trimestre 1 (01/09 - 31/12) et Programmation Trimestre 2 (01/01 - 31/03)

- ❌ **INTERDIT**: Plusieurs programmations actives avec des périodes qui se chevauchent
  - Exemple: Programme A (01/09 - 31/12) et Programme B (01/10 - 30/06) → **Erreur de validation**

- ✅ **AUTORISÉ**: Plusieurs programmations pour la même sirène si au moins une est inactive (`actif=false`)
  - Exemple: Programmation active (01/09 - 30/06) et Programmation archivée inactive (même période)

**Comportement**:
- Le système vérifie automatiquement les chevauchements lors de la création/modification
- Si un chevauchement est détecté avec une programmation active, une erreur de validation est renvoyée
- Pour désactiver une programmation et en créer une nouvelle sur la même période, il faut d'abord mettre `actif=false` sur l'ancienne

#### Corps de la requête (Request body)

```json
{
  "nom_programmation": "Programmation normale",
  "date_debut": "2025-09-01",
  "date_fin": "2026-06-30",
  "actif": true,
  "calendrier_id": null,
  "horaires_sonneries": [
    {
      "heure": 8,
      "minute": 0,
      "jours": [1, 2, 3, 4, 5],
      "duree_sonnerie": 3,
      "description": "Début des cours"
    },
    {
      "heure": 10,
      "minute": 0,
      "jours": [1, 2, 3, 4, 5],
      "duree_sonnerie": 5,
      "description": "Récréation"
    }
  ],
  "jours_feries_inclus": false,
  "jours_feries_exceptions": [
    {
      "date": "2025-12-25",
      "action": "exclude",
      "est_national": true,
      "recurrent": true
    }
  ]
}
```

#### Exemple de requête

```javascript
const createProgrammation = async (sireneId, data) => {
  try {
    const response = await axios.post(
      `/api/sirenes/${sireneId}/programmations`,
      data,
      {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      }
    );

    return response.data;
  } catch (error) {
    if (error.response?.status === 422) {
      // Erreurs de validation
      console.error('Erreurs validation:', error.response.data.errors);
    }
    throw error;
  }
};
```

#### Réponse (201 Created)

```json
{
  "success": true,
  "message": "Programmation créée avec succès.",
  "data": {
    "id": "01HN5KZQR7WYX9G3V8M2TCBJ4P",
    "nom_programmation": "Programmation normale",
    // ... tous les champs de la programmation créée
  }
}
```

---

### Voir une programmation

**Endpoint:** `GET /api/sirenes/{sirene_id}/programmations/{programmation_id}`

#### Exemple de requête

```javascript
const getProgrammation = async (sireneId, programmationId) => {
  const response = await axios.get(
    `/api/sirenes/${sireneId}/programmations/${programmationId}`
  );
  return response.data;
};
```

---

### Modifier une programmation

**Endpoint:** `PUT /api/sirenes/{sirene_id}/programmations/{programmation_id}`

Tous les champs sont optionnels (utilisez `PATCH` style).

#### Exemple de requête

```javascript
const updateProgrammation = async (sireneId, programmationId, updates) => {
  const response = await axios.put(
    `/api/sirenes/${sireneId}/programmations/${programmationId}`,
    updates
  );
  return response.data;
};

// Exemple: mettre à jour seulement le nom
await updateProgrammation(sireneId, programmationId, {
  nom_programmation: "Nouveau nom"
});
```

---

### Supprimer une programmation

**Endpoint:** `DELETE /api/sirenes/{sirene_id}/programmations/{programmation_id}`

#### Exemple de requête

```javascript
const deleteProgrammation = async (sireneId, programmationId) => {
  await axios.delete(
    `/api/sirenes/${sireneId}/programmations/${programmationId}`
  );
};
```

#### Réponse (204 No Content)

Pas de corps de réponse.

---

## Schémas de données

### HoraireSonnerie

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `heure` | integer (0-23) | ✅ Oui | Heure de la sonnerie |
| `minute` | integer (0-59) | ✅ Oui | Minute de la sonnerie |
| `jours` | array[integer] | ✅ Oui | Jours de la semaine (0=Dimanche...6=Samedi), au moins 1 jour requis |
| `duree_sonnerie` | integer (1-30) | ❌ Non | Durée de la sonnerie en secondes (défaut: 3s) |
| `description` | string (max 255) | ❌ Non | Description de l'horaire |

**Exemple:**
```json
{
  "heure": 8,
  "minute": 30,
  "jours": [1, 2, 3, 4, 5],
  "duree_sonnerie": 5,
  "description": "Début des cours"
}
```

**Contraintes:**
- ✅ Les horaires doivent être triés chronologiquement
- ✅ Pas de doublons (même heure/minute/jours)
- ✅ Les jours doivent être uniques dans le tableau

### JourFerieException

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `date` | string (YYYY-MM-DD) | ✅ Oui | Date de l'exception |
| `action` | string ("include"\|"exclude") | ✅ Oui | Action à appliquer |
| `est_national` | boolean\|null | ❌ Non | Jour férié national (true) ou local (false) |
| `recurrent` | boolean\|null | ❌ Non | Récurrent/annuel (true) ou exceptionnel (false) |

**Exemple:**
```json
{
  "date": "2025-12-25",
  "action": "exclude",
  "est_national": true,
  "recurrent": true
}
```

**Actions:**
- `"include"`: La sonnerie sera active même si c'est un jour férié
- `"exclude"`: La sonnerie ne sera PAS active pour ce jour férié

**Contraintes:**
- ✅ Une seule exception par date
- ✅ Les exceptions sont automatiquement triées chronologiquement

---

## Gestion des erreurs

### Erreurs de validation (422)

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "horaires_sonneries.0.heure": [
      "L'heure doit être comprise entre 0 et 23."
    ],
    "jours_feries_exceptions.0": [
      "Exception en double pour la date 25/12/2025. Une seule exception par date est autorisée."
    ]
  }
}
```

### Erreur de chevauchement de dates (422)

Lorsqu'une programmation active chevauche une autre programmation active existante :

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "date_debut": [
      "Cette période (2025-10-01 au 2026-06-30) chevauche une programmation active existante \"Programmation Trimestre 1\" (2025-09-01 au 2025-12-31). Une seule programmation active par période est autorisée."
    ]
  }
}
```

**Solution**: Désactiver la programmation existante (`actif=false`) ou ajuster les dates pour qu'elles ne se chevauchent pas.

### Erreur d'autorisation (403)

```json
{
  "success": false,
  "message": "Vous n'êtes pas autorisé à effectuer cette action."
}
```

### Erreur non trouvé (404)

```json
{
  "success": false,
  "message": "Ressource non trouvée."
}
```

### Erreur serveur (500)

```json
{
  "success": false,
  "message": "Une erreur est survenue."
}
```

---

## TypeScript Interfaces

```typescript
// Types pour TypeScript
interface Programmation {
  id: string;
  ecole_id: string;
  site_id: string;
  sirene_id: string;
  abonnement_id: string;
  calendrier_id: string | null;
  nom_programmation: string;
  horaires_sonneries: HoraireSonnerie[];
  jour_semaine: number[];
  jours_feries_inclus: boolean;
  jours_feries_exceptions: JourFerieException[] | null;
  chaine_programmee: string | null;
  chaine_cryptee: string | null;
  date_debut: string;
  date_fin: string;
  actif: boolean;
  cree_par: string;
  created_at: string;
  updated_at: string;
}

interface HoraireSonnerie {
  heure: number; // 0-23
  minute: number; // 0-59
  jours: number[]; // 0=Dimanche...6=Samedi
  duree_sonnerie?: number; // 1-30 secondes
  description?: string; // max 255 caractères
}

interface JourFerieException {
  date: string; // YYYY-MM-DD
  action: 'include' | 'exclude';
  est_national?: boolean | null;
  recurrent?: boolean | null;
}

interface PaginatedResponse<T> {
  success: boolean;
  message: string;
  data: T[];
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number;
    to: number;
    has_more_pages: boolean;
  };
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
}
```

---

## Notes importantes

1. **Champs auto-assignés**: Les champs `ecole_id`, `site_id`, `abonnement_id`, `cree_par`, `chaine_programmee` et `chaine_cryptee` sont automatiquement assignés par le système et ne doivent PAS être fournis dans le payload
2. **Abonnement actif requis**: Une programmation ne peut être créée que si l'école a un abonnement actif. Le système vérifie automatiquement cela et assigne l'abonnement actif
3. **Pas de chevauchement**: Une seule programmation active par période est autorisée pour une même sirène. Les programmations avec `actif=true` ne peuvent pas avoir des périodes qui se chevauchent
4. **Pagination automatique**: Par défaut, la liste des programmations est paginée (15 items/page)
5. **Tri automatique**: Les horaires et exceptions sont automatiquement triés chronologiquement
6. **Validation stricte**: Les DTOs garantissent la cohérence des données
7. **Relations chargées**: Les relations (ecole, site, sirene, abonnement, etc.) sont incluses automatiquement
8. **Permissions**: Vérifier que l'utilisateur a les permissions nécessaires (`creer_programmation`, `modifier_programmation`, etc.)
9. **ULID**: Les IDs sont au format ULID (26 caractères)

---

## Support

Pour toute question ou problème, contactez l'équipe backend.
