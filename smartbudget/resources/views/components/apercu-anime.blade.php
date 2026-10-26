@props(['erreur' => false])
{{--
    Aperçu animé de l'application pour les pages de connexion et d'inscription : des cartes d'interface (solde, répartition,
    croissance, revenus et dépenses) qui entrent en cascade puis flottent, dans l'esprit du shot « Promo Landing Page for a
    Personal Finance Platform » (Outcrowd, Dribbble). Les chiffres sont des exemples. Tout est décoratif : masqué aux lecteurs d'écran.
    public/js/animations.js lui donne vie : léger effet de profondeur avec le curseur, les montants se floutent quand on tape le
    mot de passe (confidentialité) et réapparaissent si on l'affiche, les cartes tremblent si les identifiants sont faux
    et s'envolent à l'envoi du formulaire.
--}}
<div class="sb-ap {{ $erreur ? 'is-error' : '' }}" aria-hidden="true" data-mascotte data-oeil>
    <div class="sb-ap-scene">

        <div class="sb-ap-c sb-c1"><div class="sb-ap-f"><div class="sb-ap-k sb-ap-k-solde">
            <div class="sb-ap-ligne"><span class="sb-ap-etiquette">Solde du mois</span><span class="sb-ap-puce sb-ap-puce-vert">+ 12 %</span></div>
            <div class="sb-ap-grand sb-v">87 000 <small>FCFA</small></div>
            <svg class="sb-ap-courbe" viewBox="0 0 100 30" preserveAspectRatio="none" focusable="false">
                <path class="sb-ap-aire" d="M0 26 C12 24 18 14 30 16 S50 22 62 12 S84 6 100 2 V30 H0 Z"/>
                <path class="sb-ap-trait" pathLength="1" d="M0 26 C12 24 18 14 30 16 S50 22 62 12 S84 6 100 2"/>
            </svg>
        </div></div></div>

        <div class="sb-ap-c sb-c2"><div class="sb-ap-f"><div class="sb-ap-k sb-ap-k-jauge">
            <div class="sb-ap-etiquette">Ce mois-ci</div>
            <div class="sb-ap-anneau">
                <svg viewBox="0 0 120 120" focusable="false">
                    <circle class="sb-arc sb-arc-fond" cx="60" cy="60" r="46"/>
                    <circle class="sb-arc sb-arc-rouge" cx="60" cy="60" r="46" pathLength="100"/>
                    <circle class="sb-arc sb-arc-vert" cx="60" cy="60" r="46" pathLength="100"/>
                    <circle class="sb-arc sb-arc-bleu" cx="60" cy="60" r="46" pathLength="100"/>
                </svg>
                <div class="sb-ap-centre"><strong class="sb-v">164 000</strong><span>FCFA disponibles</span></div>
            </div>
            <div class="sb-ap-legende"><span><i class="sb-pt-rouge"></i>Dépenses</span><span><i class="sb-pt-vert"></i>Revenus</span><span><i class="sb-pt-bleu"></i>Reste</span></div>
        </div></div></div>

        <div class="sb-ap-c sb-c3"><div class="sb-ap-f"><div class="sb-ap-k sb-ap-k-croissance">
            <div class="sb-ap-etiquette">Croissance du solde</div>
            <div class="sb-ap-grand sb-ap-grand-sm">+ 18 %</div>
            <div class="sb-ap-barres"><span class="sb-ap-barre sb-bb1"></span><span class="sb-ap-barre sb-bb2"></span></div>
        </div></div></div>

        <div class="sb-ap-c sb-c4"><div class="sb-ap-f"><div class="sb-ap-k sb-ap-k-flux">
            <div class="sb-ap-flux"><span class="sb-ap-fleche sb-ap-fleche-vert">↗</span><span><em>Revenus</em><b class="sb-v">250 000 FCFA</b></span></div>
            <div class="sb-ap-flux"><span class="sb-ap-fleche sb-ap-fleche-rouge">↘</span><span><em>Dépenses</em><b class="sb-v">163 000 FCFA</b></span></div>
        </div></div></div>

        <div class="sb-ap-c sb-c5"><div class="sb-ap-f"><div class="sb-ap-k sb-ap-k-toast">
            <span class="sb-ap-coche">✓</span><span>Revenu ajouté <b class="sb-v">+ 250 000 FCFA</b></span>
        </div></div></div>

    </div>
</div>
