<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
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
        return DB::table('users')->where('id_user', $uid)->first();
    }

    public function index(Request $request)
    {
        $reviews = DB::table('reviews')
            ->join('users', 'reviews.id_buyer', '=', 'users.id_user')
            ->join('products', 'reviews.id_product', '=', 'products.id_product')
            ->select('reviews.*', 'users.nama as buyer_nama', 'products.nama_produk')
            ->orderBy('reviews.created_at', 'desc')
            ->get();
        return response()->json($reviews);
    }

    /**
     * GET /api/reviews/product/{id} — all reviews for a product, plus avg + count.
     * Used by the product-detail modal to render the "Review Produk" section.
     */
    public function forProduct($id)
    {
        $reviews = DB::table('reviews')
            ->join('users', 'reviews.id_buyer', '=', 'users.id_user')
            ->where('reviews.id_product', $id)
            ->select('reviews.*', 'users.nama as buyer_nama')
            ->orderBy('reviews.created_at', 'desc')
            ->get();
        $avg = DB::table('reviews')->where('id_product', $id)->avg('rating');
        $count = DB::table('reviews')->where('id_product', $id)->count();
        return response()->json([
            'reviews' => $reviews,
            'avg_rating' => $avg ? round((float)$avg, 2) : 0,
            'count' => $count,
        ]);
    }

    /**
     * GET /api/reviews/order/{id} — reviews already written for a specific order.
     * The buyer's rating-modal uses this to pre-fill + disable already-rated items.
     */
    public function forOrder($id)
    {
        $reviews = DB::table('reviews')
            ->join('users', 'reviews.id_buyer', '=', 'users.id_user')
            ->join('products', 'reviews.id_product', '=', 'products.id_product')
            ->where('reviews.id_order', $id)
            ->select('reviews.*', 'users.nama as buyer_nama', 'products.nama_produk')
            ->get();
        return response()->json($reviews);
    }

    /**
     * GET /api/reviews/my — all reviews by the current logged-in user.
     * Frontend caches this as a Set of "orderId:productId" to decide whether
     * to show a "Beri Rating" button on each buyer order card.
     */
    public function mine()
    {
        $user = $this->requireLogin();
        if (!$user) return response()->json(['error' => 'Not authorized'], 401);
        $reviews = DB::table('reviews')
            ->join('products', 'reviews.id_product', '=', 'products.id_product')
            ->where('reviews.id_buyer', $user->id_user)
            ->select('reviews.*', 'products.nama_produk')
            ->orderBy('reviews.created_at', 'desc')
            ->get();
        return response()->json($reviews);
    }

    /**
     * POST /api/reviews — buyer rates one item from a received order.
     * Idempotent: only one review per (id_buyer, id_order, id_product).
     */
    public function store(Request $request)
    {
        $user = $this->requireRole('buyer');
        if (!$user) {
            return response()->json(['error' => 'Buyer only'], 401);
        }
        $data = $request->json()->all();
        $productId = (int)($data['id_product'] ?? 0);
        $orderId = (isset($data['id_order']) && $data['id_order'] !== '' && $data['id_order'] !== null)
            ? (int)$data['id_order'] : null;
        $rating = (int)($data['rating'] ?? 0);
        $komentar = trim((string)($data['komentar'] ?? ''));

        if (!$productId) return response()->json(['error' => 'id_product wajib diisi'], 422);
        if ($rating < 1 || $rating > 5) return response()->json(['error' => 'Rating harus 1-5 bintang'], 422);

        // Order ownership + status guard.
        if ($orderId) {
            $order = DB::table('orders')->where('id_order', $orderId)->first();
            if (!$order || $order->id_buyer != $user->id_user) {
                return response()->json(['error' => 'Order tidak valid'], 403);
            }
            if (!in_array($order->status_order, ['confirmed', 'completed'])) {
                return response()->json(['error' => 'Hanya order yang sudah diterima (confirmed) yang bisa di-review'], 422);
            }
            $item = DB::table('order_items')
                ->where('id_order', $orderId)
                ->where('id_product', $productId)
                ->first();
            if (!$item) {
                return response()->json(['error' => 'Produk bukan bagian dari order ini'], 422);
            }
        }

        // Idempotency: one review per (buyer, order, product).
        $existingQuery = DB::table('reviews')
            ->where('id_buyer', $user->id_user)
            ->where('id_product', $productId);
        if ($orderId) {
            $existingQuery->where('id_order', $orderId);
        } else {
            $existingQuery->whereNull('id_order');
        }
        $existing = $existingQuery->first();
        if ($existing) {
            return response()->json([
                'error' => 'Anda sudah memberi rating untuk produk ini',
                'id_review' => $existing->id_review,
            ], 409);
        }

        $rid = DB::table('reviews')->insertGetId([
            'id_order' => $orderId,
            'id_product' => $productId,
            'id_buyer' => $user->id_user,
            'rating' => $rating,
            'komentar' => $komentar,
            'created_at' => now(),
        ], 'id_review');

        // Refresh the seller's profile rating = this product's average.
        $p = DB::table('products')->where('id_product', $productId)->first(['id_seller']);
        if ($p) {
            $avg = DB::table('reviews')->where('id_product', $productId)->avg('rating');
            DB::table('seller_profiles')->where('id_seller', $p->id_seller)->update([
                'rating' => $avg ? round((float)$avg, 2) : 5.0,
            ]);
        }

        return response()->json(['success' => true, 'id_review' => $rid], 201);
    }
}
