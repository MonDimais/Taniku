<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
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

    public function stats(Request $request)
    {
        $user = $this->requireRole('admin');
        if (!$user) {
            return response()->json(['error' => 'Admin only'], 401);
        }
        $stats = [
            'total_users' => DB::table('users')->count(),
            // Non-admin account count (the "users I manage" figure).
            'total_accounts' => DB::table('users')->where('id_role', '!=', 1)->count(),
            'total_sellers' => DB::table('users')->where('id_role', 2)->count(),
            'total_buyers' => DB::table('users')->where('id_role', 3)->count(),
            'total_products' => DB::table('products')->count(),
            'pending_products' => DB::table('products')->where('status', 'pending')->count(),
            'rejected_products' => DB::table('products')->where('status', 'rejected')->count(),
            'total_orders' => DB::table('orders')->count(),
            'total_revenue' => DB::table('orders')->sum('total_amount') ?: 0,
            // Money currently held by the admin in escrow (paid, not yet released).
            'escrow_held' => (float) DB::table('payments_escrow')
                ->where('status_escrow', 'held')
                ->sum('jumlah') ?: 0,
            'open_disputes' => DB::table('disputes')->where('status', 'open')->count(),
        ];
        return response()->json($stats);
    }

    /**
     * GET /api/admin/users — the list of managed accounts (sellers + buyers).
     * Admin accounts are excluded. Only exposes name + role so the admin
     * dashboard shows just those two things, nothing sensitive.
     */
    public function users(Request $request)
    {
        $user = $this->requireRole('admin');
        if (!$user) {
            return response()->json(['error' => 'Admin only'], 401);
        }
        $rows = DB::table('users')
            ->where('id_role', '!=', 1)
            ->select('id_user', 'nama', 'id_role')
            ->orderBy('id_user', 'asc')
            ->get();

        $roleLabels = [2 => 'seller', 3 => 'buyer'];
        return response()->json([
            'users' => $rows->map(function ($r) use ($roleLabels) {
                return [
                    'id_user' => (int) $r->id_user,
                    'nama' => $r->nama,
                    'role' => $roleLabels[(int) $r->id_role] ?? 'user',
                ];
            })->all(),
            'total' => $rows->count(),
        ]);
    }
}
