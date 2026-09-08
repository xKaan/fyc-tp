<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mes Notes</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 780px; margin: 0 auto; padding: 24px; color: #222; }
        header { display: flex; gap: 16px; align-items: baseline; border-bottom: 1px solid #ddd; padding-bottom: 12px; margin-bottom: 24px; }
        header h1 { font-size: 20px; margin: 0; flex: 1; }
        a { color: #2563eb; text-decoration: none; }
        .note { border: 1px solid #e5e5e5; border-radius: 6px; padding: 12px 16px; margin-bottom: 12px; }
        .tag { display: inline-block; background: #eef2ff; color: #3730a3; border-radius: 4px; padding: 1px 8px; margin-right: 4px; font-size: 12px; }
        .resume { background: #f0fdf4; border-left: 3px solid #16a34a; padding: 8px 12px; margin: 12px 0; }
        .erreur { background: #fef2f2; border-left: 3px solid #dc2626; padding: 8px 12px; margin-bottom: 16px; }
        .statut { background: #eff6ff; border-left: 3px solid #2563eb; padding: 8px 12px; margin-bottom: 16px; }
        input[type=text], textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font: inherit; }
        textarea { min-height: 180px; }
        label { display: block; margin: 12px 0 4px; font-weight: 600; font-size: 14px; }
        button { background: #2563eb; color: #fff; border: 0; border-radius: 4px; padding: 8px 14px; font: inherit; cursor: pointer; }
        button.secondaire { background: #6b7280; }
        form.inline { display: inline; }
    </style>
</head>
<body>
<header>
    <h1><a href="{{ route('notes.index') }}">Mes Notes</a></h1>
    <a href="{{ route('notes.create') }}">Nouvelle note</a>
</header>

@if (session('status'))
    <p class="statut">{{ session('status') }}</p>
@endif

@if ($errors->any())
    <div class="erreur">
        @foreach ($errors->all() as $message)
            <p>{{ $message }}</p>
        @endforeach
    </div>
@endif

@yield('contenu')
</body>
</html>
