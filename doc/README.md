# Documentation de SmartBudget

SmartBudget est une application web Laravel / MySQL de gestion des dépenses personnelles en FCFA.
Ce dossier documente **l'ensemble du projet** (le dossier global `SmartBudget-App/`) ; le code est dans `smartbudget/`.

## Organisation du dossier global

```
SmartBudget-App/
├── Cahier des charges — SmartBudget.pdf   ← exigences (tirées du mémoire)
├── doc/                                    ← cette documentation
│   ├── README.md              (ce fichier : index)
│   ├── ARCHITECTURE.md        structure du code, base de données, routes, décisions de conception
│   ├── TESTS.md               tests T1 à T10 du cahier des charges, résultats, comment les rejouer
│   ├── SECURITE.md            revue de sécurité : mesures, preuves, limites
│   ├── DESIGN.md              système de design : jetons, mise en page, composants, skill utilisée
│   ├── PLAN_ACTION.md         plan par modules, décisions, journal d'avancement
│   └── mémo_Moustapha (2).pdf ← mémoire de Licence
└── smartbudget/                            ← l'application (voir son README pour l'installation)
```

## Par où commencer

| Je veux… | Je lis… |
|---|---|
| **Installer et lancer** l'application sous XAMPP | [`smartbudget/README.md`](../smartbudget/README.md) |
| Comprendre comment le code est organisé | [ARCHITECTURE.md](ARCHITECTURE.md) |
| Savoir ce qui a été testé et comment | [TESTS.md](TESTS.md) |
| Savoir comment les données sont protégées | [SECURITE.md](SECURITE.md) |
| Modifier l'apparence (couleurs, polices, composants) | [DESIGN.md](DESIGN.md) |
| Retrouver les décisions prises et leur raison | [PLAN_ACTION.md](PLAN_ACTION.md) |

## État du projet

Les **9 modules** du plan sont réalisés et testés : fondations, authentification, catégories, dépenses, revenus,
profil, tableau de bord / statistiques / graphiques, historique et recherche, administration, puis la revue de sécurité
et les livrables.

| Mesure | Valeur |
|---|---|
| Tests automatisés | **584 tests**, 6 014 assertions, tous verts |
| Vérification dans un vrai navigateur (Edge) | 35 mesures de pages (3 largeurs), 0 problème |
| Performance (CDC : < 2 s avec 1 000 transactions) | 0,5 à 0,8 s mesuré avec 1 200 transactions |
| Dépendances | `composer audit` : aucune faille connue |

## Conformité au cahier des charges et au mémoire

Chaque exigence structurelle du cahier des charges (§7.1 à §7.5, §8.1) est une assertion dans
`smartbudget/tests/Feature/ConformiteCahierDesChargesTest.php` ; deux parcours complets (utilisateur, administrateur) traversent les 12 exigences fonctionnelles du mémoire.

| Exigence | Réalisé | Preuve |
|---|---|---|
| **EF1 à EF12** (inscription, authentification, profil, revenus, dépenses, catégories, historique, tableau de bord, statistiques, graphiques, recherche, déconnexion) | Oui, les 12 | Parcours utilisateur |
| Cas d'utilisation du mémoire (tables 2.6 à 2.14), dont « Gérer les utilisateurs » (consulter, modifier, suspendre, réactiver, supprimer) | Oui | Parcours utilisateur et administrateur |
| §7.1 modèles et relations | Oui | Test de conformité |
| §7.2 contrôleurs et méthodes (7 contrôleurs) | Oui, toutes les méthodes | Test de conformité |
| §7.3 routes (34, avec leur action et leur accès) | Oui, toutes | Test de conformité (jeu de données) |
| §7.4 middlewares (`auth`, `guest`, `admin`, `actif`) et 3 policies | Oui (+ `utilisateur`) | Test de conformité, `SecuriteTest` |
| §7.5 Form Requests | Oui | Test de conformité |
| §8.1 vues et composants (dont les dossiers `depenses/` et `revenus/`) | Oui, toutes | Test de conformité |
| §8.2 écrans et §3 règles de gestion | Oui | Modules 1 à 8, vérification dans Edge |

