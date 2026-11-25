@extends('layout')

@section('contenu')

    <h2>Se connecter en tant que...</h2>
    <p><small>Connexion de démonstration du TP, sans mot de passe. Disponible en local uniquement.</small></p>

    @foreach ($users as $user)
        <div class="note">
            <form class="inline" method="post" action="{{ route('login.as', $user) }}">
                @csrf
                <button>{{ $user->name }}</button>
            </form>
            <small>Projets : {{ $user->projects->pluck('name')->join(', ') ?: 'aucun' }}</small>
        </div>
    @endforeach

@endsection
