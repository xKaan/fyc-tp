<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Les données du fil rouge : six personnes, trois projets, les 26 notes de
 * database/notes/ et une tâche déjà ouverte.
 *
 * Droits : le projet Infra n'est lisible que par Bruno et Chloé. Alice ne doit
 * jamais voir ses notes, ni par la recherche ni par l'agent.
 */
class NoteFlowSeeder extends Seeder
{
    /** identifiant court => [nom, e-mail, projets] */
    private const PEOPLE = [
        'alice' => ['Alice Martin', 'alice@noteflow.test', ['Produit', 'Équipe']],
        'bruno' => ['Bruno Lefèvre', 'bruno@noteflow.test', ['Produit', 'Équipe', 'Infra']],
        'chloe' => ['Chloé Petit', 'chloe@noteflow.test', ['Équipe', 'Infra']],
        'malik' => ['Malik Diallo', 'malik@noteflow.test', ['Produit', 'Équipe']],
        'sarah' => ['Sarah Roux', 'sarah@noteflow.test', ['Produit', 'Équipe']],
        'tom' => ['Tom Garnier', 'tom@noteflow.test', ['Produit', 'Équipe']],
    ];

    public function run(): void
    {
        $projets = collect(['Produit', 'Équipe', 'Infra'])
            ->mapWithKeys(fn (string $nom): array => [$nom => Project::create(['name' => $nom])]);

        $personnes = [];

        foreach (self::PEOPLE as $cle => [$nom, $email, $sesProjets]) {
            // Mot de passe de démonstration : la connexion du TP n'en demande pas.
            $personnes[$cle] = User::create(['name' => $nom, 'email' => $email, 'password' => 'noteflow-demo']);
            $personnes[$cle]->projects()->attach($projets->only($sesProjets)->pluck('id'));
        }

        foreach (self::noteFiles() as $note) {
            Note::create([
                'title' => $note['titre'],
                'content' => $note['contenu'],
                'tags' => $note['tags'],
                'project_id' => $projets[$note['projet']]->id,
                'author_id' => $personnes[$note['auteur']]->id,
            ]);
        }

        // Une tâche déjà ouverte : l'agent doit la voir avant d'en proposer une identique.
        Task::create([
            'project_id' => $projets['Produit']->id,
            'note_id' => Note::where('title', 'Compte-rendu : réunion de cadrage NoteFlow')->value('id'),
            'created_by' => $personnes['alice']->id,
            'title' => 'Préparer les maquettes du partage en lecture seule',
            'due_on' => '2026-09-16',
            'status' => Task::OPEN,
        ]);
    }

    /**
     * Lit database/notes/*.md : un en-tête (titre, projet, auteur, tags) entre
     * deux lignes « --- », puis le contenu en markdown.
     *
     * @return list<array{titre: string, projet: string, auteur: string, tags: string, contenu: string}>
     */
    public static function noteFiles(): array
    {
        $fichiers = glob(database_path('notes/*.md')) ?: [];
        sort($fichiers);

        return array_map(function (string $fichier): array {
            if (! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', (string) file_get_contents($fichier), $parties)) {
                throw new RuntimeException("En-tête manquant dans {$fichier}.");
            }

            $entete = [];

            foreach (explode("\n", $parties[1]) as $ligne) {
                [$cle, $valeur] = array_map('trim', explode(':', $ligne, 2));
                $entete[$cle] = $valeur;
            }

            return [
                'titre' => $entete['titre'],
                'projet' => $entete['projet'],
                'auteur' => $entete['auteur'],
                'tags' => $entete['tags'] ?? '',
                'contenu' => trim($parties[2]),
            ];
        }, $fichiers);
    }
}
