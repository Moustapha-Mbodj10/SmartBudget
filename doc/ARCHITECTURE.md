# Architecture de SmartBudget

## Vue d'ensemble

SmartBudget suit le modèle **MVC** de Laravel 12 avec un rendu **côté serveur** (vues Blade). Il n'y a pas d'API JSON séparée :
les « API » du projet sont les routes web et leurs contrôleurs. Seules les données des graphiques sont transmises en JSON à Chart.js.

```
Navigateur ──► routes/web.php ──► middlewares ──► Contrôleur ──► Form Request (validation)
                                  (auth, actif,        │            Policy (autorisation)
                                   admin, utilisateur) │
                                                       ├──► Modèles Eloquent ──► MySQL
                                                       ├──► Services (BudgetStats, FiltresTransactions)
                                                       └──► Vue Blade ──► HTML + Bootstrap 5 + Chart.js
```

| Couche | Technologie | Rôle |
|---|---|---|
| Présentation | Blade, Bootstrap 5, Bootstrap Icons, Chart.js (servis en local dans `public/vendor`, aucun CDN) | Pages, formulaires, graphiques |
| Métier | PHP 8.2, Laravel 12 : contrôleurs, middlewares, policies, Form Requests, services | Règles de gestion, validation, calculs |
| Persistance | Eloquent, MySQL 8 / MariaDB 10.4 (XAMPP) | Stockage |

L'application fonctionne **sans Internet** (bibliothèques front incluses), ce qui convient à la soutenance en local.

## Base de données

Quatre tables métier ; l'héritage « Administrateur hérite d'Utilisateur » est réalisé par la colonne `role` de `users`.
Sessions, cache et files d'attente utilisent des fichiers : il n'y a donc **pas** de tables `sessions`, `cache` ni `jobs`.

```
users ─────────┬──────────────┬─────────────┐
 id            │ 1..n         │ 1..n        │ 1..n
 nom, prenom   ▼              ▼             ▼
 email (unique) categories   depenses      revenus
 password (bcrypt) id         id            id
 role: utilisateur|admin  user_id ─┐   user_id (CASCADE)   user_id (CASCADE)
 statut: actif|suspendu   nom      │   categorie_id ───┐   categorie_id ───┐
 remember_token           description  montant DECIMAL(12,2)  montant DECIMAL(12,2)
 created_at, updated_at   type: depense|revenu  date, description  date, description
                          unique(user_id, nom, type)  index(user_id, date)  index(user_id, date)
                              ▲                          │ RESTRICT          │ RESTRICT
                              └──────────────────────────┴───────────────────┘
```

| Relation | Règle | Conséquence |
|---|---|---|
| `categories.user_id → users.id` | `ON DELETE CASCADE` | Supprimer un compte supprime ses catégories |
| `depenses.user_id`, `revenus.user_id → users.id` | `ON DELETE CASCADE` | Idem pour ses transactions |
| `depenses.categorie_id`, `revenus.categorie_id → categories.id` | `ON DELETE RESTRICT` | Une catégorie utilisée ne peut pas être supprimée |
| `categories` : unique `(user_id, nom, type)` | — | Même nom autorisé pour deux types ou deux utilisateurs différents |

> **Point d'attention** : avec ces règles du cahier des charges, `DELETE FROM users` échoue dès que l'utilisateur a des transactions
> (la cascade vers ses catégories bute sur le RESTRICT, erreur MySQL 1451). L'administration supprime donc **d'abord** ses dépenses et revenus,
> puis le compte, **dans une transaction SQL** (`Admin\UtilisateurController::destroy`).

Les montants sont en `DECIMAL(12,2)` (pas de nombres à virgule flottante) ; les dates en `DATE`. Fuseau : `Africa/Dakar`. Langue : français.

## Modèles

| Modèle | Table | Relations | Particularités |
|---|---|---|---|
| `User` | `users` | `hasMany` Categorie, Depense, Revenu | `role` et `statut` **non assignables en masse** ; `isAdmin()`, `isActif()`, `homeRoute()` ; mot de passe haché (cast `hashed`) |
| `Categorie` | `categories` | `belongsTo` User ; `hasMany` Depense, Revenu | `creerParDefaut()` crée les 13 catégories de départ (dont 4 de factures) ; `estUtilisee()` |
| `Depense`, `Revenu` | `depenses`, `revenus` | `belongsTo` User, Categorie | `montant` en `decimal:2`, `date` en `date` ; `user_id` fixé par le contrôleur, jamais par le formulaire |

