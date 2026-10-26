@props(['trait' => 17])
{{--
    Le logo de SmartBudget (portefeuille, pièce, barres et flèche de croissance), redessiné en SVG à partir du fichier
    public/img/logo-smartbudget.jpg. Blanc et menthe : à poser sur un fond bleu (.sb-logo). Décoratif : le nom est écrit à côté.
--}}
<svg viewBox="395 300 300 360" aria-hidden="true" focusable="false" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <path d="M470 380 H440 Q410 380 410 410 V535 Q410 560 432 578 L532 645 Q545 653 558 645 L655 583 Q680 565 680 535 V450 Q680 415 645 415 H612" stroke="#fff" stroke-width="{{ $trait }}"/>
    <path d="M472 424 A68 68 0 1 1 608 424" stroke="#fff" stroke-width="{{ $trait }}"/>
    <path d="M494 410 A46 46 0 1 1 586 410 Z" fill="#52eba8" stroke="none"/>
    <text x="540" y="384" text-anchor="middle" font-size="58" font-weight="800" fill="#054086" stroke="none" font-family="Urbanist, system-ui, sans-serif">$</text>
    <rect x="605" y="478" width="85" height="62" rx="15" fill="#fff" stroke="none"/>
    <circle cx="640" cy="509" r="12" fill="#054086" stroke="none"/>
    <rect x="458" y="558" width="30" height="42" rx="5" fill="#fff" stroke="none"/>
    <rect x="503" y="534" width="30" height="66" rx="5" fill="#fff" stroke="none"/>
    <rect x="548" y="510" width="30" height="90" rx="5" fill="#fff" stroke="none"/>
    <path d="M457 527 Q525 514 572 472" stroke="#52eba8" stroke-width="{{ $trait - 3 }}"/>
    <path d="M556 466 L581 461 L576 487" stroke="#52eba8" stroke-width="{{ $trait - 3 }}"/>
</svg>
