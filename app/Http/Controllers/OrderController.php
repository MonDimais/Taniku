<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
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
        ]);
    }

    /**
     * Escrow is held by the admin until the buyer confirms receipt. This helper
     * marks the escrow released, accrues the seller's pendapatan, and accrues the
     * admin's platform fee. Called from the buyer-confirmation and admin-release
     * paths — idempotent (only fires while the escrow is still 'held').
     */
    private function releaseEscrow($order, $adminFeeRate = 0.0)
    {
        $escrow = DB::table('payments_escrow')->where('id_order', $order->id_order)->first();
        if (!$escrow || $escrow->status_escrow !== 'held') {
            return; // already released/refunded — don't double-pay
        }
        $amount = (float) $escrow->jumlah;
        $adminFee = round($amount * $adminFeeRate, 2);
        $sellerGets = $amount - $adminFee;

        DB::table('payments_escrow')->where('id_order', $order->id_order)->update([
            'status_escrow' => 'released',
            'released_at' => now(),
        ]);
        if ($sellerGets > 0) {
            DB::table('users')->where('id_user', $order->id_seller)->update([
                'pendapatan' => DB::raw('pendapatan + ' . $sellerGets),
            ]);
        }
        if ($adminFee > 0) {
            $admin = DB::table('users')->where('id_role', 1)->orderBy('id_user', 'asc')->first();
            if ($admin) {
                DB::table('users')->where('id_user', $admin->id_user)->update([
                    'pendapatan' => DB::raw('pendapatan + ' . $adminFee),
                ]);
            }
        }
    }

    public function index(Request $request)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        $roleMap = [1 => 'admin', 2 => 'seller', 3 => 'buyer'];
        $role = $roleMap[$user->id_role] ?? null;
        $query = DB::table('orders');
        if ($role == 'admin') {
            // all orders
        } elseif ($role == 'seller') {
            $query->where('id_seller', $user->id_user);
        } else {
            $query->where('id_buyer', $user->id_user);
        }
        $orders = $query->orderBy('tanggal_order', 'desc')->get();
        
        // Get all items for these orders in a single query
        $orderIds = $orders->pluck('id_order')->toArray();
        $allItems = DB::table('order_items')
            ->join('products', 'order_items.id_product', '=', 'products.id_product')
            ->whereIn('order_items.id_order', $orderIds)
            ->select('order_items.*', 'products.nama_produk', 'products.satuan', 'products.foto')
            ->get();
        
        // Group items by order_id
        $itemsByOrder = $allItems->groupBy('id_order');
        
        // Attach items to each order
        foreach ($orders as $order) {
            $order->items = $itemsByOrder->get($order->id_order, collect());
        }
        
        return response()->json($orders);
    }

    public function show(Request $request, $id)
    {
        $order = DB::table('orders')->where('id_order', $id)->first();
        if (!$order) return response()->json(null);
        $items = DB::table('order_items')
            ->join('products', 'order_items.id_product', '=', 'products.id_product')
            ->where('order_items.id_order', $id)
            ->select('order_items.*', 'products.nama_produk', 'products.satuan', 'products.foto')
            ->get();
        $payment = DB::table('payments_escrow')->where('id_order', $id)->first();
        $result = (array)$order;
        $result['items'] = $items;
        $result['payment'] = $payment;
        return response()->json($result);
    }

    public function store(Request $request)
    {
        $user = $this->requireRole('buyer');
        if (!$user) {
            return response()->json(['error' => 'Buyer only'], 401);
        }
        $data = $request->json()->all();
        $total = 0;
        $itemData = [];
        foreach ($data['items'] as $item) {
            $p = DB::table('products')
                ->where('id_product', $item['id_product'])
                ->where('stok', '>=', $item['quantity'])
                ->first();
            if (!$p) {
                return response()->json(['error' => 'Produk ' . $item['id_product'] . ' stok tidak mencukupi'], 400);
            }
            $subtotal = (float)$p->harga * $item['quantity'];
            $total += $subtotal;
            $itemData[] = [$item['id_product'], $item['quantity'], (float)$p->harga, $subtotal];
            DB::table('products')->where('id_product', $item['id_product'])->update([
                'stok' => DB::raw('stok - ' . (int)$item['quantity']),
            ]);
        }
        $sellerUser = DB::table('products')
            ->join('seller_profiles', 'products.id_seller', '=', 'seller_profiles.id_seller')
            ->join('users', 'seller_profiles.id_user', '=', 'users.id_user')
            ->where('products.id_product', $data['items'][0]['id_product'])
            ->select('users.id_user')
            ->first();
        $orderId = DB::table('orders')->insertGetId([
            'id_buyer' => $user->id_user,
            'id_seller' => $sellerUser->id_user,
            'total_amount' => $total,
            'status_order' => 'pending',
            'alamat_pengiriman' => $data['alamat_pengiriman'] ?? '',
            'tanggal_order' => now(),
        ], 'id_order');
        foreach ($itemData as $d) {
            DB::table('order_items')->insert([
                'id_order' => $orderId,
                'id_product' => $d[0],
                'quantity' => $d[1],
                'harga_satuan' => $d[2],
                'subtotal' => $d[3],
            ]);
        }
        DB::table('payments_escrow')->insert([
            'id_order' => $orderId,
            'metode_pembayaran' => $data['metode_pembayaran'] ?? 'transfer',
            'jumlah' => $total,
            'status_pembayaran' => 'pending',
            'status_escrow' => 'held',
        ]);
        $this->sendNotif($sellerUser->id_user, 'new_order', "Order #{$orderId} baru dari {$user->nama}. Total: Rp" . number_format($total, 0, ',', '.'), $orderId);
        return response()->json(['success' => true, 'id_order' => $orderId, 'total' => $total], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $user = $this->requireLogin();
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        $data = $request->json()->all();
        $newStatus = $data['status'] ?? null;
        $order = DB::table('orders')->where('id_order', $id)->first();
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }
        
        // Status transition rules
        // paid -> approved (seller packs + photo) -> shipped (seller ships + resi) -> confirmed (buyer) or disputed (buyer)
        
        // Validate seller can only transition specific statuses
        if ($user->id_role == 2 && $order->id_seller == $user->id_user) {
            // Seller can approve (paid -> approved) with packing photo
            if ($newStatus == 'approved' && $order->status_order == 'paid') {
                // Require packing photo
                if (empty($data['packing_photo'])) {
                    return response()->json(['error' => 'Packing photo wajib diisi'], 422);
                }
                DB::table('orders')->where('id_order', $id)->update([
                    'status_order' => 'approved',
                    'packing_photo' => $data['packing_photo'],
                ]);
                $this->sendNotif($order->id_buyer, 'order_approved', "Order #{$id} telah diapprove oleh seller. Barang sedang dikemas.", $id);
                return response()->json(['success' => true, 'status' => 'approved']);
            }
            // Seller can ship (approved -> shipped) with resi
            if ($newStatus == 'shipped' && $order->status_order == 'approved') {
                // Require resi
                if (empty($data['tracking_number']) || empty($data['shipping_service'])) {
                    return response()->json(['error' => 'Tracking number dan shipping service wajib diisi'], 422);
                }
                DB::table('orders')->where('id_order', $id)->update([
                    'status_order' => 'shipped',
                    'tracking_number' => $data['tracking_number'],
                    'shipping_service' => $data['shipping_service'],
                    'shipping_cost' => $data['shipping_cost'] ?? 0,
                ]);
                $this->sendNotif($order->id_buyer, 'order_shipped', "Order #{$id} telah dikirim oleh seller. Tracking: {$data['tracking_number']} ({$data['shipping_service']}).", $id);
                return response()->json(['success' => true, 'status' => 'shipped']);
            }
            return response()->json(['error' => 'Seller tidak dapat mengubah status ini'], 403);
        }
        
        // Validate buyer can only confirm receipt
        if ($user->id_role == 3 && $order->id_buyer == $user->id_user) {
            if ($newStatus == 'confirmed' && $order->status_order == 'shipped') {
                DB::table('orders')->where('id_order', $id)->update([
                    'status_order' => 'confirmed',
                ]);
                $this->sendNotif($order->id_seller, 'order_confirmed', "Buyer mengonfirmasi menerima barang untuk Order #{$id}.", $id);
                $this->sendNotif($user->id_user, 'awaiting_release', "Order #{$id} menunggu admin untuk persetujuan & pencairan dana. Anda bisa ajukan dispute jika ada masalah.", $id);
                return response()->json(['success' => true, 'status' => 'confirmed']);
            }
            return response()->json(['error' => 'Buyer hanya dapat konfirmasi terima barang dari order shipped. Untuk dispute, gunakan tombol Ajukan Dispute.'], 403);
        }
        
        // Admin can override any status
        if ($user->id_role == 1) {
            // Admin confirming a status only changes the order status — it does
            // NOT move money. Escrow release happens only via the dedicated
            // release endpoint (POST /orders/{id}/release), which accrues the
            // seller's pendapatan. Releasing here would make the "Cairkan Dana"
            // button report "escrow already released" every time.
            DB::table('orders')->where('id_order', $id)->update([
                'status_order' => $newStatus,
                'tracking_number' => $data['tracking_number'] ?? $order->tracking_number,
                'shipping_service' => $data['shipping_service'] ?? $order->shipping_service,
                'shipping_cost' => $data['shipping_cost'] ?? $order->shipping_cost,
            ]);
            $otherUser = null;
            if ($user->id_user == $order->id_buyer) {
                $otherUser = $order->id_seller;
            } elseif ($user->id_user == $order->id_seller) {
                $otherUser = $order->id_buyer;
            }
            if ($otherUser) {
                $this->sendNotif($otherUser, 'order_update', "Status Order #{$id} berubah menjadi: {$newStatus}", $id);
            }
            return response()->json(['success' => true, 'status' => $newStatus]);
        }
        
        return response()->json(['error' => 'Unauthorized status change'], 403);
    }

    /**
     * POST /api/orders/{id}/release — admin releases held escrow.
     * This is the money-transfer step: it runs after the buyer confirms receipt
     * (status 'confirmed') and no dispute exists, so the seller actually gets
     * paid. Sets the order to 'completed' and accrues the seller's pendapatan
     * (minus the admin's platform fee). Idempotent: returns 400 if already paid.
     */
    public function releaseEscrowEndpoint(Request $request, $id)
    {
        $user = $this->requireRole('admin');
        if (!$user) {
            return response()->json(['error' => 'Admin only'], 401);
        }
        $order = DB::table('orders')->where('id_order', $id)->first();
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }
        // Demo mode: the admin can release from any order status. We only block
        // a duplicate release — once the escrow is paid out, it's gone.
        $escrow = DB::table('payments_escrow')->where('id_order', $id)->first();
        if (!$escrow) {
            return response()->json(['error' => 'Order ini tidak punya escrow payment'], 400);
        }
        if ($escrow->status_escrow !== 'held') {
            return response()->json([
                'error' => 'Escrow tidak dalam status dipegang admin (dapat jadi sudah dirilis)',
                'current_status' => $escrow->status_escrow,
            ], 400);
        }

        // 5% platform fee to admin, remainder to seller.
        $this->releaseEscrow($order, 0.05);
        DB::table('orders')->where('id_order', $id)->update(['status_order' => 'completed']);

        $this->sendNotif($order->id_seller, 'escrow_released', "Admin telah mencairkan dana Order #{$id}. Pendapatan Anda bertambah.", $id);
        $this->sendNotif($order->id_buyer, 'escrow_released', "Admin telah mencairkan dana untuk Order #{$id}. Transaksi selesai.", $id);

        $seller = DB::table('users')->where('id_user', $order->id_seller)->first(['pendapatan']);
        return response()->json([
            'success' => true,
            'status' => 'completed',
            'seller_pendapatan' => $seller ? (float) $seller->pendapatan : 0,
        ]);
    }
    
    public function uploadPackingPhoto(Request $request, $id)
    {
        $user = $this->requireRole('seller');
        if (!$user) {
            return response()->json(['error' => 'Seller only'], 401);
        }
        $order = DB::table('orders')->where('id_order', $id)->first();
        if (!$order || $order->id_seller != $user->id_user) {
            return response()->json(['error' => 'Order tidak ditemukan atau bukan milik Anda'], 403);
        }
        if (!$request->hasFile('packing_photo')) {
            return response()->json(['error' => 'File packing photo tidak diunggah'], 422);
        }
        $file = $request->file('packing_photo');
        if ($file->getSize() > 5 * 1024 * 1024) {
            return response()->json(['error' => 'Maks 5MB'], 422);
        }
        $ext = $file->getClientOriginalExtension();
        $filename = 'packing_' . $id . '_' . time() . '.' . $ext;
        $dir = public_path('uploads/packing/');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file->move($dir, $filename);
        $url = '/uploads/packing/' . $filename;
        DB::table('orders')->where('id_order', $id)->update(['packing_photo' => $url]);
        return response()->json(['success' => true, 'packing_photo' => $url]);
    }
}
