# Projet Edito-2 — Contexte & Décisions d'architecture

> Fork du plugin WordPress **Edito** (workflow éditorial).  
> Objectif : rendre le plugin générique, en supprimant toutes les dépendances hardcodées au site d'origine.

---

## Ce qui change par rapport à Edito v1

| Sujet | Edito v1 | Edito-2 |
|---|---|---|
| Catégories articles | Slugs WP figés + icônes codées dans `Edito_Admin` | WP taxonomy native enrichie via `term_meta('edito_icon')` |
| Catégories partenaires | ENUM `agrement/nco` dans la DB | Table `wp_edito_cat_contact` — 2 niveaux configurables |
| Types contacts | ENUM `client/partenaire` figé | `contact_type VARCHAR(50)` libre |
| Import contacts | Aucun | Import CSV AJAX — upsert sur `(nom + email)` |
| Niveaux catégories | 1 seul | 2 niveaux max (contrôlé à l'écriture) |
| CSS templates | `style=""` inline présents | Zéro style inline — BEM exclusivement |

---

## Décisions d'architecture

### 1. Deux référentiels séparés

**Articles** → WP taxonomy `category` (native)
- Enrichi via `term_meta('edito_icon')` → URL médiathèque WP
- 2e niveau via le champ `parent` natif de WP
- `Edito_Categories::get_article_icon($term_id)` remplace `Edito_Admin::get_category_icon($slug)`

**Partenaires** → table custom `wp_edito_cat_contact`
- `cat_parent = 0` → niveau 1 / `cat_parent = cat_id` → niveau 2
- Maximum 2 niveaux (contrôlé à l'écriture)
- Indépendant du WP taxonomy

### 2. Contacts refactorisés
- Table `wp_edito_contacts` — suppression des ENUMs
- `contact_cat_id INT FK` → vers `wp_edito_cat_contact`
- Import CSV : upsert sur `(contact_nom, contact_email)`
- Rapport d'import ligne par ligne : ok / updated / skip

### 3. Règle CSS absolue
**Aucun `style=""` de présentation dans les templates PHP.**  
Tout passe par des classes BEM (`edito-stat-card__icon--pending`, etc.).  
Exception tolérée : `style="display:none/flex"` pour le pilotage d'état JS uniquement.

---

## Structure du fork

```
wp-content/plugins/
├── edito/              ← plugin original (non modifié)
└── edito-v2/
    ├── edito-v2.php    ← point d'entrée
    ├── includes/
    │   ├── class-edito-categories.php   ← NOUVEAU
    │   ├── class-edito-db.php           ← FORKÉ
    │   ├── class-edito-core.php
    │   ├── class-edito-editor.php
    │   └── class-edito-admin.php
    ├── templates/
    │   ├── dashboard.php                ← MODIFIÉ
    │   ├── editor.php                   ← MODIFIÉ
    │   ├── contacts.php                 ← FORKÉ
    │   ├── categories.php               ← NOUVEAU
    │   └── login.php
    └── assets/
        ├── css/
        │   ├── edito-style.css
        │   ├── dashboard-style.css      ← NOUVEAU
        │   ├── categories-style.css     ← NOUVEAU
        │   ├── contacts-style.css       ← FORKÉ
        │   └── edito-shared.css         ← À CRÉER (drawer commun)
        └── [js, images…]
```

---

## Fichiers produits en session

| # | Module | Fichiers |
|---|---|---|
| 1 | Classes PHP | `class-edito-categories.php` |
| 2 | Classes PHP | `class-edito-db.php` (fork) |
| 3 | Template catégories | `categories.php` + `categories-style.css` |
| 4 | Template contacts | `contacts.php` + `contacts-style.css` |
| 5 | Dashboard + Éditeur | `dashboard.php` + `editor.php` + `dashboard-style.css` |

---

## Points de vigilance avant déploiement

1. **CSS drawer dupliqué** dans `categories-style.css` et `contacts-style.css`  
   → À consolider dans `edito-shared.css`, chargé via `wp_enqueue_style` avec dépendance `edito-style`

2. **Enqueue CSS** à ajouter dans le core pour chaque nouvelle page :
   ```php
   wp_enqueue_style( 'edito-categories', EDITO_ASSETS_URL . 'css/categories-style.css', ['edito-style'], EDITO_VERSION );
   wp_enqueue_style( 'edito-contacts',   EDITO_ASSETS_URL . 'css/contacts-style.css',   ['edito-style'], EDITO_VERSION );
   wp_enqueue_style( 'edito-dashboard',  EDITO_ASSETS_URL . 'css/dashboard-style.css',  ['edito-style'], EDITO_VERSION );
   ```

3. **Activation** : `register_activation_hook` doit appeler `Edito_Categories::install()` ET `Edito_DB::install()`

4. **Déploiement** : désactiver `edito` avant d'activer `edito-v2` dans WP Admin

---

## Workflow Git recommandé

```
main          ← code stable déployé sur prod
├── develop
│   ├── feature/categories-template
│   ├── feature/csv-import
│   └── feature/editor-dynamic-cats
└── hotfix/…
```

**Déploiement o2switch via SSH :**
```bash
cd ~/public_html/wp-content/plugins
git clone https://github.com/toi/edito-v2.git edito-v2
# Mise à jour : git pull
```

---

## Skills à consulter pour tout travail sur ce projet

| Skill | Quand l'utiliser |
|---|---|
| `edito-plugin` | Avant tout fichier PHP (routing, classes, AJAX, nonces) |
| `edito-bdd` | Avant toute requête SQL ou appel `Edito_DB` |
| `edito-design` | Avant tout HTML/CSS (classes BEM, variables CSS, composants) |
| `frontend-design` | Pour toute interface web générique |
