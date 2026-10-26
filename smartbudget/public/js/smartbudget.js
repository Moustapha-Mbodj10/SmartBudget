// Confirmation avant suppression : <form data-confirm="Message"> ouvre la modale commune.
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('confirmModal');
    if (modalEl) {
        var modal = new bootstrap.Modal(modalEl);
        var pendingForm = null;

        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (form.hasAttribute('data-confirm') && !form.dataset.confirmed) {
                e.preventDefault();
                pendingForm = form;
                modalEl.querySelector('[data-confirm-message]').textContent = form.getAttribute('data-confirm');
                modal.show();
            }
        });

        modalEl.querySelector('[data-confirm-accept]').addEventListener('click', function () {
            if (pendingForm) {
                pendingForm.dataset.confirmed = '1';
                pendingForm.submit();
            }
        });
    }

    // Barre latérale : bouton pour la replier / la déplier. Le choix est mémorisé dans le navigateur
    // (simple confort : si le stockage est indisponible, la barre reste simplement repliée).
    var bascule = document.querySelector('[data-rail-toggle]');
    if (bascule) {
        var racine = document.documentElement;
        var majBascule = function () {
            var ouvert = racine.classList.contains('sb-rail-open');
            var texte = ouvert ? 'Réduire le menu' : 'Déplier le menu';
            bascule.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
            bascule.setAttribute('aria-label', texte);
            bascule.setAttribute('title', texte);
        };
        majBascule();
        bascule.addEventListener('click', function () {
            racine.classList.toggle('sb-rail-open');
            try { localStorage.setItem('sb-rail', racine.classList.contains('sb-rail-open') ? 'ouvert' : 'ferme'); } catch (e) { /* stockage indisponible */ }
            majBascule();
        });
    }

    // Battement de session (pages connectées) : toutes les 15 secondes, la page demande si la session est toujours valide.
    // Si l'administrateur a suspendu ou supprimé le compte entre-temps, la personne est renvoyée tout de suite vers la connexion,
    // sans avoir à cliquer. Une page ouverte compte aussi comme « en ligne » pour l'administrateur.
    var urlEtat = document.querySelector('meta[name="sb-session-etat"]');
    var urlLogin = document.querySelector('meta[name="sb-session-login"]');
    if (urlEtat && urlLogin && window.fetch) {
        var verifier = function () {
            fetch(urlEtat.content, { credentials: 'same-origin', redirect: 'manual', cache: 'no-store', headers: { 'Accept': 'application/json' } })
                .then(function (reponse) {
                    if (reponse.status !== 204) { window.location.assign(urlLogin.content); }
                })
                .catch(function () { /* hors connexion : on réessaie au prochain battement */ });
        };
        setInterval(verifier, 15000);
        document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') { verifier(); } });
    }

    // Tableau de bord de l'administrateur : la page se recharge toute seule (data-actualiser = délai en ms)
    // pour que « en ligne » et les durées de déconnexion restent justes. Rien n'est rechargé si l'onglet est caché.
    var actualiser = document.querySelector('[data-actualiser]');
    if (actualiser) {
        setInterval(function () {
            if (document.visibilityState === 'visible') { window.location.reload(); }
        }, parseInt(actualiser.getAttribute('data-actualiser'), 10) || 30000);
    }

    // Les alertes flash disparaissent après 5 secondes.
    document.querySelectorAll('.alert[data-autohide]').forEach(function (el) {
        setTimeout(function () { bootstrap.Alert.getOrCreateInstance(el).close(); }, 5000);
    });
});
