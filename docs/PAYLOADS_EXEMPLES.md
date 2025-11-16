# Exemples de Payloads - API Calendrier Scolaire et Programmation

Ce document présente des exemples concrets de payloads pour toutes les opérations des APIs Calendrier Scolaire et Programmation.

## Table des matières
- [Calendrier Scolaire](#calendrier-scolaire)
  - [Créer un calendrier](#1-créer-un-calendrier-scolaire)
  - [Mettre à jour un calendrier](#2-mettre-à-jour-un-calendrier-scolaire)
  - [Ajouter des jours fériés](#3-ajouter-des-jours-fériés-en-masse)
  - [Mettre à jour des jours fériés](#4-mettre-à-jour-des-jours-fériés-en-masse)
- [Programmation](#programmation)
  - [Créer une programmation](#1-créer-une-programmation)
  - [Mettre à jour une programmation](#2-mettre-à-jour-une-programmation)

---

## Calendrier Scolaire

### 1. Créer un Calendrier Scolaire

**Endpoint:** `POST /api/calendrier-scolaire`

**Headers:**
```json
{
  "Authorization": "Bearer {access_token}",
  "Content-Type": "application/json",
  "Accept": "application/json"
}
```

**Payload complet:**
```json
{
  "pays_id": "01JA8B9C0D1E2F3G4H5J6K7M8N",
  "annee_scolaire": "2025-2026",
  "description": "Calendrier scolaire pour l'année académique 2025-2026 au Sénégal",
  "date_rentree": "01/09/2025",
  "date_fin_annee": "30/06/2026",
  "periodes_vacances": [
    {
      "nom": "Vacances de Noël",
      "date_debut": "20/12/2025",
      "date_fin": "05/01/2026"
    },
    {
      "nom": "Vacances de Pâques",
      "date_debut": "10/04/2026",
      "date_fin": "20/04/2026"
    },
    {
      "nom": "Vacances d'été",
      "date_debut": "01/07/2026",
      "date_fin": "31/08/2026"
    }
  ],
  "jours_feries_defaut": [
    {
      "nom": "Jour de l'An",
      "date": "01/01/2026"
    },
    {
      "nom": "Fête de l'Indépendance",
      "date": "04/04/2026"
    },
    {
      "nom": "Fête du Travail",
      "date": "01/05/2026"
    },
    {
      "nom": "Tabaski",
      "date": "15/06/2026"
    },
    {
      "nom": "Assomption",
      "date": "15/08/2026"
    }
  ],
  "actif": true
}
```

**Payload minimal (champs requis uniquement):**
```json
{
  "pays_id": "01JA8B9C0D1E2F3G4H5J6K7M8N",
  "annee_scolaire": "2025-2026",
  "date_rentree": "01/09/2025",
  "date_fin_annee": "30/06/2026"
}
```

**Réponse (201 Created):**
```json
{
  "status": "success",
  "message": "Calendrier scolaire créé avec succès",
  "data": {
    "id": "01JB5X7Y9Z0A1B2C3D4E5F6G7H",
    "pays_id": "01JA8B9C0D1E2F3G4H5J6K7M8N",
    "annee_scolaire": "2025-2026",
    "description": "Calendrier scolaire pour l'année académique 2025-2026 au Sénégal",
    "date_rentree": "2025-09-01",
    "date_fin_annee": "2026-06-30",
    "periodes_vacances": [
      {
        "nom": "Vacances de Noël",
        "date_debut": "2025-12-20",
        "date_fin": "2026-01-05"
      },
      {
        "nom": "Vacances de Pâques",
        "date_debut": "2026-04-10",
        "date_fin": "2026-04-20"
      },
      {
        "nom": "Vacances d'été",
        "date_debut": "2026-07-01",
        "date_fin": "2026-08-31"
      }
    ],
    "jours_feries_defaut": [
      {
        "nom": "Jour de l'An",
        "date": "2026-01-01"
      },
      {
        "nom": "Fête de l'Indépendance",
        "date": "2026-04-04"
      },
      {
        "nom": "Fête du Travail",
        "date": "2026-05-01"
      },
      {
        "nom": "Tabaski",
        "date": "2026-06-15"
      },
      {
        "nom": "Assomption",
        "date": "2026-08-15"
      }
    ],
    "actif": true,
    "created_at": "2025-11-15T10:30:00.000000Z",
    "updated_at": "2025-11-15T10:30:00.000000Z",
    "deleted_at": null
  }
}
```

**Règles de validation:**
- `pays_id`: **Requis**, doit exister dans la table `pays`
- `annee_scolaire`: **Requis**, format `YYYY-YYYY` (années consécutives), ne peut pas être dans le passé
- `date_rentree`: **Requis**, format `dd/mm/YYYY` (converti en `Y-m-d`)
- `date_fin_annee`: **Requis**, doit être après `date_rentree`
- `periodes_vacances`: Tableau optionnel d'objets avec `nom`, `date_debut`, `date_fin`
- `jours_feries_defaut`: Tableau optionnel d'objets avec `nom`, `date`
- `actif`: Boolean optionnel (par défaut `true`)

---

### 2. Mettre à jour un Calendrier Scolaire

**Endpoint:** `PUT /api/calendrier-scolaire/{id}`

**Payload (tous les champs sont optionnels):**
```json
{
  "description": "Calendrier mis à jour avec nouvelles dates",
  "periodes_vacances": [
    {
      "nom": "Vacances de Noël",
      "date_debut": "23/12/2025",
      "date_fin": "07/01/2026"
    },
    {
      "nom": "Vacances de Pâques",
      "date_debut": "13/04/2026",
      "date_fin": "23/04/2026"
    }
  ],
  "actif": true
}
```

**Réponse (200 OK):**
```json
{
  "status": "success",
  "message": "Calendrier scolaire mis à jour avec succès",
  "data": {
    "id": "01JB5X7Y9Z0A1B2C3D4E5F6G7H",
    "pays_id": "01JA8B9C0D1E2F3G4H5J6K7M8N",
    "annee_scolaire": "2025-2026",
    "description": "Calendrier mis à jour avec nouvelles dates",
    "date_rentree": "2025-09-01",
    "date_fin_annee": "2026-06-30",
    "periodes_vacances": [
      {
        "nom": "Vacances de Noël",
        "date_debut": "2025-12-23",
        "date_fin": "2026-01-07"
      },
      {
        "nom": "Vacances de Pâques",
        "date_debut": "2026-04-13",
        "date_fin": "2026-04-23"
      }
    ],
    "actif": true,
    "updated_at": "2025-11-15T14:20:00.000000Z"
  }
}
```

---

### 3. Ajouter des Jours Fériés en Masse

**Endpoint:** `POST /api/calendrier-scolaire/{id}/jours-feries/bulk`

**Payload:**
```json
[
  {
    "intitule_journee": "Magal de Touba",
    "date": "2025-10-15",
    "recurrent": true,
    "actif": true,
    "est_national": true
  },
  {
    "intitule_journee": "Journée Pédagogique École ABC",
    "date": "2025-11-20",
    "recurrent": false,
    "actif": true,
    "est_national": false
  },
  {
    "intitule_journee": "Korité",
    "date": "2026-04-21",
    "recurrent": true,
    "actif": true,
    "est_national": true
  }
]
```

**Réponse (200 OK):**
```json
{
  "status": "success",
  "message": "3 jours fériés créés avec succès",
  "data": [
    {
      "id": "01JC1M2N3P4Q5R6S7T8U9V0W1X",
      "calendrier_id": "01JB5X7Y9Z0A1B2C3D4E5F6G7H",
      "ecole_id": null,
      "pays_id": "01JA8B9C0D1E2F3G4H5J6K7M8N",
      "libelle": "Magal de Touba",
      "nom": "Magal de Touba",
      "date_ferie": "2025-10-15",
      "date": "2025-10-15",
      "type": "national",
      "recurrent": true,
      "actif": true,
      "created_at": "2025-11-15T15:00:00.000000Z",
      "updated_at": "2025-11-15T15:00:00.000000Z"
    },
    {
      "id": "01JC1M2N3P4Q5R6S7T8U9V0W2Y",
      "calendrier_id": "01JB5X7Y9Z0A1B2C3D4E5F6G7H",
      "ecole_id": "01JD2E3F4G5H6J7K8L9M0N1P2Q",
      "pays_id": null,
      "libelle": "Journée Pédagogique École ABC",
      "nom": "Journée Pédagogique École ABC",
      "date_ferie": "2025-11-20",
      "date": "2025-11-20",
      "type": "personnalise",
      "recurrent": false,
      "actif": true,
      "created_at": "2025-11-15T15:00:00.000000Z",
      "updated_at": "2025-11-15T15:00:00.000000Z"
    },
    {
      "id": "01JC1M2N3P4Q5R6S7T8U9V0W3Z",
      "calendrier_id": "01JB5X7Y9Z0A1B2C3D4E5F6G7H",
      "ecole_id": null,
      "pays_id": "01JA8B9C0D1E2F3G4H5J6K7M8N",
      "libelle": "Korité",
      "nom": "Korité",
      "date_ferie": "2026-04-21",
      "date": "2026-04-21",
      "type": "national",
      "recurrent": true,
      "actif": true,
      "created_at": "2025-11-15T15:00:00.000000Z",
      "updated_at": "2025-11-15T15:00:00.000000Z"
    }
  ]
}
```

**Règles de validation:**
- `intitule_journee`: **Requis**, string
- `date`: **Requis**, format `YYYY-MM-DD`
- `recurrent`: **Requis**, boolean (true si le jour férié se répète chaque année)
- `actif`: Boolean optionnel (par défaut `true`)
- `est_national`: Boolean optionnel (par défaut `false`)

---

### 4. Mettre à jour des Jours Fériés en Masse

**Endpoint:** `PUT /api/calendrier-scolaire/{id}/jours-feries/bulk`

**Payload (avec ID pour mise à jour):**
```json
[
  {
    "id": "01JC1M2N3P4Q5R6S7T8U9V0W1X",
    "intitule_journee": "Magal de Touba (Modifié)",
    "date": "2025-10-16",
    "recurrent": true,
    "actif": true,
    "est_national": true
  },
  {
    "intitule_journee": "Nouveau jour férié",
    "date": "2026-05-10",
    "recurrent": false,
    "actif": true,
    "est_national": false
  }
]
```

**Note:** Si `id` est fourni, le jour férié est mis à jour. Sinon, il est créé.

---

## Programmation

### 1. Créer une Programmation

**Endpoint:** `POST /api/sirenes/{sirene}/programmations`

**Headers:**
```json
{
  "Authorization": "Bearer {access_token}",
  "Content-Type": "application/json",
  "Accept": "application/json"
}
```

**Payload complet:**
```json
{
  "nom_programmation": "Programmation Trimestre 1 - 2025",
  "date_debut": "2025-09-01",
  "date_fin": "2025-12-20",
  "calendrier_id": "01JB5X7Y9Z0A1B2C3D4E5F6G7H",
  "horaires_sonneries": [
    "08:00",
    "10:00",
    "10:15",
    "12:00",
    "14:00",
    "16:00",
    "16:15",
    "18:00"
  ],
  "jour_semaine": [
    "Lundi",
    "Mardi",
    "Mercredi",
    "Jeudi",
    "Vendredi"
  ],
  "jours_feries_inclus": false,
  "jours_feries_exceptions": [
    {
      "date": "2025-10-15",
      "action": "include"
    },
    {
      "date": "2025-11-01",
      "action": "exclude"
    }
  ],
  "abonnement_id": "01JE3F4G5H6J7K8L9M0N1P2Q3R",
  "actif": true
}
```

**Payload minimal (champs requis uniquement):**
```json
{
  "nom_programmation": "Programmation Standard",
  "date_debut": "2025-09-01",
  "date_fin": "2025-12-20",
  "horaires_sonneries": [
    "08:00",
    "12:00",
    "14:00",
    "18:00"
  ],
  "jour_semaine": [
    "Lundi",
    "Mardi",
    "Mercredi",
    "Jeudi",
    "Vendredi"
  ]
}
```

**Réponse (201 Created):**
```json
{
  "status": "success",
  "message": "Programmation créée avec succès",
  "data": {
    "id": "01JF5G6H7J8K9L0M1N2P3Q4R5S",
    "ecole_id": "01JD2E3F4G5H6J7K8L9M0N1P2Q",
    "site_id": "01JG7H8J9K0L1M2N3P4Q5R6S7T",
    "sirene_id": "01JH8J9K0L1M2N3P4Q5R6S7T8U",
    "abonnement_id": "01JE3F4G5H6J7K8L9M0N1P2Q3R",
    "calendrier_id": "01JB5X7Y9Z0A1B2C3D4E5F6G7H",
    "nom_programmation": "Programmation Trimestre 1 - 2025",
    "horaire_json": null,
    "horaires_sonneries": [
      "08:00",
      "10:00",
      "10:15",
      "12:00",
      "14:00",
      "16:00",
      "16:15",
      "18:00"
    ],
    "horaire_debut": "08:00",
    "horaire_fin": "18:00",
    "jour_semaine": [
      "Lundi",
      "Mardi",
      "Mercredi",
      "Jeudi",
      "Vendredi"
    ],
    "jours_feries_inclus": false,
    "vacances": null,
    "types_etablissement": [],
    "chaine_programmee": "Programmation: Programmation Trimestre 1 - 2025 | Jours: Lundi, Mardi, Mercredi, Jeudi, Vendredi | Horaires: 08:00, 10:00, 10:15, 12:00, 14:00, 16:00, 16:15, 18:00 | Période: 01/09/2025 au 20/12/2025",
    "chaine_cryptee": "eyJpdiI6IjhXZkRjNGxYTzBVcGRQdWpvRFR...",
    "date_debut": "2025-09-01",
    "date_fin": "2025-12-20",
    "actif": true,
    "date_creation": "2025-11-15T16:00:00.000000Z",
    "cree_par": "01JK9L0M1N2P3Q4R5S6T7U8V9W",
    "created_at": "2025-11-15T16:00:00.000000Z",
    "updated_at": "2025-11-15T16:00:00.000000Z",
    "deleted_at": null,
    "jours_feries_exceptions": [
      {
        "date": "2025-10-15",
        "action": "include"
      },
      {
        "date": "2025-11-01",
        "action": "exclude"
      }
    ]
  }
}
```

**Règles de validation:**
- `nom_programmation`: **Requis**, string max 255 caractères
- `date_debut`: **Requis**, date, doit être avant ou égale à `date_fin`
- `date_fin`: **Requis**, date, doit être après ou égale à `date_debut`
- `horaires_sonneries`: **Requis**, tableau d'au moins 1 horaire au format `HH:MM`, triés chronologiquement, sans doublons
- `jour_semaine`: **Requis**, tableau d'au moins 1 jour, valeurs possibles: `Lundi`, `Mardi`, `Mercredi`, `Jeudi`, `Vendredi`, `Samedi`, `Dimanche`
- `calendrier_id`: Optionnel, doit exister dans la table `calendriers_scolaires`
- `jours_feries_inclus`: Boolean optionnel (par défaut `false`)
- `jours_feries_exceptions`: Tableau optionnel d'objets avec `date` (format `YYYY-MM-DD`) et `action` (`include` ou `exclude`)
- `abonnement_id`: Optionnel, doit exister et être actif, les dates doivent être couvertes par l'abonnement
- `actif`: Boolean optionnel (par défaut `true`)

**Notes importantes:**
- Les horaires sont automatiquement triés et validés
- La `chaine_programmee` et la `chaine_cryptee` sont générées automatiquement
- L'école doit avoir un abonnement actif
- La sirène doit appartenir à l'école connectée
- `horaire_debut` et `horaire_fin` sont automatiquement extraits du premier et dernier horaire

---

### 2. Mettre à jour une Programmation

**Endpoint:** `PUT /api/sirenes/{sirene}/programmations/{programmation}`

**Payload (tous les champs sont optionnels):**
```json
{
  "nom_programmation": "Programmation T1 - Modifiée",
  "horaires_sonneries": [
    "08:30",
    "10:30",
    "12:30",
    "14:30",
    "16:30"
  ],
  "actif": true
}
```

**Payload pour modifier uniquement les jours:**
```json
{
  "jour_semaine": [
    "Lundi",
    "Mardi",
    "Mercredi",
    "Jeudi",
    "Vendredi",
    "Samedi"
  ]
}
```

**Payload pour ajouter/modifier des exceptions de jours fériés:**
```json
{
  "jours_feries_inclus": true,
  "jours_feries_exceptions": [
    {
      "date": "2025-12-25",
      "action": "exclude"
    },
    {
      "date": "2025-11-15",
      "action": "include"
    }
  ]
}
```

**Réponse (200 OK):**
```json
{
  "status": "success",
  "message": "Programmation mise à jour avec succès",
  "data": {
    "id": "01JF5G6H7J8K9L0M1N2P3Q4R5S",
    "ecole_id": "01JD2E3F4G5H6J7K8L9M0N1P2Q",
    "site_id": "01JG7H8J9K0L1M2N3P4Q5R6S7T",
    "sirene_id": "01JH8J9K0L1M2N3P4Q5R6S7T8U",
    "nom_programmation": "Programmation T1 - Modifiée",
    "horaires_sonneries": [
      "08:30",
      "10:30",
      "12:30",
      "14:30",
      "16:30"
    ],
    "horaire_debut": "08:30",
    "horaire_fin": "16:30",
    "chaine_programmee": "Programmation: Programmation T1 - Modifiée | Jours: Lundi, Mardi, Mercredi, Jeudi, Vendredi | Horaires: 08:30, 10:30, 12:30, 14:30, 16:30 | Période: 01/09/2025 au 20/12/2025",
    "chaine_cryptee": "eyJpdiI6Im5ld0VuY3J5cHRlZFRva2VuLi4u",
    "actif": true,
    "updated_at": "2025-11-15T17:30:00.000000Z"
  }
}
```

**Champs interdits (en lecture seule):**
- `ecole_id`: Ne peut pas être modifié
- `site_id`: Ne peut pas être modifié
- `sirene_id`: Ne peut pas être modifié
- `cree_par`: Ne peut pas être modifié
- `chaine_programmee`: Généré automatiquement
- `chaine_cryptee`: Généré automatiquement

**Note:** Après modification des horaires ou jours, la `chaine_programmee` et la `chaine_cryptee` sont automatiquement régénérées.

---

## Cas d'usage avancés

### Exemple 1: Programmation avec calendrier scolaire

Ce cas d'usage permet à une école de créer une programmation qui respecte automatiquement le calendrier scolaire (vacances et jours fériés).

```json
{
  "nom_programmation": "Programmation Année Complète 2025-2026",
  "date_debut": "2025-09-01",
  "date_fin": "2026-06-30",
  "calendrier_id": "01JB5X7Y9Z0A1B2C3D4E5F6G7H",
  "horaires_sonneries": [
    "08:00",
    "09:00",
    "10:00",
    "10:15",
    "11:15",
    "12:15",
    "14:00",
    "15:00",
    "16:00"
  ],
  "jour_semaine": [
    "Lundi",
    "Mardi",
    "Mercredi",
    "Jeudi",
    "Vendredi"
  ],
  "jours_feries_inclus": false
}
```

**Comportement:**
- La sirène ne sonnera PAS pendant les périodes de vacances définies dans le calendrier
- La sirène ne sonnera PAS les jours fériés nationaux du pays
- La programmation suivra automatiquement les horaires définis pour les jours de classe

---

### Exemple 2: Programmation avec exceptions de jours fériés

Ce cas permet de gérer finement les jours fériés (inclure certains, exclure d'autres).

```json
{
  "nom_programmation": "Programmation avec Exceptions",
  "date_debut": "2025-09-01",
  "date_fin": "2025-12-20",
  "calendrier_id": "01JB5X7Y9Z0A1B2C3D4E5F6G7H",
  "horaires_sonneries": [
    "08:00",
    "12:00",
    "14:00",
    "18:00"
  ],
  "jour_semaine": [
    "Lundi",
    "Mardi",
    "Mercredi",
    "Jeudi",
    "Vendredi"
  ],
  "jours_feries_inclus": false,
  "jours_feries_exceptions": [
    {
      "date": "2025-11-01",
      "action": "include"
    },
    {
      "date": "2025-12-25",
      "action": "exclude"
    }
  ]
}
```

**Comportement:**
- Par défaut, les jours fériés sont exclus (`jours_feries_inclus: false`)
- Le 1er novembre 2025: sonnerie ACTIVE (exception `include`)
- Le 25 décembre 2025: sonnerie INACTIVE (exception `exclude` explicite)

---

### Exemple 3: Programmation du samedi (demi-journée)

Pour les écoles qui travaillent le samedi matin.

```json
{
  "nom_programmation": "Programmation Samedi Matin",
  "date_debut": "2025-09-01",
  "date_fin": "2026-06-30",
  "horaires_sonneries": [
    "08:00",
    "10:00",
    "10:15",
    "12:00"
  ],
  "jour_semaine": [
    "Samedi"
  ],
  "jours_feries_inclus": false
}
```

---

### Exemple 4: Récupération des programmations effectives à une date

**Endpoint:** `GET /api/sirenes/{sirene}/programmations?date=2025-11-15`

**Réponse:**
```json
{
  "status": "success",
  "data": [
    {
      "id": "01JF5G6H7J8K9L0M1N2P3Q4R5S",
      "nom_programmation": "Programmation Trimestre 1 - 2025",
      "date_debut": "2025-09-01",
      "date_fin": "2025-12-20",
      "horaires_sonneries": [
        "08:00",
        "10:00",
        "10:15",
        "12:00",
        "14:00",
        "16:00",
        "16:15",
        "18:00"
      ],
      "actif": true,
      "is_effective": true,
      "effective_reason": "Date dans la période et jour de semaine valide"
    }
  ]
}
```

**Note:** Le paramètre `?date=YYYY-MM-DD` filtre uniquement les programmations actives à cette date spécifique.

---

## Codes d'erreur courants

### Erreur 422 - Validation

**Exemple: Horaires non triés**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "horaires_sonneries": [
      "Les horaires de sonnerie doivent être triés dans l'ordre chronologique."
    ]
  }
}
```

**Exemple: Jour de semaine invalide**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "jour_semaine.0": [
      "Le jour de la semaine doit être l'un des suivants: Lundi, Mardi, Mercredi, Jeudi, Vendredi, Samedi, Dimanche."
    ]
  }
}
```

**Exemple: Abonnement invalide**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "abonnement_id": [
      "Les dates de programmation (2025-09-01 au 2026-06-30) doivent être couvertes par votre abonnement actif (2025-09-01 au 2025-12-31)."
    ]
  }
}
```

### Erreur 403 - Non autorisé

```json
{
  "message": "Cette action n'est pas autorisée."
}
```

**Causes possibles:**
- L'utilisateur n'est pas une école
- La sirène n'appartient pas à l'école connectée
- Pas de permission pour créer/modifier une programmation

### Erreur 404 - Non trouvé

```json
{
  "message": "Calendrier scolaire non trouvé"
}
```

---

## Notes sur les formats de date

### Format d'entrée (pour les requêtes)

**Calendrier Scolaire:**
- Dates envoyées au format `dd/mm/YYYY` (ex: `01/09/2025`)
- Automatiquement converties en `Y-m-d` par le serveur

**Programmation:**
- Horaires au format `HH:MM` (ex: `08:00`, `14:30`)
- Dates au format `YYYY-MM-DD` (ex: `2025-09-01`)

### Format de sortie (dans les réponses)

- Dates: `YYYY-MM-DD` (ex: `2025-09-01`)
- Heures: `HH:MM` (ex: `08:00`)
- DateTime: `YYYY-MM-DDTHH:MM:SS.000000Z` (ISO 8601)

---

## Résumé des endpoints

### Calendrier Scolaire
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/calendrier-scolaire` | Liste tous les calendriers |
| POST | `/api/calendrier-scolaire` | Créer un calendrier |
| GET | `/api/calendrier-scolaire/{id}` | Voir un calendrier |
| PUT | `/api/calendrier-scolaire/{id}` | Mettre à jour un calendrier |
| DELETE | `/api/calendrier-scolaire/{id}` | Supprimer un calendrier |
| GET | `/api/calendrier-scolaire/{id}/jours-feries` | Liste les jours fériés |
| GET | `/api/calendrier-scolaire/{id}/calculate-school-days` | Calculer les jours d'école |
| POST | `/api/calendrier-scolaire/{id}/jours-feries/bulk` | Créer des jours fériés en masse |
| PUT | `/api/calendrier-scolaire/{id}/jours-feries/bulk` | Mettre à jour des jours fériés en masse |

### Programmation
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/sirenes/{sirene}/programmations` | Liste toutes les programmations |
| GET | `/api/sirenes/{sirene}/programmations?date=YYYY-MM-DD` | Liste les programmations effectives à une date |
| POST | `/api/sirenes/{sirene}/programmations` | Créer une programmation |
| GET | `/api/sirenes/{sirene}/programmations/{id}` | Voir une programmation |
| PUT | `/api/sirenes/{sirene}/programmations/{id}` | Mettre à jour une programmation |
| DELETE | `/api/sirenes/{sirene}/programmations/{id}` | Supprimer une programmation |

---

**Dernière mise à jour:** 15 novembre 2025
