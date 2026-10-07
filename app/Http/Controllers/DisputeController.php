<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class DisputeController extends Controller
{
    private function requireRole($role)
    {
        $uid = Session::get('user_id');
        if (!$uid) return null;
        $user = DB::table('users')->where('id_user', $uid)->first();
        if (!$user) return null;
        $roleMap = ['admin' => 1, 'seller' => 2, 'buyer' => 3];
        if ($user->id_role != ($roleMap[$role] ?? 0)) return null;
        return $user;
    }

    private function requireLogin()
    {
        $uid = Session::get('user_id');
        if (!$uid) return null;
        $user = DB::table('users')->where('id_user', $uid)->first();
        return $user;
    }

    private function sendNotif($userId, $tipe, $pesan, $orderId = null)
    {
        DB::table('notifications')->insert([
            'id_user' => $userId,
            'id_order' => $orderId,
            'tipe' => $tipe,
            'pesan' => $pesan,
            'is_read' => 0,
            'created_at' => now(),
            'metadata' => $orderId ? json_encode(['order_id' => $orderId]) : null,
        ]);
    }

    public function index(Request $request)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        $roleMap = [1 => 'admin', 2 => 'seller', 3 => 'buyer'];
        $role = $roleMap[$user->id_role] ?? null;
        $query = DB::table('disputes')
            ->join('orders', 'disputes.id_order', '=', 'orders.id_order')
            ->select('disputes.*', 'orders.id_buyer', 'orders.id_seller');
        if ($role != 'admin') {
            $query->where('orders.id_buyer', $user->id_user)
                  ->orWhere('orders.id_seller', $user->id_user);
        }
        $disputes = $query->orderBy('disputes.id_dispute', 'desc')->get();
        return response()->json($disputes);
    }

    public function store(Request $request)
    {
        $user = $this->requireRole('buyer');
        if (!$user) {
            return response()->json(['error' => 'Buyer only'], 401);
        }
        $data = $request->json()->all();
        $orderId = (int)($data['id_order'] ?? 0);
        $order = DB::table('orders')->where('id_order', $orderId)->first();
        if (!$order || $order->id_buyer != $user->id_user) {
            return response()->json(['error' => 'Order tidak valid'], 403);
        }
        if (!in_array($order->status_order, ['confirmed'])) {
            return response()->json(['error' => 'Hanya order yang sudah diterima (confirmed) yang bisa di-dispute'], 400);
        }
        $did = DB::table('disputes')->insertGetId([
            'id_order' => $orderId,
            'opened_by' => $user->id_user,
            'alasan' => $data['alasan'] ?? '',
            'deskripsi' => $data['deskripsi'] ?? '',
            'status' => 'open',
        ], 'id_dispute');
        // Set order to disputed status
        DB::table('orders')->where('id_order', $orderId)->update(['status_order' => 'disputed']);
        // Notify seller and admin
        $this->sendNotif($order->id_seller, 'dispute_opened', 'Dispute untuk Order #' . $orderId . ' dibuka: ' . ($data['alasan'] ?? ''), $orderId);
        $this->sendNotif($user->id_user, 'dispute_opened', 'Anda telah membuka dispute untuk Order #' . $orderId . '.', $orderId);
        // Notify all admins
        $admins = DB::table('users')->where('id_role', 1)->get();
        foreach ($admins as $admin) {
            $this->sendNotif($admin->id_user, 'dispute_opened', 'Dispute baru untuk Order #' . $orderId . ' dari ' . $user->nama, $orderId);
        }
        return response()->json(['success' => true, 'id_dispute' => $did], 201);
    }

    public function getMessages(Request $request, $id)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        $dispute = DB::table('disputes')->where('id_dispute', $id)->first();
        if (!$dispute) {
            return response()->json(['error' => 'Dispute not found'], 404);
        }
        $order = DB::table('orders')->where('id_order', $dispute->id_order)->first();
        // Authorize: admin, or buyer/seller of the order
        if ($user->id_role != 1 && $order->id_buyer != $user->id_user && $order->id_seller != $user->id_user) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $msgs = DB::table('dispute_messages')
            ->join('users', 'dispute_messages.sender_id', '=', 'users.id_user')
            ->where('dispute_messages.id_dispute', $id)
            ->select('dispute_messages.*', 'users.nama as sender_nama')
            ->orderBy('dispute_messages.sent_at', 'asc')
            ->get();
        // Attach related dispute_attachments for each message
        $attRows = DB::table('dispute_attachments')
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

    public function send(Request $request, $id)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        $dispute = DB::table('disputes')->where('id_dispute', $id)->first();
        if (!$dispute) {
            return response()->json(['error' => 'Dispute not found'], 404);
        }
        if ($dispute->status != 'open') {
            return response()->json(['error' => 'Dispute sudah ditutup'], 400);
        }
        $order = DB::table('orders')->where('id_order', $dispute->id_order)->first();
        if ($user->id_role != 1 && $order->id_buyer != $user->id_user && $order->id_seller != $user->id_user) {
            return response()->json(['error' => 'Unauthorized'], 403);
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
                $filename = 'dispute_' . $id . '_' . time() . '_' . uniqid() . '.' . $ext;
                $file->storeAs('public/uploads/disputes', $filename);
                $path = '/storage/uploads/disputes/' . $filename;
                $attachments[] = [
                    'path' => $path,
                    'filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }
        // Legacy single-file support
        if (empty($attachments) && $request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $maxSize = 10 * 1024 * 1024;
            if ($file->getSize() > $maxSize) {
                return response()->json(['error' => 'File terlalu besar (maks 10MB)'], 422);
            }
            $allowedExtensions = ['jpg','jpeg','png','gif','webp','mp4','mov','webm','pdf','doc','docx','xls','xlsx','csv','mp3','wav','ogg','aac'];
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, $allowedExtensions)) {
                return response()->json(['error' => 'Tipe file tidak diizinkan'], 422);
            }
            $filename = 'dispute_' . $id . '_' . time() . '_' . uniqid() . '.' . $ext;
            $file->storeAs('public/uploads/disputes', $filename);
            $path = '/storage/uploads/disputes/' . $filename;
            $attachments[] = [
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        // pesan: support both JSON and FormData (multipart when file attached)
        // IMPORTANT: do not read the JSON body stream on multipart requests — it consumes
        // the request body and makes $request->input('pesan') return null.
        $pesan = trim($request->input('pesan') ?? '');
        if ($pesan === '' && ($request->header('Content-Type') ?? '') !== '') {
            $ct = $request->header('Content-Type');
            if (stripos($ct, 'application/json') !== false) {
                $pesan = trim($request->json()->input('pesan') ?? '');
            }
        }
        if (empty($pesan) && empty($attachments)) {
            return response()->json(['error' => 'Pesan atau file wajib diisi'], 422);
        }
        if (empty($pesan)) {
            $pesan = '[File attachment]';
        }

        $mid = DB::table('dispute_messages')->insertGetId([
            'id_dispute' => $id,
            'sender_id' => $user->id_user,
            'pesan' => $pesan,
            'attachment' => null, // legacy column kept for backward compat
            'sent_at' => now(),
        ], 'id_message');

        // Insert attachment rows
        foreach ($attachments as $att) {
            DB::table('dispute_attachments')->insert([
                'id_message' => $mid,
                'path' => $att['path'],
                'filename' => $att['filename'],
                'mime_type' => $att['mime_type'],
                'size' => $att['size'],
                'created_at' => now(),
            ]);
        }
        
        // Notify other participants
        $others = [
            $order->id_buyer,
            $order->id_seller,
        ];
        // Add all admins if sender is not admin
        if ($user->id_role != 1) {
            foreach (DB::table('users')->where('id_role', 1)->pluck('id_user') as $aid) {
                $others[] = $aid;
            }
        } else {
            // If admin sends, notify buyer and seller
        }
        $others = array_unique(array_filter($others, function($v) use ($user) {
            return $v != $user->id_user;
        }));
        foreach ($others as $oid) {
            $this->sendNotif($oid, 'dispute_message', "Pesan baru di dispute Order #{$dispute->id_order}", $dispute->id_order);
        }
        return response()->json(['success' => true, 'id_message' => $mid, 'attachments' => $attachments], 201);
    }

    public function deleteMessage(Request $request, $id, $message)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        $msg = DB::table('dispute_messages')->where('id_message', $message)->first();
        if (!$msg) {
            return response()->json(['error' => 'Pesan tidak ditemukan'], 404);
        }
        // Only sender or admin can delete
        if ($msg->sender_id != $user->id_user && $user->id_role != 1) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        // Delete attachment files from disk (multi-file via dispute_attachments table)
        $atts = DB::table('dispute_attachments')->where('id_message', $message)->get();
        foreach ($atts as $att) {
            $rel = $att->path;
            if (is_file(public_path($rel))) {
                @unlink(public_path($rel));
            }
        }
        // Also handle legacy single-attachment column
        if ($msg->attachment) {
            $rel = $msg->attachment;
            if (is_file(public_path($rel))) {
                @unlink(public_path($rel));
            }
        }
        // Delete DB rows (attachments cascade via FK, but explicit for safety)
        DB::table('dispute_attachments')->where('id_message', $message)->delete();
        DB::table('dispute_messages')->where('id_message', $message)->delete();
        return response()->json(['success' => true]);
    }

    public function resolve(Request $request, $id)
    {
        $user = $this->requireRole('admin');
        if (!$user) {
            return response()->json(['error' => 'Admin only'], 401);
        }
        $dispute = DB::table('disputes')->where('id_dispute', $id)->first();
        if (!$dispute) {
            return response()->json(['error' => 'Dispute not found'], 404);
        }
        if ($dispute->status != 'open') {
            return response()->json(['error' => 'Dispute sudah di-resolve'], 400);
        }
        $data = $request->json()->all();
        $resolution = $data['resolution'] ?? ''; // refund, partial_release, full_release
        if (!in_array($resolution, ['refund', 'partial_release', 'full_release'])) {
            return response()->json(['error' => 'Resolution invalid'], 422);
        }
        $amount = (float)($data['amount'] ?? 0);
        $desc = $data['deskripsi'] ?? '';
        $order = DB::table('orders')->where('id_order', $dispute->id_order)->first();

        DB::table('disputes')->where('id_dispute', $id)->update([
            'status' => 'resolved',
            'resolved_by' => $user->id_user,
            'resolved_at' => now(),
            'deskripsi' => $desc,
        ]);

        // Post resolution message to the thread
        $resolutionText = match($resolution) {
            'refund' => "RESOLUSI ADMIN: REFUND penuh ke buyer (Rp{$amount}).",
            'partial_release' => "RESOLUSI ADMIN: PARTIAL RELEASE - Rp{$amount} ke seller.",
            'full_release' => "RESOLUSI ADMIN: FULL RELEASE - dana penuh ke seller.",
        };
        DB::table('dispute_messages')->insert([
            'id_dispute' => $id,
            'sender_id' => $user->id_user,
            'pesan' => $resolutionText,
            'sent_at' => now(),
        ]);

        // Update order status and escrow
        if ($resolution == 'refund') {
            DB::table('orders')->where('id_order', $dispute->id_order)->update([
                'status_order' => 'refunded',
                'dispute_resolution' => 'refund',
            ]);
            // Refund escrow back to buyer - set status to refunded
            DB::table('payments_escrow')->where('id_order', $dispute->id_order)->update([
                'status_escrow' => 'refunded',
                'released_at' => now(),
            ]);
            $this->sendNotif($order->id_buyer, 'dispute_resolved', "Dispute Order #{$dispute->id_order} di-resolve: REFUND ke Anda.", $dispute->id_order);
            $this->sendNotif($order->id_seller, 'dispute_resolved', "Dispute Order #{$dispute->id_order} di-resolve: dana di-refund ke buyer.", $dispute->id_order);
        } elseif ($resolution == 'partial_release') {
            DB::table('orders')->where('id_order', $dispute->id_order)->update([
                'status_order' => 'confirmed',
                'dispute_resolution' => 'partial_release',
            ]);
            // Partial release - release to seller, note partial
            DB::table('payments_escrow')->where('id_order', $dispute->id_order)->update([
                'status_escrow' => 'partial_released',
                'released_at' => now(),
            ]);
            $this->sendNotif($order->id_seller, 'escrow_released', "Partial release untuk Order #{$dispute->id_order}: Rp{$amount} dirilis ke Anda.", $dispute->id_order);
            $this->sendNotif($order->id_buyer, 'dispute_resolved', "Dispute Order #{$dispute->id_order}: partial release, sisa dana di-refund.", $dispute->id_order);
        } else {
            // full_release
            DB::table('orders')->where('id_order', $dispute->id_order)->update([
                'status_order' => 'confirmed',
                'dispute_resolution' => 'full_release',
            ]);
            $escrow = DB::table('payments_escrow')->where('id_order', $dispute->id_order)->first();
            if ($escrow && $escrow->status_escrow === 'held') {
                DB::table('payments_escrow')->where('id_order', $dispute->id_order)->update([
                    'status_escrow' => 'released',
                    'released_at' => now(),
                ]);
                DB::table('users')->where('id_user', $order->id_seller)->update([
                    'pendapatan' => DB::raw('pendapatan + ' . (float) $escrow->jumlah),
                ]);
            }
            $this->sendNotif($order->id_seller, 'escrow_released', "Escrow untuk Order #{$dispute->id_order} telah dirilis penuh.", $dispute->id_order);
            $this->sendNotif($order->id_buyer, 'dispute_resolved', "Dispute Order #{$dispute->id_order} di-resolve: full release ke seller.", $dispute->id_order);
        }

        return response()->json(['success' => true]);
    }
}