**Interfaces et « API »** : le cahier des charges et le mémoire ne prévoient **aucune API REST / JSON**. Les interfaces sont les routes web ci-dessus, avec un rendu côté serveur ;
seules les données des graphiques sont transmises en JSON à Chart.js.

**Non couvert par l'application** : les responsabilités de l'administrateur décrites au mémoire (§2.2.2) « contrôler la cohérence des données », « assurer la maintenance »
et « garantir la disponibilité et la sécurité » sont des tâches d'exploitation, sans écran dédié dans le cahier des charges (le §3.7 ne détaille que la gestion des comptes).

## Écarts assumés avec le cahier des charges

| Cahier des charges | Réalisé | Pourquoi |
|---|---|---|
| Laravel 11 | **Laravel 12** | Composer refuse toutes les versions 11.x (avis de sécurité). À corriger dans le mémoire. |
| 4 tables | 4 tables (+ `migrations` de Laravel) | Sessions, cache et files d'attente en fichiers, pas en base. |
| `whereMonth` / `whereYear` | Intervalles de dates (`BETWEEN`) | Même résultat, mais utilise l'index `(user_id, date)`. |
| Middlewares `auth`, `guest`, `admin`, `actif` | + `utilisateur` | Réserve l'espace budget aux comptes « utilisateur » : l'administrateur ne voit jamais les transactions (CDC §3.7). |
| — | Limitation du débit sur l'inscription et le changement de mot de passe | Protection supplémentaire contre les abus. |

Le cahier des charges demande aussi de reporter `DashboardController` et `ProfilController` dans le diagramme de classes du mémoire.

## Décisions prises sur les points laissés ouverts

Le cahier des charges et le mémoire ne tranchaient pas quatre points ; voici ce qui a été décidé, et pourquoi.

| Point ouvert | Décision | Raison |
|---|---|---|
| Organisation des vues des dépenses et des revenus | Dossiers `depenses/` et `revenus/` (index, create, edit) créés comme au cahier des charges (§8.1) ; ce sont des vues de quelques lignes qui réutilisent la mise en page partagée `transactions/` | Respecte la structure demandée sans dupliquer le code des deux modules |
| Un administrateur peut-il suspendre ou supprimer un **autre** administrateur ? | **Non** : un compte administrateur ne peut être ni suspendu ni supprimé (le sien non plus) ; il reste modifiable | Le mémoire (§2.2.2) définit l'administration comme la gestion des *comptes utilisateurs* ; évite qu'un compte compromis supprime ou verrouille tous les autres administrateurs |
| Solde du tableau de bord : du mois ou cumulé ? | **Les deux** : « Solde du mois » (revenus − dépenses du mois, comme le cahier des charges) et, sous la carte, « Solde cumulé » depuis le début jusqu'à aujourd'hui | Les deux lectures sont légitimes ; aucune carte supplémentaire |
| Supprimer depuis l'historique | **Oui** : bouton par ligne avec fenêtre de confirmation ; retour sur l'historique avec les mêmes filtres et la même page | Évite d'aller dans une autre page ; le retour n'accepte que l'historique (pas de redirection libre) |

## Ce qui reste à faire de votre côté

- Corriger « Laravel 11 » en « Laravel 12 » dans le mémoire et y reporter les deux contrôleurs ajoutés.
- Prendre les **captures d'écran** du mémoire (section 3.4) : le script `smartbudget/tests/Navigateur/verif_navigateur.cjs` en produit déjà de bonnes (voir `smartbudget/README.md`).
- Contrôler une dernière fois l'affichage sur un vrai téléphone.
- Mettre le code sur **GitHub** (livrable du cahier des charges, §9.3). Le fichier `.gitignore` exclut déjà `.env` et `vendor/`.
