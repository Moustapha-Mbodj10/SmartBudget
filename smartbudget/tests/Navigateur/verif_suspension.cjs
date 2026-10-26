// Vérification dans Edge : l'administrateur suspend un utilisateur EN LIGNE.
//  - sur le tableau de bord de l'administrateur, l'utilisateur passe aussitôt « Déconnecté » ;
//  - l'utilisateur, qui ne touche à rien, est renvoyé vers la connexion en quelques secondes (battement de session).
// Deux navigateurs distincts (deux profils) : un pour l'utilisateur, un pour l'administrateur.
// Usage : node tests/Navigateur/verif_suspension.cjs   (serveur sur SB_BASE, base remplie : php artisan migrate:fresh --seed)
const { spawn } = require('child_process'); const fs = require('fs'); const os = require('os'); const path = require('path');
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const BASE = process.env.SB_BASE || 'http://127.0.0.1:8765';
const EDGE = process.env.SB_EDGE || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';

async function ouvrir(port) {
    const profil = fs.mkdtempSync(path.join(os.tmpdir(), 'edge-sb-'));
    const edge = spawn(EDGE, ['--headless=new', '--disable-gpu', '--user-data-dir=' + profil, '--remote-debugging-port=' + port, 'about:blank'], { stdio: 'ignore' });
    let cibles; for (let i = 0; i < 80; i++) { try { cibles = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json(); if (cibles.some((t) => t.type === 'page')) break; } catch (e) { } await sleep(250); }
    const ws = new WebSocket(cibles.find((t) => t.type === 'page').webSocketDebuggerUrl); await new Promise((r) => ws.addEventListener('open', r));
    let n = 0; const pend = {}; ws.addEventListener('message', (m) => { const d = JSON.parse(m.data); if (d.id && pend[d.id]) { pend[d.id](d.result); delete pend[d.id]; } });
    const send = (method, params = {}) => new Promise((r) => { const id = ++n; pend[id] = r; ws.send(JSON.stringify({ id, method, params })); });
    const js = async (e) => (await send('Runtime.evaluate', { expression: e, returnByValue: true })).result.value;
    await send('Page.enable'); await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 800, deviceScaleFactor: 1, mobile: false });
    const aller = async (url, pause = 2500) => { await send('Page.navigate', { url: BASE + url }); await sleep(pause); return js('location.pathname'); };
    const connecter = async (email, mdp) => { await aller('/login'); await js(`document.querySelector('#email').value=${JSON.stringify(email)};document.querySelector('#password').value=${JSON.stringify(mdp)};document.querySelector('form').submit();1`); await sleep(5000); return js('location.pathname'); };
    return { edge, js, aller, connecter };
}

(async () => {
    const res = []; const note = (nom, ok, detail) => res.push({ nom, ok: !!ok, detail });
    const utilisateur = await ouvrir(9601);
    const admin = await ouvrir(9602);

    note('1. l\'utilisateur se connecte', (await utilisateur.connecter('awa@smartbudget.sn', 'Password123')) === '/dashboard');
    note('2. l\'administrateur se connecte (autre navigateur)', (await admin.connecter('admin@smartbudget.sn', 'Admin1234')) === '/admin');

    await admin.aller('/admin');
    const avant = await admin.js(`(() => { const l = [...document.querySelectorAll('tbody tr')].find((t) => /awa@smartbudget\\.sn/.test(t.innerText)); return l ? l.innerText.replace(/\\s+/g, ' ') : null; })()`);
    note('3. sur le tableau de bord, Awa est « En ligne »', /En ligne/.test(avant || ''), avant);

    // Suspension depuis la liste des utilisateurs (le vrai bouton de l'interface)
    await admin.aller('/admin/utilisateurs?q=awa@smartbudget.sn');
    const heure = Date.now();
    await admin.js(`document.querySelector('form[action*="/suspendre"]').submit(); 1`);
    await sleep(3000);
    await admin.aller('/admin');
    const apres = await admin.js(`(() => { const l = [...document.querySelectorAll('tbody tr')].find((t) => /awa@smartbudget\\.sn/.test(t.innerText)); return l ? l.innerText.replace(/\\s+/g, ' ') : null; })()`);
    note('4. aussitôt après la suspension, Awa est « Déconnecté » sur le tableau de bord', /Déconnecté/.test(apres || '') && /Suspendu/.test(apres || '') && !/En ligne/.test(apres || ''), apres);

    // L'utilisateur ne touche à rien : sa page doit le renvoyer vers la connexion
    let chemin = await utilisateur.js('location.pathname'); let secondes = null;
    for (let i = 0; i < 40 && chemin === '/dashboard'; i++) { await sleep(1000); chemin = await utilisateur.js('location.pathname'); }
    secondes = Math.round((Date.now() - heure) / 1000);
    const texte = await utilisateur.js('document.body.innerText');
    note('5. sans rien faire, l\'utilisateur est renvoyé vers la connexion', chemin === '/login', `${chemin} après ${secondes} s depuis la suspension`);
    note('6. il voit pourquoi', /suspendu/i.test(texte || ''), (texte || '').replace(/\s+/g, ' ').slice(0, 120));
    note('7. il ne peut plus se reconnecter', (await utilisateur.connecter('awa@smartbudget.sn', 'Password123')) === '/login' && /suspendu/i.test(await utilisateur.js('document.body.innerText')));

    for (const r of res) console.log((r.ok ? 'OK     ' : 'ECHEC  ') + r.nom + (r.detail ? ' -> ' + r.detail : ''));
    console.log(res.filter((r) => r.ok).length + '/' + res.length);
    utilisateur.edge.kill(); admin.edge.kill(); process.exit(0);
})();
