<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;

class User extends Model
{
    use HasFactory;

    protected $table = 'users';
    protected $primaryKey = 'id_user';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = ['id_role', 'nama', 'email', 'password', 'status_verifikasi'];

    public function role()
    {
        return $this->belongsTo(Role::class, 'id_role', 'id_role');
    }

    public function sellerProfile()
    {
        return $this->hasOne(SellerProfile::class, 'id_user', 'id_user');
    }

    public function ordersAsBuyer()
    {
        return $this->hasMany(Order::class, 'id_buyer', 'id_user');
    }

    public function ordersAsSeller()
    {
        return $this->hasMany(Order::class, 'id_seller', 'id_user');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'id_buyer', 'id_user');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'id_user', 'id_user');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id', 'id_user');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id', 'id_user');
    }
}
