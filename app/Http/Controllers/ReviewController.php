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

    public function store(Request $request)
    {
        $user = $this->requireRole('buyer');
        if (!$user) {
            return response()->json(['error' => 'Buyer only'], 401);
        }
        $data = $request->json()->all();
        $rid = DB::table('reviews')->insertGetId([
            'id_order' => $data['id_order'] ?? null,
            'id_product' => $data['id_product'],
            'id_buyer' => $user->id_user,
            'rating' => (int)$data['rating'],
            'komentar' => $data['komentar'] ?? '',
            'created_at' => now(),
        ], 'id_review');
        $p = DB::table('products')->where('id_product', $data['id_product'])->first();
        if ($p) {
            $avg = DB::table('reviews')->where('id_product', $data['id_product'])->avg('rating');
            DB::table('seller_profiles')->where('id_seller', $p->id_seller)->update([
                'rating' => $avg ? $avg : 5.0,
            ]);
        }
        return response()->json(['success' => true, 'id_review' => $rid], 201);
    }
}
