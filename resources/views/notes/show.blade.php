@extends('layout')

@section('contenu')

    <h2>{{ $note->title }}</h2>
    <p><small>{{ $note->word_count }} mots &middot; {{ $note->reading_time }} min de lecture</small></p>

    @foreach ($note->tag_list as $tag)
        <a class="tag" href="{{ route('notes.tagged', $tag) }}">{{ $tag }}</a>
    @endforeach

    @if ($note->summary)
        <div class="resume">
            <strong>Resume</strong>
            <p>{{ $note->summary }}</p>
        </div>
    @endif

    <p>{!! nl2br(e($note->content)) !!}</p>

    <hr>

    <form class="inline" method="post" action="{{ route('notes.summarize', $note) }}">
        @csrf
        <button>Resumer avec l'IA</button>
    </form>

    <form class="inline" method="post" action="{{ route('notes.tags', $note) }}">
        @csrf
        <button>Suggerer des tags</button>
    </form>

    <a href="{{ route('notes.edit', $note) }}">Modifier</a>

    <form class="inline" method="post" action="{{ route('notes.destroy', $note) }}"
          onsubmit="return confirm('Supprimer cette note ?')">
        @csrf
        @method('DELETE')
        <button class="secondaire">Supprimer</button>
    </form>

@endsection
