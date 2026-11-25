<?php

namespace Tests\Feature;

use App\Application\Agent\InvalidToolArguments;
use App\Application\Agent\Tools\ListerTachesOuvertes;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\NoteFlowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Outil fourni, modèle des deux autres : ces tests sont verts dès le départ. */
class ListerTachesOuvertesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NoteFlowSeeder::class);
    }

    public function test_il_liste_les_taches_ouvertes_des_projets_de_l_utilisateur(): void
    {
        $chloe = User::where('email', 'chloe@noteflow.test')->firstOrFail();
        Task::create([
            'project_id' => Project::where('name', 'Infra')->value('id'),
            'created_by' => $chloe->id,
            'title' => 'Revoir les accès du mois',
        ]);

        $alice = User::where('email', 'alice@noteflow.test')->firstOrFail();
        $resultat = (new ListerTachesOuvertes)->run([], $alice);

        $titres = array_column($resultat->data['taches'], 'titre');
        $this->assertContains('Préparer les maquettes du partage en lecture seule', $titres);
        $this->assertNotContains('Revoir les accès du mois', $titres, 'Alice ne voit pas les tâches du projet Infra.');
    }

    public function test_des_arguments_invalides_sont_refuses(): void
    {
        $this->expectException(InvalidToolArguments::class);

        (new ListerTachesOuvertes)->run(['projet' => ['pas', 'une', 'chaine']], User::firstOrFail());
    }
}
