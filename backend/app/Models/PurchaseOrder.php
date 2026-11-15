<?php

namespace App\Models;

use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order for stock from a supplier, delivered into one warehouse. Lines can change only while it is a draft.
 */
#[Fillable(['organization_id', 'sequence', 'number', 'supplier_id', 'warehouse_id', 'status', 'expected_at', 'notes', 'created_by', 'ordered_at', 'cancelled_at', 'closed_at'])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    /**
     * Statuses in lifecycle order: draft → ordered → partially_received → received. Drafts and untouched
     * orders can be cancelled; a partly received order can be closed when the rest will not arrive.
     */
    public const STATUSES = ['draft', 'ordered', 'partially_received', 'received', 'closed', 'cancelled'];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'expected_at' => 'date',
            'ordered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * The display number for a sequence, e.g. PO-0007.
     */
    public static function numberFor(int $sequence): string
    {
        return 'PO-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Drafts and orders that have not received anything yet can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'ordered'], true);
    }

    /**
     * Stock can be received while the order is placed and not yet complete, closed or cancelled.
     */
    public function canReceive(): bool
    {
        return in_array($this->status, ['ordered', 'partially_received'], true);
    }

    /**
     * A partly received order can be closed when the supplier will not send the rest.
     */
    public function canBeClosed(): bool
    {
        return $this->status === 'partially_received';
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
        return $this->hasMany(PurchaseOrderLine::class);
    }
}
