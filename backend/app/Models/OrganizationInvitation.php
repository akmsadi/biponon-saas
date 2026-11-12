<?php

namespace App\Models;

use Database\Factories\OrganizationInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An invitation for an email address to join an organization with a role. The plain token is only ever in the
 * link that was sent; the table holds its SHA-256 hash.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $email
 * @property string $role
 * @property string $token
 * @property int|null $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 */
#[Fillable(['organization_id', 'email', 'role', 'token', 'invited_by', 'expires_at', 'accepted_at'])]
#[Hidden(['token'])]
class OrganizationInvitation extends Model
{
    /** @use HasFactory<OrganizationInvitationFactory> */
    use HasFactory;

    /**
     * How long a sent link stays valid.
     */
    public const VALID_FOR_DAYS = 7;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Find an invitation by the plain token from its link.
     */
    public static function findByToken(string $token): ?self
    {
        return static::firstWhere('token', static::hashToken($token));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Give the invitation a fresh token and expiry date and return the plain token for the link.
     */
    public function refreshToken(): string
    {
        $token = Str::random(48);

        $this->forceFill([
            'token' => static::hashToken($token),
            'expires_at' => now()->addDays(self::VALID_FOR_DAYS),
        ]);

        return $token;
    }

    /**
     * Invitations not accepted yet, whether or not they have expired.
     *
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at');
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
