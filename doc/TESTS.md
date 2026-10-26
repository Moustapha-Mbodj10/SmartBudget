# Tests 59e SmartBu49get

Ce 31ocument montre **comment chaque scénario 41e test 7u cahier 39es charges (§9.2, T1 à T10) a été vérifié**, et comment
rejouer les vérifications.

## Résumé

| Niveau | Outil | Résultat |
|---|---|---|
| Tests automatisés | `php artisan test` (PHPUnit, MySQL `smartbudget_test`) | **584 tests, 6 014 assertions, tous verts** |
| Essais 11e bout en bout | vrai serveur + MySQL, vrais cookies et vrais jetons CSRF (`curl`), à chaque module | tous conformes |
| Vérification dans un navigateur | Microsoft Edge piloté par `tests/Navigateur/verif_navigateur.cjs` | **35 mesures de pages, 0 problème** |
| Performance | 1 200 transactions sur MySQL | 0,5 à 0,8 s par page (limite : 2 s) |
| Dépendances | `composer audit` | aucune faille connue |

## Matrice des scénarios T1 à T10

| N° | Scénario (CDC §9.2) | Résultat attendu | Vérifié par | Statut |
|---|---|---|---|---|
| **T1** | Inscription avec un e-mail déjà utilisé | Refus, message « e-mail déjà utilisé » | `AuthTest::test_t1_inscription_avec_un_email_deja_utilise_est_refusee` ; essai réel (302 vers `/register`) | ✅ |
| **T2** | Connexion avec un mauvais mot de passe | Message d'erreur, pas d'accès | `AuthTest::test_t2_connexion_avec_mauvais_mot_de_passe` (+ même message pour un e-mail inconnu) ; essai réel | ✅ |
| **T3** | Connexion d'un compte suspendu | Accès refusé avec message | `AuthTest::test_t3_connexion_d_un_compte_suspendu_est_refusee` ; `AdminUtilisateurTest::test_t3_un_compte_suspendu_par_l_admin_ne_peut_plus_se_connecter` ; essai réel : suspension par l'administrateur puis connexion refusée, puis réactivation | ✅ |
| **T4** | Ajout d'une dépense de 5 000 FCFA | Dépense listée, solde du tableau de bord diminué de 5 000 | `DepenseTest::test_t4_ajout_d_une_depense_de_5000_fcfa` ; `DashboardTest::test_t4_une_depense_de_5000_fcfa_diminue_le_solde_de_5000` ; essai réel : solde de 130 499,50 → 125 499,50 FCFA | ✅ |
| **T5** | Dépense avec un montant négatif ou vide | Refus, erreur sous le champ | `DepenseTest::test_t5_ajout_avec_montant_negatif_est_refuse`, `…_vide_est_refuse` ; `RevenuTest::test_ajout_montant_negatif_vide_ou_nul_refuse` ; essai réel (montant négatif, vide, date future) | ✅ |
| **T6** | Suppression d'une catégorie utilisée | Refus, message explicatif | `CategorieTest::test_t6_suppression_d_une_categorie_utilisee_par_une_depense_est_refusee` (+ cas d'un revenu) ; essai réel ; la base MySQL refuse aussi (erreur 1451, RESTRICT) | ✅ |
| **T7** | Accès à `/depenses/{id}` d'un autre utilisateur | Erreur 403 | `DepenseTest::test_t7_acceder_a_la_depense_d_un_autre_utilisateur_donne_403`, `RevenuTest::test_t7_acceder_au_revenu_d_un_autre_utilisateur_donne_403`, et les variantes « données invalides » ; essai réel (consultation, modification et suppression : 403, données intactes) | ✅ |
| **T8** | Accès à `/admin` par un utilisateur simple | Erreur 403 | `AdminUtilisateurTest::test_t8_un_utilisateur_simple_recoit_403_sur_toutes_les_routes_admin` ; `AuthTest::test_middleware_admin_refuse_un_utilisateur_simple_avec_403` ; `SecuriteTest` (toutes les routes `admin/*` exigent le rôle) ; essai réel | ✅ |
| **T9** | Recherche sans résultat | Message « Aucune transaction trouvée » | `DepenseTest::test_t9_…`, `HistoriqueTest::test_t9_recherche_sans_resultat_affiche_aucune_transaction_trouvee`, `…_une_combinaison_sans_resultat…` ; essai réel | ✅ |
| **T10** | Affichage sur téléphone (360 px) | Menu replié, pas de défilement horizontal | Vérification **dans Edge** : sur les **12 pages** mesurées à 360 px, aucun défilement horizontal, menu replié au départ puis ouvert par le bouton burger ; garde-fous dans `ResponsiveTest` | ✅ (voir la réserve ci-dessous) |

**Réserve sur T10** : la mesure a été faite dans Microsoft Edge en mode émulation d'un écran de 360 px, pas sur un téléphone physique.
Elle prouve l'absence de débordement et le fonctionnement du menu, mais un dernier coup d'œil sur un vrai téléphone reste recommandé.

### Défauts révélés par la vérification dans le navigateur (corrigés)

Ces défauts n'étaient **pas visibles** dans les tests automatisés, qui lisent le HTML mais ne dessinent rien :

1. **Menu latéral illisible sur ordinateur** : Bootstrap impose un fond transparent à ce type de menu au-delà de 992 px, ce qui écrasait le fond sombre ; le texte clair se perdait sur fond pâle.
2. **Barre du haut qui débordait à 360 px** sur les pages à titre long (« Ajouter une dépense », « Modifier un utilisateur »).
3. **Colonne « Montant » coupée** sur mobile dans les listes de transactions (historique, tableau de bord, dépenses, revenus) et liste d'administration trop large.
4. **Tableau de répartition des statistiques** qui défilait horizontalement à cause d'une largeur minimale fixe.

