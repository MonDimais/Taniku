<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';
    protected $primaryKey = 'id_notification';
    public $incrementing = true;
    protected $keyType = 'int';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $fillable = ['id_user', 'id_order', 'tipe', 'pesan', 'is_read'];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
