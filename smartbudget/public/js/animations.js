// Animations de SmartBudget : barre de recherche extensible et aperçu animé de la page de connexion.
// Inspirées de composants de 21st.dev, réécrites sans aucune bibliothèque (tout fonctionne hors ligne).
// Sans JavaScript, tout reste utilisable : la barre de recherche est simplement ouverte, le formulaire part normalement.
(function () {
    'use strict';

    var reduit = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---------------------------------------------------------------------------------------------
    // Barre de recherche extensible
    // ---------------------------------------------------------------------------------------------
    function initialiserRecherche(form) {
        var bouton = form.querySelector('[data-recherche-ouvrir]');
        var champ = form.querySelector('.sb-search-input');
        var hint = form.querySelector('.sb-search-hint');
        if (!bouton || !champ) { return; }

        var suggestions = [];
        try { suggestions = JSON.parse(champ.getAttribute('data-suggestions') || '[]'); } catch (e) { suggestions = []; }
        var prefixe = champ.getAttribute('data-prefixe') || '';
        var textes = [champ.getAttribute('aria-label') || ''].concat(suggestions.slice(0, 6).map(function (s) { return prefixe + s; }));

        var aUneValeur = function () { return champ.value.trim() !== ''; };
        var majValeur = function () { form.classList.toggle('has-value', aUneValeur()); };

        var ouvrir = function (focus) {
            form.classList.add('is-open');
            bouton.setAttribute('aria-expanded', 'true');
            if (focus) { champ.focus({ preventScroll: true }); }
        };
        var fermer = function () {
            if (aUneValeur()) { return; } // une recherche en cours reste visible
            form.classList.remove('is-open');
            bouton.setAttribute('aria-expanded', 'false');
        };

        champ.setAttribute('placeholder', ''); // le texte animé prend le relais (l'étiquette accessible reste aria-label)
        majValeur();

        bouton.addEventListener('click', function () {
            if (form.classList.contains('is-open')) { champ.focus(); } else { ouvrir(true); }
        });
        champ.addEventListener('input', majValeur);
        champ.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') { return; }
            if (champ.value !== '') { champ.value = ''; majValeur(); } else { fermer(); champ.blur(); }
        });
        form.addEventListener('focusout', function () {
            setTimeout(function () { if (!form.contains(document.activeElement)) { fermer(); } }, 0);
        });
        document.addEventListener('click', function (e) { if (!form.contains(e.target)) { fermer(); } });

        // Raccourci « / » : ouvre la recherche (sauf pendant la saisie dans un autre champ)
        document.addEventListener('keydown', function (e) {
            var actif = document.activeElement;
            var enSaisie = actif && (/^(INPUT|TEXTAREA|SELECT)$/.test(actif.tagName) || actif.isContentEditable);
            if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && !enSaisie) {
                e.preventDefault();
                ouvrir(true);
            }
        });

        // Suggestions qui défilent dans le champ vide : la plus ancienne glisse vers le haut, la suivante monte
        if (hint) {
            var i = 0;
            hint.textContent = textes[0];
            if (!reduit && textes.length > 1) {
                setInterval(function () {
                    if (aUneValeur() || !form.classList.contains('is-open')) { return; }
                    hint.classList.add('sb-hint-out');
                    setTimeout(function () {
                        i = (i + 1) % textes.length;
                        hint.textContent = textes[i];
                        hint.classList.remove('sb-hint-out');
                        hint.classList.add('sb-hint-in');
                        requestAnimationFrame(function () { requestAnimationFrame(function () { hint.classList.remove('sb-hint-in'); }); });
                    }, 230);
                }, 2800);
            }
        }
    }
    document.querySelectorAll('[data-recherche]').forEach(initialiserRecherche);

    // ---------------------------------------------------------------------------------------------
    // L'aperçu animé de la page de connexion / d'inscription
    // ---------------------------------------------------------------------------------------------
    var tirelire = document.querySelector('[data-mascotte]');
    if (!tirelire) { return; }

    var champsMdp = Array.prototype.slice.call(document.querySelectorAll('[data-mdp]'));
    var bascule = document.querySelector('[data-afficher-mdp]');
    var dernierClicBascule = 0;

    // 1) Effet de profondeur : les cartes se décalent légèrement selon la position du curseur (une mise à jour par image)
    if (!reduit) {
        var dernierPointeur = null;
        var enAttente = false;
        document.addEventListener('pointermove', function (e) {
            dernierPointeur = e;
            if (enAttente) { return; }
            enAttente = true;
            requestAnimationFrame(function () {
                enAttente = false;
                var nx = dernierPointeur.clientX / window.innerWidth - 0.5;
                var ny = dernierPointeur.clientY / window.innerHeight - 0.5;
                tirelire.style.setProperty('--px', (nx * -14).toFixed(2) + 'px');
                tirelire.style.setProperty('--py', (ny * -10).toFixed(2) + 'px');
            });
        });
    }

    // 2) Mot de passe : les montants se floutent pendant la saisie (confidentialité) ; si on l'affiche, ils réapparaissent
    var majYeux = function () {
        var actif = document.activeElement;
        var surMdp = champsMdp.indexOf(actif) >= 0;
        var visible = champsMdp.length > 0 && champsMdp[0].type === 'text';
        var recent = Date.now() - dernierClicBascule < 1500;
        tirelire.classList.toggle('is-closed', surMdp && !visible);
        tirelire.classList.toggle('is-peek', visible && (surMdp || actif === bascule || recent));
    };
    champsMdp.forEach(function (champ) {
        champ.addEventListener('focus', majYeux);
        champ.addEventListener('blur', function () { setTimeout(majYeux, 0); });
    });
    if (bascule && champsMdp.length) {
        bascule.addEventListener('click', function () {
            var afficher = champsMdp[0].type === 'password';
            champsMdp.forEach(function (champ) { champ.type = afficher ? 'text' : 'password'; });
            bascule.setAttribute('aria-pressed', afficher ? 'true' : 'false');
            bascule.setAttribute('aria-label', afficher ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            var icone = bascule.querySelector('i');
            if (icone) { icone.className = afficher ? 'bi bi-eye-slash' : 'bi bi-eye'; }
            dernierClicBascule = Date.now();
            majYeux();
            setTimeout(majYeux, 1600);
        });
    }

    // 3) À l'envoi du formulaire : les cartes s'envolent, puis le formulaire part (délai court, jamais bloquant)
    var formulaire = document.querySelector('[data-connexion]');
    if (formulaire && !reduit) {
        var envoye = false;
        formulaire.addEventListener('submit', function (e) {
            if (envoye) { return; }
            envoye = true;
            e.preventDefault();
            tirelire.classList.remove('is-closed', 'is-peek');
            tirelire.classList.add('is-drop');
            var valider = formulaire.querySelector('[type="submit"]');
            if (valider) { valider.setAttribute('aria-busy', 'true'); }
            setTimeout(function () { formulaire.submit(); }, 520);
        });
    }
})();
