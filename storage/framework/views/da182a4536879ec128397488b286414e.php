<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>TaniKu - Marketplace Pertanian</title>
    <link rel="stylesheet" href="<?php echo e(asset("css/style.css")); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <!-- Loading Screen -->
    <div id="loadingScreen" class="loading-screen">
        <div class="loading-logo">
            <i class="fas fa-leaf"></i>
            <h1>TaniKu</h1>
        </div>
    </div>

    <!-- Top Navigation -->
    <nav id="topNav" class="top-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <i class="fas fa-leaf"></i>
                <span>TaniKu</span>
            </div>
            <div class="nav-links" id="navLinks">
                <a href="#" onclick="navigate('home')" data-page="home">Beranda</a>
                <a href="#" onclick="navigate('products')" data-page="products">Produk</a>
                <a href="#" id="sellerNav" onclick="navigate('dashboard')" data-page="dashboard" style="display:none">Dashboard</a>
                <a href="#" id="adminNav" onclick="navigate('admin')" data-page="admin" style="display:none">Admin</a>
            </div>
            <div class="nav-actions">
                <button class="nav-icon-btn" id="searchToggle" onclick="toggleSearch()">
                    <i class="fas fa-search"></i>
                </button>
                <button class="nav-icon-btn" id="notifBtn" onclick="showNotifications()" style="display:none">
                    <i class="fas fa-bell"></i>
                    <span id="notifBadge" class="badge" style="display:none">0</span>
                </button>
                <button class="nav-icon-btn" id="chatBtn" onclick="showChat()" style="display:none">
                    <i class="fas fa-comment-dots"></i>
                    <span id="chatBadge" class="badge" style="display:none">0</span>
                </button>
                <button class="nav-icon-btn cart-nav-btn" id="cartNavBtn" onclick="showCart()" title="Keranjang">
                    <i class="fas fa-shopping-cart"></i>
                    <span id="cartBadge" class="badge" style="display:none">0</span>
                </button>
                <button class="btn btn-primary" id="authBtn" onclick="showAuthModal()">Masuk</button>
                <div id="userMenu" style="display:none" class="user-menu" onclick="event.stopPropagation()">
                    <img id="userAvatar" class="user-avatar" src="" alt="" onclick="toggleProfileDropdown(event)">
                    <div id="profileDropdown" class="profile-dropdown" style="display:none">
                        <div class="profile-dropdown-header">
                            <img id="profileAvatarSmall" src="" style="display:none">
                            <div class="info">
                                <div class="name" id="profileName">User</div>
                                <div class="role" id="profileRole">User</div>
                            </div>
                        </div>
                        <div class="profile-dropdown-item" onclick="navigate('profile');closeProfileDropdown()"><i class="fas fa-user"></i> Profil Saya</div>
                        <div class="profile-dropdown-item" onclick="navigate('orders');closeProfileDropdown()"><i class="fas fa-shopping-bag"></i> Pesanan Saya</div>
                        <div class="profile-dropdown-item" onclick="navigate('history');closeProfileDropdown()"><i class="fas fa-clock"></i> Riwayat</div>
                        <div class="profile-dropdown-item" onclick="showChat();closeProfileDropdown()"><i class="fas fa-comments"></i> Pesan</div>
                        <hr class="profile-dropdown-divider">
                        <div class="profile-dropdown-item danger" onclick="showLogoutConfirm();closeProfileDropdown()"><i class="fas fa-sign-out-alt"></i> Keluar</div>
                    </div>
                </div>
            <button class="nav-icon-btn mobile-menu-btn" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Search Bar -->
    <div id="searchBar" class="search-bar" style="display:none">
        <input type="text" id="searchInput" placeholder="Cari produk pertanian (realtime)..." oninput="handleSearch()">
        <button onclick="clearSearch()" class="btn-icon" title="Bersihkan pencarian"><i class="fas fa-eraser"></i></button>
        <button onclick="toggleSearch()" class="btn-icon"><i class="fas fa-times"></i></button>
    </div>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="mobile-menu" style="display:none">
        <div class="mobile-menu-header">
            <span id="mobileUserName">Login untuk melanjutkan</span>
            <button onclick="toggleMobileMenu()" class="btn-icon"><i class="fas fa-times"></i></button>
        </div>
        <div class="mobile-menu-body">
            <a href="#" data-page="home" onclick="navigate('home');toggleMobileMenu()"><i class="fas fa-home"></i> Beranda</a>
            <a href="#" data-page="products" onclick="navigate('products');toggleMobileMenu()"><i class="fas fa-box"></i> Produk</a>
            <a href="#" id="mobileProfileLink" data-page="profile" onclick="navigate('profile');toggleMobileMenu()"><i class="fas fa-user"></i> Profil Saya</a>
            <a href="#" id="mobileOrdersLink" data-page="orders" onclick="navigate('orders');toggleMobileMenu()"><i class="fas fa-shopping-bag"></i> Pesanan Saya</a>
            <a href="#" id="mobileHistoryLink" data-page="history" onclick="navigate('history');toggleMobileMenu()"><i class="fas fa-clock"></i> Riwayat</a>
            <a href="#" id="mobileChatLink" onclick="showChat();toggleMobileMenu()"><i class="fas fa-comment-dots"></i> Chat</a>
            <a href="#" id="mobileNotifLink" onclick="showNotifications();toggleMobileMenu()"><i class="fas fa-bell"></i> Notifikasi</a>
            <a href="#" id="mobileDashboardLink" data-page="dashboard" onclick="navigate('dashboard');toggleMobileMenu()"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="#" id="mobileAdminLink" data-page="admin" onclick="navigate('admin');toggleMobileMenu()"><i class="fas fa-shield-alt"></i> Admin</a>
            <a href="#" onclick="showAuthModal();toggleMobileMenu()" id="mobileLoginLink"><i class="fas fa-user"></i> <span id="mobileAuthText">Masuk / Daftar</span></a>
            <a href="#" onclick="handleLogout();toggleMobileMenu()" id="mobileLogoutLink" style="display:none"><i class="fas fa-sign-out-alt"></i> Keluar</a>
        </div>
    </div>

    <!-- Main Content -->
    <main id="mainContent">
        <!-- HOME PAGE -->
        <div id="page-home" class="page active">
            <!-- Hero Section -->
            <section class="hero-section">
                <div class="hero-bg"></div>
                <div class="hero-content">
                    <h1 class="hero-title">Belanja Pertanian<br><span>Kualitas Premium</span></h1>
                    <p class="hero-subtitle">Pasar digital untuk produk pertanian langsung dari petani lokal. Garansi kualitas dan sistem escrow yang aman.</p>
                    <div class="hero-actions">
                        <button class="btn btn-primary btn-lg" onclick="navigate('products')">
                            <i class="fas fa-shopping-cart"></i> Belanja Sekarang
                        </button>
                        <button class="btn btn-outline btn-lg" onclick="handleMulaiJualan()">
                            <i class="fas fa-store"></i> Mulai Jual
                        </button>
                    </div>
                    <div class="hero-stats">
                        <div class="stat-item">
                            <span class="stat-number" data-count="150">0</span>
                            <span class="stat-label">+ Petani</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" data-count="500">0</span>
                            <span class="stat-label">+ Produk</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" data-count="10000">0</span>
                            <span class="stat-label">+ Transaksi</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Categories -->
            <section class="section">
                <div class="section-header">
                    <h2>Kategori Produk</h2>
                    <a href="#" onclick="navigate('products')" class="view-all">Lihat Semua <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="category-grid" id="categoryGrid"></div>
            </section>

            <!-- Featured Products -->
            <section class="section">
                <div class="section-header">
                    <h2>Produk Unggulan</h2>
                    <a href="#" onclick="navigate('products')" class="view-all">Lihat Semua <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="product-grid" id="featuredProducts"></div>
            </section>

            <!-- Features -->
            <section class="section">
                <h2 class="section-title">Kenapa Pilih TaniKu?</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                        <h3>Sistem Escrow</h3>
                        <p>Transaksi aman dengan dana ditahan hingga produk diterima</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-seedling"></i></div>
                        <h3>Langsung dari Petani</h3>
                        <p>Belanja langsung dari petani lokal, harga terjangkau</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-check-circle"></i></div>
                        <h3>Terverifikasi</h3>
                        <p>Produk diverifikasi oleh tim admin untuk kualitas terbaik</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-headset"></i></div>
                        <h3>Resolusi Dispute</h3>
                        <p>Sistem dispute untuk menyelesaikan masalah dengan adil</p>
                    </div>
                </div>
            </section>
        </div>

        <!-- PRODUCTS PAGE -->
        <div id="page-products" class="page">
            <div class="products-page">
                <div class="products-header">
                    <h1>Semua Produk</h1>
                </div>
                <div class="products-filters">
                    <div class="filter-group">
                        <span class="filter-label"><i class="fas fa-tags"></i> Kategori</span>
                        <div id="categoryFilter" class="cat-filter-scroll"></div>
                    </div>
                    <div class="filter-group">
                        <span class="filter-label"><i class="fas fa-sort"></i> Urutkan</span>
                        <div id="sortFilter" class="filter-pills">
                            <button type="button" class="filter-pill active" onclick="setSort('newest')">Terbaru</button>
                            <button type="button" class="filter-pill" onclick="setSort('price-low')">Termurah</button>
                            <button type="button" class="filter-pill" onclick="setSort('price-high')">Termahal</button>
                        </div>
                    </div>
                </div>
                <div class="product-grid" id="allProducts"></div>
                <div id="noProducts" class="empty-state" style="display:none">
                    <i class="fas fa-box-open"></i>
                    <p>Belum ada produk tersedia</p>
                </div>
            </div>
        </div>

        <!-- PRODUCT DETAIL MODAL -->
        <div id="productModal" class="modal" style="display:none">
            <div class="modal-content product-modal-content">
                <button class="modal-close" onclick="closeModal('productModal')"><i class="fas fa-times"></i></button>
                <div id="productDetailContent"></div>
            </div>
        </div>

        <!-- DASHBOARD PAGE -->
        <div id="page-dashboard" class="page">
            <div class="dashboard-layout">
                <div class="dashboard-sidebar">
                    <div class="sidebar-section">
                        <h3>Seller</h3>
                        <a href="#" onclick="showDashboardTab('products')" class="active"><i class="fas fa-box"></i> Produk Saya</a>
                        <a href="#" onclick="showDashboardTab('orders')" ><i class="fas fa-shopping-bag"></i> Order Saya</a>
                        <a href="#" onclick="showDashboardTab('messages')"><i class="fas fa-comments"></i> Pesan</a>
                        <a href="#" onclick="showDashboardTab('settings')"><i class="fas fa-cog"></i> Pengaturan</a>
                    </div>
                </div>
                <div class="dashboard-content" id="dashboardContent">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>

        <!-- ADMIN PAGE -->
        <div id="page-admin" class="page">
            <div class="dashboard-layout">
                <div class="dashboard-sidebar">
                    <div class="sidebar-section">
                        <h3>Admin</h3>
                        <a href="#" onclick="showAdminTab('overview')" class="active"><i class="fas fa-chart-bar"></i> Ringkasan</a>
                        <a href="#" onclick="showAdminTab('products')"><i class="fas fa-box"></i> Verifikasi Produk</a>
                        <a href="#" onclick="showAdminTab('orders')"><i class="fas fa-shopping-bag"></i> Order</a>
                        <a href="#" onclick="showAdminTab('disputes')"><i class="fas fa-exclamation-triangle"></i> Dispute</a>
                        <a href="#" onclick="showAdminTab('categories')"><i class="fas fa-tags"></i> Kategori</a>
                        <a href="#" onclick="showAdminTab('users')"><i class="fas fa-users"></i> Pengguna</a>
                    </div>
                </div>
                <div class="dashboard-content" id="adminContent">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>
    
        <!-- PROFILE PAGE -->
        <div id="page-profile" class="page page-profile">
            <div id="profilePageContent"></div>
        </div>

        <!-- ORDERS PAGE -->
        <div id="page-orders" class="page page-orders">
            <div class="orders-header">
                <h1>Pesanan Saya</h1>
            </div>
            <div id="ordersPageContent"></div>
        </div>

        <!-- HISTORY PAGE -->
        <div id="page-history" class="page page-history">
            <div class="orders-header">
                <h1>Riwayat Transaksi</h1>
            </div>
            <div id="historyPageContent"></div>
        </div>
