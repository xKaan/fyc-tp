@extends('layout')

@section('contenu')

    <form method="get" action="{{ route('notes.index') }}">
        <input type="text" name="q" placeholder="Rechercher dans les titres..." value="{{ request('q') }}">
    </form>

    @forelse ($notes as $note)
        <div class="note">
            <h2><a href="{{ route('notes.show', $note) }}">{{ $note->title }}</a></h2>
            <p>{{ $note->excerpt }}</p>

            @foreach ($note->tag_list as $tag)
                <a class="tag" href="{{ route('notes.tagged', $tag) }}">{{ $tag }}</a>
            @endforeach

            @if ($note->summary)
                <p><em>Resume disponible</em></p>
            @endif
        </div>
    @empty
        <p>Aucune note pour l'instant.</p>
    @endforelse

@endsection
