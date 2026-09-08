@extends('layout')

@section('contenu')

    <h2>Modifier la note</h2>

    <form method="post" action="/notes/{{ $note->id }}">
        @csrf

        <label for="title">Titre</label>
        <input type="text" name="title" id="title" value="{{ $note->title }}">

        <label for="content">Contenu</label>
        <textarea name="content" id="content">{{ $note->content }}</textarea>

        <label for="tags">Tags (separes par des virgules)</label>
        <input type="text" name="tags" id="tags" value="{{ $note->tags }}">

        <p>
            <button>Enregistrer</button>
            <a href="/notes/{{ $note->id }}">Annuler</a>
        </p>
    </form>

@endsection
