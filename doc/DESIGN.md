# Système de design de SmartBudget

Refonte graphique de toutes les interfaces, inspirée de la maquette fournie (tableau de bord financier : cartes blanches très arrondies sur fond gris perle, navigation en pilule, rail d'icônes à gauche, barres arrondies). Ce document décrit les choix, pour pouvoir faire évoluer l'interface sans la défigurer.

## Méthode

- **Skill de design installée depuis GitHub** : [`frontend-design`](https://github.com/anthropics/skills/tree/main/skills/frontend-design) du dépôt officiel d'Anthropic, installée au niveau du projet dans `.claude/skills/frontend-design/` (origine consignée dans `ORIGINE.txt`). Son contenu a été lu en entier avant installation : ce sont des consignes de conception, sans script ni commande.
- `theme-factory`, du même dépôt, a été examinée mais **pas installée** : elle sert à habiller des présentations avec des thèmes prédéfinis, alors que la maquette fixe déjà la palette.
- Démarche imposée par la skill : plan de design (jetons de couleur, typographie, mise en page), relecture du plan contre la consigne, construction, puis critique sur captures d'écran réelles. Ici, chaque écran a été vérifié dans Microsoft Edge à 360, 768 et 1920 px.

## Jetons de design

Définis une seule fois dans `public/css/smartbudget.css` (bloc `:root`) ; changer une valeur change toute l'interface.

| Jeton | Valeur | Usage |
|---|---|---|
| `--sb-canvas` | `#e9eaee` | fond de page, gris perle |
| `--sb-surface` | `#ffffff` | cartes |
| `--sb-inset` | `#f3f4f6` | champs de formulaire, pastille de navigation |
| `--sb-ink` | `#14171c` | texte |
| `--sb-muted` | `#7c828c` | texte secondaire (contraste suffisant sur blanc) |
| `--sb-brand` | `#054086` | **couleur de la marque** (bleu du logo) : boutons principaux, onglet actif, liens, focus |
| `--sb-brand-deep` / `--sb-brand-bright` | `#032c61` / `#0b5bc4` | survol et fin de dégradé / début de dégradé et onglet actif du menu |
| `--sb-brand-soft` / `--sb-brand-wash` | `#c3d4ee` / `#e8eff9` | contours, fonds de pastilles de catégories |
| `--sb-mint` | `#52eba8` | vert menthe du logo : accents sur fond bleu |
| `--sb-green` | `#14a05b` | **revenus** et montants positifs |
| `--sb-green-deep` | `#0c6b3f` | textes verts (montants de revenus) |
| `--sb-green-soft` / `--sb-green-wash` | `#bfe0ce` / `#e7f4ec` | barres des mois passés, fonds de pastilles de revenus |
| `--sb-red` | `#e04b5a` | **dépenses uniquement** (charte du cahier des charges : vert = revenus, rouge = dépenses) |
| `--sb-radius-hero` / `-card` / `-field` | 30 / 24 / 14 px | rayons **hiérarchisés** : la carte du solde est plus arrondie que les cartes, elles-mêmes plus que les champs ; boutons et pastilles sont en pilule complète |

**Typographie** : **Urbanist** (la police de la maquette), une seule famille du poids 400 au 800, **servie en local** (`public/vendor/fonts`, licence SIL OFL) : l'application reste utilisable sans Internet pour la soutenance. Chiffres tabulaires activés pour que les montants s'alignent.

## Identité visuelle : le logo

Le logo fourni (fichier d'origine conservé dans `public/img/logo-smartbudget.jpg`) est un portefeuille en écusson blanc sur fond **bleu roi**, avec une pièce, trois barres et une flèche de croissance en **vert menthe**. Il a été **redessiné en SVG** (net à toutes les tailles, aucune image à charger) :

- `<x-logo>` : le logo fixe, posé dans une pastille bleue (`.sb-logo`) en haut de chaque page, sur les pages de connexion et d'inscription et dans les pages d'erreur ;
- `<x-apercu-anime>` : des cartes d'interface en FCFA qui animent la page de connexion (voir « Animations ») ;
- `public/favicon.svg` : l'icône de l'onglet du navigateur.

Les couleurs de l'interface sont celles du logo : le **bleu `#054086`** devient la couleur de la marque (boutons principaux, onglet actif, pastille d'utilisateur, liens, anneau de focus, carte du solde, panneau de connexion, courbe du solde) et le **vert menthe `#52eba8`** son accent sur fond bleu (flèche et pièce du logo, barre mise en avant du panneau de connexion, rang suivant dans l'anneau des catégories). **Le sens des couleurs ne change pas** : vert = revenus et montants positifs, rouge = dépenses et montants négatifs (charte du cahier des charges).

## Mise en page

```
ordinateur (≥ 992 px)                                      téléphone (< 992 px)
┌──┐ ┌────────────────────────────────────────────────┐   ┌──────────────────────────┐
│▦ │ │ ◉ SmartBudget  ( Tableau… Dépenses… Revenus… ) AD │   │ ◉ SmartBudget        ☰  │
│+ │ └────────────────────────────────────────────────┘   └──────────────────────────┘
│+ │  Bon retour, Awa             [oct. 2026][+Dép][+Rev]    Bon retour, Awa
│🏷 │  ┌ carte bleue ┐ ┌ barres rondes (6 mois) ───────┐     [+ Dépense] [+ Revenu]
│  │  │ solde       │ │                               │     ┌ carte bleue ────────┐
│👤│  └─────────────┘ └───────────────────────────────┘     └──────────────────────┘
│⎋ │  [revenus] [dépenses] [transactions]                   barres, chiffres, tableau…
└──┘  ┌ dernières transactions ─────┐ ┌ anneau ┐          (menu = panneau sombre via ☰)
```

- **Barre du haut en pilule** : logo, navigation centrale (Tableau de bord, Dépenses, Revenus, Statistiques, Historique), pastille de l'utilisateur.
- **Barre latérale fixe à gauche** (ordinateur) : tableau de bord, **ajout rapide d'une dépense (rouge) et d'un revenu (vert)**, catégories, profil, déconnexion. Les deux zones ont des rôles distincts : la pastille sert à *consulter*, la barre latérale à *ajouter et gérer*.
  - **Vraiment fixe** (`position: fixed`) : collée à 20 px du haut et du bas de l'écran, elle ne bouge pas quand on fait défiler la page (mesuré dans Edge : 20 px du haut et du bas avant et après un défilement de 900 px).
  - **Repliable** : un bouton en haut de la barre la déplie (78 px → 252 px) et affiche les libellés à côté des icônes. Le choix est **mémorisé** dans le navigateur et restauré avant l'affichage de la page (aucun saut visuel) ; si le stockage est bloqué, la barre reste simplement repliée. Le contenu se décale avec elle.
  - **Écrans moyens** : barre dépliée, le contenu perd 174 px ; la barre du haut se resserre (nom de la marque et nom de l'utilisateur masqués) et les colonnes secondaires des tableaux (description, date d'inscription, e-mail déplacé sous le nom) disparaissent plutôt que de faire défiler. Vérifié de 1024 à 1920 px, repliée et dépliée, sur 5 pages utilisateur et 2 pages d'administration.
- **Téléphone** : le même bloc de navigation (`#sidebar`) devient un panneau sombre ouvert par le bouton burger, et reprend tout ce que porte le rail.
- **Administrateur** : navigation réduite à « Utilisateurs » ; aucun lien vers l'espace budget.
- **Un seul point fort** : la **carte bleue du solde** (montant du mois en grand, solde cumulé en pied de carte, comme sur une carte bancaire). Elle devient rouge si le solde du mois est négatif. Tout le reste reste volontairement sobre.

## Composants

| Élément | Classe ou fichier | Remarque |
|---|---|---|
| Coque, rail, barre du haut | `.sb-shell`, `.sb-rail`, `.sb-topbar`, `.sb-nav` | `resources/views/layouts/app.blade.php` |
| En-tête de page | `.sb-page-head`, `.sb-page-title` | titre en deux tons possible (`.sb-soft`) ; section `entete` ou `actions` |
| Titre de carte à deux lignes | `.sb-card-title` + `.sb-card-sub` | titre, puis sous-titre gris |
| Carte de chiffre | `<x-stat-card>` (`hero` pour la carte bleue) | icône dans un carré arrondi |
| Pastille de catégorie | `.sb-cat`, `.sb-cat-dot` | initiale dans un rond, bleu de la marque (rouge sur les lignes de dépenses) |
| Tableaux | `.table`, `.sb-table-compact` | lignes douces, sans cadre ; compacts sous 576 px |
| Boutons, badges | `.btn` en pilule, `.badge` | teintes douces (vert / rouge / gris) |
| Graphiques | `public/js/graphiques.js` | barres arrondies, mois mis en valeur en couleur pleine et les autres en teinte douce ; **courbe lissée du solde** ; anneau pour les catégories ; infobulle sombre |
| Authentification | `.sb-auth` | grande surface bleue à gauche (ordinateur) portant l'aperçu animé, formulaire à droite |
| Erreurs | `.sb-error` | carte centrée |

## Animations (page de connexion et barre de recherche)

Inspirées de composants de [21st.dev](https://21st.dev/), **réécrites en CSS et JavaScript natifs** : ces composants sont en React, Tailwind et Framer Motion et s'installent par une CLI avec compte, alors que SmartBudget est en Blade et Bootstrap et doit fonctionner hors ligne. Aucun code n'a été copié ; seules les idées sont reprises. Fichiers : `public/js/animations.js` et la section « Animations » de `public/css/smartbudget.css`.

| Idée reprise | Source d'inspiration | Dans SmartBudget |
|---|---|---|
| Une page de présentation de plateforme de finances personnelles : cartes d'interface qui entrent en cascade puis flottent | Shot Dribbble [Promo Landing Page for a Personal Finance Platform](https://dribbble.com/shots/26496450-Promo-Landing-Page-for-a-Personal-Finance-Platform) (Outcrowd), demandé par l'utilisateur pour la page de connexion | **L'aperçu animé** (`<x-apercu-anime>`, HTML et SVG, sans image) : cinq cartes aux données d'exemple (solde du mois avec courbe qui se dessine, anneau de répartition aux trois arcs, croissance du solde aux deux barres qui montent, revenus et dépenses, notification « Revenu ajouté ») entrent en cascade en se précisant, puis flottent lentement. Un léger effet de profondeur suit le curseur. **Les montants se floutent quand on tape le mot de passe** et réapparaissent si on l'affiche ; les cartes **tremblent** et l'arc bleu **vire au rouge** si les identifiants sont faux ; elles **s'envolent** à l'envoi |
| Arrière-plan qui bouge doucement, entrée de page orchestrée | [Modern Animated Sign In](https://21st.dev/community/components/arunachalam0606/modern-animated-sign-in), [Login Card](https://21st.dev/community/components/ruixenui/login-card/default) (21st.dev) | Les grands cercles du panneau bleu dérivent très lentement ; les cartes arrivent d'abord, le texte suit |
| Barre de recherche qui s'étend depuis une icône | [Expandable Search Bar](https://21st.dev/community/components/arunachalam0606/expandable-search-bar/default), [Expanding Search Bar](https://21st.dev/community/components/explore/expanding-search-bar) | `<x-recherche>` : icône ronde qui s'étend en champ (340 px), focus automatique, suggestions qui défilent dans le champ (les noms de **vos** catégories ; « Chercher par nom / prénom / e-mail » pour l'administration), bouton de validation qui apparaît dès qu'on tape |
| Raccourci clavier | pratique courante des barres de commande | « **/** » ouvre la recherche ; **Échap** efface puis referme ; un clic ailleurs la referme si elle est vide ; une recherche en cours reste ouverte |

Principes retenus : le mouvement **répond à une action** (clic, saisie, envoi) ; les seuls mouvements spontanés sont l'entrée des cartes à l'ouverture (une fois), leur flottement très lent et la dérive des cercles de fond. Le bouton « afficher le mot de passe » est une amélioration d'usage en soi.

- **Sans JavaScript** : la barre de recherche reste ouverte et fonctionne comme un formulaire GET classique ; la page de connexion se soumet normalement. Les états « repliés » ne s'appliquent qu'avec la classe `js` ajoutée par la page.
- **Réduire les animations** : plus aucun mouvement (CSS), les suggestions ne défilent pas et le formulaire part sans délai.
- **Hors ligne et sans fuite** : aucun service externe (testé : le script ne contient aucune adresse ni requête réseau).
- **Sécurité** : les suggestions sont échappées dans l'attribut HTML ; une catégorie nommée `"><script>` reste inoffensive (testé).
- **Envoi jamais bloqué** : les cartes s'envolent pendant 520 ms puis le formulaire part, une seule fois.
- **Mobile** : le panneau bleu (et donc l'aperçu) est masqué ; le formulaire reste seul à l'écran.
- **Données d'exemple** : les montants des cartes (87 000, 164 000 FCFA…) sont fictifs et identiques pour tout le monde ; aucune donnée de compte n'est affichée avant la connexion.

## Tableau de bord de l'administrateur

Même langage visuel que celui de l'utilisateur, avec une carte bleue pour le chiffre qui change le plus (**personnes en ligne**), quatre compteurs (comptes utilisateurs, déconnectés, actifs, suspendus) et un tableau « Statut des utilisateurs » : compte actif / suspendu (pastille verte ou rouge), connexion avec un **point vert « En ligne »** ou un point gris « Déconnecté » suivi de la durée (« depuis 2 heures et 20 minutes », « aucune connexion enregistrée » pour un compte jamais connecté) et dernière activité. En ligne d'abord, puis les déconnexions les plus récentes. La page se recharge seule toutes les 30 secondes (onglet visible). Composant `<x-connexion>` réutilisé dans la liste et la fiche des utilisateurs.

## Page « Rechercher un utilisateur » (administrateur)

Un menu à part (« Rechercher », loupe, entre « Tableau de bord » et « Utilisateurs ») ouvre une page qui ne sert qu'à retrouver un compte : un grand champ de recherche en pilule (nom, prénom ou e-mail), deux filtres facultatifs (statut du compte, en ligne / déconnecté) et, **seulement après une recherche**, la liste des résultats avec le terme **surligné**, le rôle, le statut, la connexion (« En ligne » ou « Déconnecté depuis… ») et les boutons consulter / modifier. Sans recherche, la page invite à taper un nom ; sans résultat, elle le dit. Le champ est actif dès l'ouverture (autofocus).

## Accessibilité et mouvement

- Focus clavier visible partout (anneau bleu).
- `prefers-reduced-motion` respecté, y compris par les graphiques : si l'utilisateur demande de réduire les animations, rien ne bouge. Sinon, le mouvement se limite aux réponses aux actions (fenêtres, survols) et à l'apparition des graphiques ; il n'y a aucune animation d'entrée sur les sections.
- Boutons-icônes munis d'`aria-label` et d'un `title` ; titres de colonnes masqués sur téléphone conservés pour les lecteurs d'écran.
- Les montants ne sont jamais le seul porteur de sens : le type est aussi écrit (badge « Dépense » / « Revenu ») et le signe est affiché (+ / −).

## Écarts voulus avec la maquette

| Maquette | SmartBudget | Raison |
|---|---|---|
| Carte bancaire Visa | Carte bleue du solde | Le sujet est un budget en FCFA, pas une carte de paiement |
| Courbe « Total Balance » | **Reprise** : « Évolution du solde » (courbe lissée avec dégradé bleu, dernier point mis en valeur) | Solde cumulé à la fin de chacun des 6 derniers mois, calculé par `BudgetStats::evolutionSolde()` ; son dernier point égale le « Solde cumulé » de la carte verte. Ni le bouton « Send / Receive » ni la carte « Amount of credit » ne sont repris (pas de sens pour un budget) |
| Navigation anglaise, dollars | Français, FCFA, dates JJ/MM/AAAA | Exigence du cahier des charges |
| Titres avec un mot grisé | Repris pour « Bon retour, Awa » | Fidèle à la maquette ; limité à cet écran |

## Défauts trouvés et corrigés pendant la refonte

Relevés sur captures réelles, pas par les tests automatisés :
- la **pagination affichait « Showing 1 to 10 of 69 results » en anglais** sur ordinateur (fichier `lang/fr.json` ajouté) ;
- le champ de date « Du » de l'historique était trop étroit (les filtres sont réorganisés) ;
- les liens restaient bleus (Bootstrap 5.3 lit `--bs-link-color-rgb`) ;
- la carte du solde laissait un grand vide dans sa moitié basse ;
- « Nouveau dépense » (accord corrigé : « Nouvelle dépense ») et « Statistiques de octobre » (élision : « d'octobre »).

## Faire évoluer le design

1. Changer une couleur ou un rayon : modifier le bloc `:root` de `public/css/smartbudget.css`.
2. Ajouter une page : étendre `layouts.app`, définir la section `titre` (et `actions` pour un bouton en haut à droite), puis utiliser `.card`, `.sb-card-title` et `.sb-card-sub`.
3. Garder les hooks de test : plusieurs tests s'appuient sur des classes (`sb-positive`, `sb-negative`, `sb-table-compact`, `table-responsive`, `text-bg-success`...) et sur des libellés ; le guide de lancement est dans `smartbudget/README.md`.
4. Rejouer la vérification visuelle : `node tests/Navigateur/verif_navigateur.cjs captures` (voir `TESTS.md`).
