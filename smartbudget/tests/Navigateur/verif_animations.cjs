// Vérification des ANIMATIONS dans Microsoft Edge (mode sans fenêtre) : aperçu animé de la page de connexion et barre de recherche extensible.
// Usage : node tests/Navigateur/verif_animations.cjs <dossier de sortie>
// Prérequis : base remplie (php artisan migrate:fresh --seed), serveur lancé (php artisan serve --port=8765) et Microsoft Edge installé.
// Variables facultatives : SB_BASE (adresse du serveur), SB_EDGE (chemin de msedge.exe).
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
        const canvases = [...document.querySelectorAll('canvas')].map((c) => {
            const px = c.getContext('2d').getImageData(0, 0, c.width, c.height).data;
            let dessines = 0; for (let i = 3; i < px.length; i += 4) { if (px[i] > 0) dessines++; }
            return { id: c.id, largeur: c.clientWidth, hauteur: c.clientHeight, pixelsDessines: dessines };
        });
        const burger = document.querySelector('[data-bs-toggle="offcanvas"]');
        const side = document.getElementById('sidebar');
        const rs = side ? side.getBoundingClientRect() : null;
        return {
            scrollWidth: de.scrollWidth, clientWidth: cw, debordement: de.scrollWidth > cw + 1, elementsQuiDebordent: hors, canvases,
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

        // ======================================================================================================
    // Vérification des ANIMATIONS : aperçu animé de la page de connexion et barre de recherche extensible
    // ======================================================================================================
    const { execSync } = require('child_process');
    const racineProjet = path.join(__dirname, '..', '..');
    const viderLimiteur = () => { try { execSync('php artisan cache:clear', { cwd: racineProjet, stdio: 'ignore' }); } catch (e) { /* sans importance */ } };
    const resultats = [];
    const note = (nom, ok, detail) => resultats.push({ nom, ok: !!ok, detail: detail === undefined ? '' : String(detail) });
    const touche = async (key, code, vk, text) => {
        await send('Input.dispatchKeyEvent', { type: 'keyDown', key, code, windowsVirtualKeyCode: vk, text });
        await send('Input.dispatchKeyEvent', { type: 'keyUp', key, code, windowsVirtualKeyCode: vk });
    };
    const souris = (x, y) => send('Input.dispatchMouseEvent', { type: 'mouseMoved', x, y });
    const saisir = (texte) => send('Input.insertText', { text: texte });
    const etatPig = `(() => {
        const p = document.querySelector('[data-mascotte]'); if (!p) { return null; }
        const flou = p.querySelector('.sb-v'), trace = p.querySelector('.sb-ap-trait'), b2 = p.querySelector('.sb-bb2');
        const dy = (el) => { const m = getComputedStyle(el).transform; return m === 'none' ? 0 : new DOMMatrix(m).f; };
        const ech = (el) => { const m = getComputedStyle(el).transform; return m === 'none' ? 1 : new DOMMatrix(m).d; };
        return { visible: p.getBoundingClientRect().width > 0, classes: p.getAttribute('class'), px: p.style.getPropertyValue('--px'), py: p.style.getPropertyValue('--py'),
                 flou: getComputedStyle(flou).filter, contour: Number(parseFloat(getComputedStyle(trace).strokeDashoffset).toFixed(2)), barre2: Number(ech(b2).toFixed(2)), cartes: p.querySelectorAll('.sb-ap-k').length };
    })()`;

    // ---------- 1. Page de connexion, ordinateur ----------
    await viewport(1280, 800, false);
    await aller('/login', 3300);
    let e0 = await js(etatPig);
    note('l\'aperçu animé est visible sur la page de connexion (5 cartes)', e0 && e0.visible && e0.cartes === 5, e0 && e0.classes);
    note('introduction terminée : courbe tracée, barres montées, montants nets', e0.contour === 0 && e0.barre2 === 1 && e0.flou === 'none', JSON.stringify(e0));
    note('page de connexion : pas de débordement', !(await js(`document.documentElement.scrollWidth > document.documentElement.clientWidth + 1`)));
    await capture('apercu_connexion_1280', 1280, false);

    await souris(60, 60);
    await sleep(350);
    const aGauche = await js(etatPig);
    await souris(1220, 760);
    await sleep(350);
    const aDroite = await js(etatPig);
    note('les cartes suivent le curseur (profondeur)', aGauche.px !== aDroite.px || aGauche.py !== aDroite.py, `curseur en haut à gauche : (${aGauche.px}, ${aGauche.py}) ; en bas à droite : (${aDroite.px}, ${aDroite.py})`);
    const amplitude = Math.max(Math.abs(parseFloat(aGauche.px)), Math.abs(parseFloat(aGauche.py)), Math.abs(parseFloat(aDroite.px)), Math.abs(parseFloat(aDroite.py)));
    note('le décalage des cartes reste limité (7 px au plus avant pondération)', amplitude <= 7.1, `amplitude maximale ${amplitude}`);

    await js(`document.querySelector('#email').focus(); 1`);
    await sleep(300);
    const surEmail = await js(etatPig);
    note('montants nets quand on tape l\'e-mail', surEmail.flou === 'none', `filtre ${surEmail.flou}`);

    await js(`document.querySelector('#password').focus(); 1`);
    await sleep(700);
    const surMdp = await js(etatPig);
    note('les montants se FLOUTENT quand on tape le mot de passe', /blur/.test(surMdp.flou) && /is-closed/.test(surMdp.classes), `filtre ${surMdp.flou}, classes « ${surMdp.classes} »`);
    await capture('apercu_mot_de_passe_1280', 1280, false);

    await js(`document.querySelector('[data-afficher-mdp]').click(); 1`);
    await sleep(700);
    const apresAffichage = await js(`({ type: document.querySelector('#password').type, pressed: document.querySelector('[data-afficher-mdp]').getAttribute('aria-pressed'), label: document.querySelector('[data-afficher-mdp]').getAttribute('aria-label') })`);
    const peek = await js(etatPig);
    note('« afficher » rend le mot de passe lisible', apresAffichage.type === 'text' && apresAffichage.pressed === 'true' && /Masquer/.test(apresAffichage.label), JSON.stringify(apresAffichage));
    note('les montants réapparaissent quand le mot de passe est visible', peek.flou === 'none' && /is-peek/.test(peek.classes), `filtre ${peek.flou}`);
    await capture('apercu_mot_de_passe_visible_1280', 1280, false);

    await js(`document.querySelector('[data-afficher-mdp]').click(); 1`);
    await sleep(300);
    note('« masquer » remet le champ en mode mot de passe', (await js(`document.querySelector('#password').type`)) === 'password');

    await js(`document.querySelector('#email').focus(); 1`);
    await sleep(2000);
    const reposee = await js(etatPig);
    note('les montants restent nets quand on quitte le mot de passe', reposee.flou === 'none' && !/is-closed|is-peek/.test(reposee.classes), `filtre ${reposee.flou}`);

    // ---------- 2. Mauvais identifiants : les cartes tremblent ----------
    await js(`document.querySelector('#email').value = 'awa@smartbudget.sn'; document.querySelector('#password').value = 'mauvais-mot-de-passe'; 1`);
    viderLimiteur();
    evenements = [];
    const t0 = Date.now();
    await js(`document.querySelector('[data-connexion] [type=submit]').click(); 1`);
    await sleep(120);
    const pendant = await js(etatPig);
    note('à l\'envoi, les cartes s\'envolent', pendant && /is-drop/.test(pendant.classes), pendant && pendant.classes);
    await attendreChargement();
    const dureeErreur = Date.now() - t0;
    await sleep(2200);
    const apresErreur = await js(`({ chemin: location.pathname, message: !!document.querySelector('.invalid-feedback'), pig: document.querySelector('[data-mascotte]').getAttribute('class'),
        animation: getComputedStyle(document.querySelector('.sb-ap-k')).animationName, arc: getComputedStyle(document.querySelector('.sb-arc-bleu')).stroke })`);
    note('identifiants faux : retour sur /login avec le message', apresErreur.chemin === '/login' && apresErreur.message, JSON.stringify(apresErreur));
    note('identifiants faux : les cartes tremblent (« sb-secoue-carte ») et l\'arc bleu passe au rouge', /is-error/.test(apresErreur.pig) && apresErreur.animation === 'sb-secoue-carte' && /224, 75, 90/.test(apresErreur.arc), JSON.stringify(apresErreur));
    note('l\'envoi n\'est retardé que d\'une demi-seconde environ', dureeErreur >= 450 && dureeErreur < 3000, `${dureeErreur} ms entre le clic et la page d'erreur`);
    await capture('apercu_erreur_1280', 1280, false);

    // ---------- 3. Connexion réussie : les cartes s'envolent puis on arrive sur le tableau de bord ----------
    await js(`document.querySelector('#email').value = 'awa@smartbudget.sn'; document.querySelector('#password').value = 'Password123'; 1`);
    viderLimiteur();
    evenements = [];
    const t1 = Date.now();
    await js(`document.querySelector('[data-connexion] [type=submit]').click(); 1`);
    await attendreChargement();
    await sleep(500);
    note('connexion réussie malgré l\'animation', (await js('location.pathname')) === '/dashboard', `${Date.now() - t1} ms`);

    // ---------- 4. « Réduire les animations » : aucun délai, aucun mouvement ----------
    await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    await js(`document.querySelector('form[action$="/logout"]').submit(); 1`);
    await attendreChargement(); await sleep(500);
    await aller('/login', 1000);
    const reduit = await js(`({ matche: matchMedia('(prefers-reduced-motion: reduce)').matches, animation: getComputedStyle(document.querySelector('[data-mascotte]')).animationName,
        paupiere: getComputedStyle(document.querySelector('.sb-ap-k')).animationName })`);
    note('mouvement réduit : plus aucune animation sur l\'aperçu', reduit.matche && (reduit.animation === 'none' || reduit.animation === '') && (reduit.paupiere === 'none' || reduit.paupiere === ''), JSON.stringify(reduit));
    await js(`document.querySelector('#email').value = 'awa@smartbudget.sn'; document.querySelector('#password').value = 'mauvais'; 1`);
    viderLimiteur();
    evenements = [];
    const t2 = Date.now();
    await js(`document.querySelector('[data-connexion] [type=submit]').click(); 1`);
    await attendreChargement();
    const dureeReduite = Date.now() - t2;
    note('mouvement réduit : le formulaire part sans délai d\'animation', dureeReduite < dureeErreur - 200, `${dureeReduite} ms au lieu de ${dureeErreur} ms`);
    await send('Emulation.setEmulatedMedia', { features: [] });

    // ---------- 5. Connexion sur téléphone : pas d\'aperçu animé, pas de débordement, bouton « afficher » utilisable ----------
    await viewport(360, 800, true);
    await aller('/login', 800);
    note('téléphone : le panneau bleu (et l\'aperçu animé) est masqué', (await js(etatPig)).visible === false);
    note('téléphone : pas de débordement', !(await js(`document.documentElement.scrollWidth > document.documentElement.clientWidth + 1`)));
    await js(`document.querySelector('[data-afficher-mdp]').click(); 1`);
    note('téléphone : le bouton « afficher » fonctionne', (await js(`document.querySelector('#password').type`)) === 'text');
    await capture('connexion_360', 360, true);

    // ---------- 6. Barre de recherche extensible (compte utilisateur) ----------
    await viewport(1280, 800, false);
    await js(`localStorage.removeItem('sb-rail'); 1`);
    await aller('/login', 400);
    viderLimiteur();
    await js(`document.querySelector('#email').value = 'awa@smartbudget.sn'; document.querySelector('#password').value = 'Password123'; document.querySelector('[data-connexion]').submit(); 1`);
    await attendreChargement(); await sleep(900);
    note("connexion de l'utilisateur pour tester la recherche", (await js('location.pathname')) === '/dashboard', await js('location.pathname + " - " + document.title'));

    const etatRecherche = `(() => {
        const f = document.querySelector('[data-recherche]'); const c = f.querySelector('.sb-search-champ'); const i = f.querySelector('.sb-search-input');
        const h = f.querySelector('.sb-search-hint'); const go = f.querySelector('.sb-search-go'); const k = f.querySelector('.sb-search-kbd');
        return { classes: f.getAttribute('class'), largeurChamp: Math.round(c.getBoundingClientRect().width), actif: document.activeElement === i, valeur: i.value,
                 aria: f.querySelector('[data-recherche-ouvrir]').getAttribute('aria-expanded'), hint: h.textContent, hintVisible: getComputedStyle(h).display !== 'none',
                 goVisible: getComputedStyle(go).display !== 'none', kbdVisible: getComputedStyle(k).display !== 'none', placeholder: i.getAttribute('placeholder'),
                 largeurPage: document.documentElement.scrollWidth, largeurFenetre: document.documentElement.clientWidth };
    })()`;
    await aller('/categories', 600);
    let r = await js(etatRecherche);
    note('recherche : repliée au départ (simple icône)', r.largeurChamp === 0 && r.aria === 'false' && !/is-open/.test(r.classes), `largeur du champ ${r.largeurChamp}px`);
    await capture('recherche_repliee_1280', 1280, false);

    await js(`document.querySelector('[data-recherche-ouvrir]').click(); 1`);
    await sleep(700);
    r = await js(etatRecherche);
    note('recherche : un clic l\'étend en champ', r.largeurChamp >= 300 && r.aria === 'true', `largeur ${r.largeurChamp}px`);
    note('recherche : le champ a le focus automatiquement', r.actif);
    note('recherche : le texte animé annonce « Rechercher une catégorie »', r.hintVisible && /Rechercher une catégorie/.test(r.hint) && r.placeholder === '', `texte « ${r.hint} »`);
    await capture('recherche_ouverte_1280', 1280, false);

    await sleep(3400);
    const apres = await js(etatRecherche);
    note('recherche : les suggestions défilent (noms de vos catégories)', /^Ex\. : /.test(apres.hint) && apres.hint !== r.hint, `texte « ${apres.hint} »`);

    await saisir('ali');
    await sleep(400);
    r = await js(etatRecherche);
    note('recherche : en tapant, le bouton de validation apparaît et les suggestions se retirent', r.goVisible && !r.hintVisible && /has-value/.test(r.classes), r.classes);
    await touche('Escape', 'Escape', 27);
    await sleep(400);
    r = await js(etatRecherche);
    note('recherche : Échap efface le texte et garde la barre ouverte', r.valeur === '' && r.largeurChamp >= 300 && !r.goVisible, `valeur « ${r.valeur} », largeur ${r.largeurChamp}px`);
    await touche('Escape', 'Escape', 27);
    await sleep(700);
    r = await js(etatRecherche);
    note('recherche : un second Échap la referme', r.largeurChamp === 0 && r.aria === 'false', `largeur ${r.largeurChamp}px`);

    await touche('/', 'Slash', 191, '/');
    await sleep(700);
    r = await js(etatRecherche);
    note('recherche : le raccourci « / » l\'ouvre et place le curseur dedans', r.largeurChamp >= 300 && r.actif && r.valeur === '', `largeur ${r.largeurChamp}px, valeur « ${r.valeur} »`);
    await js(`document.querySelector('h1').click(); 1`);
    await send('Input.dispatchMouseEvent', { type: 'mousePressed', x: 700, y: 400, button: 'left', clickCount: 1 });
    await send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: 700, y: 400, button: 'left', clickCount: 1 });
    await sleep(700);
    r = await js(etatRecherche);
    note('recherche : un clic ailleurs la referme quand elle est vide', r.largeurChamp === 0, `largeur ${r.largeurChamp}px`);

    await js(`document.querySelector('[data-recherche-ouvrir]').click(); 1`);
    await sleep(600);
    await saisir('trans');
    await touche('Enter', 'Enter', 13, '\r');
    await attendreChargement(); await sleep(900);
    const resultat = await js(`(() => { const r = (${etatRecherche}); return { ...r, url: location.search, lignes: [...document.querySelectorAll('.table tbody tr')].map(t => t.querySelector('td').textContent.trim()), lienEffacer: !!document.querySelector('.sb-search-clear') }; })()`);
    note('recherche : la touche Entrée lance la recherche (?q=trans)', resultat.url === '?q=trans' && resultat.lignes.length >= 1 && resultat.lignes.every((l) => /trans/i.test(l)), `${resultat.url}, lignes : ${resultat.lignes.join(', ')}`);
    note('recherche : après la recherche, la barre reste ouverte avec le texte et le bouton « effacer »', resultat.largeurChamp >= 300 && resultat.valeur === 'trans' && resultat.lienEffacer, `valeur « ${resultat.valeur} »`);
    await capture('recherche_resultat_1280', 1280, false);

    // ---------- 7. Barre de recherche : mouvement réduit, téléphone, administration ----------
    await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    await aller('/categories', 500);
    await js(`document.querySelector('[data-recherche-ouvrir]').click(); 1`);
    await sleep(500);
    const h1 = (await js(etatRecherche)).hint;
    await sleep(3400);
    const h2 = (await js(etatRecherche)).hint;
    note('mouvement réduit : les suggestions ne défilent pas', h1 === h2, `« ${h1} » puis « ${h2} »`);
    await send('Emulation.setEmulatedMedia', { features: [] });

    await viewport(360, 800, true);
    await aller('/categories', 600);
    await js(`document.querySelector('[data-recherche-ouvrir]').click(); 1`);
    await sleep(700);
    r = await js(etatRecherche);
    note('téléphone : la barre ouverte tient dans l\'écran (360 px)', r.largeurPage <= r.largeurFenetre + 1 && r.largeurChamp >= 150, `champ ${r.largeurChamp}px, page ${r.largeurPage}/${r.largeurFenetre}`);
    await capture('recherche_ouverte_360', 360, true);

    await viewport(1280, 800, false);
    await js(`document.querySelector('form[action$="/logout"]').submit(); 1`);
    await attendreChargement(); await sleep(500);
    await aller('/login', 400);
    viderLimiteur();
    await js(`document.querySelector('#email').value = 'admin@smartbudget.sn'; document.querySelector('#password').value = 'Admin1234'; document.querySelector('[data-connexion]').submit(); 1`);
    await attendreChargement(); await sleep(900);
    note("connexion de l'administrateur", (await js('location.pathname')) === '/admin/utilisateurs', await js('location.pathname + " - " + document.title'));
    await aller('/admin/utilisateurs', 600);
    await js(`document.querySelector('[data-recherche-ouvrir]').click(); 1`);
    await sleep(700);
    r = await js(etatRecherche);
    note('administration : la recherche d\'utilisateurs s\'étend aussi', r.largeurChamp >= 300 && /Rechercher un utilisateur/.test(r.hint), `texte « ${r.hint} »`);
    await sleep(3400);
    note('administration : suggestions « Chercher par nom / prénom / e-mail »', /^Chercher par /.test((await js(etatRecherche)).hint));
    await capture('recherche_admin_1280', 1280, false);

    fs.writeFileSync(path.join(OUT, 'rapport_animations.json'), JSON.stringify(resultats, null, 2));
    ws.close();
    edge.kill();
    const echecs = resultats.filter((x) => !x.ok);
    console.log(`${resultats.length - echecs.length}/${resultats.length} verifications reussies`);
    echecs.forEach((x) => console.log('ECHEC : ' + x.nom + ' -> ' + x.detail));
    process.exit(echecs.length ? 2 : 0);
})().catch((e) => { console.error('ERREUR SCRIPT :', e.message); process.exit(1); });
