@extends('layouts.app')
@section('titre', 'Modifier un utilisateur')

@section('contenu')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-7 col-xl-6">
            <a href="{{ route('admin.utilisateurs.show', $utilisateur) }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Retour à la fiche</a>

            <div class="card mt-2">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-pencil me-1"></i>Modifier {{ $utilisateur->prenom }} {{ $utilisateur->nom }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.utilisateurs.update', $utilisateur) }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-12 col-sm-6 mb-3">
                                <label for="prenom" class="form-label">Prénom</label>
                                <input type="text" id="prenom" name="prenom" maxlength="100" required
                                       value="{{ old('prenom', $utilisateur->prenom) }}"
                                       class="form-control @error('prenom') is-invalid @enderror">
                                @error('prenom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12 col-sm-6 mb-3">
                                <label for="nom" class="form-label">Nom</label>
                                <input type="text" id="nom" name="nom" maxlength="100" required
                                       value="{{ old('nom', $utilisateur->nom) }}"
                                       class="form-control @error('nom') is-invalid @enderror">
                                @error('nom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" id="email" name="email" maxlength="150" required
                                   value="{{ old('email', $utilisateur->email) }}"
                                   class="form-control @error('email') is-invalid @enderror">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                            <a href="{{ route('admin.utilisateurs.show', $utilisateur) }}" class="btn btn-outline-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
