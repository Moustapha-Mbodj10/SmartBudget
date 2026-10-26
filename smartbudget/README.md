# SmartBudget

Application web de gestion et de suivi des dépenses personnelles, en **FCFA**.
Chaque utilisateur enregistre ses revenus et ses dépenses, les classe par catégorie et suit sa situation
grâce à un tableau de bord, des statistiques et des graphiques. Un administrateur gère les comptes.

Projet de Licence en Informatique (ESTM, 2025-2026). Stack : **Laravel 12**, **PHP 8.2**, **MySQL / MariaDB**
(XAMPP), Blade, Bootstrap 5, Chart.js.

> La documentation complète (architecture, tests, sécurité, plan d'action) est dans le dossier [`../doc/`](../doc/README.md).

## Prérequis

| Outil | Version | Remarque |
|---|---|---|
| XAMPP | PHP **8.2 ou plus** et MySQL / MariaDB | Seul MySQL doit être démarré (Apache est facultatif) |
| Composer | 2.x | Installe les dépendances PHP |
| Microsoft Edge + Node 20+ | facultatif | Seulement pour la vérification dans un navigateur (voir plus bas) |

Extensions PHP nécessaires (activées par défaut dans XAMPP) : `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`.

## Installation sous XAMPP (Windows)

Dans un terminal, depuis le dossier `smartbudget/` :

```powershell
# 1. Dépendances PHP
composer install

# 2. Fichier de configuration
copy .env.example .env
php artisan key:generate
```

3. **Démarrer MySQL** depuis le panneau de contrôle XAMPP, puis **créer la base** dans phpMyAdmin
   (`http://localhost/phpmyadmin`) : nom `smartbudget`, interclassement `utf8mb4_unicode_ci`.
   Dans `.env`, les valeurs par défaut conviennent à XAMPP (`DB_USERNAME=root`, mot de passe vide).

4. **Remplir la base**, au choix :

   ```powershell
   # Option A — via Laravel (recommandée) : crée les tables, les comptes de démonstration et les données de test
   php artisan migrate --seed
   ```

   ou **Option B** — importer le fichier [`database/smartbudget.sql`](database/smartbudget.sql) dans la base `smartbudget`
   avec phpMyAdmin (onglet *Importer*). Il contient exactement les mêmes tables et données que l'option A.

5. **Lancer l'application** :

   ```powershell
   php artisan serve
   ```

   puis ouvrir **http://127.0.0.1:8000**.

> Si PHP n'est pas dans le `PATH`, utilisez celui de XAMPP, par exemple `C:\xampp\php\php.exe artisan serve`.

## Comptes de démonstration

⚠️ **Données de test** : à ne jamais utiliser en production. Les transactions fictives de ces comptes ont une description qui commence par `[TEST]`.

| Rôle | E-mail | Mot de passe |
|---|---|---|
| Administrateur | `admin@smartbudget.sn` | `Admin1234` |
| Utilisateur | `awa@smartbudget.sn` | `Password123` |
| Utilisateur | `moussa@smartbudget.sn` | `Password123` |

Les deux utilisateurs disposent d'environ 6 mois de transactions fictives (salaire de 250 000 FCFA, loyer de 75 000 FCFA, courses…).

**22 comptes utilisateurs supplémentaires** (Fatou Sow, Ibrahima Fall, Aminata Ba, Cheikh Gueye… : adresses de la forme `prenom.nom@smartbudget.sn`, mot de passe `Password123`), chacun avec environ 6 mois de revenus et de dépenses `[TEST]`, se créent à la demande (ils ne font pas partie de `migrate --seed`) :

```bash
php artisan db:seed --class=UtilisateursSupplementairesSeeder
```

La commande est rejouable sans doublon et ne modifie ni les comptes existants ni leurs mots de passe.

**Présence de démonstration** (pour voir le tableau de bord de l'administrateur vivre) : `php artisan demo:presence` met 12 de ces comptes « en ligne » et les 10 autres « déconnectés » depuis 45 minutes, 3 heures, 1 jour, 3 jours, 1 semaine, 2 semaines, 1 mois, 2 mois, 3 mois et 6 mois. Un compte n'est « en ligne » que 5 minutes après sa dernière activité : ajoutez `--maintenir` pour renouveler la présence chaque minute jusqu'à Ctrl+C. La commande ne touche que ces 22 comptes et est refusée en production.
L'administrateur n'a pas d'espace budget : il gère uniquement les comptes et ne voit jamais le détail des transactions.

## Fonctionnalités

- **Compte** : inscription, connexion (5 essais par minute), déconnexion, profil, changement de mot de passe (nom et prénom modifiables ; l'adresse e-mail est définitive dès l'inscription). La page de connexion est animée par un aperçu de l'application (cartes en FCFA qui entrent en cascade et flottent ; les montants se floutent pendant la saisie du mot de passe), avec un bouton pour afficher le mot de passe.
- **Catégories** : 13 catégories proposées à l'inscription (dont « Facture d'électricité », « Facture d'eau », « Facture d'internet » puis « Facture globale », qui additionne les trois dans « Dépenses par catégorie ») ; ajout, modification, suppression (refusée si la catégorie est utilisée).
- **Dépenses et revenus** : ajout, modification, suppression avec confirmation, recherche, pagination (10 par page).
- **Tableau de bord** : revenus, dépenses, solde (et solde cumulé) et nombre de transactions du mois, barres sur 6 mois, courbe d'évolution du solde, anneau par catégorie, 5 dernières transactions. Une barre latérale fixe (repliable, état mémorisé) donne les ajouts rapides, les catégories, le profil et la déconnexion.
- **Statistiques** : par mois ou par année, répartition par catégorie (montant et %), catégorie la plus coûteuse, moyenne mensuelle.
- **Historique** : toutes les transactions dans une liste, avec recherche multicritère (mot-clé, type, catégorie, dates, montants), modification et suppression de chaque ligne.
- **Tableau de bord de l'administrateur** : nombre de comptes, comptes actifs / suspendus, **personnes en ligne** et, pour chaque personne déconnectée, **depuis combien de temps** (la page se met à jour chaque minute).
- **Recherche d'un utilisateur** : un menu « Rechercher » dédié à l'administrateur (nom, prénom, e-mail, filtres statut et connexion, terme surligné).
- **Administration** : liste, recherche, consultation, modification, suspension / réactivation et suppression des comptes utilisateurs (un compte administrateur ne peut être ni suspendu ni supprimé).

## Lancer les tests

Les tests utilisent une **base séparée** : créez-la une fois dans phpMyAdmin, nom `smartbudget_test`, interclassement `utf8mb4_unicode_ci`. Puis :

```powershell
php artisan test
```

Résultat attendu : **584 tests, 6 014 assertions, tous verts** (environ 1 minute).

> ⚠️ Ne retirez jamais `DB_DATABASE=smartbudget_test` de `phpunit.xml` : chaque test réinitialise la base qu'il utilise, ce qui viderait votre base réelle.

## Vérification dans un vrai navigateur (facultatif)

Le script [`tests/Navigateur/verif_navigateur.cjs`](tests/Navigateur/verif_navigateur.cjs) pilote Microsoft Edge (sans fenêtre) : il se connecte avec les comptes de démonstration, parcourt
toutes les pages à 360, 768 et 1920 px, mesure le défilement horizontal (test **T10**), contrôle que les graphiques sont dessinés, relève les erreurs JavaScript et prend des captures d'écran.

```powershell
php artisan migrate:fresh --seed          # base de démonstration
php artisan serve --port=8765             # dans un premier terminal
node tests/Navigateur/verif_navigateur.cjs captures    # dans un second terminal
```

Le rapport est écrit dans `captures/rapport.json`, avec les images. Variables facultatives : `SB_BASE` (adresse du serveur), `SB_EDGE` (chemin de `msedge.exe`).

## Structure du projet

```
app/
  Http/Controllers/    AuthController, CategorieController, DashboardController, ProfilController,
                       TransactionController (base) → DepenseController, RevenuController, Admin/UtilisateurController
  Http/Middleware/     EnsureUserIsActive (actif), EnsureUserIsAdmin (admin), EnsureUserIsUtilisateur (utilisateur), SecurityHeaders
  Http/Requests/       validation des formulaires (Form Requests)
  Models/              User, Categorie, Depense, Revenu
  Policies/            CategoriePolicy, DepensePolicy, RevenuPolicy
  Services/            BudgetStats (calculs du tableau de bord, des statistiques et de l'historique)
  Support/             FiltresTransactions (filtres de recherche), helpers.php (format_fcfa, format_date)
database/
  migrations/          users, categories, depenses, revenus
  seeders/             UserSeeder, TransactionSeeder (données de test)
  smartbudget.sql      export complet de la base de démonstration
resources/views/       layouts, auth, dashboard, transactions, categories, profil, admin, errors, components
routes/web.php         toutes les routes
tests/Feature/         584 tests automatisés (tests/Navigateur : vérification navigateur)
```

## Avant une mise en ligne

Ce projet vise une exécution **locale** (soutenance). Pour l'héberger :

1. `APP_ENV=production` et **`APP_DEBUG=false`** dans `.env` (sinon les erreurs affichent des détails techniques).
2. Servir en **HTTPS** et ajouter `SESSION_SECURE_COOKIE=true`.
3. Créer un mot de passe pour MySQL et ne pas utiliser `root`.
4. Supprimer les comptes et les données de démonstration : `php artisan migrate:fresh` (**sans** `--seed`), puis créer un vrai administrateur.
5. `php artisan config:cache` et `php artisan route:cache`.
