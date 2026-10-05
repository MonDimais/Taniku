<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';
    protected $primaryKey = 'id_order';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = ['id_buyer', 'id_seller', 'tanggal_order', 'total_amount', 'status_order', 'alamat_pengiriman'];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'id_buyer', 'id_user');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'id_seller', 'id_user');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'id_order', 'id_order');
    }

    public function payment()
    {
        return $this->hasOne(PaymentEscrow::class, 'id_order', 'id_order');
    }

    public function disputes()
    {
        return $this->hasMany(Dispute::class, 'id_order', 'id_order');
    }
}
