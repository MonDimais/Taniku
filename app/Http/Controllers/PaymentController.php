<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
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

    private function sendNotif($userId, $tipe, $pesan, $orderId = null)
    {
        DB::table('notifications')->insert([
            'id_user' => $userId,
            'id_order' => $orderId,
            'tipe' => $tipe,
            'pesan' => $pesan,
            'is_read' => 0,
            'created_at' => now(),
        ]);
    }

    public function show(Request $request, $orderId)
    {
        $p = DB::table('payments_escrow')->where('id_order', $orderId)->first();
        return response()->json($p ? (array)$p : null);
    }

    public function pay(Request $request, $orderId)
    {
        $user = $this->requireRole('buyer');
        if (!$user) {
            return response()->json(['error' => 'Buyer only'], 401);
        }
        $order = DB::table('orders')->where('id_order', $orderId)->first();
        if (!$order) {
            return response()->json(['error' => 'Order tidak ditemukan'], 404);
        }
        $escrow = DB::table('payments_escrow')->where('id_order', $orderId)->first();
        if (!$escrow) {
            // Defensive: if no escrow row exists for this order (e.g. an order
            // created before the row was added), create one with the held
            // balance = the order total. Without this the buyer's payment would
            // never be held and the admin would have nothing to release.
            DB::table('payments_escrow')->insert([
                'id_order' => $orderId,
                'metode_pembayaran' => 'transfer',
                'jumlah' => $order->total_amount,
                'status_pembayaran' => 'pending',
                'status_escrow' => 'held',
            ]);
        }
        DB::table('payments_escrow')->where('id_order', $orderId)->update([
            'status_pembayaran' => 'paid',
            'status_escrow' => 'held',
            'paid_at' => now(),
        ]);
        DB::table('orders')->where('id_order', $orderId)->update([
            'status_order' => 'paid',
        ]);
        $this->sendNotif($order->id_seller, 'payment_received', "Pembayaran Order #{$orderId} telah diterima dan di-escrow.", $orderId);
        return response()->json(['success' => true]);
    }

    public function release(Request $request, $orderId)
    {
        $user = $this->requireRole('admin');
        if (!$user) {
            return response()->json(['error' => 'Admin only'], 401);
        }
        DB::table('payments_escrow')->where('id_order', $orderId)->update([
            'status_escrow' => 'released',
            'released_at' => now(),
        ]);
        DB::table('orders')->where('id_order', $orderId)->update([
            'status_order' => 'completed',
        ]);
        return response()->json(['success' => true]);
    }
}
