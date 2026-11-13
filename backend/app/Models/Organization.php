<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'status', 'settings', 'created_by'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * Every organization setting and the value used while it is not set.
     *
     * @var array{contact_email: ?string, phone: ?string, address: ?string, timezone: string, default_warehouse_id: ?int, default_reorder_level: ?int}
     */
    public const SETTING_DEFAULTS = [
        'contact_email' => null,
        'phone' => null,
        'address' => null,
        'timezone' => 'Asia/Dhaka',
        'default_warehouse_id' => null,
        'default_reorder_level' => null,
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * A setting's value, or its default while unset.
     */
    public function setting(string $key): mixed
    {
        return ($this->settings ?? [])[$key] ?? self::SETTING_DEFAULTS[$key];
    }

    /**
     * Store some settings, keeping the others.
     *
     * @param  array<string, mixed>  $values
     */
    public function putSettings(array $values): void
    {
        $this->settings = [...($this->settings ?? []), ...array_intersect_key($values, self::SETTING_DEFAULTS)];
    }

    /**
     * The default warehouse for operations, while it still exists and is active.
     */
    public function defaultWarehouseId(): ?int
    {
        $warehouseId = $this->setting('default_warehouse_id');

        if ($warehouseId === null) {
            return null;
        }

        return $this->warehouses()->whereKey($warehouseId)->where('is_active', true)->exists() ? (int) $warehouseId : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role', 'status', 'joined_at')->withTimestamps();
    }

    /**
     * Roles a member can hold, from most to least privileged.
     */
    public const ROLES = ['admin', 'manager', 'member'];

    /**
     * A suspended organization is hidden from all its members until a super-admin reactivates it.
     */
    public const STATUSES = ['active', 'suspended'];

    /**
     * Whether a role includes everything the minimum role may do (admin > manager > member).
     */
    public static function roleIncludes(?string $role, string $minimum): bool
    {
        $rank = array_search($role, self::ROLES, true);

        return $rank !== false && $rank <= array_search($minimum, self::ROLES, true);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * Whether the user is an active admin of this organization.
     */
    public function hasAdmin(User $user): bool
    {
        return $this->admins()->whereKey($user->id)->exists();
    }

    /**
     * Active members with the admin role.
     */
    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'admin')->wherePivot('status', 'active');
    }

    public function colors(): HasMany
    {
        return $this->hasMany(Color::class);
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(Size::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
