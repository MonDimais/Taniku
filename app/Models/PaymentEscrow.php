<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentEscrow extends Model
{
    use HasFactory;

    protected $table = 'payments_escrow';
    protected $primaryKey = 'id_payment';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = ['id_order', 'metode_pembayaran', 'jumlah', 'status_pembayaran', 'status_escrow', 'paid_at', 'released_at'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order', 'id_order');
    }
}
