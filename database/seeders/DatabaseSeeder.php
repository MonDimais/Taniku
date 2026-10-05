<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $pw = function($x) {
            return hash('sha256', $x);
        };

        // Roles
        DB::table('roles')->insert([
            ['id_role' => 1, 'nama_role' => 'admin'],
            ['id_role' => 2, 'nama_role' => 'seller'],
            ['id_role' => 3, 'nama_role' => 'buyer'],
        ]);

        // Users
        $users = [
            [1, 'Budi Santoso', 'budi@taniku.com', $pw('budi123'), 1, 'seller'],
            [2, 'Siti Rahayu', 'siti@taniku.com', $pw('siti123'), 1, 'seller'],
            [3, 'Admin TaniKu', 'admin@taniku.com', $pw('admin123'), 1, 'admin'],
            [4, 'Andi Wijaya', 'andi@taniku.com', $pw('andi123'), 1, 'buyer'],
            [5, 'Dewi Lestari', 'dewi@taniku.com', $pw('dewi123'), 1, 'buyer'],
        ];
        foreach ($users as $u) {
            $roleId = ['admin' => 1, 'seller' => 2, 'buyer' => 3][$u[5]];
            DB::table('users')->insert([
                'id_user' => $u[0],
                'id_role' => $roleId,
                'nama' => $u[1],
                'email' => $u[2],
                'password' => $u[3],
                'status_verifikasi' => $u[4],
                'created_at' => now(),
            ]);
        }

        // Seller profiles
        DB::table('seller_profiles')->insert([
            ['id_seller' => 1, 'id_user' => 1, 'nama_usaha' => 'Sahabat Tani', 'alamat' => 'Jl. Raya Sawah No.1, Jakarta', 'no_telepon' => '081234567890', 'foto_shop' => null, 'rating' => 4.8],
            ['id_seller' => 2, 'id_user' => 2, 'nama_usaha' => 'Tani Makmur', 'alamat' => 'Jl. Pertanian No.5, Bandung', 'no_telepon' => '089876543210', 'foto_shop' => null, 'rating' => 4.6],
        ]);

        // Categories
        $cats = [
            [1, 'Sayuran'],
            [2, 'Bumbu Dapur'],
            [3, 'Buah-buahan'],
            [4, 'Biji ' . chr(38) . ' Benih'],
            [5, 'Pupuk ' . chr(38) . ' Pestisida'],
            [6, 'Alat Pertanian'],
        ];
        foreach ($cats as $c) {
            DB::table('categories')->insert([
                'id_kategori' => $c[0],
                'nama_kategori' => $c[1],
            ]);
        }

        // Products
        $products = [
            [1, 'Cabai Merah', 'Cabai merah segar kualitas premium, cocok untuk masakan pedas.', 15000, 50, 'kg', 1],
            [2, 'Bawang Merah', 'Bawang merah segar dari petani lokal, aroma kuat.', 25000, 100, 'kg', 1],
            [3, 'Tomat', 'Tomat merah segar dan matang sempurna, kaya vitamin.', 12000, 80, 'kg', 1],
            [4, 'Daun Kemangi', 'Kemangi segar untuk masakan Nusantara.', 5000, 200, 'ikat', 2],
            [5, 'Jahe', 'Jahe segar para bumbu dapur dan obat herbal.', 20000, 60, 'kg', 2],
            [6, 'Mentimun', 'Mentimun segar dan renyah, kaya air dan nutrisi.', 8000, 90, 'kg', 2],
            [7, 'Pupuk NPK', 'Pupuk NPK 15-15-15 untuk semua jenis tanaman.', 35000, 200, 'kg', 1],
            [8, 'Bibit Sawi', 'Bibit sawi pilihan, cepat tumbuh dan berbuah.', 3000, 500, 'paket', 2],
        ];
        foreach ($products as $p) {
            DB::table('products')->insert([
                'id_product' => $p[0],
                'id_seller' => $p[6],
                'nama_produk' => $p[1],
                'deskripsi' => $p[2],
                'harga' => $p[3],
                'stok' => $p[4],
                'satuan' => $p[5],
                'status' => 'active',
                'grade' => 'A',
                'foto' => '/static/uploads/product_' . $p[0] . '.jpg',
                'created_at' => now(),
            ]);
        }

        // Product categories
        DB::table('product_categories')->insert([
            ['id_product' => 1, 'id_kategori' => 1],
            ['id_product' => 1, 'id_kategori' => 2],
            ['id_product' => 2, 'id_kategori' => 1],
            ['id_product' => 3, 'id_kategori' => 3],
            ['id_product' => 4, 'id_kategori' => 2],
            ['id_product' => 5, 'id_kategori' => 2],
            ['id_product' => 6, 'id_kategori' => 1],
            ['id_product' => 7, 'id_kategori' => 5],
            ['id_product' => 8, 'id_kategori' => 4],
        ]);

        // Notifications for all users
        for ($uid = 1; $uid <= 5; $uid++) {
            DB::table('notifications')->insert([
                'id_user' => $uid,
                'id_order' => null,
                'tipe' => 'info',
                'pesan' => 'Selamat datang di TaniKu! Selamat berbelanja.',
                'is_read' => 0,
                'created_at' => now(),
            ]);
        }
    }
}
