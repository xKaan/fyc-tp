@extends('layout')

@section('contenu')

    <h2>Modifier la note</h2>

    <form method="post" action="{{ route('notes.update', $note) }}">
        @csrf

        <label for="title">Titre</label>
        <input type="text" name="title" id="title" value="{{ old('title', $note->title) }}">

        <label for="content">Contenu</label>
        <textarea name="content" id="content">{{ old('content', $note->content) }}</textarea>

        <label for="tags">Tags (separes par des virgules)</label>
        <input type="text" name="tags" id="tags" value="{{ old('tags', $note->tags) }}">

        <p>
            <button>Enregistrer</button>
            <a href="{{ route('notes.show', $note) }}">Annuler</a>
        </p>
    </form>

@endsection
