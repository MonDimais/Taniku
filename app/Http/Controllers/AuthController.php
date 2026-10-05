<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class AuthController extends Controller
{
    private function getRoleMap()
    {
        return ['admin' => 1, 'seller' => 2, 'buyer' => 3];
    }

    public function register(Request $request)
    {
        $data = $request->json()->all();
        $role = $data['role'] ?? 'buyer';
        $roleMap = $this->getRoleMap();
        $roleId = $roleMap[$role] ?? 3;
        $password = hash('sha256', $data['password']);

        try {
            $userId = DB::table('users')->insertGetId([
                'id_role' => $roleId,
                'nama' => $data['nama'],
                'email' => $data['email'],
                'password' => $password,
                'status_verifikasi' => 1,
                'created_at' => now(),
            ], 'id_user');

            if ($role === 'seller') {
                $namaUsaha = $data['nama_usaha'] ?? $data['nama'];
                $alamat = $data['alamat'] ?? '';
                $noTelepon = $data['no_telepon'] ?? '';
                DB::table('seller_profiles')->insert([
                    'id_user' => $userId,
                    'nama_usaha' => $namaUsaha,
                    'alamat' => $alamat,
                    'no_telepon' => $noTelepon,
                    'rating' => 5.0,
                ]);
            }

            Session::put('user_id', $userId);
            return response()->json([
                'success' => true,
                'user_id' => $userId,
                'message' => 'Registrasi berhasil!'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function login(Request $request)
    {
        $data = $request->json()->all();

        if (!isset($data['email']) || !isset($data['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah'
            ], 401);
        }

        $password = hash('sha256', $data['password']);

        $user = DB::table('users')
            ->where('email', $data['email'])
            ->where('password', $password)
            ->first();

        if ($user) {
            Session::put('user_id', $user->id_user);
            return response()->json([
                'success' => true,
                'user_id' => $user->id_user,
                'role' => $user->id_role,
                'nama' => $user->nama,
                'avatar_url' => $user->avatar_url ?? null,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Email atau password salah'
        ], 401);
    }

    public function logout(Request $request)
    {
        Session::flush();
        return response()->json(['success' => true]);
    }

    public function uploadAvatar(Request $request)
    {
        $uid = Session::get('user_id');
        if (!$uid) return response()->json(['error' => 'Not authorized'], 401);
        $user = DB::table('users')->where('id_user', $uid)->first();
        if (!$user) return response()->json(['error' => 'Not authorized'], 401);

        if (!$request->hasFile('foto')) {
            return response()->json(['error' => 'File foto tidak diunggah'], 422);
        }

        $file = $request->file('foto');
        if ($file->getSize() > 5 * 1024 * 1024) {
            return response()->json(['error' => 'Maks 5MB'], 422);
        }

        $ext = $file->getClientOriginalExtension();
        $filename = 'avatar_' . $user->id_user . '_' . time() . '.' . $ext;
        $path = public_path('uploads/avatars/' . $filename);
        $dir = public_path('uploads/avatars/');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file->move($dir, $filename);

        $url = '/uploads/avatars/' . $filename;
        DB::table('users')->where('id_user', $user->id_user)->update(['avatar_url' => $url]);

        return response()->json(['success' => true, 'avatar_url' => $url]);
    }

    public function me(Request $request)
    {
        $uid = Session::get('user_id');
        if (!$uid) {
            return response()->json(['logged_in' => false], 401);
        }
        $user = DB::table('users')->where('id_user', $uid)->first();
        if (!$user) {
            return response()->json(['logged_in' => false], 401);
        }
        $roleMap = [1 => 'admin', 2 => 'seller', 3 => 'buyer'];
        $role = $roleMap[$user->id_role] ?? 'user';
        $userArr = (array) $user;
        // Don't leak the password hash to the client.
        unset($userArr['password']);
        return response()->json([
            'logged_in' => true,
            'user' => $userArr,
            'role' => $role,
        ]);
    }

    /**
     * POST /api/auth/address — save the logged-in user's shipping address.
     * Used by buyers (sellers use their seller_profiles.alamat instead).
     */
    public function updateAddress(Request $request)
    {
        $uid = Session::get('user_id');
        if (!$uid) {
            return response()->json(['error' => 'Not authorized'], 401);
        }
        $alamat = trim((string) ($request->input('alamat') ?? ''));
        DB::table('users')->where('id_user', $uid)->update(['alamat' => $alamat ?: null]);
        return response()->json(['success' => true, 'alamat' => $alamat ?: null]);
    }
}
