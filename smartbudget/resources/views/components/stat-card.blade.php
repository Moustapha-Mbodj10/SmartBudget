@props(['titre', 'valeur', 'icone' => 'bi-graph-up', 'variante' => 'primary', 'detail' => null, 'hero' => false])

{{-- Carte de chiffre. « hero » : la grande carte verte du solde (rouge si le solde est négatif). --}}
<div class="card sb-stat-card sb-{{ $variante }} {{ $hero ? 'sb-hero' : '' }} h-100">
    <div class="card-body">
        <i class="bi {{ $icone }} sb-stat-icon"></i>
        <div>
            <div class="text-muted small">{{ $titre }}</div>
            <div {{ $attributes->class(['sb-stat-value', 'sb-amount']) }}>{{ $valeur }}</div>
            @if ($detail)
                <div class="small text-muted sb-amount">{{ $detail }}</div>
            @endif
        </div>
    </div>
</div>
