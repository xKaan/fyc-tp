@extends('layout')

@section('contenu')

    <form method="get" action="/notes">
        <input type="text" name="q" placeholder="Rechercher dans les titres..." value="{{ request('q') }}">
    </form>

    @if (count($notes) == 0)
        <p>Aucune note pour l'instant.</p>
    @endif

    @foreach ($notes as $note)
        @php
            // On coupe le contenu pour l'apercu.
            $apercu = $note->content;
            if (strlen($apercu) > 120) {
                $apercu = substr($apercu, 0, 120) . '...';
            }

            // Les tags sont stockes en une chaine, il faut les redecouper.
            $listeTags = [];
            if ($note->tags != '') {
                foreach (explode(',', $note->tags) as $t) {
                    if (trim($t) != '') {
                        $listeTags[] = trim($t);
                    }
                }
            }
        @endphp

        <div class="note">
            <h2><a href="/notes/{{ $note->id }}">{{ $note->title }}</a></h2>
            <p>{{ $apercu }}</p>

            @foreach ($listeTags as $t)
                <a class="tag" href="/tag/{{ $t }}">{{ $t }}</a>
            @endforeach

            @if ($note->summary != '')
                <p><em>Resume disponible</em></p>
            @endif
        </div>
    @endforeach

@endsection
