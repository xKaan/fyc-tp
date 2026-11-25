<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Ports\ToolCall;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une action d'écriture proposée par l'agent, qui attend la validation de
 * l'utilisateur. La boucle s'arrête dessus : rien n'est écrit tant que
 * l'utilisateur n'a pas confirmé.
 */
class PendingAction extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['user_id', 'tool', 'arguments', 'description', 'status'];

    protected function casts(): array
    {
        return ['arguments' => 'array'];
    }

    /** Enregistre la demande du modèle telle quelle, avec la phrase montrée à l'utilisateur. */
    public static function propose(User $user, ToolCall $call, string $description): self
    {
        return self::create([
            'user_id' => $user->id,
            'tool' => $call->name,
            'arguments' => $call->arguments,
            'description' => $description,
            'status' => self::PENDING,
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
