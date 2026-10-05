<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProductController extends Controller
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

    /**
     * Send notification to all admin users.
     */
    private function notifyAdmins($tipe, $pesan, $orderId = null)
    {
        $admins = DB::table('users')->where('id_role', 1)->pluck('id_user');
        foreach ($admins as $adminId) {
            DB::table('notifications')->insert([
                'id_user' => $adminId,
                'id_order' => $orderId,
                'tipe' => $tipe,
                'pesan' => $pesan,
                'is_read' => 0,
                'created_at' => now(),
            ]);
        }
    }

    public function index(Request $request)
    {
        $query = DB::table('products')
            ->join('seller_profiles', 'products.id_seller', '=', 'seller_profiles.id_seller')
            ->join('users', 'seller_profiles.id_user', '=', 'users.id_user')
            ->leftJoin('product_categories', 'product_categories.id_product', '=', 'products.id_product')
            ->leftJoin('categories', 'categories.id_kategori', '=', 'product_categories.id_kategori')
            ->select(
                'products.*',
                'users.nama as seller_nama',
                'seller_profiles.nama_usaha',
                DB::raw('GROUP_CONCAT(categories.nama_kategori) as categories')
            );

        if ($request->has('category')) {
            $query->where('categories.id_kategori', (int)$request->query('category'));
        }
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function($q) use ($search) {
                $q->where('products.nama_produk', 'like', '%' . $search . '%')
                  ->orWhere('products.deskripsi', 'like', '%' . $search . '%');
            });
        }
        if ($request->has('seller')) {
            $query->where('users.id_user', (int)$request->query('seller'));
        }
        if ($request->has('status')) {
            $query->where('products.status', $request->query('status'));
        } elseif (!$request->has('seller')) {
            // Public listing: only show active products (not pending/rejected)
            $query->where('products.status', 'active');
        }

        $products = $query->groupBy('products.id_product')->orderBy('products.created_at', 'desc')->get()
            ->map(function ($item) {
                $item->foto = $this->resolvePhotoUrl($item->foto);
                return $item;
            });
        return response()->json($products);
    }

    public function show(Request $request, $id)
    {
        $p = DB::table('products')
            ->join('seller_profiles', 'products.id_seller', '=', 'seller_profiles.id_seller')
            ->join('users', 'seller_profiles.id_user', '=', 'users.id_user')
            ->leftJoin('product_categories', 'product_categories.id_product', '=', 'products.id_product')
            ->leftJoin('categories', 'categories.id_kategori', '=', 'product_categories.id_kategori')
            ->select(
                'products.*',
                'users.nama as seller_nama',
                'users.id_user as seller_id',
                'seller_profiles.nama_usaha',
                'seller_profiles.rating as seller_rating',
                DB::raw('GROUP_CONCAT(categories.nama_kategori) as categories')
            )
            ->where('products.id_product', $id)
            ->groupBy('products.id_product')
            ->first();

        if (!$p) {
            return response()->json(null);
        }

        $result = (array)$p;
        // foto is persisted as a storage-relative path (e.g. uploads/products/8/foo.jpg).
        // Normalize it to a real web URL so the frontend can render it directly.
        $result['foto'] = $this->resolvePhotoUrl($p->foto ?? null);
        $result['documents'] = $this->documentsForProduct($id);
        return response()->json($result);
    }

    /**
     * Shared formatter for product documents.
     */
    private function documentsForProduct($id)
    {
        $docs = DB::table('product_documents')
            ->where('id_product', $id)
            ->orderBy('id_document', 'desc')
            ->get()
            ->map(function ($d) {
                return [
                    'id_document' => (int)$d->id_document,
                    'id_product' => (int)$d->id_product,
                    'nama_berkas' => $d->nama_berkas,
                    'tipe_berkas' => $d->tipe_berkas,
                    'ukuran' => (int)$d->ukuran,
                    'ukuran_formatted' => $this->formatBytes((int)$d->ukuran),
                    'url' => $this->documentUrl($d->path_berkas),
                    'path_berkas' => $d->path_berkas,
                    'created_at' => (string)$d->created_at,
                ];
            });

        return $docs;
    }

    /**
     * GET /api/products/{id}/documents
     */
    public function documents(Request $request, $id)
    {
        $p = DB::table('products')->where('id_product', $id)->first();
        if (!$p) {
            return response()->json(['error' => 'Produk tidak ditemukan'], 404);
        }

        return response()->json([
            'id_product' => (int)$id,
            'total' => count($this->documentsForProduct($id)),
            'documents' => $this->documentsForProduct($id),
        ]);
    }

    /**
     * POST /api/products/{id}/documents - multipart upload, seller only.
     */
    public function uploadDocument(Request $request, $id)
    {
        $user = $this->requireRole('seller');
        if (!$user) {
            return response()->json(['error' => 'Seller only'], 401);
        }

        $product = DB::table('products')->where('id_product', $id)->first();
        if (!$product) {
            return response()->json(['error' => 'Produk tidak ditemukan'], 404);
        }

        $profile = DB::table('seller_profiles')->where('id_user', $user->id_user)->first();
        if (!$profile || (int)$product->id_seller !== (int)$profile->id_seller) {
            return response()->json(['error' => 'Bukan produk Anda'], 403);
        }

        if (!$request->hasFile('berkas')) {
            return response()->json(['error' => 'File "berkas" wajib diunggah'], 422);
        }

        $file = $request->file('berkas');
        if (!$file->isValid()) {
            return response()->json(['error' => 'File tidak valid'], 422);
        }

        if ($file->getSize() > 5242880) {
            return response()->json(['error' => 'Ukuran file maksimal 5MB'], 422);
        }

        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];
        $ext = strtolower($file->getClientOriginalExtension());
        if (empty($ext)) {
            return response()->json(['error' => 'File tidak memiliki ekstensi'], 422);
        }

        // Prefer the MIME type declared by the client in the multipart form
        // (curl -F "...;type=..."), then fall back to sniffing the bytes.
        $declaredMime = strtolower((string)$file->getClientMimeType());
        $sniffed = $file->getMimeType();
        $mime = ($declaredMime && isset($mimeMap[$declaredMime])) ? $declaredMime
            : (($sniffed && isset($mimeMap[$sniffed])) ? $sniffed : null);

        // Reject when magic bytes clearly identify a different allowed type,
        // e.g. a real JPEG renamed to .pdf.
        if ($sniffed && isset($mimeMap[$sniffed]) && strtolower($mimeMap[$sniffed]) !== $ext) {
            return response()->json(['error' => 'Ekstensi file tidak cocok dengan tipe file'], 422);
        }

        if (!$mime && !$sniffed) {
            return response()->json(['error' => 'Tipe file tidak diizinkan'], 422);
        }

        // Normalize: treat jpeg as jpg
        $tipe = ($ext === 'jpeg') ? 'jpg' : $ext;

        $subDir = 'uploads/products/' . (int)$id;
        $destPath = public_path($subDir);
        if (!is_dir($destPath) && !mkdir($destPath, 0755, true)) {
            return response()->json(['error' => 'Gagal membuat direktori penyimpanan'], 500);
        }

        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeBase = $this->slugify($baseName) ?: 'berkas';
        $storedName = $safeBase . '_' . uniqid('', true) . '.' . $tipe;

        if (!$file->move($destPath, $storedName)) {
            return response()->json(['error' => 'Gagal menyimpan file'], 500);
        }

        $fileSize = filesize($destPath . '/' . $storedName);

        $relPath = $subDir . '/' . $storedName;
        $docId = DB::table('product_documents')->insertGetId([
            'id_product' => (int)$id,
            'nama_berkas' => $file->getClientOriginalName(),
            'path_berkas' => $relPath,
            'tipe_berkas' => $tipe,
            'ukuran' => $fileSize ?: 0,
            'created_at' => now(),
        ], 'id_document');

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil diunggah',
            'document' => $this->documentsForProduct($id)->firstWhere('id_document', $docId),
        ], 201);
    }

    /**
     * GET /api/uploads/{path} - serve uploaded product documents.
     */
    public function serveDocument($path)
    {
        $path = str_replace(['..', " "], '', $path);
        $path = ltrim($path, '/');
        if (strpos($path, 'uploads/') !== 0 || preg_match('/\.\./', $path)) {
            abort(404);
        }

        $real = realpath(public_path($path));
        $root = realpath(public_path('uploads'));
        if (!$real || !$root || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) {
            abort(404);
        }

        return response()->file($real);
    }

    private function documentUrl($relPath)
    {
        if (!$relPath) return null;
        $relPath = ltrim($relPath, '/');
        // Newer rows already store the storage-relative "uploads/products/.." path.
        if (strpos($relPath, 'uploads/') === 0) {
            return '/' . str_replace('\\', '/', $relPath);
        }
        return '/uploads/' . str_replace('\\', '/', $relPath);
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Resolve the stored photo value to a web-accessible URL.
     *
     * Accepted stored values:
     *  - storage-relative path : "uploads/products/8/photo.jpg"
     *  - path from /static      : "/static/uploads/product_8.jpg"  (legacy rows)
     *  - legacy static name     : "product_8.jpg"
     *  - already absolute URL   : "https://.../uploads/..." (passed through)
     */
    private function resolvePhotoUrl($value)
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        // Leave absolute URLs untouched.
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        // Legacy rows were saved as "/static/uploads/<name>".
        if (preg_match('#^/static/uploads/#i', $value)) {
            return '/uploads/' . rawurlencode(ltrim(preg_replace('#^/static/uploads/#i', '', $value), '/'));
        }
        // Legacy rows may store just the bare filename.
        if (preg_match('#^product_\d+\.[a-z0-9]{2,5}$#i', $value)) {
            return '/uploads/products/' . preg_replace('#^product_(\d+)\..*$#i', '$1', $value) . '/' . rawurlencode($value);
        }
        $value = ltrim($value, '/');
        if (strpos($value, 'uploads/') === 0) {
            return '/' . str_replace('\\', '/', $value);
        }
        return '/' . $value;
    }

    /**
     * Process multipart uploads attached to a product form:
     *   foto     -> single product image  (updates products.foto)
     *   berkas[] -> grade documents       (inserts product_documents rows)
     *
     * Returns: ['photo' => <relative path|null>, 'documents' => <DocumentCollection>, 'errors' => <array>]
     */
    private function processUploadedFiles(Request $request, $pid)
    {
        $info = ['photo' => null, 'documents' => collect(), 'errors' => []];

        // ---- foto -------------------------------------------------------
        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            if (!$file || !$file->isValid()) {
                $info['errors'][] = 'File "foto" tidak valid';
            } else {
                $ext = strtolower($file->getClientOriginalExtension());
                if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                    $info['errors'][] = 'Foto harus berformat jpg, jpeg, atau png';
                } elseif ($file->getSize() > 5242880) {
                    $info['errors'][] = 'Ukuran foto maksimal 5MB';
                } else {
                    $tipe = ($ext === 'jpeg') ? 'jpg' : $ext;
                    $destPath = $this->productUploadDir($pid);

                    if ($destPath === null) {
                        $info['errors'][] = 'Gagal membuat direktori penyimpanan foto';
                    } else {
                        $baseName = $this->slugify(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'foto';
                        $storedName = $baseName . '_' . uniqid('', true) . '.' . $tipe;

                        if ($file->move($destPath, $storedName)) {
                            $relPath = 'uploads/products/' . (int)$pid . '/' . $storedName;
                            DB::table('products')->where('id_product', $pid)->update(['foto' => $relPath]);
                            $info['photo'] = $relPath;
                        } else {
                            $info['errors'][] = 'Gagal menyimpan foto produk';
                        }
                    }
                }
            }
        }

        // ---- berkas[] ---------------------------------------------------
        $berkas = $request->file('berkas', $request->file('berkas[]', []));
        if (!is_array($berkas)) {
            $berkas = [$berkas];
        }
        foreach ($berkas as $file) {
            if (!$file || !$file->isValid() || $file->getSize() === 0) {
                continue;
            }
            if ($file->getSize() > 5242880) {
                $info['errors'][] = 'Ukuran berkas "' . $file->getClientOriginalName() . '" maksimal 5MB';
                continue;
            }
            $ext = strtolower($file->getClientOriginalExtension());
            if (empty($ext)) {
                $info['errors'][] = 'Berkas "' . $file->getClientOriginalName() . '" tidak memiliki ekstensi';
                continue;
            }
            $tipe = ($ext === 'jpeg') ? 'jpg' : $ext;
            $destPath = $this->productUploadDir($pid);
            if ($destPath === null) {
                $info['errors'][] = 'Gagal membuat direktori penyimpanan berkas';
                continue;
            }
            $baseName = $this->slugify(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'berkas';
            $storedName = $baseName . '_' . uniqid('', true) . '.' . $tipe;

            if (!$file->move($destPath, $storedName)) {
                $info['errors'][] = 'Gagal menyimpan berkas "' . $file->getClientOriginalName() . '"';
                continue;
            }

            $fileSize = filesize($destPath . '/' . $storedName);
            $relPath = 'uploads/products/' . (int)$pid . '/' . $storedName;
            $docId = DB::table('product_documents')->insertGetId([
                'id_product' => (int)$pid,
                'nama_berkas' => $file->getClientOriginalName(),
                'path_berkas' => 'uploads/products/' . (int)$pid . '/' . $storedName,
                'tipe_berkas' => $tipe,
                'ukuran' => $fileSize ?: 0,
                'created_at' => now(),
            ], 'id_document');

            $info['documents']->push($this->documentsForProduct($pid)->firstWhere('id_document', $docId));
        }

        return $info;
    }

    /**
     * Create and return the public storage directory for a product's uploads.
     */
    private function productUploadDir($pid)
    {
        $destPath = public_path('uploads/products/' . (int)$pid);
        if (is_dir($destPath)) {
            return $destPath;
        }
        return is_dir($destPath) || mkdir($destPath, 0755, true) ? $destPath : null;
    }

    private function slugify($text)
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '_', $text);
        return trim($text, '_');
    }

    /**
     * Sync a product's category selection.
     *
     * Categories are single-select: this replaces the product's current
     * category with the one category the seller selected (or removes it
     * entirely when no category was chosen).
     */
    private function syncProductCategory($pid, $request)
    {
        $raw = $request->input('category')
            ?? $request->input('categories')
            ?? $request->input('categories[]');

        // Normalize: accept a scalar (single-select) or, defensively, an array.
        $catIds = is_array($raw) ? array_filter($raw, fn($v) => $v !== null && $v !== '') : (string)$raw;
        if (is_array($catIds)) {
            $catIds = array_slice(array_map('intval', $catIds), 0, 1);
        } else {
            $catIds = ($catIds !== null && $catIds !== '') ? [intval($catIds)] : [];
        }
        $catIds = array_values(array_filter($catIds, fn($v) => $v > 0));

        // Replace-all so the product always has exactly one category (or none).
        DB::table('product_categories')->where('id_product', $pid)->delete();
        foreach ($catIds as $catId) {
            DB::table('product_categories')->insert(['id_product' => $pid, 'id_kategori' => $catId]);
        }

        return $catIds;
    }

    public function store(Request $request)
    {
        $user = $this->requireRole('seller');
        if (!$user) {
            return response()->json(['error' => 'Seller only'], 401);
        }
        $data = $request->all();
        $profile = DB::table('seller_profiles')->where('id_user', $user->id_user)->first();
        if (!$profile) {
            return response()->json(['error' => 'No seller profile'], 400);
        }
        $pid = DB::table('products')->insertGetId([
            'id_seller' => $profile->id_seller,
            'nama_produk' => $data['nama_produk'],
            'deskripsi' => $data['deskripsi'] ?? '',
            'harga' => (float)$data['harga'],
            'stok' => (int)$data['stok'],
            'satuan' => $data['satuan'] ?? 'pcs',
            'status' => 'pending',
            'foto' => $data['foto'] ?? '',
            'created_at' => now(),
        ], 'id_product');

        // Single-select category: store the one chosen category (or none).
        $this->syncProductCategory($pid, $request);

        // Multipart form: foto (product image) + berkas[] (grade documents).
        $uploadInfo = $this->processUploadedFiles($request, $pid);

        // Notify all admins about new product submission
        $this->notifyAdmins('new_product', "Seller {$user->nama} ({$data['nama_produk']}) telah mengajukan produk #{$pid} untuk verifikasi.");

        return response()->json([
            'success' => true,
            'id_product' => $pid,
            'files' => $uploadInfo,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = $this->requireRole('seller');
        if (!$user) {
            return response()->json(['error' => 'Seller only'], 401);
        }
        $profile = DB::table('seller_profiles')->where('id_user', $user->id_user)->first();
        if (!$profile) {
            return response()->json(['error' => 'No seller profile'], 400);
        }
        $product = DB::table('products')->where('id_product', $id)->first();
        if (!$product) {
            return response()->json(['error' => 'Produk tidak ditemukan'], 404);
        }
        if ((int)$product->id_seller !== (int)$profile->id_seller) {
            return response()->json(['error' => 'Bukan produk Anda'], 403);
        }
        $data = $request->all();

        // Seller cannot control status directly.
        // Rejected products become 'pending' again when seller edits them (re-submission).
        $oldStatus = $product->status;
        $newStatus = $oldStatus;
        if ($oldStatus === 'rejected') {
            $newStatus = 'pending';
        }

        DB::table('products')
            ->where('id_product', $id)
            ->where('id_seller', $profile->id_seller)
            ->update([
                'nama_produk' => $data['nama_produk'] ?? $product->nama_produk,
                'deskripsi' => $data['deskripsi'] ?? $product->deskripsi,
                'harga' => (float)($data['harga'] ?? $product->harga),
                'stok' => (int)($data['stok'] ?? $product->stok),
                'satuan' => $data['satuan'] ?? $product->satuan,
                'grade' => $data['grade'] ?? $product->grade,
                'status' => $newStatus,
            ]);

        // Notify admins when a rejected product is re-submitted
        if ($newStatus === 'pending' && $oldStatus === 'rejected') {
            $this->notifyAdmins('product_resubmitted', "Seller {$user->nama} mengunggah ulang produk #{$id} untuk verifikasi.");
        }

        // Single-select category: replace the product's category with the one chosen (or clear).
        $this->syncProductCategory($id, $request);

        // Multipart form: foto (product image) + berkas[] (grade documents).
        $uploadInfo = $this->processUploadedFiles($request, $id);

        return response()->json([
            'success' => true,
            'id_product' => (int)$id,
            'files' => $uploadInfo,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $this->requireRole('seller');
        if (!$user) {
            return response()->json(['error' => 'Seller only'], 401);
        }
        DB::table('products')->where('id_product', $id)->delete();
        return response()->json(['success' => true]);
    }

    public function approve(Request $request, $id)
    {
        $user = $this->requireRole('admin');
        if (!$user) {
            return response()->json(['error' => 'Admin only'], 401);
        }
        $data = $request->json()->all();
        $status = $data['status'] ?? 'active';
        $grade = $data['grade'] ?? 'A';
        DB::table('products')->where('id_product', $id)->update([
            'status' => $status,
            'grade' => $grade,
        ]);
        $p = DB::table('products')->where('id_product', $id)->first();
        if ($p) {
            $seller = DB::table('seller_profiles')->where('id_seller', $p->id_seller)->first();
            if ($seller) {
                $this->sendNotif($seller->id_user, 'product_update', "Produk #{$id} telah disetujui dengan grade {$grade}.");
            }
        }
        return response()->json(['success' => true]);
    }

    public function review(Request $request, $id)
    {
        $user = $this->requireRole('admin');
        if (!$user) {
            return response()->json(['error' => 'Admin only'], 401);
        }
        $data = $request->json()->all();
        $status = $data['action'] ?? 'active';
        $grade = $data['grade'] ?? 'A';
        DB::table('products')->where('id_product', $id)->update([
            'status' => $status,
            'grade' => $grade,
        ]);
        $p = DB::table('products')->where('id_product', $id)->first();
        if ($p) {
            $seller = DB::table('seller_profiles')->where('id_seller', $p->id_seller)->first();
            if ($seller) {
                if ($status == 'active') {
                    $this->sendNotif($seller->id_user, 'product_approved', "Produk #{$id} disetujui! Grade: {$grade}");
                } else {
                    $this->sendNotif($seller->id_user, 'product_rejected', "Produk #{$id} ditolak. Silakan revisi.");
                }
            }
        }
        return response()->json(['success' => true]);
    }
}

