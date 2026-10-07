<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    private function requireLogin()
    {
        $uid = Session::get('user_id');
        if (!$uid) return null;
        $user = DB::table('users')->where('id_user', $uid)->first();
        return $user;
    }

    /**
     * Chat is only allowed between a buyer and a seller, or a seller and admin.
     * Roles: 1=admin, 2=seller, 3=buyer. Returns a 403 error response if the
     * pair is not permitted, or null when the pair is fine.
     */
    private function chatAllowed($user, $receiverId)
    {
        $receiver = DB::table('users')->where('id_user', $receiverId)->first(['id_user', 'id_role']);
        if (!$receiver) {
            return response()->json(['error' => 'User tidak ditemukan'], 404);
        }
        $mine = (int) $user->id_role;
        $theirs = (int) $receiver->id_role;
        // Allowed pairs (unordered): buyer<->seller, seller<->admin, admin<->buyer.
        // Admins can chat with everyone they manage (sellers and buyers).
        $allowed = [
            [2, 3], // seller <-> buyer
            [1, 2], // admin <-> seller
            [1, 3], // admin <-> buyer
        ];
        $mineMin = min($mine, $theirs);
        $theirsMax = max($mine, $theirs);
        $ok = false;
        foreach ($allowed as $pair) {
            if ($pair[0] === $mineMin && $pair[1] === $theirsMax) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            return response()->json(['error' => 'Percakapan hanya diizinkan antara buyer-seller atau seller-admin'], 403);
        }
        return null;
    }

    public function getMessages(Request $request, $conversationId)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        $guard = $this->chatAllowed($user, $conversationId);
        if ($guard) return $guard;
        // Fetch the thread with $conversationId in BOTH directions.
        // NOTE: wrap each direction in its own closure — a bare ->orWhere() between
        // multiple conditions is OR'd at top level and leaks messages from other
        // conversations (e.g. A->B and B->C would both match "involved with B").
        $msgs = DB::table('messages')
            ->join('users as su', 'messages.sender_id', '=', 'su.id_user')
            ->join('users as ru', 'messages.receiver_id', '=', 'ru.id_user')
            ->select('messages.*', 'su.nama as sender_nama', 'ru.nama as receiver_nama')
            ->where(function ($q) use ($user, $conversationId) {
                $q->where('messages.sender_id', $user->id_user)
                  ->where('messages.receiver_id', $conversationId);
            })
            ->orWhere(function ($q) use ($user, $conversationId) {
                $q->where('messages.sender_id', $conversationId)
                  ->where('messages.receiver_id', $user->id_user);
            })
            ->orderBy('messages.sent_at', 'asc')
            ->get();
        DB::table('messages')
            ->where('sender_id', $conversationId)
            ->where('receiver_id', $user->id_user)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        // Attach related messages_attachments for each message
        $attRows = DB::table('messages_attachments')
            ->whereIn('id_message', $msgs->pluck('id_message')->all())
            ->orderBy('id_attachment', 'asc')
            ->get();
        $attByMsg = [];
        foreach ($attRows as $a) {
            $attByMsg[$a->id_message][] = [
                'id_attachment' => $a->id_attachment,
                'path' => $a->path,
                'filename' => $a->filename,
                'mime_type' => $a->mime_type,
                'size' => $a->size,
            ];
        }
        $result = [];
        foreach ($msgs as $m) {
            $arr = (array) $m;
            $arr['attachments'] = $attByMsg[$m->id_message] ?? [];
            $result[] = $arr;
        }
        return response()->json($result);
    }

    public function send(Request $request)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }

        // Handle file upload first if present
        $attachments = [];
        if ($request->hasFile('attachments')) {
            $files = $request->file('attachments');
            $maxSize = 10 * 1024 * 1024;
            $allowedExtensions = ['jpg','jpeg','png','gif','webp','mp4','mov','webm','pdf','doc','docx','xls','xlsx','csv','mp3','wav','ogg','aac'];
            foreach ($files as $file) {
                if ($file->getSize() > $maxSize) {
                    return response()->json(['error' => 'File terlalu besar (maks 10MB tiap file)'], 422);
                }
                $ext = strtolower($file->getClientOriginalExtension());
                if (!in_array($ext, $allowedExtensions)) {
                    return response()->json(['error' => 'Tipe file tidak diizinkan: ' . $ext], 422);
                }
                $filename = 'chat_' . time() . '_' . uniqid() . '.' . $ext;
                $file->storeAs('public/uploads/chats', $filename);
                $path = '/storage/uploads/chats/' . $filename;
                $attachments[] = [
                    'path' => $path,
                    'filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }

        // pesan: support both JSON and FormData (multipart when file attached)
        // IMPORTANT: do not read the JSON body stream on multipart requests.
        $pesan = trim($request->input('pesan') ?? '');
        if ($pesan === '' && ($request->header('Content-Type') ?? '') !== '') {
            $ct = $request->header('Content-Type');
            if (stripos($ct, 'application/json') !== false) {
                $pesan = trim($request->json()->input('pesan') ?? '');
            }
        }

        // receiver_id is always in the JSON body (chat is always with a specific user)
        $receiverId = (int) ($request->input('receiver_id') ?? 0);
        if (!$receiverId && $pesan === '') {
            // Fallback: pull from JSON body if this is a pure JSON request
            $json = $request->json()->all();
            $receiverId = (int) ($json['receiver_id'] ?? 0);
            $pesan = trim($json['pesan'] ?? '');
        }
        if (!$receiverId) {
            return response()->json(['error' => 'receiver_id wajib diisi'], 422);
        }
        $guard = $this->chatAllowed($user, $receiverId);
        if ($guard) return $guard;
        if (empty($pesan) && empty($attachments)) {
            return response()->json(['error' => 'Pesan atau file wajib diisi'], 422);
        }
        if (empty($pesan)) {
            $pesan = '[File attachment]';
        }

        $id = DB::table('messages')->insertGetId([
            'sender_id' => $user->id_user,
            'receiver_id' => $receiverId,
            'id_order' => null,
            'pesan' => $pesan,
            'sent_at' => now(),
        ], 'id_message');

        foreach ($attachments as $att) {
            DB::table('messages_attachments')->insert([
                'id_message' => $id,
                'path' => $att['path'],
                'filename' => $att['filename'],
                'mime_type' => $att['mime_type'],
                'size' => $att['size'],
                'created_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'id_message' => $id, 'attachments' => $attachments], 201);
    }

    public function deleteMessage(Request $request, $messageId)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        $msg = DB::table('messages')->where('id_message', $messageId)->first();
        if (!$msg) {
            return response()->json(['error' => 'Pesan tidak ditemukan'], 404);
        }
        // Only sender or admin can delete
        if ($msg->sender_id != $user->id_user && $user->id_role != 1) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        // Delete attachment files from disk
        $atts = DB::table('messages_attachments')->where('id_message', $messageId)->get();
        foreach ($atts as $att) {
            if (is_file(public_path($att->path))) {
                @unlink(public_path($att->path));
            }
        }
        DB::table('messages_attachments')->where('id_message', $messageId)->delete();
        DB::table('messages')->where('id_message', $messageId)->delete();
        return response()->json(['success' => true]);
    }

    public function sendNegotiation(Request $request)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        $data = $request->json()->all();
        $productId = (int)($data['id_product'] ?? 0);
        $sellerId = (int)($data['seller_id'] ?? 0);
        $harga = (int)($data['harga'] ?? 0);
        $pesan = $data['pesan'] ?? '';
        $namaProd = $data['nama_produk'] ?? '';
        $fotoProd = $data['foto_produk'] ?? '';
        $hargaProd = (int)($data['harga_produk'] ?? 0);

        if (!$sellerId || !$pesan) {
            return response()->json(['error' => 'seller_id dan pesan wajib diisi'], 422);
        }

        $p = DB::table('products')->where('id_product', $productId)->first();
        if ($p) {
            $namaProd = $p->nama_produk;
            $hargaProd = $p->harga;
            $fotoProd = $p->foto || '';
        }

        $msgText = "Nego: Harga Rp" . number_format($harga) . " untuk " . ($namaProd ?: "produk");
        if ($pesan) $msgText .= "
" . $pesan;

        DB::table('messages')->insert([
            'sender_id' => $user->id_user,
            'receiver_id' => $sellerId,
            'id_order' => null,
            'pesan' => $msgText,
            'id_product' => $productId ?: null,
            'nama_produk' => $namaProd ?: null,
            'harga' => $harga,
            'foto_produk' => $fotoProd ?: null,
            'sent_at' => now(),
        ]);

        $p = DB::table('products')->where('id_product', $productId)->first();
        $prodName = $p ? $p->nama_produk : "produk";
        DB::table('notifications')->insert([
            'id_user' => $sellerId,
            'id_order' => null,
            'tipe' => 'negotiation',
            'pesan' => "Ada nego baru untuk {$prodName} dari {$user->nama}.",
            'is_read' => 0,
            'metadata' => json_encode([
                'product_id' => $request->input('id_product'),
                'seller_id' => $sellerId,
                'buyer_id' => $user->id_user,
                // The chat counterpart for whoever clicks this notification.
                // This notification goes to the seller, so the partner is the buyer.
                'partner_id' => $user->id_user,
            ]),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function acceptNego(Request $request)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        if ($user->id_role !== 2) return response()->json(['error' => 'Hanya seller'], 403);

        $data = $request->json()->all();
        $msgId = (int)($data['message_id'] ?? 0);
        $idProduct = (int)($data['id_product'] ?? 0);
        $harga = (int)($data['harga'] ?? 0);
        $buyerId = (int)($data['buyer_id'] ?? 0);
        $namaProd = $data['nama_produk'] ?? '';

        if ($msgId < 1 || $idProduct < 1 || $buyerId < 1) {
            return response()->json(['error' => 'Data tidak valid'], 422);
        }

        DB::table('messages')->where('id_message', $msgId)->update(['read_at' => now()]);

        $orderId = DB::table('orders')->insertGetId([
            'id_buyer' => $buyerId,
            'id_seller' => $user->id_user,
            'tanggal_order' => now(),
            'total_amount' => $harga,
            'status_order' => 'pending',
            'alamat_pengiriman' => 'Akan diisi saat checkout',
        ]);

        // Create the escrow row for this negotiated order. Without it, the
        // buyer's pay() call updates a non-existent row and the money is
        // never held by the admin — so the admin has nothing to release.
        DB::table('payments_escrow')->insert([
            'id_order' => $orderId,
            'metode_pembayaran' => 'transfer',
            'jumlah' => $harga,
            'status_pembayaran' => 'pending',
            'status_escrow' => 'held',
        ]);

        DB::table('messages')->where('id_message', $msgId)->update(['id_order' => $orderId]);

        DB::table('notifications')->insert([
            'id_user' => $buyerId,
            'tipe' => 'order',
            'pesan' => "Nego Anda untuk {$namaProd} diterima! Order #{$orderId} dibuat.",
            'is_read' => 0,
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'order_id' => $orderId]);
    }

    public function rejectNego(Request $request)
    {
        $user = $this->requireLogin();
        if (!$user) return response()->json(['error' => 'Not authorized'], 401);

        $data = $request->json()->all();
        $msgId = (int)($data['message_id'] ?? 0);
        if ($msgId < 1) return response()->json(['error' => 'Data tidak valid'], 422);

        DB::table('messages')->where('id_message', $msgId)->update(['read_at' => now()]);

        DB::table('notifications')->insert([
            'id_user' => $user->id_user,
            'tipe' => 'negotiation',
            'pesan' => 'Nego Anda ditolak oleh seller.',
            'is_read' => 0,
            'created_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function counterNego(Request $request)
    {
        $user = $this->requireLogin();
        if (!$user) return response()->json(['error' => 'Not authorized'], 401);

        $data = $request->json()->all();
        $msgId = (int)($data['message_id'] ?? 0);
        $idProduct = (int)($data['id_product'] ?? 0);
        $buyerId = (int)($data['buyer_id'] ?? 0);
        $harga = (int)($data['harga'] ?? 0);

        if ($msgId < 1 || $idProduct < 1 || $buyerId < 1) {
            return response()->json(['error' => 'Data tidak valid'], 422);
        }

        DB::table('messages')->where('id_message', $msgId)->update(['read_at' => now()]);

        $product = DB::table('products')->where('id_product', $idProduct)->first();
        $prodName = $product->nama_produk ?? '';
        $prodFoto = $product->foto ?? '';

        DB::table('messages')->insert([
            'sender_id' => $user->id_user,
            'receiver_id' => $buyerId,
            'pesan' => "Banding: Saya terima Rp" . number_format($harga) . ". Apakah cocok?",
            'sent_at' => now(),
            'id_product' => $idProduct,
            'nama_produk' => $prodName,
            'harga' => $harga,
            'foto_produk' => $prodFoto,
        ]);

        DB::table('notifications')->insert([
            'id_user' => $buyerId,
            'tipe' => 'negotiation',
            'pesan' => "Banding: {$prodName} - Rp" . number_format($harga) . ". Klik untuk lihat detail.",
            'is_read' => 0,
            'created_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function conversations(Request $request)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }

        // A conversation only appears here when (a) messages already exist between
        // the user and that contact, AND (b) the role pair is allowed.
        // Allowed: buyer<->seller, seller<->admin, admin<->seller.
        // Roles: 1=admin, 2=seller, 3=buyer. Buyer can't chat with admin/other buyers;
        // admin can't chat with buyers.
        $uid = $user->id_user;
        $myRole = (int) $user->id_role;

        // contact role column via CASE; restrict to allowed (my_role, contact_role) pairs.
        $conds = [];
        if ($myRole === 3) {
            $conds[] = "contact_role = 2";           // buyer -> sellers only
        } elseif ($myRole === 2) {
            $conds[] = "contact_role IN (1,3)";      // seller -> admin or buyers
        } elseif ($myRole === 1) {
            $conds[] = "contact_role IN (2,3)";      // admin -> sellers or buyers
        }
        if (empty($conds)) {
            return response()->json([]);
        }
        $whereSql = '(' . implode(' AND ', $conds) . ')';

        $convos = DB::select(
            'SELECT u.id_user as contact_id, u.nama as contact_nama, '
            . 'MAX(m.sent_at) as last_sent_at, MAX(m.id_message) as last_id_message '
            . "FROM messages m "
            . 'JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = u.id_user '
            . 'WHERE (m.sender_id = ? OR m.receiver_id = ?) '
            . 'AND ' . str_replace('contact_role', 'u.id_role', $whereSql) . ' '
            . 'GROUP BY u.id_user, u.nama, u.id_role '
            . 'ORDER BY last_sent_at DESC',
            [$uid, $uid, $uid]
        );

        // Attach the latest message text as the preview.
        $convos = collect($convos)->transform(function ($c) {
            $last = DB::table('messages')->where('id_message', $c->last_id_message)->first(['pesan']);
            $c->pesan = $last->pesan ?? '';
            return $c;
        })->all();

        return response()->json($convos);
    }
}
