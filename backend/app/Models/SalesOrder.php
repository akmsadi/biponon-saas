<?php

namespace App\Models;

use Database\Factories\SalesOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order from a customer, shipped from one warehouse by default. Lines can change only while it is a draft.
 * Stock is not reserved on confirmation, so an order may be confirmed while stock is short (a backorder).
 */
#[Fillable(['organization_id', 'sequence', 'number', 'customer_id', 'warehouse_id', 'status', 'deliver_by', 'notes', 'created_by', 'confirmed_at', 'cancelled_at', 'closed_at'])]
class SalesOrder extends Model
{
    /** @use HasFactory<SalesOrderFactory> */
    use HasFactory;

    /**
     * Statuses in lifecycle order: draft → confirmed → partially_dispatched → dispatched. Drafts and untouched
     * orders can be cancelled; a partly dispatched order can be closed when the rest will not be sent.
     */
    public const STATUSES = ['draft', 'confirmed', 'partially_dispatched', 'dispatched', 'closed', 'cancelled'];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'deliver_by' => 'date',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * The display number for a sequence, e.g. SO-0007.
     */
    public static function numberFor(int $sequence): string
    {
        return 'SO-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Drafts and orders that have not dispatched anything yet can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'confirmed'], true);
    }

    /**
     * Stock can be dispatched while the order is confirmed and not yet complete, closed or cancelled.
     */
    public function canDispatch(): bool
    {
        return in_array($this->status, ['confirmed', 'partially_dispatched'], true);
    }

    /**
     * A partly dispatched order can be closed when the rest will not be sent.
     */
    public function canBeClosed(): bool
    {
        return $this->status === 'partially_dispatched';
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }
}
