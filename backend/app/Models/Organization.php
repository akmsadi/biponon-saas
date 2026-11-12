<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'status', 'created_by'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

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

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * Whether the user is an active admin of this organization.
     */
    public function hasAdmin(User $user): bool
    {
        return $this->users()
            ->whereKey($user->id)
            ->wherePivot('role', 'admin')
            ->wherePivot('status', 'active')
            ->exists();
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
