<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRetry extends Model
{
    protected $fillable = [
        'payment_id',
        'attempt',
        'exception_message',
        'exception_type',
        'error',
        'operation'
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