## Routes

Toutes dans `routes/web.php`. Accès : *invité* = pas connecté ; *connecté* = `auth` + `actif` ; *utilisateur* = en plus `utilisateur` (403 pour l'administrateur) ; *admin* = en plus `admin` (403 sinon).

| Méthode | URL | Action | Accès |
|---|---|---|---|
| GET | `/` | redirige vers `/login` ou l'espace du rôle | tous |
| GET, POST | `/register` | `AuthController@showRegister`, `register` (10/min) | invité |
| GET, POST | `/login` | `AuthController@showLogin`, `login` (5/min) | invité |
| POST | `/logout` | `AuthController@logout` | connecté |
| GET, PUT | `/profil`, `/profil/password` | `ProfilController@edit`, `update`, `updatePassword` (5/min) | connecté |
| GET | `/dashboard` | `DashboardController@index` | utilisateur |
| GET | `/statistiques` | `DashboardController@statistiques` (`?periode=mois\|annee&mois=AAAA-MM&annee=AAAA`) | utilisateur |
| GET | `/historique` | `DashboardController@historique` (filtres `q`, `type`, `categorie_id`, `date_debut`, `date_fin`, `montant_min`, `montant_max`) | utilisateur |
| resource | `/depenses` (sauf `show`) | `DepenseController` | utilisateur |
| resource | `/revenus` (sauf `show`) | `RevenuController` | utilisateur |
| resource | `/categories` (`index`, `store`, `update`, `destroy`) | `CategorieController` | utilisateur |
| GET | `/session/etat` | closure (204) | connecté : battement de session appelé toutes les 15 s par les pages ; toute autre réponse renvoie vers la connexion |
| GET | `/admin/recherche` | `Admin\RechercheController@index` | admin : page dédiée à la recherche d'un utilisateur (texte, statut du compte, en ligne / déconnecté) |
| GET | `/admin` | `Admin\TableauDeBordController@index` | admin : comptes, statut actif / suspendu, en ligne / déconnecté et durée de déconnexion |
| resource | `/admin/utilisateurs` (`index`, `show`, `edit`, `update`, `destroy`) | `Admin\UtilisateurController` | admin |
| PATCH | `/admin/utilisateurs/{id}/suspendre`, `/reactiver` | idem (suspendre et supprimer refusés pour tout compte administrateur) | admin |

## Middlewares

| Alias | Classe | Rôle |
|---|---|---|
| `auth`, `guest` | Laravel | Page réservée aux connectés / aux invités (un connecté est renvoyé vers son espace) |
| `actif` | `EnsureUserIsActive` | Déconnecte un compte suspendu **en cours de session** |
| `admin` | `EnsureUserIsAdmin` | 403 si le rôle n'est pas `admin` |
| `activite` | `SuivreActivite` | note la dernière activité de la personne connectée (au plus une écriture par minute) pour le suivi « en ligne » de l'administrateur |
| `utilisateur` | `EnsureUserIsUtilisateur` | 403 pour l'administrateur : il n'accède jamais aux données budget |
| `throttle` | Laravel | Limitation de débit (connexion, inscription, mot de passe) |
| *(global)* | `SecurityHeaders` | En-têtes de sécurité et interdiction de mettre en cache les pages connectées |

Les middlewares `admin` et `utilisateur` sont prioritaires sur la liaison des modèles (`bootstrap/app.php`) : un utilisateur simple reçoit le même 403 pour un compte existant et inexistant.

## Organisation du code

```
app/Http/Controllers/
    AuthController            inscription, connexion, déconnexion
    CategorieController       catégories (EF6)
    TransactionController     LOGIQUE COMMUNE aux dépenses et aux revenus (abstract)
      ├─ DepenseController    configuration : modèle, type de catégorie, libellés (EF5)
      └─ RevenuController     idem (EF4)
    DashboardController       tableau de bord, statistiques, historique (EF7 à EF11)
    ProfilController          profil (EF3)
    Admin/UtilisateurController  gestion des comptes
app/Services/BudgetStats.php  calculs SQL : totaux, répartition, moyenne, série mensuelle, historique (UNION)
app/Support/FiltresTransactions.php   lecture, validation et application des filtres de recherche
app/Support/helpers.php       format_fcfa() → « 150 000 FCFA », format_date() → « JJ/MM/AAAA »
```

**Choix de conception**

- **Dépenses et revenus partagent leur code** : mêmes champs, mêmes règles. `TransactionController` porte toute la logique ; un contrôleur fils ne fournit que sa configuration (`config()`). Les vues `depenses/*` et `revenus/*` du cahier des charges (§8.1) réutilisent les mises en page communes `transactions/index` et `transactions/form`. La `TransactionRequest` déduit le type depuis le nom de la route.
- **Calculs en SQL**, filtrés sur l'utilisateur connecté (`SUM`, `GROUP BY`) ; les périodes sont des intervalles de dates pour exploiter l'index `(user_id, date)`.
- **Historique** : un `UNION ALL` des deux tables, paginé en SQL, avec un ordre total (date, création, type, id) pour qu'aucune ligne ne soit perdue ni dupliquée entre deux pages.
- **Suppression depuis l'historique** : le formulaire envoie `retour=historique` et la chaîne de recherche ; après suppression on revient sur l'historique avec les mêmes filtres et la même page. Seule la page « historique » est possible (aucune adresse libre : pas de redirection ouverte) et seuls ses paramètres connus sont repris. Si la page demandée n'existe plus (dernière ligne supprimée), on est redirigé vers la dernière page existante, message conservé.
- **Deux soldes** : « Solde du mois » (revenus − dépenses du mois) et « Solde cumulé » (tout depuis le début jusqu'à aujourd'hui, `BudgetStats::soldeCumule()`), affiché sous la carte.
- **Comptes administrateurs protégés** : `UtilisateurController::refuserSiCompteProtege()` refuse de suspendre ou de supprimer son propre compte ainsi que tout autre compte administrateur.
- **Autorisation avant validation** : dans les Form Requests de modification, `authorize()` s'exécute avant `rules()` ; la transaction d'un autre utilisateur donne 403 même avec des données invalides.

## Vues

```
resources/views/
  layouts/        app (barre du haut en pilule, rail d'icônes, panneau burger sur téléphone) · guest (connexion en deux colonnes, erreurs centrées)
  components/     stat-card · alert-flash · confirm-modal · pagination · recherche (barre extensible) · connexion (en ligne / déconnecté) · logo (SVG fixe) · apercu-anime (cartes animées de la connexion)
  auth/           login · register
  dashboard/      index · statistiques · historique
  depenses/       index · create · edit     (vues du cahier des charges : quelques lignes chacune)
  revenus/        index · create · edit     (idem)
  transactions/   index · form              (mise en page COMMUNE aux dépenses et aux revenus)
  categories/     index                 (deux listes + fenêtre modale)
  profil/         edit                  (deux onglets)
  admin/                dashboard · recherche
  admin/utilisateurs/   index · show · edit
  errors/         403 · 404 · 419 · 429 · 500
public/js/        smartbudget.js (confirmations, alertes, barre latérale repliable) · graphiques.js (Chart.js : barres, courbe du solde, anneau) · animations.js (recherche extensible, aperçu animé de connexion)
public/vendor/    Bootstrap, Bootstrap Icons, Chart.js et la police Urbanist (locaux, aucun CDN)
public/css/       smartbudget.css : jetons de design et composants (voir DESIGN.md)
```

Charte : bleu du logo pour la marque ; vert pour les revenus et un solde positif, rouge pour les dépenses et un solde négatif ; montants « 150 000 FCFA » ; dates `JJ/MM/AAAA` ;
alerte verte après chaque ajout, modification ou suppression ; fenêtre de confirmation avant toute suppression ; interface entièrement en français.

## Données de démonstration

`php artisan demo:presence [--maintenir]` (`Console\Commands\PresenceDemo`) simule la présence de ces 22 comptes (12 en ligne, 10 déconnectés depuis des durées variées) pour la démonstration du tableau de bord de l'administrateur.
`php artisan db:seed --class=UtilisateursSupplementairesSeeder` ajoute à la demande 22 comptes utilisateurs avec leurs transactions `[TEST]` (rejouable, hors seeder par défaut).
`php artisan migrate --seed` crée 1 administrateur et 2 utilisateurs (comptes dans le README), leurs catégories par défaut et environ 6 mois de transactions
fictives dont la description commence par `[TEST]`. Le seeder est rejouable et ne touche pas aux vraies transactions. L'export complet est dans `smartbudget/database/smartbudget.sql`.