</main>

    <!-- Notifications Panel -->
    <div id="notifPanel" class="side-panel" style="display:none">
        <div class="panel-header">
            <h3>Notifikasi</h3>
            <div>
                <button onclick="markAllRead()" class="btn-text">Tandai dibaca</button>
                <button onclick="closeNotifPanel()" class="btn-icon"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="panel-body" id="notifList"></div>
    </div>

    <!-- Chat Panel -->
    <div id="chatPanel" class="side-panel" style="display:none">
        <div class="panel-header">
            <h3>Pesan</h3>
            <button onclick="closeChatPanel()" class="btn-icon"><i class="fas fa-times"></i></button>
        </div>
        <div class="panel-body chat-panel-body">
            <div id="chatConvos" class="chat-convos"></div>
            <div id="chatThread" class="chat-thread" style="display:none">
                <div class="chat-thread-header">
                    <button onclick="backToConvos()" class="btn-icon"><i class="fas fa-arrow-left"></i></button>
                    <span id="chatContactName"></span>
                </div>
                <div class="chat-messages" id="chatMessages"></div>
                <div class="chat-input-area">
                    <input type="text" id="chatInput" placeholder="Ketik pesan..." onkeydown="if(event.key==='Enter')sendChat()">
                    <button onclick="sendChat()" class="btn-icon"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Auth Modal -->
    <div id="authModal" class="modal" style="display:none">
        <div class="modal-content auth-modal">
            <button class="modal-close" onclick="closeModal('authModal')"><i class="fas fa-times"></i></button>
            <div class="auth-tabs">
                <button class="auth-tab active" onclick="switchAuthTab('login',this)">Masuk</button>
                <button class="auth-tab" onclick="switchAuthTab('register',this)">Daftar</button>
            </div>
            <!-- Login Form -->
            <form id="loginForm" onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="loginEmail" required placeholder="email@contoh.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="loginPassword" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Masuk</button>
                <p class="auth-hint">Demo: admin@taniku.com / admin123, budi@taniku.com / budi123, andi@taniku.com / andi123</p>
            </form>
            <!-- Register Form -->
            <form id="registerForm" onsubmit="handleRegister(event)" style="display:none">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" id="regNama" required placeholder="Nama Anda">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="regEmail" required placeholder="email@contoh.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="regPassword" required placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label>Daftar sebagai</label>
                    <select id="regRole">
                        <option value="buyer">Pembeli</option>
                        <option value="seller">Penjual</option>
                    </select>
                </div>
                <div id="sellerFields" style="display:none">
                    <div class="form-group">
                        <label>Nama Usaha</label>
                        <input type="text" id="regNamaUsaha" placeholder="Nama Toko Anda">
                    </div>
                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea id="regAlamat" placeholder="Alamat lengkap"></textarea>
                    </div>
                    <div class="form-group">
                        <label>No. Telepon</label>
                        <input type="tel" id="regTelepon" placeholder="08xxxxxxxxxx">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Daftar</button>
            </form>
        </div>
    </div>

    <!-- Add/Edit Product Modal -->
    <div id="productFormModal" class="modal" style="display:none">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('productFormModal')"><i class="fas fa-times"></i></button>
            <h2 id="productFormTitle">Tambah Produk</h2>
            <form id="productForm" onsubmit="handleProductSubmit(event)">
                <input type="hidden" id="productId">
                <div class="form-group">
                    <label>Nama Produk</label>
                    <input type="text" id="prodNama" required placeholder="Nama produk">
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea id="prodDeskripsi" rows="3" placeholder="Deskripsi produk"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Harga (Rp)</label>
                        <input type="number" id="prodHarga" required placeholder="15000" min="0">
                    </div>
                    <div class="form-group">
                        <label>Stok</label>
                        <input type="number" id="prodStok" required placeholder="50" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label>Satuan</label>
                    <input type="text" id="prodSatuan" placeholder="kg, pcs, ikat" value="kg">
                </div>
                <div class="form-group">
                    <label>Kategori</label>
                    <div id="prodCategories" class="checkbox-group"></div>
                </div>
                <div class="form-group">
                    <label>Foto Produk</label>
                    <div class="file-input" id="fotoDropZone" onclick="document.getElementById('prodFoto').click()" style="cursor:pointer">
                        <input type="file" id="prodFoto" accept=".jpg,.jpeg,.png" onchange="renderFotoPreview()" style="display:none">
                        <i class="fas fa-image" style="font-size:26px;color:var(--green-500);margin-bottom:8px"></i>
                        <p style="font-size:13px;font-weight:600;color:var(--slate-600);margin-bottom:2px">Klik untuk unggah foto produk</p>
                        <p style="font-size:11px;color:var(--slate-400)">JPG, PNG &mdash; maks 5 MB</p>
                        <div id="fotoPreview" class="berkas-list" style="display:none;margin-top:8px"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Dokumen Grading &amp; Sertifikat</label>
                    <div class="file-input" id="berkasDropZone" onclick="document.getElementById('prodBerkas').click()">
                        <input type="file" id="prodBerkas" multiple
                               onchange="renderBerkasPreview()" style="display:none">
                        <i class="fas fa-cloud-upload-alt" style="font-size:26px;color:var(--green-500);margin-bottom:8px"></i>
                        <p style="font-size:13px;font-weight:600;color:var(--slate-600);margin-bottom:2px">Klik untuk unggah berkas</p>
                        <p style="font-size:11px;color:var(--slate-400)">Semua tipe file &mdash; maks 5 MB per file</p>
                    </div>
                    <div id="berkasPreview" class="berkas-list"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Simpan Produk</button>
            </form>
        </div>
    </div>

    <!-- Cart Modal -->
    <div id="cartModal" class="modal" style="display:none">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('cartModal')"><i class="fas fa-times"></i></button>
            <h2><i class="fas fa-shopping-cart"></i> Keranjang</h2>
            <div id="cartItems"></div>
            <div id="cartFooter" style="display:none">
                <div class="cart-total">
                    <span>Total</span>
                    <span id="cartTotal">Rp0</span>
                </div>
                <button class="btn btn-primary btn-block" onclick="checkout()">Checkout</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>

    <!-- Logout Confirm Modal -->
    <div id="logoutModal" class="modal" style="display:none">
        <div class="modal-content modal-sm">
            <h3>Yakin keluar?</h3>
            <p>Anda akan keluar dari sesi ini.</p>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closeModal('logoutModal')">Batal</button>
                <button class="btn btn-danger" onclick="handleLogout()">Keluar</button>
            </div>
        </div>
    </div>

    <!-- Ship Order Modal -->
    <div id="shipModal" class="modal" style="display:none">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('shipModal')"><i class="fas fa-times"></i></button>
            <h3 id="shipModalTitle">Kirim Order</h3>
            <div id="shipModalContent"></div>
        </div>
    </div>

    <!-- Dispute Chat Modal -->
    <div id="disputeModal" class="modal" style="display:none">
        <div class="modal-content" style="max-height:80vh;display:flex;flex-direction:column">
            <button class="modal-close" onclick="closeModal('disputeModal')"><i class="fas fa-times"></i></button>
            <h3 id="disputeModalTitle">Dispute Order</h3>
            <div id="disputeChatContent" style="flex:1;overflow-y:auto;padding:16px;background:var(--slate-50);border-radius:8px;margin:16px 0"></div>
            <div id="disputeChatInput" style="display:flex;gap:8px;margin-bottom:8px">
                <input type="text" id="disputeMessageInput" placeholder="Tulis pesan..." style="flex:1;padding:10px;border:1px solid var(--slate-300);border-radius:8px">
                <button class="btn btn-primary" onclick="sendDisputeMessage()">Kirim</button>
            </div>
            <button id="disputeResolveBtn" class="btn btn-success" style="display:none;width:100%" onclick="resolveDispute(currentDisputeId)"><i class="fas fa-check"></i> Resolve Dispute</button>
        </div>
    </div>

    <!-- Resolve Dispute Modal -->
    <div id="resolveModal" class="modal" style="display:none">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('resolveModal')"><i class="fas fa-times"></i></button>
            <h3 id="resolveModalTitle">Resolve Dispute</h3>
            <div id="resolveModalContent"></div>
        </div>
    </div>

    <script src="<?php echo e(asset("js/app.js")); ?>"></script>
</body>
</html><?php /**PATH /home/khanzaetha/Kodingan/taniku-laravel-v2/resources/views/welcome.blade.php ENDPATH**/ ?>