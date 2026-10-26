@foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $cle => $classe)
    @if (session($cle))
        <div class="alert alert-{{ $classe }} alert-dismissible fade show" role="alert" @if ($cle === 'success') data-autohide @endif>
            {{ session($cle) }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    @endif
@endforeach
