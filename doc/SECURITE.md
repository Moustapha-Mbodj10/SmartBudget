# Revue de sécurité de SmartBudget

Revue transverse réalisée au Module 9 : chaque mesure du cahier des charges (§9.1) est située dans le code et **prouvée par un test**.
Les points non couverts sont listés en fin de document.

## 1. Mesures du cahier des charges (§9.1)

| Risque | Protection | Où | Preuve |
|---|---|---|---|
| Vol de mots de passe | Hachage **bcrypt** (`$2y$`), jamais stocké ni affiché en clair, jamais pré-rempli après une erreur | cast `hashed` du modèle `User` | `SecuriteTest::test_le_mot_de_passe_n_est_jamais_expose`, `…champs_mot_de_passe_ne_sont_jamais_pre_remplis` ; `AuthTest` ; la base contient `$2y$` |
| Accès aux données d'un autre utilisateur | Requêtes filtrées par `user_id` **et** policies : **403** | `CategoriePolicy`, `DepensePolicy`, `RevenuPolicy`, Form Requests | T7 ; `DepenseTest`, `RevenuTest`, `CategorieTest` (consultation, modification, suppression) ; essais réels |
| Falsification de formulaire (CSRF) | Jeton `@csrf` sur chaque formulaire ; requête sans jeton refusée (**419**) | middleware `VerifyCsrfToken` de Laravel | `SecuriteTest::test_chaque_formulaire_non_get_contient_un_jeton_csrf` (analyse de toutes les vues) ; essai réel sur le serveur : les **16 routes** qui modifient des données répondent **419** sans jeton, et aucune donnée n'est modifiée |
| Injection SQL | Eloquent et requêtes préparées uniquement ; `%` et `_` traités comme du texte dans les recherches | tout le code | Charges `'; DROP TABLE …` dans la recherche, la période et les filtres : sans effet (`HistoriqueTest`, `StatistiquesTest`, `AdminUtilisateurTest`) |
| Injection de script (XSS) | Affichage échappé `{{ }}` partout, **aucun** `{!! !!}` ; données placées dans un `<script>` encodées avec `JSON_HEX_*` | toutes les vues | `SecuriteTest::test_aucune_sortie_html_non_echappee…` ; `…donnees_saisies_sont_echappees_sur_toutes_les_pages` (5 charges × 15 pages) ; `…donnees_injectees_dans_les_balises_script…` |
| Accès administrateur non autorisé | Middleware `admin` sur toutes les routes `/admin` ; **403** | `EnsureUserIsAdmin`, `bootstrap/app.php` | T8 ; `SecuriteTest::test_toutes_les_routes_d_administration_exigent_le_role_admin` |
| Vol de session | Session **régénérée** à la connexion, **détruite** à la déconnexion ; cookie `HttpOnly` et `SameSite=Lax` ; expiration 120 min | `AuthController`, `config/session.php` | `SecuriteTest::test_la_session_est_regeneree…`, `…cookie_de_session_est_httponly…` |
| Force brute | **5 tentatives de connexion par minute** (429) | `throttle:5,1` sur `POST /login` | `AuthTest::test_limitation_a_5_tentatives…` ; essai réel : la 6e est bloquée |

## 2. Mesures ajoutées pendant la revue

| Mesure | Pourquoi | Preuve |
|---|---|---|
| **En-têtes HTTP** : `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy` sur **toutes** les réponses (redirections, erreurs et 404 compris) | Anti-clickjacking, pas d'exécution de fichiers mal typés | `SecuriteTest::test_les_en_tetes_de_securite…` |
| **Pas de mise en cache des pages connectées** (`Cache-Control: no-store`) | Poste partagé (cybercafé) : après déconnexion, le bouton « retour » ne doit pas réafficher des données financières | `SecuriteTest::test_les_pages_connectees_ne_sont_pas_mises_en_cache` |
| **Limitation de débit** sur l'inscription (10/min) et le changement de mot de passe (5/min) | Création de comptes en masse ; deviner l'ancien mot de passe depuis une session ouverte | `SecuriteTest::test_l_inscription_est_limitee…`, `ProfilTest` |
| **Contrôles de rôle avant la liaison des modèles** | Un utilisateur simple recevait 403 pour un compte existant et 404 pour un inexistant, et pouvait donc deviner quels comptes existent | `AdminUtilisateurTest::test_un_utilisateur_simple_ne_peut_pas_deviner…` |
| **Pages d'erreur sans détail technique** (403, 404, 419, 429, 500 en français) ; la 500 est autonome (aucune session ni base) | Ne rien révéler (trace, chemins, requêtes SQL) | `SecuriteTest::test_une_erreur_interne_n_expose_ni_trace_ni_chemins_ni_requetes` |
| **Affectation en masse verrouillée** : `role`, `statut`, `user_id` ne sont jamais acceptés depuis un formulaire | Un utilisateur ne peut pas se donner le rôle administrateur ni écrire dans le compte d'un autre | `AuthTest`, `ProfilTest`, `AdminUtilisateurTest`, `DepenseTest` (valeurs injectées ignorées) |
| **Confidentialité de l'administrateur** : il ne voit que le *nombre* de transactions ; 403 sur toute route budget | CDC §3.7 | `AdminUtilisateurTest::test_l_administrateur_ne_voit_jamais_le_detail_des_transactions` |
| **Comptes administrateurs protégés** : ni suspension ni suppression, y compris par un autre administrateur | Un compte administrateur compromis ne peut pas supprimer ou verrouiller les autres administrateurs | `AdminUtilisateurTest::test_un_compte_administrateur_ne_peut_etre_ni_suspendu_ni_supprime_par_un_autre` |
| **Retour après suppression limité à l'historique** (liste blanche, paramètres reconnus seulement) | Pas de redirection ouverte vers un site extérieur, pas d'injection d'en-tête | `HistoriqueTest::test_une_adresse_de_retour_hostile_est_ignoree` |
| **Audit automatique des routes** | Qu'aucune route ne soit oubliée lors d'une évolution | `SecuriteTest` : toute route est protégée par `auth` (sauf `/`, `/login`, `/register`), `actif`, et `admin` ou `utilisateur` selon son préfixe |

