<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Review extends Model
{
    use HasFactory;

    protected $table = 'reviews';
    protected $primaryKey = 'id_review';
    public $incrementing = true;
    protected $keyType = 'int';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $fillable = ['id_order', 'id_product', 'id_buyer', 'rating', 'komentar'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product', 'id_product');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'id_buyer', 'id_user');
    }
}
