<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';
    protected $primaryKey = 'id_product';
    public $incrementing = true;
    protected $keyType = 'int';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $fillable = ['id_seller', 'nama_produk', 'deskripsi', 'harga', 'stok', 'satuan', 'status', 'grade', 'foto'];

    public function sellerProfile()
    {
        return $this->belongsTo(SellerProfile::class, 'id_seller', 'id_seller');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_categories', 'id_product', 'id_kategori');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'id_product', 'id_product');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'id_product', 'id_product');
    }
}
