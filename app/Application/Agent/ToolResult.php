<?php

declare(strict_types=1);

namespace App\Application\Agent;

/**
 * Le résultat d'un outil, renvoyé au modèle en JSON.
 *
 * $sources liste les notes que ce résultat a montrées au modèle : ce sont
 * les seules qu'il a le droit de citer dans sa réponse finale.
 */
final class ToolResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $sources
     */
    private function __construct(
        public readonly array $data,
        public readonly array $sources = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $sources  identifiants des notes montrées au modèle
     */
    public static function ok(array $data, array $sources = []): self
    {
        return new self($data, array_values($sources));
    }

    public static function error(string $message): self
    {
        return new self(['erreur' => $message]);
    }

    public function isError(): bool
    {
        return array_key_exists('erreur', $this->data);
    }

    public function toJson(): string
    {
        return (string) json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
