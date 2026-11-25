<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AsTagList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Note extends Model
{
    /** Vitesse de lecture retenue pour l'estimation, en mots par minute. */
    private const WORDS_PER_MINUTE = 200;

    /** Longueur de l'apercu affiche dans la liste. */
    private const EXCERPT_LENGTH = 120;

    protected $fillable = ['title', 'content', 'summary', 'tags', 'project_id', 'author_id'];

    protected function casts(): array
    {
        return [
            'tags' => AsTagList::class,
        ];
    }

    /**
     * Les tags sous forme de liste. Les vues n'ont plus a decouper la chaine.
     *
     * @return Attribute<list<string>, never>
     */
    protected function tagList(): Attribute
    {
        return Attribute::get(fn (): array => AsTagList::parse($this->tags));
    }

    /** @return Attribute<string, never> */
    protected function excerpt(): Attribute
    {
        return Attribute::get(fn (): string => Str::limit((string) $this->content, self::EXCERPT_LENGTH));
    }

    /** @return Attribute<int, never> */
    protected function wordCount(): Attribute
    {
        return Attribute::get(function (): int {
            $mots = preg_split('/\s+/u', trim((string) $this->content), -1, PREG_SPLIT_NO_EMPTY);

            return count($mots ?: []);
        });
    }

    /** @return Attribute<int, never> */
    protected function readingTime(): Attribute
    {
        return Attribute::get(fn (): int => max(1, (int) ceil($this->word_count / self::WORDS_PER_MINUTE)));
    }

    /**
     * Filtre les notes portant un tag donne, en base et non en PHP.
     *
     * On encadre la colonne de virgules avant de comparer, sinon le tag
     * "cours" ressortirait sur toutes les notes taguees "courses".
     */
    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeTagged(Builder $query, string $tag): void
    {
        $query->whereRaw("',' || tags || ',' LIKE ?", ['%,'.AsTagList::normalize($tag).',%']);
    }

    public function scopeMatchingTitle(Builder $query, string $terme): void
    {
        $query->where('title', 'like', '%'.$terme.'%');
    }
}
