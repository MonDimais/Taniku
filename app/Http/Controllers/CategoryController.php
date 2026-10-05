<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
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
        $cats = DB::table('categories')->orderBy('nama_kategori')->get();
        return response()->json($cats);
    }

    public function store(Request $request)
    {
        $user = $this->requireRole('admin');
        if (!$user) {
            return response()->json(['error' => 'Admin only'], 401);
        }
        $data = $request->json()->all();
        DB::table('categories')->insert([
            'nama_kategori' => $data['nama_kategori'],
        ]);
        return response()->json(['success' => true], 201);
    }

    public function destroy(Request $request, $id)
    {
        $user = $this->requireRole('admin');
        if (!$user) {
            return response()->json(['error' => 'Admin only'], 401);
        }
        DB::table('product_categories')->where('id_kategori', $id)->delete();
        DB::table('categories')->where('id_kategori', $id)->delete();
        return response()->json(['success' => true]);
    }
}
