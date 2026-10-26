<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategorieRequest;
use App\Models\Categorie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** EF6 — Gestion des catégories : ajouter, modifier, supprimer, rechercher. */
class CategorieController extends Controller
{
    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q', ''));

        $categories = $request->user()->categories()
            ->withCount(['depenses', 'revenus'])
            ->when($recherche !== '', fn ($q) => $q->where('nom', 'like', '%'.$recherche.'%'))
            ->orderBy('nom')
            ->get();

        return view('categories.index', [
            'depenses' => $categories->where('type', Categorie::TYPE_DEPENSE),
            'revenus' => $categories->where('type', Categorie::TYPE_REVENU),
            'recherche' => $recherche,
        ]);
    }

    public function store(CategorieRequest $request): RedirectResponse
    {
        $request->user()->categories()->create($request->validated());

        return redirect()->route('categories.index')->with('success', 'Catégorie ajoutée avec succès.');
    }

    public function update(CategorieRequest $request, Categorie $categorie): RedirectResponse
    {
        $categorie->update($request->validated());

        return redirect()->route('categories.index')->with('success', 'Catégorie modifiée avec succès.');
    }

    public function destroy(Categorie $categorie): RedirectResponse
    {
        Gate::authorize('delete', $categorie);

        $nombre = $categorie->nombreTransactions();

        if ($nombre > 0) {
            return redirect()->route('categories.index')->with('error',
                'Impossible de supprimer la catégorie « '.$categorie->nom.' » : elle est utilisée par '
                .$nombre.' transaction'.($nombre > 1 ? 's' : '').'. Modifiez ou supprimez d\'abord ces transactions.');
        }

        $categorie->delete();

        return redirect()->route('categories.index')->with('success', 'Catégorie supprimée avec succès.');
    }
}
