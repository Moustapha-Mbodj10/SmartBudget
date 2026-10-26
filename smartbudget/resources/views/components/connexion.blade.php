@props(['utilisateur', 'detail' => true])

{{--
    État de connexion d'un compte pour l'administrateur : « En ligne » (point vert), ou « Déconnecté » avec, en dessous,
    la durée de déconnexion. Un compte qui ne s'est jamais connecté depuis le suivi le dit clairement.
--}}
@if ($utilisateur->estEnLigne())
    <span class="sb-presence sb-presence-en-ligne"><i class="sb-point"></i>En ligne</span>
@elseif ($utilisateur->deconnecteDepuis())
    <span class="sb-presence"><i class="sb-point"></i>Déconnecté</span>
    @if ($detail)
        <span class="sb-presence-duree d-block" title="Déconnecté depuis le {{ $utilisateur->deconnecteDepuis()->translatedFormat('d/m/Y à H:i') }}">depuis {{ $utilisateur->dureeDeconnexion() }}</span>
    @endif
@else
    <span class="sb-presence"><i class="sb-point"></i>Déconnecté</span>
    @if ($detail)
        <span class="sb-presence-duree d-block">aucune connexion enregistrée</span>
    @endif
@endif
