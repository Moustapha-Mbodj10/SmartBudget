# Plan d'action — SmartBudget

Sources : `Cahier des charges — SmartBudget.pdf` (racine) et `doc/mémo_Moustapha (2).pdf` (mémoire).
Stack : Laravel 11, PHP 8.2, MySQL (XAMPP), Blade, Bootstrap 5, Chart.js. Devise : FCFA.
Projet Laravel dans `smartbudget/` (à côté de `doc/`).

## Méthode
- Un module à la fois. Ordre dans chaque module : BDD → Backend → Frontend.
- À la fin de chaque phase : récapitulatif + vérification de conformité + **tests de bon fonctionnement**.
- Pas de passage à la phase suivante sans le feu vert de l'utilisateur.

## Décisions validées
1. Suppression d'un utilisateur (admin) : supprimer d'abord dépenses et revenus, puis l'utilisateur, dans une transaction (évite le blocage RESTRICT sur `categorie_id`).
2. Validation du montant : `min:0.01` (strictement positif, cohérent avec DECIMAL).
3. Écarts de forme du cahier des charges : ajouter `index` et `store` à `CategorieController` ; reporter `DashboardController` et `ProfilController` dans le diagramme de classes du mémoire.
4. Structure : `SmartBudget-App/smartbudget/` (code) + `SmartBudget-App/doc/` (documentation).
5. **Laravel 12 au lieu de 11** : Composer bloque toutes les versions 11.x (avis de sécurité). Choix de l'utilisateur : Laravel 12 (PHP 8.2 OK). À corriger dans le mémoire : « Laravel 11 » → « Laravel 12 ».
6. Sessions, cache et file d'attente en `file` / `sync` (pas de tables `sessions`, `cache`, `jobs`) pour respecter les 4 tables du CDC (users, categories, depenses, revenus). Migrations par défaut cache/jobs supprimées.
7. Bootstrap, Bootstrap Icons et Chart.js sont servis en local (`public/vendor`) : l'application fonctionne sans Internet pour la soutenance.
8. MariaDB (XAMPP) se lance avec : `C:\xampp1\mysql\bin\mysqld.exe --defaults-file=C:\xampp1\mysql\bin\my.ini --standalone` (root sans mot de passe).

## Suivi des modules

Légende : [ ] à faire · [~] en cours · [x] terminé et testé

### Module 0 — Fondations (étapes 1 et 4 du CDC) — [x] terminé et testé
- [x] Vérifier l'environnement (PHP 8.2.12, Composer 2.9.5, MariaDB 10.4 via XAMPP `C:\xampp1`)
- [x] BDD : base `smartbudget` (utf8mb4), installation Laravel 12, `.env` MySQL, locale `fr`, fuseau `Africa/Dakar`
- [x] Frontend : layouts `app` / `guest`, composants (`stat-card`, `alert-flash`, `confirm-modal`, `pagination`), helpers `format_fcfa()` / `format_date()`, Bootstrap 5 + Icons + Chart.js en local (`public/vendor`)
- [x] Tests de bon fonctionnement : `tests/Feature/FondationsTest.php` (6 tests, 27 assertions) + vérification HTTP réelle sur MySQL (page 200, assets 200)

### Module 1 — Authentification (EF1, EF2, EF12) — [x] terminé et testé
- [x] BDD : migration `users` (nom, prenom, email unique, password, role, statut), modèle `User`, factory, `UserSeeder` (1 admin + 2 utilisateurs)
- [x] Backend : `AuthController`, `RegisterRequest`, `LoginRequest`, middlewares `guest`, `auth`, `actif`, `admin`, throttle 5/min, messages FR (`lang/fr`)
- [x] Frontend : vues `auth/login`, `auth/register`, pages d'erreur 403 / 404 / 429 en français
- [x] Tests : `tests/Feature/AuthTest.php` (33 tests) dont T1, T2, T3, T8 (partie middleware) + essai de bout en bout sur MySQL avec CSRF réel

**Comptes de démonstration (données de test)** :
| Rôle | E-mail | Mot de passe |
|------|--------|--------------|
| Administrateur | admin@smartbudget.sn | Admin1234 |
| Utilisateur | awa@smartbudget.sn | Password123 |
| Utilisateur | moussa@smartbudget.sn | Password123 |

