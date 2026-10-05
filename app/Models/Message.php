<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory;

    protected $table = 'messages';
    protected $primaryKey = 'id_message';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = ['sender_id', 'receiver_id', 'id_order', 'pesan', 'sent_at', 'read_at'];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id', 'id_user');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id', 'id_user');
    }
}
