// Vérification réelle dans Microsoft Edge (mode sans fenêtre) via le protocole DevTools.
// Usage : node tests/Navigateur/verif_navigateur.cjs <dossier de sortie>
// Prérequis : base remplie (php artisan migrate:fresh --seed), serveur lancé (php artisan serve --port=8765) et Microsoft Edge installé.
// Variables facultatives : SB_BASE (défaut http://127.0.0.1:8765), SB_EDGE (chemin de msedge.exe).
const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const EDGE = process.env.SB_EDGE || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const BASE = process.env.SB_BASE || 'http://127.0.0.1:8765';
const OUT = process.argv[2] || 'captures';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
fs.mkdirSync(OUT, { recursive: true });

(async () => {
    const profil = fs.mkdtempSync(path.join(os.tmpdir(), 'edge-sb-'));
    const edge = spawn(EDGE, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
        '--user-data-dir=' + profil, '--remote-debugging-port=9333', '--hide-scrollbars=false', 'about:blank'], { stdio: 'ignore' });

    let cibles;
    for (let i = 0; i < 80; i++) {
        try { cibles = await (await fetch('http://127.0.0.1:9333/json/list')).json(); if (cibles.some((t) => t.type === 'page')) break; } catch (e) { /* démarrage */ }
        await sleep(250);
    }
    const page = cibles.find((t) => t.type === 'page');
    const ws = new WebSocket(page.webSocketDebuggerUrl);
    await new Promise((r) => ws.addEventListener('open', r));

    let n = 0;
    const attente = new Map();
    let evenements = [];
    ws.addEventListener('message', (e) => {
        const m = JSON.parse(e.data);
        if (m.id && attente.has(m.id)) { attente.get(m.id)(m); attente.delete(m.id); } else { evenements.push(m); }
    });
    const send = (method, params = {}) => new Promise((res, rej) => {
        const id = ++n;
        attente.set(id, (m) => (m.error ? rej(new Error(method + ' : ' + m.error.message)) : res(m.result)));
        ws.send(JSON.stringify({ id, method, params }));
    });
    const js = async (expression) => {
        const r = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (r.exceptionDetails) throw new Error('JS : ' + JSON.stringify(r.exceptionDetails.exception && r.exceptionDetails.exception.description || r.exceptionDetails).slice(0, 900));
        return r.result.value;
    };
    for (const d of ['Page', 'Runtime', 'Log', 'Network']) await send(d + '.enable');

    const attendreChargement = async () => {
        for (let i = 0; i < 150; i++) { if (evenements.some((e) => e.method === 'Page.loadEventFired')) break; await sleep(100); }
    };
    const aller = async (url, pause = 1900) => {
        evenements = [];
        await send('Page.navigate', { url: BASE + url });
        await attendreChargement();
        await sleep(pause); // laisse Chart.js finir son animation
    };
    const problemes = () => {
        const liste = [];
        for (const e of evenements) {
            if (e.method === 'Runtime.exceptionThrown') liste.push('exception JS : ' + (e.params.exceptionDetails.exception?.description || e.params.exceptionDetails.text).split('\n')[0]);
            if (e.method === 'Runtime.consoleAPICalled' && e.params.type === 'error') liste.push('console.error : ' + e.params.args.map((a) => a.value || a.description).join(' ').slice(0, 160));
            if (e.method === 'Log.entryAdded' && e.params.entry.level === 'error' && !/favicon/.test(e.params.entry.url || '')) liste.push('log : ' + e.params.entry.text.slice(0, 160));
            if (e.method === 'Network.responseReceived' && e.params.response.status >= 400 && !/favicon/.test(e.params.response.url)) liste.push('HTTP ' + e.params.response.status + ' ' + e.params.response.url.replace(BASE, ''));
            if (e.method === 'Network.loadingFailed' && !/favicon/.test(e.params.requestId)) liste.push('requête échouée : ' + (e.params.errorText || ''));
        }
        return [...new Set(liste)];
    };
    const viewport = (largeur, hauteur, mobile) => send('Emulation.setDeviceMetricsOverride', { width: largeur, height: hauteur, deviceScaleFactor: 1, mobile });
    const capture = async (nom, largeur, mobile) => {
        const hauteur = Math.min(await js('document.documentElement.scrollHeight'), 4000);
        await viewport(largeur, hauteur, mobile);
        await sleep(500);
        const { data } = await send('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(path.join(OUT, nom + '.png'), Buffer.from(data, 'base64'));
        await viewport(largeur, 900, mobile);
    };

    const mesures = `(() => {
        const de = document.documentElement, cw = de.clientWidth;
        const hors = [...document.querySelectorAll('body *')].filter((el) => {
            const r = el.getBoundingClientRect();
            return r.width > 0 && r.right > cw + 1 && !el.closest('.table-responsive') && getComputedStyle(el).position !== 'fixed';
        }).slice(0, 5).map((el) => el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.split(' ').join('.') : ''));
        // Un montant (« 250 000 FCFA ») ne doit jamais être coupé par sa carte
        const montantsCoupes = [...document.querySelectorAll('.sb-stat-value')].filter((el) => el.scrollWidth > el.clientWidth + 1 || el.getBoundingClientRect().right > el.closest('.card').getBoundingClientRect().right - 4).map((el) => el.textContent.trim());
        const canvases = [...document.querySelectorAll('canvas')].map((c) => {
            const px = c.getContext('2d').getImageData(0, 0, c.width, c.height).data;
            let dessines = 0; for (let i = 3; i < px.length; i += 4) { if (px[i] > 0) dessines++; }
            return { id: c.id, largeur: c.clientWidth, hauteur: c.clientHeight, pixelsDessines: dessines };
        });
        const burger = document.querySelector('[data-bs-toggle="offcanvas"]');
        const side = document.getElementById('sidebar');
        const rs = side ? side.getBoundingClientRect() : null;
        return {
            scrollWidth: de.scrollWidth, clientWidth: cw, debordement: de.scrollWidth > cw + 1, elementsQuiDebordent: hors, montantsCoupes, canvases,
            burgerVisible: !!burger && getComputedStyle(burger).display !== 'none',
            menuVisible: !!rs && rs.right > 0 && rs.left >= 0 && rs.width > 0,
            tableauxAvecDefilementInterne: [...document.querySelectorAll('.table-responsive')].filter((t) => t.scrollWidth > t.clientWidth + 1).length,
            tableauxHorsConteneur: [...document.querySelectorAll('table')].filter((t) => !t.closest('.table-responsive')).length,
            titre: document.title
        };
    })()`;

    const connecter = async (email, motDePasse) => {
        await aller('/login', 300);
        evenements = [];
        await js(`document.querySelector('#email').value=${JSON.stringify(email)}; document.querySelector('#password').value=${JSON.stringify(motDePasse)}; document.querySelector('form').submit(); 1`);
        await attendreChargement();
        await sleep(800);
        return js('location.pathname');
    };

    const rapport = [];
    const verifierPage = async (url, tailles, nomCapture) => {
        for (const [largeur, hauteur, mobile] of tailles) {
            await viewport(largeur, hauteur, mobile);
            await aller(url);
            const m = await js(mesures);
            const p = problemes();
            if (m.montantsCoupes && m.montantsCoupes.length) { p.push('montant coupé : ' + m.montantsCoupes.join(', ')); }
            rapport.push({ url, largeur, ...m, problemes: p });
            if (nomCapture && (largeur === 360 || largeur === 1280)) await capture(nomCapture + '_' + largeur, largeur, mobile);
        }
    };

    const TAILLES = [[360, 800, true], [768, 1024, false], [1920, 1080, false]];
    const interactions = [];

    // ---------- Compte utilisateur ----------
    interactions.push('connexion awa -> ' + await connecter('awa@smartbudget.sn', 'Password123'));
    for (const url of ['/dashboard', '/statistiques', '/historique', '/depenses', '/depenses/create', '/revenus', '/categories', '/profil']) {
        await verifierPage(url, TAILLES, url === '/dashboard' ? 'dashboard' : url === '/categories' ? 'categories' : url === '/historique' ? 'historique' : url === '/statistiques' ? 'statistiques' : null);
    }
    await verifierPage('/statistiques?periode=annee&annee=2026', [[360, 800, true], [1920, 1080, false]], null);

    // Menu burger (mobile) et menu fixe (ordinateur)
    await viewport(360, 800, true);
    await aller('/dashboard', 300);
    interactions.push('mobile 360px : menu replié au départ = ' + !(await js(mesures)).menuVisible);
    await js(`document.querySelector('[data-bs-toggle="offcanvas"]').click(); 1`);
    await sleep(700);
    interactions.push('mobile 360px : menu visible après clic sur le burger = ' + (await js(mesures)).menuVisible);
    await capture('menu_burger_ouvert_360', 360, true);
    await viewport(1920, 1080, false);
    await aller('/dashboard', 300);
    const bureau = await js(mesures);
    interactions.push('ordinateur 1920px : menu latéral fixe visible = ' + bureau.menuVisible + ' ; burger masqué = ' + !bureau.burgerVisible);

    // Barre latérale fixe : elle reste en place au défilement, se déplie, mémorise son état et ne fait jamais déborder la page
    const etatRail = `(() => {
        const r = document.querySelector('.sb-rail'); if (!r) { return null; }
        const b = r.getBoundingClientRect(); const lab = r.querySelector('.sb-rail-label'); const bascule = r.querySelector('[data-rail-toggle]');
        const barre = document.querySelector('.sb-topbar').getBoundingClientRect();
        return { position: getComputedStyle(r).position, haut: Math.round(b.top), bas: Math.round(window.innerHeight - b.bottom), largeur: Math.round(b.width),
                 libelleVisible: getComputedStyle(lab).display !== 'none', ouvert: document.documentElement.classList.contains('sb-rail-open'),
                 ariaExpanded: bascule.getAttribute('aria-expanded'), hauteurBarreDuHaut: Math.round(barre.height),
                 debordement: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1 };
    })()`;
    await js(`localStorage.removeItem('sb-rail'); 1`);
    await viewport(1280, 700, false);
    await aller('/historique', 300);
    const avantDefilement = await js(etatRail);
    await js(`window.scrollTo(0, 900); 1`);
    await sleep(400);
    const apresDefilement = await js(etatRail);
    interactions.push(`barre latérale 1280px : position ${avantDefilement.position}, ${avantDefilement.haut}px du haut, ${avantDefilement.bas}px du bas, largeur ${avantDefilement.largeur}px (repliée, aria-expanded = ${avantDefilement.ariaExpanded})`);
    interactions.push(`après un défilement de 900px la barre n'a pas bougé = ${apresDefilement.haut === avantDefilement.haut && apresDefilement.bas === avantDefilement.bas}`);
    await js(`document.querySelector('[data-rail-toggle]').click(); 1`);
    await sleep(600);
    const deplie = await js(etatRail);
    interactions.push(`après clic sur le bouton : dépliée = ${deplie.ouvert}, largeur ${deplie.largeur}px, libellés visibles = ${deplie.libelleVisible}, aria-expanded = ${deplie.ariaExpanded}`);
    await js(`window.scrollTo(0, 0); 1`);
    await capture('barre_depliee_1280', 1280, false);
    await aller('/dashboard', 300);
    const memorise = await js(etatRail);
    interactions.push(`état mémorisé après rechargement = ${memorise.ouvert} (largeur ${memorise.largeur}px, libellés visibles = ${memorise.libelleVisible})`);
    const debordements = [];
    for (const [largeur, ouvert] of [[1024, false], [1024, true], [1100, true], [1280, false], [1280, true], [1366, true], [1920, true]]) {
        await js(`localStorage.setItem('sb-rail', '${ouvert ? 'ouvert' : 'ferme'}'); 1`);
        await viewport(largeur, 800, false);
        for (const url of ['/dashboard', '/historique', '/statistiques', '/depenses', '/categories']) {
            await aller(url, 300);
            const m = await js(etatRail);
            const t = await js(`[...document.querySelectorAll('.table-responsive')].filter((x) => x.scrollWidth > x.clientWidth + 1).length`);
            if (m.debordement || t > 0 || m.hauteurBarreDuHaut > 90) { debordements.push(`${largeur}px ${ouvert ? 'dépliée' : 'repliée'} ${url} (page ${m.debordement ? 'déborde' : 'ok'}, tableaux ${t}, barre du haut ${m.hauteurBarreDuHaut}px)`); }
        }
    }
    interactions.push(`largeurs intermédiaires 1024 à 1920px, barre repliée et dépliée, 5 pages : ${debordements.length === 0 ? 'aucun débordement, barre du haut sur une seule ligne' : 'PROBLEMES : ' + debordements.join(' ; ')}`);
    await js(`localStorage.removeItem('sb-rail'); 1`);

    // Fenêtre modale d'ajout de catégorie et fenêtre de confirmation de suppression
    await viewport(360, 800, true);
    await aller('/categories', 300);
    await js(`document.querySelector('[data-categorie-ajouter]').click(); 1`);
    await sleep(700);
    interactions.push('modale « Ajouter une catégorie » ouverte = ' + await js(`document.getElementById('categorieModal').classList.contains('show')`));
    await capture('modale_categorie_360', 360, true);
    await aller('/depenses', 300);
    await js(`document.querySelector('form[data-confirm] button').click(); 1`);
    await sleep(700);
    interactions.push('modale de confirmation de suppression ouverte = ' + await js(`document.getElementById('confirmModal').classList.contains('show')`)
        + ' ; message = « ' + await js(`document.querySelector('#confirmModal [data-confirm-message]').textContent`) + ' »');
    await js(`document.querySelector('#confirmModal .btn-secondary').click(); 1`);
    await sleep(600);
    interactions.push('annuler referme la modale et ne supprime rien = ' + !(await js(`document.getElementById('confirmModal').classList.contains('show')`)));

    // Déconnexion
    await viewport(1280, 900, false);
    await aller('/dashboard', 300);
    await capture('dashboard_1280', 1280, false);
    evenements = [];
    await js(`document.querySelector('form[action$="/logout"]').submit(); 1`);
    await attendreChargement(); await sleep(600);
    interactions.push('déconnexion -> ' + await js('location.pathname'));

    // ---------- Compte administrateur ----------
    interactions.push('connexion admin -> ' + await connecter('admin@smartbudget.sn', 'Admin1234'));
    for (const url of ['/admin', '/admin/recherche?q=a', '/admin/utilisateurs', '/admin/utilisateurs/2', '/admin/utilisateurs/2/edit']) {
        await verifierPage(url, TAILLES, url === '/admin/utilisateurs' ? 'admin_liste' : (url === '/admin' ? 'admin_dashboard' : null));
    }

    // Administration, barre latérale dépliée, écrans moyens : aucun débordement
    const debordementsAdmin = [];
    for (const largeur of [1024, 1100, 1280, 1366]) {
        await js(`localStorage.setItem('sb-rail', 'ouvert'); 1`);
        await viewport(largeur, 800, false);
        for (const url of ['/admin', '/admin/recherche?q=a', '/admin/utilisateurs', '/admin/utilisateurs/2']) {
            await aller(url, 300);
            const m = await js(etatRail);
            const t = await js(`[...document.querySelectorAll('.table-responsive')].filter((x) => x.scrollWidth > x.clientWidth + 1).length`);
            if (m.debordement || t > 0 || m.hauteurBarreDuHaut > 90) { debordementsAdmin.push(`${largeur}px ${url} (page ${m.debordement ? 'déborde' : 'ok'}, tableaux ${t}, barre du haut ${m.hauteurBarreDuHaut}px)`); }
        }
    }
    interactions.push(`administration, barre dépliée, 1024 à 1366px : ${debordementsAdmin.length === 0 ? 'aucun débordement' : 'PROBLEMES : ' + debordementsAdmin.join(' ; ')}`);
    await js(`localStorage.removeItem('sb-rail'); 1`);

    // ---------- Pages d'erreur ----------
    await viewport(360, 800, true);
    await aller('/depenses', 300);
    interactions.push('admin sur /depenses -> titre « ' + await js('document.title') + ' »');
    await capture('erreur_403_360', 360, true);

    fs.writeFileSync(path.join(OUT, 'rapport.json'), JSON.stringify({ rapport, interactions }, null, 2));
    ws.close();
    edge.kill();
    console.log('OK');
    process.exit(0);
})().catch((e) => { console.error('ERREUR SCRIPT :', e.message); process.exit(1); });
