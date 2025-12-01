<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'gateway_response' => 'array',
        'amount' => 'decimal:2',
    ];

    const STATUS_AUTHORISED = 'authorised';
    const STATUS_CAPTURED = 'captured';
    const STATUS_VOIDED = 'voided';
    const STATUS_FAILED = 'failed';

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function addEvent(string $type, array $payload = [], string $source = null, string $processorTxnId = null): PaymentEvent
    {
        return $this->events()->create([
            'event_type' => $type,
            'payload' => $payload ?: null,
            'source' => $source,
            'processor_txn_id' => $processorTxnId,
        ]);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function getRefundedAmountAttribute()
    {
        return $this->refunds()->sum('amount');
    }

    public function getRemainingAmountAttribute()
    {
        return $this->amount - $this->refunded_amount;
    }

    // State transition logic. Ensure for example that a payment cannot be refunded if it is already voided.
    public function ensureCanCapture()
    {
        if ($this->status !== self::STATUS_AUTHORISED) {
            throw new \Exception("Payment cannot be captured from status: {$this->status}");
        }

        if ($this->is_fully_refunded) {
            throw new \Exception("Payment cannot be captured because it is fully refunded.");
        }
    }

    public function ensureCanRefund(float $amount)
    {
        if ($this->status !== self::STATUS_CAPTURED) {
            throw new \Exception("Only captured payments can be refunded.");
        }

        if ($this->status === self::STATUS_VOIDED) {
            throw new \Exception("Voided payments cannot be refunded.");
        }

        if ($amount > $this->remaining_amount) {
            throw new \Exception("Refund amount exceeds remaining refundable amount.");
        }
    }

    public function ensureCanVoid()
    {
        if ($this->status !== self::STATUS_AUTHORISED) {
            throw new \Exception("Only authorised payments can be voided.");
        }

        if ($this->is_fully_refunded) {
            throw new \Exception("Payment cannot be voided because it is fully refunded.");
        }
    }

}
