<?php

namespace Tests\Unit;

use App\Domain\Ports\EmbeddingPort;
use App\Domain\Ports\LlmCallFailed;
use App\Infrastructure\Embeddings\FakeEmbeddingAdapter;
use App\Infrastructure\Embeddings\HttpEmbeddingAdapter;
use App\Infrastructure\Embeddings\OllamaEmbeddingAdapter;
use App\Infrastructure\Embeddings\Vectors;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

/** Corrigé S2.4 : la vectorisation, derrière son port, pour les trois fournisseurs. */
class EmbeddingAdaptersTest extends TestCase
{
    public function test_ollama_envoie_les_textes_a_api_embed_et_rend_un_vecteur_par_texte(): void
    {
        Http::fake(['*' => Http::response(['embeddings' => [[0.1, 0.2], [0.3, 0.4]]])]);

        $vecteurs = (new OllamaEmbeddingAdapter('http://host.docker.internal:11434', 'embeddinggemma', 120))
            ->embed(['un', 'deux']);

        $this->assertSame([[0.1, 0.2], [0.3, 0.4]], $vecteurs);
        Http::assertSent(fn (Request $requete) => $requete->url() === 'http://host.docker.internal:11434/api/embed'
            && $requete['model'] === 'embeddinggemma'
            && $requete['input'] === ['un', 'deux']);
    }

    public function test_le_format_openai_est_remis_dans_l_ordre_des_textes(): void
    {
        Http::fake(['*' => Http::response(['data' => [
            ['index' => 1, 'embedding' => [0.3, 0.4]],
            ['index' => 0, 'embedding' => [0.1, 0.2]],
        ]])]);

        $vecteurs = (new HttpEmbeddingAdapter('https://api.mistral.ai/v1', 'cle', 'mistral-embed', 30))
            ->embed(['un', 'deux']);

        $this->assertSame([[0.1, 0.2], [0.3, 0.4]], $vecteurs);
        Http::assertSent(fn (Request $requete) => $requete->url() === 'https://api.mistral.ai/v1/embeddings'
            && $requete->hasHeader('Authorization', 'Bearer cle')
            && $requete['model'] === 'mistral-embed');
    }

    public function test_un_nombre_de_vecteurs_inattendu_est_une_panne_du_port(): void
    {
        Http::fake(['*' => Http::response(['embeddings' => [[0.1, 0.2]]])]);

        $this->expectException(LlmCallFailed::class);

        (new OllamaEmbeddingAdapter('http://localhost:11434', 'embeddinggemma', 120))->embed(['un', 'deux']);
    }

    public function test_une_erreur_http_est_une_panne_du_port(): void
    {
        Http::fake(['*' => Http::response(['error' => 'quota'], 429)]);

        $this->expectException(LlmCallFailed::class);

        (new HttpEmbeddingAdapter('https://api.mistral.ai/v1', 'cle', 'mistral-embed', 30))->embed(['un']);
    }

    public function test_la_doublure_est_deterministe_et_rapproche_les_textes_qui_partagent_des_mots(): void
    {
        $doublure = new FakeEmbeddingAdapter(64);
        [$question, $proche, $loin] = $doublure->embed([
            'choix de la base de données',
            'Décision : choix de PostgreSQL pour la base de données',
            'Trombinoscope et disponibilités',
        ]);

        $this->assertSame($question, $doublure->embed(['choix de la base de données'])[0]);
        $this->assertCount(64, $question);
        $this->assertGreaterThan(self::cosinus($question, $loin), self::cosinus($question, $proche));
    }

    public function test_un_vecteur_s_ecrit_au_format_pgvector_qui_est_aussi_du_json(): void
    {
        $this->assertSame('[0.5,-0.25,0,1]', Vectors::literal([0.5, -0.25, 0.0, 1.0]));
        $this->assertSame([0.5, -0.25, 0, 1], json_decode(Vectors::literal([0.5, -0.25, 0.0, 1.0])));
    }

    public function test_la_configuration_choisit_l_adaptateur_d_embeddings(): void
    {
        foreach (['fake' => FakeEmbeddingAdapter::class, 'http' => HttpEmbeddingAdapter::class, 'ollama' => OllamaEmbeddingAdapter::class] as $provider => $classe) {
            config(['llm.embeddings.provider' => $provider]);

            $this->assertInstanceOf($classe, $this->app->make(EmbeddingPort::class), "llm.embeddings.provider = {$provider}");
        }

        config(['llm.embeddings.provider' => 'imaginaire']);
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(EmbeddingPort::class);
    }

    /** @param list<float> $a @param list<float> $b */
    private static function cosinus(array $a, array $b): float
    {
        return array_sum(array_map(fn (float $x, float $y): float => $x * $y, $a, $b));
    }
}
