@extends('layout')

@section('contenu')

    <h2>Nouvelle note</h2>

    <form method="post" action="{{ route('notes.store') }}">
        @csrf

        <label for="title">Titre</label>
        <input type="text" name="title" id="title" value="{{ old('title') }}">

        <label for="content">Contenu</label>
        <textarea name="content" id="content">{{ old('content') }}</textarea>

        <label for="tags">Tags (separes par des virgules)</label>
        <input type="text" name="tags" id="tags" value="{{ old('tags') }}">

        <p>
            <button>Enregistrer</button>
            <a href="{{ route('notes.index') }}">Annuler</a>
        </p>
    </form>

@endsection
