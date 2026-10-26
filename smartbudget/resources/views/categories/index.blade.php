@extends('layouts.app')
@section('titre', 'Catégories')

@section('actions')
    <button type="button" class="btn btn-primary" data-categorie-ajouter>
        <i class="bi bi-plus-lg me-1"></i>Ajouter une catégorie
    </button>
@endsection

@section('contenu')
    <div class="mb-3 mb-xl-4">
        {{-- Les suggestions qui défilent dans le champ sont les noms de vos propres catégories --}}
        <x-recherche :action="route('categories.index')" :valeur="$recherche" libelle="Rechercher une catégorie"
                     :suggestions="$depenses->pluck('nom')->merge($revenus->pluck('nom'))->take(6)->all()" />
    </div>

    <div class="row g-3 g-xl-4">
        @foreach ([
            ['titre' => 'Catégories de dépenses', 'liste' => $depenses, 'type' => 'depense', 'couleur' => 'danger', 'icone' => 'bi-cash-stack'],
            ['titre' => 'Catégories de revenus', 'liste' => $revenus, 'type' => 'revenu', 'couleur' => 'success', 'icone' => 'bi-wallet2'],
        ] as $bloc)
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <div class="sb-card-title">{{ $bloc['titre'] }}</div>
                            <div class="sb-card-sub">{{ $bloc['liste']->count() }} catégorie{{ $bloc['liste']->count() > 1 ? 's' : '' }}</div>
                        </div>
                        <span class="sb-stat-icon {{ $bloc['couleur'] === 'danger' ? 'sb-icon-dep' : 'sb-icon-rev' }}" style="width: 44px; height: 44px; border-radius: 15px"><i class="bi {{ $bloc['icone'] }}"></i></span>
                    </div>

                    @if ($bloc['liste']->isEmpty())
                        <div class="sb-empty py-4">
                            {{ $recherche !== '' ? 'Aucune catégorie trouvée.' : 'Aucune catégorie. Ajoutez-en une avec le bouton ci-dessus.' }}
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 sb-table-compact">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nom</th>
                                        <th class="d-none d-md-table-cell">Description</th>
                                        <th class="text-center">Transactions</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($bloc['liste'] as $categorie)
                                        @php($utilisations = $categorie->depenses_count + $categorie->revenus_count)
                                        <tr>
                                            <td class="fw-medium">{{ $categorie->nom }}</td>
                                            <td class="d-none d-md-table-cell text-muted">{{ $categorie->description }}</td>
                                            <td class="text-center">{{ $utilisations }}</td>
                                            <td class="text-end text-nowrap">
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                        data-categorie-modifier
                                                        data-id="{{ $categorie->id }}"
                                                        data-nom="{{ $categorie->nom }}"
                                                        data-description="{{ $categorie->description }}"
                                                        data-type="{{ $categorie->type }}"
                                                        aria-label="Modifier {{ $categorie->nom }}">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" action="{{ route('categories.destroy', $categorie) }}" class="d-inline"
                                                      data-confirm="Supprimer la catégorie « {{ $categorie->nom }} » ?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer {{ $categorie->nom }}">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Fenêtre modale unique : ajout et modification --}}
    <div class="modal fade" id="categorieModal" tabindex="-1" aria-labelledby="categorieModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('categories.store') }}" class="modal-content" id="categorieForm" novalidate>
                @csrf
                <input type="hidden" name="_method" value="POST" id="categorieMethod" disabled>
                <input type="hidden" name="_form" value="create" id="categorieMode">

                <div class="modal-header">
                    <h5 class="modal-title" id="categorieModalLabel">Ajouter une catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3" id="categorieTypeGroup">
                        <label for="categorieType" class="form-label">Type</label>
                        <select name="type" id="categorieType" class="form-select @error('type') is-invalid @enderror">
                            <option value="depense" @selected(old('type', 'depense') === 'depense')>Dépense</option>
                            <option value="revenu" @selected(old('type') === 'revenu')>Revenu</option>
                        </select>
                        @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="categorieNom" class="form-label">Nom</label>
                        <input type="text" name="nom" id="categorieNom" maxlength="100" value="{{ old('nom') }}"
                               class="form-control @error('nom') is-invalid @enderror" required>
                        @error('nom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="categorieDescription" class="form-label">Description <small class="text-muted">(facultative)</small></label>
                        <input type="text" name="description" id="categorieDescription" maxlength="255" value="{{ old('description') }}"
                               class="form-control @error('description') is-invalid @enderror">
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('categorieModal');
    var modal = new bootstrap.Modal(modalEl);
    var form = document.getElementById('categorieForm');
    var baseUrl = @json(url('categories'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    var storeUrl = @json(route('categories.store'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    function ouvrir(options) {
        var edition = options.mode !== 'create';
        form.action = edition ? baseUrl + '/' + options.id : storeUrl;
        document.getElementById('categorieMethod').value = 'PUT';
        document.getElementById('categorieMethod').disabled = !edition;
        document.getElementById('categorieMode').value = edition ? 'edit:' + options.id : 'create';
        document.getElementById('categorieModalLabel').textContent = edition ? 'Modifier la catégorie' : 'Ajouter une catégorie';
        // Le type se choisit à la création et ne se modifie plus ensuite
        document.getElementById('categorieTypeGroup').classList.toggle('d-none', edition);
        document.getElementById('categorieType').disabled = edition;
        document.getElementById('categorieType').value = options.type || 'depense';
        document.getElementById('categorieNom').value = options.nom || '';
        document.getElementById('categorieDescription').value = options.description || '';
        modal.show();
    }

    document.querySelector('[data-categorie-ajouter]').addEventListener('click', function () {
        // Réinitialise aussi les messages d'erreur d'une tentative précédente
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
        ouvrir({ mode: 'create' });
    });

    document.querySelectorAll('[data-categorie-modifier]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
            form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
            ouvrir({ mode: 'edit', id: btn.dataset.id, nom: btn.dataset.nom, description: btn.dataset.description, type: btn.dataset.type });
        });
    });

    @if ($errors->any() && old('_form'))
        // Une validation a échoué : on rouvre la modale avec les valeurs saisies et les erreurs
        (function () {
            var mode = @json(old('_form'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            var edition = mode.indexOf('edit:') === 0;
            ouvrir({
                mode: edition ? 'edit' : 'create',
                id: edition ? mode.substring(5) : null,
                type: @json(old('type', 'depense'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
                nom: @json(old('nom', ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
                description: @json(old('description', ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            });
        })();
    @endif
});
</script>
@endpush
