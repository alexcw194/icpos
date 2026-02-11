<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'item_id',
        'item_variant_id',
        'ledger_date',
        'qty_change',
        'balance_after',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected $casts = [
        'ledger_date'   => 'datetime',
        'qty_change'    => 'decimal:4',
        'balance_after' => 'decimal:4',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Backward-compatible alias used by existing controllers/views.
     */
    public function createdBy(): BelongsTo
    {
        return $this->creator();
    }

    public function getTrxTypeAttribute(): string
    {
        if (isset($this->attributes['trx_type']) && $this->attributes['trx_type'] !== null) {
            return (string) $this->attributes['trx_type'];
        }

        $qtyChange = (float) ($this->attributes['qty_change'] ?? 0);

        return $qtyChange >= 0 ? 'in' : 'out';
    }

    public function getQtyInAttribute(): float
    {
        if (isset($this->attributes['qty_in']) && $this->attributes['qty_in'] !== null) {
            return (float) $this->attributes['qty_in'];
        }

        $qtyChange = (float) ($this->attributes['qty_change'] ?? 0);

        return $qtyChange > 0 ? $qtyChange : 0.0;
    }

    public function getQtyOutAttribute(): float
    {
        if (isset($this->attributes['qty_out']) && $this->attributes['qty_out'] !== null) {
            return (float) $this->attributes['qty_out'];
        }

        $qtyChange = (float) ($this->attributes['qty_change'] ?? 0);

        return $qtyChange < 0 ? abs($qtyChange) : 0.0;
    }

    public function getBalanceAttribute(): float
    {
        if (isset($this->attributes['balance']) && $this->attributes['balance'] !== null) {
            return (float) $this->attributes['balance'];
        }

        return (float) ($this->attributes['balance_after'] ?? 0);
    }

    public function getReferenceAttribute(): string
    {
        if (isset($this->attributes['reference']) && $this->attributes['reference'] !== null) {
            return (string) $this->attributes['reference'];
        }

        $referenceType = (string) ($this->attributes['reference_type'] ?? '');
        $referenceId = $this->attributes['reference_id'] ?? null;

        if ($referenceType === '' && $referenceId === null) {
            return '';
        }

        return $referenceType . ($referenceId !== null ? '#'.$referenceId : '');
    }
}
