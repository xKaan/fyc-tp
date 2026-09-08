@extends('layout')

@section('contenu')

    @php
        // Encore le decoupage des tags, comme sur la liste.
        $listeTags = [];
        if ($note->tags != '') {
            foreach (explode(',', $note->tags) as $t) {
                if (trim($t) != '') {
                    $listeTags[] = trim($t);
                }
            }
        }

        $nombreDeMots = count(explode(' ', $note->content));
        $tempsDeLecture = ceil($nombreDeMots / 200);
    @endphp

    <h2>{{ $note->title }}</h2>
    <p><small>{{ $nombreDeMots }} mots &middot; {{ $tempsDeLecture }} min de lecture</small></p>

    @foreach ($listeTags as $t)
        <a class="tag" href="/tag/{{ $t }}">{{ $t }}</a>
    @endforeach

    @if ($note->summary != '')
        <div class="resume">
            <strong>Resume</strong>
            <p>{{ $note->summary }}</p>
        </div>
    @endif

    <p>{!! nl2br(e($note->content)) !!}</p>

    <hr>

    <form class="inline" method="post" action="/notes/{{ $note->id }}/summarize">
        @csrf
        <button>Resumer avec l'IA</button>
    </form>

    <form class="inline" method="post" action="/notes/{{ $note->id }}/tags">
        @csrf
        <button>Suggerer des tags</button>
    </form>

    <a href="/notes/{{ $note->id }}/edit">Modifier</a>
    &middot;
    <a href="/notes/{{ $note->id }}/delete">Supprimer</a>

@endsection
