<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class SellerController extends Controller
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

    public function getProfile(Request $request)
    {
        $user = $this->requireRole('seller');
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        $profile = DB::table('seller_profiles')
            ->join('users', 'seller_profiles.id_user', '=', 'users.id_user')
            ->where('seller_profiles.id_user', $user->id_user)
            ->select('seller_profiles.*', 'users.nama')
            ->first();
        return response()->json($profile ? (array) $profile : new \stdClass());
    }

    public function updateProfile(Request $request)
    {
        $user = $this->requireRole('seller');
        if (!$user) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        $data = $request->json()->all();
        DB::table('seller_profiles')
            ->where('id_user', $user->id_user)
            ->update([
                'nama_usaha' => $data['nama_usaha'] ?? null,
                'alamat' => $data['alamat'] ?? null,
                'no_telepon' => $data['no_telepon'] ?? null,
            ]);
        return response()->json(['success' => true]);
    }

    public function stats(Request $request)
    {
        $user = $this->requireRole('seller');
        if (!$user) {
            return response()->json(['error' => 'Seller only'], 401);
        }
        $sp = DB::table('seller_profiles')->where('id_user', $user->id_user)->first();
        if (!$sp) {
            return response()->json(new \stdClass());
        }
        $stats = [
            'total_products' => DB::table('products')->where('id_seller', $sp->id_seller)->count(),
            'active_products' => DB::table('products')->where('id_seller', $sp->id_seller)->where('status', 'active')->count(),
            'pending_products' => DB::table('products')->where('id_seller', $sp->id_seller)->where('status', 'pending')->count(),
            'total_orders' => DB::table('orders')->where('id_seller', $user->id_user)->count(),
            // Gross sales across all orders (informational).
            'total_revenue' => DB::table('orders')->where('id_seller', $user->id_user)->sum('total_amount') ?: 0,
            // Actual accrued earnings: grows as the admin releases each escrow.
            // This is the real "pendapatan" the seller can take out.
            'pendapatan' => (float) DB::table('users')->where('id_user', $user->id_user)->value('pendapatan') ?: 0,
            'rating' => DB::table('seller_profiles')->where('id_seller', $sp->id_seller)->value('rating'),
        ];
        return response()->json($stats);
    }
}
