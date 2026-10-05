<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SellerProfile extends Model
{
    use HasFactory;

    protected $table = 'seller_profiles';
    protected $primaryKey = 'id_seller';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = ['id_user', 'nama_usaha', 'alamat', 'no_telepon', 'foto_shop', 'rating'];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'id_seller', 'id_seller');
    }
}
