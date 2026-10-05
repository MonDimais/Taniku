# TaniKu - Installation Guide for Agent

## Overview
TaniKu is a Laravel 10 + SQLite marketplace application with vanilla JS frontend. This guide explains how to set it up on a fresh system.

## Prerequisites
- PHP 8.1+ (with SQLite extension)
- Composer 2.x
- Git (optional, for cloning)

## Quick Start (5 Steps)

### 1. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### 2. Setup Environment
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Setup
The SQLite database is already included at `database/database.sqlite` with all migrations applied and seed data loaded. No additional setup needed.

If you need to recreate from scratch:
```bash
php artisan migrate
php artisan db:seed
```

### 4. Start Development Server
```bash
php artisan serve
```
or use PHP built-in server:
```bash
php -S localhost:8000 -t public
```

### 5. Access Application
Open http://localhost:8000 in your browser.

## Test Accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@taniku.com | admin123 |
| Seller | budi@taniku.com | budi123 |
| Buyer | andi@taniku.com | andi123 |
| Seller | siti@taniku.com | siti123 |

## Project Structure

```
├── app/
│   ├── Http/Controllers/     # API controllers (Orders, Products, Auth, etc.)
│   └── Models/               # Eloquent models
├── config/                   # Laravel configuration
├── database/
│   └── database.sqlite       # SQLite database (include this!)
├── public/
│   ├── js/app.js             # Frontend vanilla JS (main SPA logic)
│   ├── css/style.css         # Frontend styles
│   └── uploads/              # User uploads (products, avatars)
├── resources/views/
│   └── welcome.blade.php     # Main HTML template (served at /)
├── routes/
│   └── api.php               # API routes
├── storage/
│   └── app/                  # Uploaded files
├── composer.json             # PHP dependencies
├── composer.lock             # Locked dependencies
├── .env.example              # Environment template
└── artisan                   # Laravel CLI
```

## Key Features

### Order Flow
1. **Buyer**: Browse products → Add to cart → Checkout → Pay (escrow)
2. **Seller**: Order status `paid` → Approve & pack (upload photo) → Ship (upload tracking)
3. **Buyer**: Order status `shipped` → Confirm (Received) OR Dispute
4. **Admin**: Resolve disputes (refund/partial/full release)

### Dispute System
- Buyer opens dispute on shipped order
- 3-way chat: Buyer, Seller, Admin
- Admin resolves: Refund, Partial Release, or Full Release
- Escrow funds automatically adjusted based on resolution

### Seller Shipping
- Upload packing photo proof when approving order
- Enter tracking number when shipping
- Seller CANNOT mark order as done (buyer controls that)

## API Endpoints (Key)

### Auth
- `POST /api/auth/register` - Register user
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `GET /api/auth/me` - Current user info

### Orders
- `GET /api/orders` - Get buyer's orders
- `POST /api/orders` - Create order
- `GET /api/orders/{id}` - Get order details
- `PUT /api/orders/{id}/status` - Update order status (with packing photo, tracking)
- `POST /api/orders/{id}/packing-photo` - Upload packing photo

### Disputes
- `GET /api/disputes` - Get disputes (admin/seller/buyer)
- `POST /api/disputes` - Open dispute
- `GET /api/disputes/{id}/messages` - Get dispute chat messages
- `POST /api/disputes/{id}/messages` - Send dispute message
- `POST /api/disputes/{id}/resolve` - Resolve dispute (admin only)

### Products
- `GET /api/products` - List products
- `POST /api/products` - Create product (seller)
- `PUT /api/products/{id}` - Update product
- `DELETE /api/products/{id}` - Delete product

## Database Schema

### Users
```sql
id_user, id_role (1=admin,2=seller,3=buyer), nama, email, password, 
status_verifikasi, avatar_url, created_at
```

### Products
```sql
id_produk, id_kategori, nama_produk, deskripsi, foto, harga, stok, 
status (pending/approved/rejected), seller_id, created_at
```

### Orders
```sql
id_order, id_buyer, id_seller, tanggal_order, total_amount, status_order,
alamat_pengiriman, tracking_number, shipping_service, shipping_cost,
packing_photo, dispute_resolution, created_at
```

**Status flow**: `pending → paid → approved → shipped → confirmed/disputed → refunded/cancelled`

### Disputes
```sql
id_dispute, id_order, opened_by, resolved_by, alasan, deskripsi, 
status (open/resolved), resolved_at, created_at
```

### Dispute Messages
```sql
id_message, id_dispute, sender_id, pesan, sent_at
```

### Notifications
```sql
id_notification, id_user, id_order, tipe, pesan, is_read, 
metadata (JSON with order_id), created_at
```

## Notes for Agent

1. **Vite is NOT used** - This project uses static JS (`public/js/app.js`), not Vite build system
2. **No npm install needed** - The `package.json` is vestigial from Laravel skeleton
3. **Server serves `welcome.blade.php`** - NOT `public/index.html`. The route is `return view('welcome')`
4. **SQLite database** - Use the included `database/database.sqlite` file (already has all data)
5. **PHP only** - No Node.js required for this project

## Troubleshooting

### "Class not found" errors
```bash
composer dump-autoload
```

### "Database not found"
```bash
# Ensure database/database.sqlite exists
# Or run migrations:
php artisan migrate
```

### "Permission denied" on storage
```bash
chmod -R 777 storage/
```

### Server won't start
```bash
# Kill existing PHP process
taskkill /F /IM php.exe (Windows)
# Or
pkill php (Linux/Mac)
```

## File Permissions (Linux/Mac)
```bash
chmod -R 777 storage/
chmod -R 777 bootstrap/cache/
```

## Production Deployment Notes

1. Set `APP_ENV=production` in `.env`
2. Set `APP_DEBUG=false` in `.env`
3. Run `composer install --no-dev --optimize-autoloader`
4. Run `php artisan config:cache`
5. Run `php artisan route:cache`
6. Use nginx/Apache with PHP-FPM instead of `php artisan serve`
7. Set up proper SSL certificates
8. Configure file upload limits in PHP (`upload_max_filesize`, `post_max_size`)