## 3. Failles trouvées et corrigées pendant le projet

| Trouvée au | Faille | Correctif |
|---|---|---|
| Module 8 | **Fuite d'existence des comptes** (403 / 404 différents) | Priorité des middlewares de rôle sur la liaison des modèles |
| Module 9 | **XSS dans les balises `<script>`** : `@json` laissait passer `</script>` et `<!--` dans les noms de catégories et les valeurs ré-affichées dans la fenêtre modale (preuve : 3 charges sur 5 injectaient une vraie balise) | Drapeaux `JSON_HEX_TAG \| JSON_HEX_AMP \| JSON_HEX_APOS \| JSON_HEX_QUOT` sur tous les `@json` |
| Module 9 | **Pages 404 sans en-têtes de sécurité** (le groupe de middlewares `web` ne s'applique pas à une URL inconnue) | Middleware `SecurityHeaders` global |
| Module 3 | **Base de développement vidée par les tests** (configuration PHPUnit) | Base dédiée `smartbudget_test` |

## 4. Dépendances

`composer audit` : **aucune faille connue**. Laravel 11 a été écarté parce que Composer en bloquait toutes les versions pour avis de sécurité ; le projet utilise Laravel 12.

## 5. Limites et points non couverts

| Sujet | État | Commentaire |
|---|---|---|
| **HTTPS** | Non applicable en local | Pour une mise en ligne : HTTPS, `SESSION_SECURE_COOKIE=true`, HSTS |
| **`APP_DEBUG`** | `true` dans `.env.example` (développement) | **Doit être `false` en production**, sinon les erreurs détaillent le code ; le README le rappelle |
| **Content-Security-Policy** | Non mise en place | Les pages utilisent de petits scripts en ligne (graphiques, fenêtres modales) ; une CSP stricte demanderait de les externaliser |
| **Mots de passe de démonstration** | Publics (README, export SQL) | À supprimer avant toute mise en ligne (`migrate:fresh` sans `--seed`) |
| **Compte MySQL** | `root` sans mot de passe (XAMPP par défaut) | Acceptable en local seulement |
| **Force du mot de passe** | 8 caractères minimum, comme le cahier des charges | Pas d'exigence de complexité ni de liste de mots de passe courants |
| **Limitation de connexion par adresse IP** | Oui ; pas de verrouillage par compte | Un attaquant qui change d'adresse IP contourne la limite |
| **Autres sessions après changement de mot de passe** | Restent ouvertes | Non demandé par le cahier des charges |
| **Réinitialisation de mot de passe oubliée, vérification d'e-mail, double authentification** | Hors périmètre | Non prévus par le cahier des charges |
| **Journal d'audit** | Absent | Les suspensions et suppressions de comptes ne sont pas tracées |
| **Inscription** | Le message « e-mail déjà utilisé » révèle qu'un compte existe | Exigé par le scénario T1 du cahier des charges |
| **Suspension immédiate** | Un compte suspendu continuait d'afficher sa page jusqu'à son prochain clic | Le compte passe « déconnecté » dès la suspension chez l'administrateur ; sa session est coupée à sa prochaine requête (`actif`) et les pages vérifient la session toutes les 15 secondes (`/session/etat`, `smartbudget.js`) | `AdminTableauDeBordTest`, `verif_suspension.cjs` |
| **Suivi de connexion** | L'administrateur voit qui est en ligne et depuis combien de temps chaque personne est déconnectée | Fonction demandée ; seules deux dates sont conservées (`derniere_activite`, `deconnecte_le`) : ni adresse IP, ni pages visitées, ni contenu du budget |
| **Connexion** | Les messages « Mot de passe incorrect. » et « Aucun compte n'est associé à cette adresse e-mail. » révèlent si une adresse a un compte | Choix de l'utilisateur (messages précis plutôt que générique) ; atténué par la limite de 5 tentatives par minute |