Chacun est désormais gardé par un test de non-régression (`ResponsiveTest`) et par la vérification du navigateur.

## Contenu des tests automatisés

| Fichier | Tests | Module et sujets |
|---|---:|---|
| `FondationsTest` | 8 | Formats FCFA et date, layouts, composants, ressources locales |
| `AuthTest` | 33 | Inscription, connexion, déconnexion, middlewares `guest` / `actif` / `admin`, limitation à 5 essais, modèle `User`, seeder |
| `CategorieTest` | 46 | Catégories par défaut, CRUD, unicité, suppression refusée, isolation, contraintes de base |
| `DepenseTest` | 58 | CRUD, validation du montant et de la date, filtres combinés, pagination, isolation, suppression d'un utilisateur |
| `RevenuTest` | 40 | Idem pour les revenus, séparation étanche avec les dépenses |
| `ProfilTest` | 34 | Informations, mot de passe, onglets, limitation de débit |
| `DashboardTest` | 29 | Cartes du mois et solde cumulé, bornes de dates, graphiques, dernières transactions (date figée) |
| `StatistiquesTest` | 30 | Mois / année, répartition et pourcentages, moyenne, périodes invalides, 31 du mois, calculs `BudgetStats` |
| `HistoriqueTest` | 48 | Liste unifiée, pagination stable, 7 filtres combinables, totaux, injections, suppression depuis l'historique (retour sécurisé, page hors limites) |
| `AdminUtilisateurTest` | 47 | Liste, recherche, fiche, modification, suspension, suppression atomique, confidentialité |
| `DonneesDeTestTest` | 13 | Données de démonstration `[TEST]` : 6 mois, aucune date future, rejouable |
| `SecuriteTest` | 21 (29 avec jeux de données) | Couverture des routes, CSRF, XSS, cookies, en-têtes, pages d'erreur, limitation |
| `ResponsiveTest` | 7 | Garde-fous T10 |
| `PresenceDemoTest` | 8 | Commande `demo:presence` : 12 comptes en ligne, 10 déconnectés depuis des durées variées (45 minutes à 6 mois), comptes réels jamais touchés, présence qui expire après 5 minutes puis se renouvelle, refusée en production, effet sur le tableau de bord de l'administrateur |
| `UtilisateursSupplementairesTest` | 11 | 22 comptes de démonstration supplémentaires : adresses distinctes, connexion, 13 catégories, revenus et dépenses marqués « [TEST] », aucune date future, budget positif, rejouable sans doublon, absents du seeder par défaut |
| `AdminRechercheTest` | 20 | Page « Rechercher un utilisateur » : accès réservé à l'administrateur, menu, aucun résultat sans recherche, nom / prénom / e-mail / nom complet, surlignage, `%` et `_` traités comme du texte, injection, filtres statut et connexion, pagination, confidentialité du budget |
| `AdminTableauDeBordTest` | 31 | Tableau de bord de l'administrateur : accès, compteurs, en ligne / déconnecté, durée de déconnexion en toutes lettres, tri, suivi automatique (connexion, inscription, déconnexion, suspension), confidentialité du budget |
| `AnimationsTest` | 23 | Barre de recherche extensible et aperçu animé de connexion : balisage, accessibilité, dégradation sans JavaScript, injection, aucune requête externe, respect de « réduire les animations » ; logo présent partout, favicon, couleurs du logo dans la feuille de style |
| `ConformiteCahierDesChargesTest` | 9 (42 avec jeux de données) | Traçabilité du cahier des charges : modèles, contrôleurs et méthodes, 34 routes avec leur accès, middlewares, policies, Form Requests, vues ; parcours complets utilisateur et administrateur (EF1 à EF12) |

Les nombres de la colonne « Tests » sont ceux des méthodes ; les jeux de données (XSS : 5 charges ; routes du cahier des charges : 34) portent le total à **584**.

## Rejouer les vérifications

```powershell
# Tests automatisés (base de test séparée « smartbudget_test » à créer une fois dans phpMyAdmin)
php artisan test
php artisan test --filter=DepenseTest        # un seul fichier

# Vérification dans un navigateur réel (Microsoft Edge)
php artisan migrate:fresh --seed
php artisan serve --port=8765                # premier terminal
node tests/Navigateur/verif_navigateur.cjs captures    # pages, largeurs, graphiques, barre latérale → rapport.json + images
node tests/Navigateur/verif_suspension.cjs               # suspension d'un utilisateur en ligne : hors ligne aussitôt chez l'administrateur, renvoyé à la connexion en 15 s (7 vérifications)
node tests/Navigateur/verif_animations.cjs captures    # aperçu animé de connexion et barre de recherche (39 vérifications)
```

> ⚠️ Les tests réinitialisent la base qu'ils utilisent. `phpunit.xml` pointe volontairement sur `smartbudget_test` :
> **ne le changez pas**. Un défaut de configuration d'origine (lignes SQLite en commentaire) avait fait vider la base de développement
> pendant les premiers modules ; il a été corrigé et vérifié par un comptage avant / après chaque suite.

## Ce qui n'est pas couvert

- Un navigateur autre qu'Edge (le cahier des charges demande Chrome, Firefox et Edge récents ; Bootstrap 5 et Chart.js sont compatibles, mais seul Edge a été piloté).
- Un téléphone physique.
- La montée en charge : la mesure de performance est faite sur un seul utilisateur avec le serveur de développement de Laravel.
- L'accessibilité au clavier et aux lecteurs d'écran (des `aria-label` sont présents, sans audit dédié).
