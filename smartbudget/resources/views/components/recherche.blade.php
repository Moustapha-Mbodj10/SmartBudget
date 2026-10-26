@props(['action', 'valeur' => '', 'libelle' => 'Rechercher', 'suggestions' => [], 'prefixe' => 'Ex. : '])

{{--
    Barre de recherche extensible : une icône qui s'étend en champ (inspirée des barres « expandable search »).
    Sans JavaScript, la barre reste ouverte et se comporte comme un simple formulaire de recherche (GET ?q=...).
    Avec JavaScript (public/js/animations.js) : ouverture animée, suggestions qui défilent dans le champ,
    raccourci « / », Échap pour effacer puis refermer.
--}}
<form method="GET" action="{{ $action }}" role="search" class="sb-search {{ $valeur !== '' ? 'is-open has-value' : '' }}" data-recherche>
    <button type="button" class="sb-search-toggle" data-recherche-ouvrir aria-label="Ouvrir la recherche" aria-expanded="{{ $valeur !== '' ? 'true' : 'false' }}">
        <i class="bi bi-search"></i>
    </button>

    <div class="sb-search-champ">
        <input type="search" name="q" value="{{ $valeur }}" class="form-control sb-search-input" autocomplete="off"
               placeholder="{{ $libelle }}" aria-label="{{ $libelle }}"
               data-suggestions="{{ json_encode(array_values($suggestions), JSON_UNESCAPED_UNICODE) }}" data-prefixe="{{ $prefixe }}">
        <span class="sb-search-hint" aria-hidden="true"></span>
        <kbd class="sb-search-kbd" aria-hidden="true">/</kbd>
    </div>

    <button type="submit" class="sb-search-go" aria-label="Rechercher"><i class="bi bi-arrow-right"></i></button>

    @if ($valeur !== '')
        <a href="{{ $action }}" class="sb-search-clear" title="Effacer la recherche" aria-label="Effacer la recherche"><i class="bi bi-x-lg"></i></a>
    @endif
</form>