**Conformité CDC** : §3.1 (règles d'inscription, message générique, compte suspendu refusé, redirection par rôle, session invalidée), §6.1 (table users), §7.4 (middlewares), §7.5 (validation inscription), §9.1 (bcrypt, CSRF, régénération de session, throttle 5/min).
**Reporté à plus tard** : création des catégories par défaut à l'inscription (Module 2, quand la table `categories` existera). Pages provisoires `/dashboard` et `/admin/utilisateurs` (vue `dev/espace`), remplacées aux Modules 6 et 8.

### Module 2 — Catégories (EF6) — [x] terminé et testé
- [x] BDD : migration `categories` (unique `user_id, nom, type`), modèle `Categorie`, factory, catégories par défaut (`Categorie::creerParDefaut`, appelé à l'inscription et par le seeder)
- [x] BDD (avancé depuis les Modules 3 et 4) : migrations + modèles + factories `depenses` et `revenus` (FK `categorie_id` en RESTRICT), nécessaires pour vérifier la règle « suppression refusée si utilisée » (T6)
- [x] Backend : `CategorieController` (index, store, update, destroy), `CategorieRequest`, `CategoriePolicy`, middleware `utilisateur` (403 pour l'administrateur), recherche par nom
- [x] Frontend : `categories/index` (deux listes dépenses / revenus, nombre de transactions, modale d'ajout et de modification, confirmation avant suppression)
- [x] Tests : `tests/Feature/CategorieTest.php` (46 tests) dont T6 + essai de bout en bout sur MySQL (ajout, doublon, modification, T6, RESTRICT MySQL 1451, 403 autre utilisateur, 403 admin)

**Décisions et points d'attention du Module 2**
- Le **type d'une catégorie ne se modifie pas** après création (cela rendrait incohérentes les transactions déjà classées dedans). Le nom et la description restent modifiables.
- Catégorie par défaut « Factures » : remplacée le 16/10/2026 par quatre catégories (voir le journal).
- L'**administrateur n'a pas de catégories** ni accès aux routes de l'espace budget (403), conformément à la confidentialité du CDC §3.7.
- Piège Laravel corrigé : la ressource `categories` doit déclarer le paramètre `categorie` (sinon Laravel le nomme `category` et la liaison de modèle échoue).
- Conséquence pour les Modules 3 et 4 : leurs migrations, modèles et factories existent déjà ; il reste les contrôleurs, Form Requests, policies, vues et tests.

### Module 3 — Dépenses (EF5) — [x] terminé et testé
- [x] BDD : migration `depenses` (DECIMAL(12,2), index `user_id, date`) — déjà faite au Module 2
- [x] Backend : `TransactionController` (logique commune dépenses / revenus) + `DepenseController` (configuration), `TransactionRequest` (type déduit de la route), `DepensePolicy`, pagination 10, tri date décroissante
- [x] Frontend : vues partagées `transactions/index` (recherche + filtres combinables, total, pagination) et `transactions/form` (ajout / modification)
- [x] Tests : `tests/Feature/DepenseTest.php` (58 tests) dont T4, T5, T7, T9 (partie dépenses) + essai de bout en bout sur MySQL (T4, T5, T7, pagination 10 / 6, filtres, suppression, 403 admin)

**Décisions et points d'attention du Module 3**
- **Contrôleur de base partagé** : `RevenuController` (Module 4) ne sera qu'une configuration (`config()`), plus `RevenuPolicy` et ses tests. Les vues `transactions/*` sont partagées au lieu de `depenses/` et `revenus/` séparés (écart de forme avec le CDC §8.1, même résultat à l'écran).
- Paramètre de route `{transaction}` commun aux deux ressources.
- Règles montant : `min:0.01`, `max:9999999999.99` (limite du DECIMAL(12,2)), 2 décimales maximum.
- Filtres de la liste : mot-clé (description), catégorie, intervalle de dates, montant min / max (CDC §3.6, EF11) ; un filtre invalide est ignoré avec un message ; le total affiché suit les filtres.
- **Constat pour le Module 8** : avec CASCADE sur `user_id` et RESTRICT sur `categorie_id`, `DELETE users` échoue sur MySQL (erreur 1451) si l'utilisateur a des transactions. L'administration doit supprimer ses dépenses et revenus d'abord, puis l'utilisateur, dans une transaction (décision n° 1). Documenté par deux tests.

### Correction de configuration des tests (découverte au Module 3)
Dans `phpunit.xml` de Laravel 12, les lignes SQLite sont commentées : **depuis le Module 1, `php artisan test` exécutait `migrate:fresh` sur la base réelle `smartbudget`** et la vidait. C'est corrigé : les tests utilisent maintenant la base MySQL dédiée **`smartbudget_test`** (créée), vérifié par comptage avant / après. Conséquence positive : toute la suite tourne sur MySQL, donc les contraintes (enum, FK RESTRICT, unique) sont testées pour de vrai. Pour recréer la base de test : `CREATE DATABASE smartbudget_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`.

### Module 4 — Revenus (EF4) — [x] terminé et testé
- [x] BDD : migration `revenus` — déjà faite au Module 2
- [x] Backend : `RevenuController` (configuration du contrôleur de base), `RevenuPolicy`, route `revenus` (paramètre `{transaction}`)
- [x] Frontend : vues partagées `transactions/*` ; ajout du genre grammatical dans la configuration (`article`, `ce`, `aucun`) pour des libellés corrects (« Aucun revenu enregistré », « Supprimer ce revenu »)
- [x] Tests : `tests/Feature/RevenuTest.php` (40 tests) + essai de bout en bout sur MySQL (ajout, catégorie de dépense refusée, montant négatif / date future refusés, T7 403, T6 avec un revenu, suppression, 403 admin)

**Points d'attention du Module 4**
- Un revenu n'accepte que les catégories de type « revenu » de l'utilisateur ; les listes de dépenses et de revenus sont étanches entre elles (testé dans les deux sens).
- Suite totale : 185 tests, 542 assertions, tous verts sur la base `smartbudget_test`.

### Module 5 — Profil (EF3) — [x] terminé et testé
- [x] BDD : aucune (table `users` existante)
- [x] Backend : `ProfilController` (edit, update, updatePassword), `ProfilRequest` (e-mail unique sauf soi-même), `PasswordRequest` (ancien mot de passe exigé, nouveau confirmé, min 8), routes `GET/PUT /profil` et `PUT /profil/password`
- [x] Frontend : `profil/edit` à deux onglets (Informations, Mot de passe), onglet actif choisi côté serveur
- [x] Tests : `tests/Feature/ProfilTest.php` (34 tests) + essai de bout en bout sur MySQL (modification, e-mail déjà pris, injection role / statut ignorée, ancien mot de passe faux, changement, reconnexion avec ancien / nouveau mot de passe, accès administrateur)

**Points d'attention du Module 5**
- Accessible à tout compte connecté, **administrateur compris** (CDC §3.2), contrairement aux modules budget (403 pour l'admin).
- Les erreurs des deux formulaires sont séparées (`$errorBag = 'password'`) : une erreur de mot de passe rouvre l'onglet « Mot de passe », une erreur d'informations reste sur « Informations ».
- Seuls nom, prénom et e-mail sont modifiables par ce formulaire ; `role`, `statut`, `id` et un éventuel `password` envoyés dans la requête sont ignorés (testé).
- Ajout hors cahier des charges : limitation à **5 essais par minute** sur `PUT /profil/password`, pour qu'une session ouverte ne serve pas à deviner l'ancien mot de passe.
- Les autres sessions ouvertes du même compte ne sont pas déconnectées après un changement de mot de passe (non demandé par le CDC).
- Suite totale : 219 tests, 653 assertions, tous verts.

### Module 6 — Tableau de bord, statistiques, graphiques (EF8, EF9, EF10) — [x] terminé et testé
- [x] BDD : aucune nouvelle table (requêtes sur `depenses` et `revenus`)
- [x] Backend : `App\Services\BudgetStats` (totaux, nombre de transactions, répartition par catégorie, moyenne mensuelle, série mensuelle, dernières transactions), `DashboardController@index` et `@statistiques`, routes `/dashboard` et `/statistiques`
- [x] Frontend : `dashboard/index` (4 cartes, camembert, barres 6 mois, 5 dernières transactions, boutons d'ajout rapide, message si aucune donnée), `dashboard/statistiques` (sélecteur mois / année, cartes, catégorie la plus coûteuse, graphiques, tableau montant + %), `public/js/graphiques.js` (Chart.js, données JSON du serveur)
- [x] Tests : `DashboardTest` (24) + `StatistiquesTest` (30) ; T4 complet (solde −5 000 FCFA) ; essai de bout en bout sur MySQL avec 6 mois de données ; exécution de `graphiques.js` sous Node sur les vraies données des pages

**Décisions du Module 6 (à valider)**
- Les **4 cartes portent toutes sur le mois en cours** (lecture littérale du CDC §3.6) : « Solde du mois » = revenus du mois − dépenses du mois. Ce n'est pas un solde cumulé depuis le début.
- « Aucune donnée » = aucune transaction du tout (toute période). Dans ce cas les cartes sont à 0 et un message invite à saisir une première transaction.
- **Moyenne des dépenses par mois** = total des dépenses de la période ÷ nombre de mois de la période qui contiennent au moins une dépense (pour un mois choisi, c'est donc le total du mois).
- Barres : un mois choisi → les 6 mois qui se terminent à ce mois ; une année choisie → les 12 mois de l'année. Le tableau de bord affiche les 6 mois jusqu'au mois courant.
- Périodes calculées par intervalles de dates `BETWEEN` (et non `whereMonth`), pour exploiter l'index `(user_id, date)` ; résultat identique.
- Une période invalide est ignorée avec un message et le mois courant s'affiche.
- Les « dernières transactions » couvrent tous les mois (pas seulement le mois en cours).

**Points d'attention**
- Piège corrigé : `Carbon::createFromFormat('Y-m', …)` prend le jour courant ; le 31 du mois, « 2026-02 » devenait mars. Le format `'!Y-m'` règle le problème (testé).
- Les infobulles Chart.js suivent le format du reste de l'application (`119 500,50 FCFA`, `62,8 %`) après correction trouvée par le test Node.
- **Non vérifié visuellement dans un navigateur** : le rendu réel des graphiques (couleurs, taille) reste à contrôler à l'œil ; la logique et les données le sont.
- `DATE_FORMAT` est spécifique à MySQL / MariaDB (cible du CDC).
- Suite totale : 273 tests, 834 assertions, tous verts.

### Module 7 — Historique et recherche (EF7, EF11) — [x] terminé et testé
- [x] BDD : aucune nouvelle table
- [x] Backend : `DashboardController@historique`, `BudgetStats::historique()` (UNION des deux tables, pagination SQL, ordre total date / création / type / id) et `totauxFiltres()`, route `/historique` ; classe partagée `App\Support\FiltresTransactions` (lecture, validation et application des filtres) désormais aussi utilisée par `TransactionController` (dépenses / revenus)
- [x] Frontend : `dashboard/historique` (filtres : mot-clé, type, catégorie groupée dépenses / revenus, dates, montants ; totaux des résultats ; badges ; lien de modification par ligne ; messages vides)
- [x] Tests : `HistoriqueTest` (38) dont T9 complet ; non-régression des 98 tests dépenses / revenus après le remaniement des filtres ; essai de bout en bout sur MySQL avec 1 200 transactions

**Décisions et points d'attention du Module 7**
- Tous les filtres se combinent ; un filtre invalide est ignoré avec un message ; le filtre « type » n'existe que dans l'historique.
- Les **totaux** (revenus, dépenses, solde) portent sur **tous les résultats filtrés**, pas seulement la page affichée (vérifié : correspondent exactement à la base).
- Une dépense et un revenu peuvent avoir le même id (deux tables) : chaque ligne renvoie vers la bonne page de modification (collision forcée dans un test).
- Les filtres de dates comparent directement la colonne DATE (`>=`, `<=`) au lieu de `whereDate`, pour exploiter l'index `(user_id, date)`.
- L'historique propose la **modification** de chaque ligne ; la suppression reste dans les pages Dépenses / Revenus.
- **Performance (CDC §4, < 2 s avec 1 000 transactions)** : mesurée avec 1 200 transactions sur MySQL, serveur de développement `artisan serve` : tableau de bord 0,63 s, statistiques 0,68–0,77 s, historique 0,55–0,63 s (y compris dernière page et recherche), dépenses 0,51 s, revenus 0,70 s. Critère respecté.
- Suite totale : 311 tests, 968 assertions, tous verts.

### Module 8 — Administration — [x] terminé et testé
- [x] BDD : aucune nouvelle table
- [x] Backend : `Admin\UtilisateurController` (index, show, edit, update, suspendre, reactiver, destroy), `UtilisateurRequest`, routes `/admin/utilisateurs…` sous le middleware `admin` ; page provisoire `dev/espace` supprimée
- [x] Frontend : `admin/utilisateurs/index` (recherche, pagination, badges rôle et statut, actions), `show` (fiche + nombre de transactions), `edit`
- [x] Tests : `AdminUtilisateurTest` (44) dont T3 et T8 ; essai de bout en bout sur MySQL (modification, auto-suspension et auto-suppression refusées, suspension puis connexion refusée, réactivation, suppression d'un compte avec transactions)

**Décisions et points d'attention du Module 8**
- **Suppression d'un compte** : dépenses et revenus supprimés d'abord, puis le compte (ses catégories partent par CASCADE), **dans une transaction SQL** (décision n° 1). Vérifié sur MySQL : la suppression SQL directe échoue (erreur 1451), la suppression par l'application réussit, les autres comptes restent intacts. Atomicité testée (échec simulé → tout est annulé).
- **Confidentialité** (CDC §3.7) : l'administrateur ne voit que le **nombre** de transactions ; aucun montant, description ni nom de catégorie n'apparaît dans les pages d'administration (testé), et toutes les routes budget lui répondent 403.
- L'administrateur **ne peut ni se suspendre ni se supprimer** : action refusée avec un message, boutons masqués sur sa ligne. Il peut en revanche suspendre ou supprimer **un autre administrateur** (non précisé par le CDC).
- Seuls nom, prénom et e-mail sont modifiables ; `role`, `statut`, `password` et `id` envoyés dans la requête sont ignorés (testé). Le rôle ne peut donc pas être changé depuis l'application.
- La liste inclut tous les comptes (administrateurs compris) avec un badge de rôle ; recherche sur nom, prénom, e-mail et « prénom nom » (insensible à la casse, `%` et `_` traités comme du texte).
- Un compte suspendu voit sa session coupée à la requête suivante (middleware `actif`) ; il ne peut plus se connecter (T3).

**Deux défauts trouvés et corrigés grâce aux tests du module**
1. **Fuite d'existence des comptes** : la liaison automatique du modèle s'exécutait avant le middleware `admin` ; un utilisateur simple obtenait 403 pour un compte existant et 404 pour un inexistant. Les middlewares `admin` et `utilisateur` sont maintenant prioritaires sur la liaison (`bootstrap/app.php`) : 403 dans les deux cas.
2. **Logo du menu** : il menait toujours à `/dashboard`, donc à une erreur 403 pour l'administrateur. Il mène maintenant à l'espace de chaque rôle.

- Suite totale : 355 tests, 1 172 assertions, tous verts.

### Module 9 — Sécurité, tests, livrables (étapes 13 à 15) — [x] terminé et testé
- [x] Revue de sécurité (policies, CSRF, XSS, sessions, en-têtes, throttle, pages d'erreur, audit des routes) : `SecuriteTest` (21 méthodes, 29 tests), détail dans [SECURITE.md](SECURITE.md)
- [x] Données de test : `TransactionSeeder` (≈ 6 mois, 91 dépenses et 16 revenus, descriptions `[TEST]`, rejouable, jamais de date future) : `DonneesDeTestTest` (13)
- [x] Tests T1 à T10 : matrice complète dans [TESTS.md](TESTS.md) ; T10 vérifié **dans Microsoft Edge** (35 mesures, 0 problème) ; garde-fous `ResponsiveTest` (6)
- [x] Livrables : `smartbudget/README.md` (installation sous XAMPP), `smartbudget/database/smartbudget.sql` (import vérifié : sommes de contrôle identiques), documentation [README](README.md) · [ARCHITECTURE](ARCHITECTURE.md) · [TESTS](TESTS.md) · [SECURITE](SECURITE.md)

**Failles et défauts trouvés et corrigés au Module 9**
1. **XSS dans les balises `<script>`** (`@json` sans `JSON_HEX_*`) : 3 charges sur 5 injectaient une vraie balise dans `/categories`. Corrigé sur les 8 usages.
2. **Pages 404 sans en-têtes de sécurité** : middleware `SecurityHeaders` rendu global.
3. **Menu latéral illisible sur ordinateur** (Bootstrap `.offcanvas-lg` impose un fond transparent `!important` dès 992 px).
4. **Débordement horizontal à 360 px** (barre du haut sur les titres longs), colonne « Montant » coupée sur mobile, tableau de répartition à largeur minimale fixe.
5. Ajouts : pas de mise en cache des pages connectées, pages 419 et 500 en français, limitation de l'inscription (10/min).

**Décisions du Module 9**
- Le script de vérification navigateur est conservé dans le projet (`smartbudget/tests/Navigateur/verif_navigateur.cjs`) pour être rejoué.
- L'export SQL ne contient **ni `CREATE DATABASE` ni `DROP DATABASE`** : il s'importe dans la base de son choix sans risquer d'en effacer une autre.
- Pas de Content-Security-Policy (scripts en ligne) ; pas de HTTPS en local : voir les limites dans [SECURITE.md](SECURITE.md).

**Suite finale : 464 tests, 2 030 assertions, tous verts.**

## Décisions finales sur les points laissés ouverts (après le Module 9)

Quatre points n'étaient tranchés ni par le cahier des charges ni par le mémoire. Décidés et implémentés, avec tests (suite finale : **464 tests, 2 030 assertions, tous verts** ; vérification Edge : 35 mesures, 0 problème).

1. **Vues `depenses/` et `revenus/`** : créées (index, create, edit), comme au §8.1 du cahier des charges. Ce sont des vues de deux lignes qui réutilisent `transactions/index` et `transactions/form` ; les contrôleurs rendent désormais `depenses.*` et `revenus.*`. Plus aucun écart d'organisation avec le cahier des charges.
2. **Administrateurs** : un compte administrateur ne peut être **ni suspendu ni supprimé** (le sien comme celui d'un autre) ; il reste modifiable et un compte administrateur suspendu peut être réactivé. Boutons masqués et message explicite. Raison : le mémoire §2.2.2 limite l'administration aux comptes utilisateurs ; évite qu'un compte compromis détruise les autres.
3. **Solde** : « Solde du mois » conservé (lecture littérale du CDC) + « Solde cumulé » (jusqu'à aujourd'hui, transactions futures ignorées) affiché sous la carte. Composant `stat-card` : nouvelle propriété facultative `detail`.
4. **Suppression depuis l'historique** : bouton par ligne + confirmation ; retour sur l'historique avec les mêmes filtres et la même page (liste blanche, pas de redirection ouverte) ; une page devenue vide redirige vers la dernière page existante en conservant le message.

**Défauts trouvés et corrigés pendant ce travail**
- Le script de vérification navigateur copié dans le projet **ne s'exécutait pas** : le `package.json` de Laravel déclare `"type": "module"`, qui interdit `require` dans un fichier `.js`. Renommé en `verif_navigateur.cjs` (documentation mise à jour).
- `DashboardController::historique()` déclarait retourner une vue alors qu'il peut rediriger (erreur 500 sur une page hors limites) : type de retour corrigé.
- Après une suppression qui vidait la dernière page, le message de succès était perdu à la seconde redirection : conservé (`reflash`).
- À 360 px, deux boutons d'action côte à côte faisaient défiler le tableau de l'historique : empilés sur téléphone.

## Refonte du design (après le Module 9)

Refonte graphique de **toutes** les interfaces, inspirée de la maquette fournie par l'utilisateur. Détail complet dans [DESIGN.md](DESIGN.md).

- **Skill de design installée depuis GitHub** : `frontend-design` (dépôt officiel `anthropics/skills`), au niveau du projet (`.claude/skills/frontend-design/`), après lecture complète de son contenu. `theme-factory` examinée, non installée (conçue pour des présentations).
- **Réalisé** : feuille de style complète (jetons, police Urbanist locale), nouvelle coque (barre du haut en pilule, rail d'icônes, panneau burger), tableau de bord avec carte verte du solde, graphiques à barres arrondies et anneau, pages de connexion et d'inscription en deux colonnes, listes, formulaires, catégories, profil, administration et pages d'erreur.
- **Aucune règle de gestion modifiée** : les 464 tests de comportement existants passent tels quels ; seuls 4 tests qui figeaient l'ancienne mise en page (position d'une carte, mot « Bonjour », structure de l'ancienne barre latérale) ont été mis à jour.
- **Vérification dans Edge** : 35 mesures de pages (360, 768 et 1920 px), 0 problème, graphiques dessinés, menu burger et fenêtres modales fonctionnels ; vérification faite sur une **base jetable** (`smartbudget_visuel`, supprimée ensuite) pour ne pas toucher aux données de l'utilisateur.
- **Défauts trouvés sur les captures et corrigés** : pagination en anglais (« Showing 1 to 10 of 69 results »), champ de date trop étroit, liens restés bleus, carte du solde déséquilibrée, « Nouveau dépense » et « de octobre ».
- **Courbe d'évolution du solde** ajoutée au tableau de bord (`BudgetStats::evolutionSolde()`, 11 tests) : solde cumulé à la fin de chacun des 6 derniers mois, transactions futures ignorées ; son dernier point égale le « Solde cumulé » de la carte verte.
- **Barre latérale fixe de bout en bout** : `position: fixed` sur toute la hauteur de l'écran, repliable (icônes) ou dépliable (libellés), choix mémorisé ; vérifiée dans Edge (ne bouge pas au défilement, se déplie à 252 px, état conservé après rechargement, aucun débordement de 1024 à 1920 px, repliée comme dépliée). Défaut trouvé et corrigé en chemin : barre dépliée sur écran moyen, des tableaux défilaient en interne (colonnes secondaires masquées).
- **Animations de la page de connexion et de la barre de recherche**, inspirées de composants de 21st.dev et réécrites en CSS / JavaScript natifs (voir DESIGN.md) : tirelire animée (yeux qui suivent le curseur, yeux fermés sur le mot de passe, œil entrouvert quand on l'affiche, secousse en cas d'erreur, pièce qui tombe à l'envoi), barre de recherche extensible (suggestions qui défilent, raccourci « / », Échap), bouton afficher / masquer le mot de passe. 19 tests PHP + **38 vérifications dans Edge** (`tests/Navigateur/verif_animations.cjs`), toutes réussies.
- **Nouveau logo** fourni par l'utilisateur (portefeuille, pièce, barres, flèche ; bleu roi et vert menthe) : redessiné en SVG (`<x-logo>`, `favicon.svg`, original conservé dans `public/img/`), **interface recolorée** (bleu `#054086` = marque ; vert = revenus, rouge = dépenses inchangés) et animation de la page de connexion remplacée. 4 tests PHP ajoutés.
- **Aperçu animé de la page de connexion** inspiré du shot Dribbble « Promo Landing Page for a Personal Finance Platform » (Outcrowd), à la demande de l'utilisateur : cartes d'interface en FCFA qui entrent en cascade puis flottent, montants floutés pendant la saisie du mot de passe, cartes qui tremblent en cas d'erreur et s'envolent à l'envoi (`<x-apercu-anime>`). 39 vérifications Edge réussies.
- **Tableau de bord de l'administrateur** (`/admin`) : comptes utilisateurs, actifs / suspendus, **en ligne / déconnecté** et **durée de déconnexion**, aussi dans la liste et la fiche des utilisateurs (voir DESIGN.md). Suivi par deux colonnes (`derniere_activite`, `deconnecte_le`) et le middleware `activite`. 20 tests ajoutés.
- **Suite finale : 533 tests, 2 298 assertions, tous verts.**

## Tests de référence (CDC §9.2)
| N° | Scénario | Résultat attendu |
|----|----------|------------------|
| T1 | Inscription avec email déjà utilisé | Refus, message « email déjà utilisé » |
| T2 | Connexion avec mauvais mot de passe | Message d'erreur, pas d'accès |
| T3 | Connexion d'un compte suspendu | Accès refusé avec message |
| T4 | Ajout d'une dépense de 5 000 FCFA | Dépense listée, solde diminué de 5 000 |
| T5 | Dépense avec montant négatif ou vide | Refus, erreur sous le champ |
| T6 | Suppression d'une catégorie utilisée | Refus, message explicatif |
| T7 | Accès à `/depenses/{id}` d'un autre utilisateur | Erreur 403 |
| T8 | Accès à `/admin` par un utilisateur simple | Erreur 403 |
| T9 | Recherche sans résultat | Message « Aucune transaction trouvée » |
| T10 | Affichage sur téléphone (360 px) | Menu replié, pas de défilement horizontal |

## Journal d'avancement
- 2026-10-12 : plan validé, fichier créé.
- 2026-10-12 : Module 0 terminé et testé (6 tests verts).
- 2026-10-12 : Module 1 terminé et testé (41 tests, 143 assertions, tous verts, + essai réel sur MySQL). Base remise à l'état propre (`migrate:fresh --seed`). 
- 2026-10-12 : Module 2 terminé et testé (87 tests, 250 assertions, tous verts, + essai réel sur MySQL). Base remise à l'état propre. 
- 2026-10-12 : Module 3 terminé et testé (145 tests, 410 assertions, tous verts sur MySQL `smartbudget_test`, + essai réel). Correction de `phpunit.xml` (les tests vidaient la base réelle). Base `smartbudget` remise à l'état propre. 
- 2026-10-12 : Module 4 terminé et testé (185 tests, 542 assertions, tous verts, + essai réel sur MySQL). Base `smartbudget` remise à l'état propre. 
- 2026-10-12 : Module 5 terminé et testé (219 tests, 653 assertions, tous verts, + essai réel sur MySQL). Base `smartbudget` remise à l'état propre. 
- 2026-10-12 : Module 6 terminé et testé (273 tests, 834 assertions, tous verts, + essai réel MySQL + exécution Node des graphiques). Base `smartbudget` remise à l'état propre. 
- 2026-10-12 : Module 7 terminé et testé (311 tests, 968 assertions, tous verts, + essai réel MySQL avec 1 200 transactions et mesure de performance). Base `smartbudget` remise à l'état propre. 
- 2026-10-12 : Module 8 terminé et testé (355 tests, 1 172 assertions, tous verts, + essai réel MySQL). Base `smartbudget` remise à l'état propre. En attente du feu vert pour le Module 9 (Sécurité, tests, livrables).
- 2026-10-12 : **Module 9 terminé et testé** (464 tests, 2 030 assertions, tous verts ; vérification dans Edge : 35 mesures, 0 problème ; export SQL vérifié ; documentation rédigée). **Tous les modules du plan sont terminés.** Reste à la charge de l'utilisateur : corrections du mémoire, captures d'écran, contrôle sur un vrai téléphone, mise sur GitHub.
- 2026-10-12 : **Quatre décisions finales** tranchées et implémentées (vues `depenses/` et `revenus/`, comptes administrateurs protégés, solde cumulé, suppression depuis l'historique). 464 tests, 2 030 assertions, tous verts ; vérification Edge : 35 mesures, 0 problème.
- 2026-10-13 : **Refonte du design de toutes les interfaces** (skill `frontend-design` installée depuis GitHub). 468 tests, 2 054 assertions, tous verts ; vérification Edge : 35 mesures, 0 problème.
- 2026-10-13 : courbe d'évolution du solde et barre latérale fixe et repliable. 483 tests, 2 099 assertions, tous verts ; vérification Edge : 35 mesures de pages + largeurs intermédiaires, 0 problème.
- 2026-10-13 : animations de la page de connexion (tirelire) et de la barre de recherche (extensible). 502 tests, 2 166 assertions, tous verts ; 38 vérifications d'animations dans Edge, toutes réussies.
- 2026-10-15 : nouveau logo (SVG), couleurs de l'interface alignées sur le logo, nouvelle animation de connexion. 506 tests, 2 187 assertions, tous verts ; 39 vérifications d'animations dans Edge, toutes réussies.
- 2026-10-16 : nouvelle animation de connexion (aperçu d'application inspiré d'un shot Dribbble). 506 tests, 2 194 assertions, tous verts ; 39 vérifications d'animations dans Edge, toutes réussies.
- 2026-10-16 : sur demande de l'utilisateur, la catégorie par défaut « Factures » est remplacée par quatre catégories de dépenses : **Facture d'électricité, Facture d'eau, Facture d'internet**, puis **Facture globale** (eau, électricité et internet réunis). Le compte propose donc **13 catégories** à l'inscription (10 de dépenses, 3 de revenus) au lieu de 10 (écart assumé avec le cahier des charges §3.3, qui en prévoyait 7 de dépenses). Les comptes déjà créés gardent leur catégorie « Factures » : aucune donnée existante n'a été modifiée. Les montants des cartes de statistiques s'adaptent désormais à la largeur de leur carte (« FCFA » jamais coupé, vérifié dans Edge de 360 à 1920 px avec des montants jusqu'à 750 millions).
- 2026-10-16 : à la demande de l'utilisateur, les quatre catégories de factures ont été **ajoutées aux 4 comptes utilisateurs existants** par une migration de données (`2026_10_16_090000_...`), après sauvegarde de la base dans `sauvegardes/smartbudget_avant_categories_factures_2026-10-16.sql`. « Factures » et ses 16 dépenses sont conservées. 507 tests, 2 198 assertions, tous verts.
- 2026-10-16 : à la demande de l'utilisateur, la catégorie « Factures » a été **supprimée pour tous les comptes** ; « Facture globale » prend sa place, notamment dans « Dépenses par catégorie » des statistiques. Une migration de données (`2026_10_16_100000_...`) déplace d'abord les dépenses de « Factures » vers « Facture globale » (16 dépenses : 11 chez Awa, 5 chez Moussa ; aucune perdue, 91 dépenses et 16 revenus avant comme après), puis supprime la catégorie. Sauvegarde préalable : `sauvegardes/smartbudget_avant_suppression_factures_2026-10-16.sql`. 508 tests, 2 205 assertions, tous verts.
- 2026-10-16 : à la demande de l'utilisateur, **« Facture globale » est toujours l'addition de toutes les factures** (électricité, eau, internet, et ce qui est saisi directement sur elle) dans « Dépenses par catégorie » (tableau de bord et statistiques, anneau, tableau et « catégorie la plus coûteuse ») ; parmi les factures, seule « Facture globale » y figure. Règle appliquée dans `BudgetStats::repartitionDepenses()` (constantes `Categorie::FACTURE_GLOBALE` et `SOUS_FACTURES`), par utilisateur. Les transactions gardent leur vraie catégorie dans l'historique, les listes et les formulaires ; les totaux (revenus, dépenses, solde) ne changent pas. 510 tests, 2 214 assertions, tous verts.
- 2026-10-17 : à la demande de l'utilisateur, **l'adresse e-mail n'est plus modifiable par l'utilisateur dès l'inscription** (page Profil : champ en lecture seule, sans attribut `name` ; `ProfilRequest` ne valide ni n'enregistre plus l'e-mail, même si une requête forgée l'envoie). Écart assumé avec le cahier des charges EF3, qui prévoyait la modification de l'e-mail. Le droit de l'administrateur de modifier un compte (module Administration) n'a pas été touché. 511 tests, 2 229 assertions, tous verts.
- 2026-10-17 : à la demande de l'utilisateur, la connexion affiche des **messages précis** : « Mot de passe incorrect. » (sous le champ mot de passe, l'e-mail saisi est conservé) quand l'adresse a un compte, « Aucun compte n'est associé à cette adresse e-mail. » sinon. Remplace le message générique « Identifiants incorrects. » choisi au Module 2 pour ne pas révéler l'existence d'un compte ; risque accepté et noté dans SECURITE.md, atténué par la limite de 5 essais par minute. Un compte suspendu avec un mauvais mot de passe reçoit « Mot de passe incorrect. » (le statut suspendu n'est révélé qu'avec le bon mot de passe). 513 tests, 2 235 assertions, tous verts.
- 2026-10-21 : à la demande de l'utilisateur, **le menu (rail latéral et panneau du téléphone) passe en bleu** : dégradé du bleu du logo, liens et icônes blancs, lien actif en pilule blanche à texte bleu, boutons d'ajout de dépense (rouge clair) et de revenu (vert clair) conservés. 513 tests, 2 237 assertions, tous verts ; 35 pages mesurées dans Edge sans problème.
- 2026-10-21 : à la demande de l'utilisateur, **tableau de bord de l'administrateur** : il voit si chaque utilisateur est actif ou suspendu, **en ligne ou déconnecté, et depuis combien de temps** il est déconnecté. Définition : « en ligne » = activité depuis moins de 5 minutes et aucune déconnexion depuis ; une session qui expire sans déconnexion compte comme déconnectée depuis la dernière activité. L'accueil de l'administrateur après connexion devient ce tableau de bord (au lieu de la liste des utilisateurs). Migration `2026_10_21_090000_...` appliquée à la base réelle après sauvegarde (`sauvegardes/smartbudget_avant_suivi_connexion_2026-10-21.sql`) ; les comptes existants affichent « aucune connexion enregistrée » jusqu'à leur prochaine connexion. 533 tests, 2 298 assertions, tous verts ; 38 pages mesurées dans Edge sans problème.
- 2026-10-21 : la séparation des sessions administrateur / utilisateurs (page `/admin/login`, cookie distinct) a été essayée puis **annulée à la demande de l'utilisateur** : retour à une seule page de connexion `/login` et à une session commune. Conséquence connue : dans un même navigateur, se connecter comme administrateur remplace la session de l'utilisateur (et inversement) ; pour avoir les deux en même temps, utiliser deux navigateurs ou une fenêtre de navigation privée. 533 tests, 2 298 assertions, tous verts.
- 2026-10-21 : à la demande de l'utilisateur, **22 comptes utilisateurs supplémentaires** avec revenus et dépenses (`UtilisateursSupplementairesSeeder`) ajoutés à la base réelle après sauvegarde (`sauvegardes/smartbudget_avant_22_comptes_2026-10-21.sql`) : 22 comptes actifs, mot de passe `Password123`, 13 catégories chacun, environ 6 mois de transactions marquées `[TEST]` (1 594 dépenses et 175 revenus au total dans la base). La base compte maintenant 27 comptes utilisateurs et 1 administrateur. 544 tests, 5 788 assertions, tous verts.
- 2026-10-21 : à la demande de l'utilisateur, **présence de démonstration** pour l'espace administrateur : commande `php artisan demo:presence` (option `--maintenir`). Appliquée à la base réelle : 12 des 22 comptes supplémentaires « en ligne », les 10 autres « déconnectés » depuis 45 minutes, 3 heures, 1 jour, 3 jours, 1 semaine, 2 semaines, 1 mois, 2 mois, 3 mois et 6 mois (mélange de déconnexions volontaires et de sessions expirées). Seules les colonnes `derniere_activite` et `deconnecte_le` de ces 22 comptes sont écrites ; les comptes réels et l'administrateur ne sont pas touchés. Ce sont des **données de démonstration**, pas de vraies connexions. 552 tests, 5 831 assertions, tous verts.
- 2026-10-22 : à la demande de l'utilisateur, **suspendre un utilisateur en ligne le déconnecte immédiatement** : il passe « Déconnecté depuis moins d'une minute » sur le tableau de bord de l'administrateur dès la suspension (une personne déjà déconnectée garde sa durée, un compte jamais connecté n'est pas modifié), et il est renvoyé vers la connexion en 15 secondes au plus, même sans cliquer, grâce à un battement de session (`GET /session/etat` appelé par `smartbudget.js`). Le tableau de bord de l'administrateur se met à jour toutes les 30 secondes (au lieu de 60). Effet de bord voulu : une page ouverte compte comme « en ligne ». Vérifié dans deux Edge distincts (7/7). 562 tests, 5 866 assertions, tous verts.
- 2026-10-26 : à la demande de l'utilisateur, **nouvelle interface et nouveau menu « Rechercher »** pour l'administrateur, uniquement dédiés à la recherche d'un utilisateur (`/admin/recherche`, `Admin\RechercheController`, helper `surligner()`) : nom, prénom, nom complet ou e-mail, filtres statut du compte et en ligne / déconnecté, résultats surlignés et paginés par 10, rien d'affiché tant qu'aucune recherche n'est lancée, aucune donnée de budget. Les durées de déconnexion n'affichent plus de secondes au-delà d'une minute (« 6 mois » et non « 6 mois et 43 secondes »). 584 tests, 6 014 assertions, tous verts ; 41 mesures Edge sans problème (360, 768, 1920 px et barre dépliée de 1024 à 1366 px).
