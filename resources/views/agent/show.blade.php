@extends('layout')

@section('contenu')

    <h2>Assistant NoteFlow</h2>
    <p><small>Il cherche dans les notes des projets de {{ auth()->user()->name }}, et propose des tâches que vous confirmez.</small></p>

    <form method="post" action="{{ route('agent.ask') }}">
        @csrf
        <label for="question">Votre question</label>
        <input type="text" name="question" id="question" value="{{ old('question') }}"
               placeholder="Qu'a-t-on décidé pour la base de données ?">
        <p><button>Demander</button></p>
    </form>

    @if ($answer)
        <div class="note">
            <p><strong>{{ $answer['question'] }}</strong></p>

            @if ($answer['pending_id'])
                <div class="attente">
                    <p><strong>L'assistant propose :</strong> {!! $answer['html'] !!}</p>
                    <p>Rien n'est créé tant que vous n'avez pas confirmé.</p>
                    <form class="inline" method="post" action="{{ route('agent.confirm', $answer['pending_id']) }}">
                        @csrf
                        <button>Confirmer</button>
                    </form>
                    <form class="inline" method="post" action="{{ route('agent.cancel', $answer['pending_id']) }}">
                        @csrf
                        <button class="secondaire">Annuler</button>
                    </form>
                </div>
            @else
                <p>{!! $answer['html'] !!}</p>
            @endif

            @if ($answer['sources'])
                <p><small>Sources :
                    @foreach ($answer['sources'] as $id => $titre)
                        <a href="{{ route('notes.show', $id) }}">{{ $titre }}</a>@if (! $loop->last), @endif
                    @endforeach
                </small></p>
            @endif

            <p><small>Arrêt : {{ $answer['reason'] }} &middot; {{ $answer['steps'] }} étape(s)</small></p>
        </div>
    @endif

@endsection
