// ===== TaniKu Frontend App =====
const API = '';
let currentUser = null;
let cart = [];
let currentChatUser = null;
let chatMsgsCache = [];
let currentCategory = '';
let currentSort = 'newest';
let currentLocation = '';
const PRODUCT_IMAGES = {
    1: 'https://images.unsplash.com/photo-1583119022894-919a68a3d0e3?w=400',
    2: 'https://images.unsplash.com/photo-1619663300408-fc31f673d704?w=400',
    3: 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=400',
    4: 'https://images.unsplash.com/photo-1597393353415-b572074cdb5a?w=400',
    5: 'https://images.unsplash.com/photo-1615485290382-443e9a7d30c2?w=400',
    6: 'https://images.unsplash.com/photo-1604977062946-606f3fbf8bec?w=400',
    7: 'https://images.unsplash.com/photo-1574263867128-b4d6bde3d4e8?w=400',
    8: 'https://images.unsplash.com/photo-1530968033775-2c92736b131e?w=400',
};
const FALLBACK_IMG = 'https://images.unsplash.com/photo-1574943320219-553eb213f72c?w=400';

function productImg(id) {
    return PRODUCT_IMAGES[id] || FALLBACK_IMG;
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', async () => {
    hideLoading();
    await checkAuth();
    loadCategories();
    loadLocations();
    loadFeaturedProducts();
    startNotifPolling();
    initRevealObserver();
    animateCounters();
    // Cart button is now in the HTML directly; ensure it's visible
    const cartBtn = document.getElementById('cartNavBtn');
    if (cartBtn) cartBtn.style.display = 'flex';
    // Update badge if cart has items (restore from localStorage)
    if (cart.length) updateCartBadge();
});

function hideLoading() {
    setTimeout(() => {
        const ls = document.getElementById('loadingScreen');
        if (ls) ls.classList.add('hidden');
    }, 800);
}

// ===== API HELPER =====
// Format an amount as Indonesian rupiah. The dashboard shows the real value
// (Rp1.900.000), never a "M" abbreviation that rounds small amounts to zero.
function fmtRp(v) {
    const n = Number(v) || 0;
    return 'Rp' + Math.round(n).toLocaleString('id-ID');
}

async function api(path, opts = {}) {
    try {
        const res = await fetch(API + path, {
            headers: { 'Content-Type': 'application/json' },
            ...opts
        });
        
        // Try to parse as JSON first
        let data;
        try {
            data = await res.json();
        } catch (jsonError) {
            // If JSON parsing fails, try to get text content
            const text = await res.text();
            // Check if it's an HTML error page
            if (text.startsWith('<!DOCTYPE') || text.startsWith('<html')) {
                console.error('API returned HTML error page for', path);
                return { success: false, error: 'Server error (HTML response)', status: res.status };
            }
            // Otherwise return the text as error message
            return { success: false, error: text || 'Invalid response', status: res.status };
        }
        
        return data;
    } catch (e) {
        console.error('API error:', e);
        return { success: false, error: 'Network error' };
    }
}

// ===== AUTH =====
async function checkAuth() {
    try {
        const res = await fetch(API + '/api/auth/me');
        if (res.ok) {
            const data = await res.json();
            if (data.user) {
                currentUser = data.user;
                updateUIForUser();
            }
        }
    } catch (e) {}
}

function updateUIForUser() {
    const authBtn = document.getElementById('authBtn');
    const userMenu = document.getElementById('userMenu');
    const notifBtn = document.getElementById('notifBtn');
    const chatBtn = document.getElementById('chatBtn');
    const sellerNav = document.getElementById('sellerNav');
    const adminNav = document.getElementById('adminNav');
    const chatBadge = document.getElementById('chatBadge');
    if (chatBadge) chatBadge.style.display = 'none';

    // Mobile menu items
    const mobileProfileLink = document.getElementById('mobileProfileLink');
    const mobileOrdersLink = document.getElementById('mobileOrdersLink');
    const mobileHistoryLink = document.getElementById('mobileHistoryLink');
    const mobileChatLink = document.getElementById('mobileChatLink');
    const mobileNotifLink = document.getElementById('mobileNotifLink');
    const mobileDashboardLink = document.getElementById('mobileDashboardLink');
    const mobileAdminLink = document.getElementById('mobileAdminLink');

    if (currentUser) {
        authBtn.style.display = 'none';
        userMenu.style.display = 'flex';
        notifBtn.style.display = 'flex';
        chatBtn.style.display = 'flex';
        const avatar = document.getElementById('userAvatar');
        if (currentUser.avatar_url) {
            avatar.src = currentUser.avatar_url;
            avatar.textContent = '';
        } else {
            avatar.src = '';
            avatar.textContent = currentUser.nama.charAt(0);
        }
        // Update profile dropdown header
        const pName = document.getElementById('profileName');
        const pRole = document.getElementById('profileRole');
        const pAvatar = document.getElementById('profileAvatarSmall');
        if (pName) pName.textContent = currentUser.nama;
        if (pRole) pRole.textContent = {1:'Admin',2:'Seller',3:'Pembeli'}[currentUser.id_role] || 'User';
        if (pAvatar) {
            if (currentUser.avatar_url) {
                pAvatar.src = currentUser.avatar_url;
                pAvatar.style.display = 'block';
            } else {
                pAvatar.style.display = 'none';
            }
        }
        document.getElementById('mobileAuthText').textContent = 'Akun';
        document.getElementById('mobileUserName').textContent = currentUser.nama;
        document.getElementById('mobileLoginLink').style.display = 'none';
        document.getElementById('mobileLogoutLink').style.display = 'flex';

        const role = {1:'admin',2:'seller',3:'buyer'}[currentUser.id_role];
        // Desktop nav — seller & admin get their dashboards; buyer gets orders/messages.
        if (role === 'seller' || role === 'buyer') sellerNav.style.display = 'block';
        if (role === 'admin') adminNav.style.display = 'block';
        // Mobile menu role gating
        if (mobileProfileLink) mobileProfileLink.style.display = 'flex';
        if (role === 'buyer' || role === 'seller') {
            if (mobileOrdersLink) mobileOrdersLink.style.display = 'flex';
            if (mobileHistoryLink) mobileHistoryLink.style.display = 'flex';
        } else {
            if (mobileOrdersLink) mobileOrdersLink.style.display = 'none';
            if (mobileHistoryLink) mobileHistoryLink.style.display = 'none';
        }
        if (mobileChatLink) mobileChatLink.style.display = (role === 'buyer' || role === 'seller') ? 'flex' : 'none';
        if (mobileNotifLink) mobileNotifLink.style.display = 'flex';
        if (mobileDashboardLink) mobileDashboardLink.style.display = (role === 'seller' || role === 'buyer') ? 'flex' : 'none';
        if (mobileAdminLink) mobileAdminLink.style.display = (role === 'admin') ? 'flex' : 'none';
    } else {
        authBtn.style.display = 'inline-flex';
        userMenu.style.display = 'none';
        notifBtn.style.display = 'none';
        chatBtn.style.display = 'none';
        sellerNav.style.display = 'none';
        adminNav.style.display = 'none';
        document.getElementById('mobileAuthText').textContent = 'Masuk / Daftar';
        document.getElementById('mobileUserName').textContent = 'Login untuk melanjutkan';
        document.getElementById('mobileLoginLink').style.display = 'flex';
        document.getElementById('mobileLogoutLink').style.display = 'none';
        // Hide all role-gated mobile menu items
        if (mobileProfileLink) mobileProfileLink.style.display = 'none';
        if (mobileOrdersLink) mobileOrdersLink.style.display = 'none';
        if (mobileHistoryLink) mobileHistoryLink.style.display = 'none';
        if (mobileChatLink) mobileChatLink.style.display = 'none';
        if (mobileNotifLink) mobileNotifLink.style.display = 'none';
        if (mobileDashboardLink) mobileDashboardLink.style.display = 'none';
        if (mobileAdminLink) mobileAdminLink.style.display = 'none';
    }
}

async function handleLogin(e) {
    e.preventDefault();
    const data = await api('/api/auth/login', {
        method: 'POST',
        body: JSON.stringify({
            email: document.getElementById('loginEmail').value,
            password: document.getElementById('loginPassword').value
        })
    });
    if (data.success) {
        // The login response only carries id_user/role/nama/avatar_url. Fetch the
        // full profile (alamat, pendapatan, status_verifikasi, …) so the profile
        // page and checkout have real data without needing a page refresh.
        currentUser = { id_user: data.user_id, id_role: data.role, nama: data.nama, avatar_url: data.avatar_url || null };
        try {
            const me = await api('/api/auth/me');
            if (me && me.user) currentUser = me.user;
        } catch (e) {}
        updateUIForUser();
        closeModal('authModal');
        showToast('Berhasil masuk!', 'success');
    } else {
        showToast(data.message || 'Gagal masuk', 'error');
    }
}

async function handleRegister(e) {
    e.preventDefault();
    const role = document.getElementById('regRole').value;
    const data = await api('/api/auth/register', {
        method: 'POST',
        body: JSON.stringify({
            nama: document.getElementById('regNama').value,
            email: document.getElementById('regEmail').value,
            password: document.getElementById('regPassword').value,
            role,
            nama_usaha: document.getElementById('regNamaUsaha')?.value,
            alamat: document.getElementById('regAlamat')?.value,
            no_telepon: document.getElementById('regTelepon')?.value
        })
    });
    if (data.success) {
        currentUser = { id_user: data.user_id, id_role: { seller:2, buyer:3 }[role], nama: document.getElementById('regNama').value };
        updateUIForUser();
        closeModal('authModal');
        showToast('Registrasi berhasil!', 'success');
    } else {
        showToast(data.message || 'Gagal daftar', 'error');
    }
}

async function handleLogout() {
    await api('/api/auth/logout', { method: 'POST' });
    currentUser = null;
    updateUIForUser();
    navigate('home');
    showToast('Anda telah keluar', 'success');
}

function handleMulaiJualan() {
    if (!currentUser) {
        showAuthModal('seller');
        return;
    }
    const role = {1:'admin',2:'seller',3:'buyer'}[currentUser.id_role];
    if (role === 'seller') {
        navigate('dashboard');
        return;
    }
    // Buyer or admin - show seller register
    showAuthModal('seller');
}

function toggleProfileDropdown(e) {
    if (e) e.stopPropagation();
    const dd = document.getElementById('profileDropdown');
    const isOpen = dd.style.display !== 'none';
    closeProfileDropdown();
    if (!isOpen) dd.style.display = 'block';
}

function closeProfileDropdown() {
    const dd = document.getElementById('profileDropdown');
    if (dd) dd.style.display = 'none';
}

document.addEventListener('click', function(e) {
    const menu = document.getElementById('userMenu');
    const dd = document.getElementById('profileDropdown');
    if (menu && dd && dd.style.display !== 'none' && !menu.contains(e.target)) {
        dd.style.display = 'none';
    }
});

function showLogoutConfirm() {
    showConfirm('Yakin keluar?', 'Anda akan keluar dari sesi ini.', () => handleLogout(), 'Keluar', 'Batal');
}

// ===== AUTH MODAL =====
function showAuthModal(role) {
    document.getElementById('authModal').style.display = 'flex';
    if (role === 'seller') {
        document.getElementById('regRole').value = 'seller';
        document.getElementById('sellerFields').style.display = 'block';
        switchAuthTab('register');
    }
}

function switchAuthTab(tab, btnEl) {
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    const btn = btnEl || document.querySelector(`.auth-tab[onclick*="${tab}"]`);
    if (btn) btn.classList.add('active');
    document.getElementById('loginForm').style.display = tab === 'login' ? 'block' : 'none';
    document.getElementById('registerForm').style.display = tab === 'register' ? 'block' : 'none';
    if (tab === 'register') {
        const role = document.getElementById('regRole').value;
        document.getElementById('sellerFields').style.display = role === 'seller' ? 'block' : 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('regRole')?.addEventListener('change', (e) => {
        document.getElementById('sellerFields').style.display = e.target.value === 'seller' ? 'block' : 'none';
    });
});

// ===== NAVIGATION =====
function navigate(page) {
    // Access control: check role before navigating
    if (!currentUser) {
        if (['dashboard','admin','profile','orders','history'].includes(page)) {
            showAuthModal();
            return;
        }
    } else {
        const role = currentUser.id_role;
        // Only admin can access admin page
        if (page === 'admin' && role !== 1) { navigate('home'); return; }
        // Dashboard: admin gets overview, seller gets products, buyer gets orders
        if (page === 'dashboard' && role === 3) { showDashboardTab('orders'); }
        if (page === 'dashboard' && role === 2) { showDashboardTab('products'); }
        if (page === 'dashboard' && role === 1) { showDashboardTab('overview'); }
    }

    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
    const el = document.getElementById('page-' + page);
    if (el) el.classList.add('active');
    document.querySelectorAll('.nav-links a[data-page="' + page + '"]').forEach(a => a.classList.add('active'));
    document.querySelectorAll('.mobile-menu-body a[data-page]').forEach(a => a.classList.remove('active'));
    document.querySelectorAll('.mobile-menu-body a[data-page="' + page + '"]').forEach(a => a.classList.add('active'));

    if (page === 'products') loadProducts();
    if (page === 'profile') showProfilePage();
    if (page === 'orders') showOrdersPage();
    if (page === 'history') showHistoryPage();
    if (page === 'admin') showAdminTab('overview');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ===== SEARCH =====
let currentSearchQuery = '';

function toggleSearch() {
    const bar = document.getElementById('searchBar');
    bar.style.display = bar.style.display === 'none' ? 'block' : 'none';
    if (bar.style.display === 'block') {
        document.getElementById('searchInput').focus();
    } else {
        // Clear search when closing
        document.getElementById('searchInput').value = '';
        currentSearchQuery = '';
        filterProductsClientSide();
    }
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    currentSearchQuery = '';
    filterProductsClientSide();
}

let searchTimeout;
function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const q = document.getElementById('searchInput').value.trim().toLowerCase();
        currentSearchQuery = q;
        // Always navigate to products page and filter client-side for fuzzy matching
        if (!document.getElementById('page-products').classList.contains('active')) {
            navigate('products');
        }
        filterProductsClientSide();
    }, 200);
}

function fuzzyMatch(text, query) {
    if (!query) return true;
    text = text.toLowerCase();
    query = query.toLowerCase();
    // Exact substring match first
    if (text.includes(query)) return true;
    // Fuzzy: check if query letters appear in order
    let qi = 0;
    for (let ti = 0; ti < text.length && qi < query.length; ti++) {
        if (text[ti] === query[qi]) qi++;
    }
    return qi === query.length;
}

function filterProductsClientSide() {
    const grid = document.getElementById('allProducts');
    if (!grid) return;
    const cards = grid.querySelectorAll('.product-card');
    let visible = 0;
    cards.forEach(card => {
        const name = card.querySelector('.product-name')?.textContent || '';
        if (fuzzyMatch(name, currentSearchQuery)) {
            card.style.display = '';
            visible++;
        } else {
            card.style.display = 'none';
        }
    });
    const noProducts = document.getElementById('noProducts');
    if (noProducts) noProducts.style.display = visible === 0 ? 'block' : 'none';
}

// ===== MOBILE MENU =====
function toggleMobileMenu() {
    document.getElementById('mobileMenu').style.display =
        document.getElementById('mobileMenu').style.display === 'none' ? 'flex' : 'none';
}

// ===== CATEGORIES =====
async function loadCategories() {
    const cats = await api('/api/categories');
    const icons = {1:'fa-carrot',2:'fa-pepper-hot',3:'fa-apple-whole',4:'fa-seedling',5:'fa-flask',6:'fa-tools'};
    const grid = document.getElementById('categoryGrid');
    if (grid) {
        grid.innerHTML = cats.map(c => `
            <div class="category-card reveal" onclick="filterByCategory(${c.id_kategori})">
                <i class="fas ${icons[c.id_kategori] || 'fa-box'}"></i>
                <span>${c.nama_kategori}</span>
            </div>
        `).join('');
        observeReveal();
    }
    // Populate category filter as scrollable small cards
    const cf = document.getElementById('categoryFilter');
    if (cf) {
        cf.classList.remove('filter-pills');
        cf.classList.add('cat-filter-scroll');
        let html = '<div class="cat-filter-card active" data-cat="" onclick="filterByCategory(0)">Semua</div>';
        cats.forEach(c => {
            html += '<div class="cat-filter-card" data-cat="' + c.id_kategori + '" onclick="filterByCategory(' + c.id_kategori + ')">' + c.nama_kategori + '</div>';
        });
        cf.innerHTML = html;
    }
    const cb = document.getElementById('prodCategories');
    if (cb) {
        cb.innerHTML = cats.map(c => `
            <label class="cat-radio"><input type="radio" name="prodCategory" value="${c.id_kategori}"> ${c.nama_kategori}</label>
        `).join('');
    }
}




function filterByCategory(id) {
    currentCategory = (id && id !== 0) ? String(id) : '';
    // Update active card
    document.querySelectorAll('.cat-filter-card').forEach(c => c.classList.remove('active'));
    const target = document.querySelector('.cat-filter-card[data-cat="' + id + '"]');
    if (target) target.classList.add('active');
    // Ensure we're on the products page
    if (!document.getElementById('page-products').classList.contains('active')) {
        navigate('products');
    }
    loadProducts();
}

function filterByLocation(loc) {
    currentLocation = loc || '';
    // Update active card
    document.querySelectorAll('.loc-filter-card').forEach(c => c.classList.remove('active'));
    const target = document.querySelector('.loc-filter-card[data-loc="' + loc + '"]');
    if (target) target.classList.add('active');
    // Ensure we're on the products page
    if (!document.getElementById('page-products').classList.contains('active')) {
        navigate('products');
    }
    loadProducts();
}

async function loadLocations() {
    // Fetch all active products to get unique seller locations
    const products = await api('/api/products?status=active');
    const locations = [...new Set(products.map(p => p.seller_alamat).filter(Boolean))]
        .map(alamat => {
            // Extract city/region - take the part after the last comma
            const parts = alamat.split(',');
            return parts.length > 1 ? parts[parts.length - 1].trim() : alamat.trim();
        })
        .filter(l => l && l.length > 0);
    
    const uniqueLocations = [...new Set(locations)].sort();
    const lf = document.getElementById('locationFilter');
    if (lf) {
        let html = '<div class="loc-filter-card active" data-loc="" onclick="filterByLocation(\'\')">Semua</div>';
        uniqueLocations.forEach(loc => {
            html += '<div class="loc-filter-card" data-loc="' + loc.replace(/"/g, '&quot;') + '" onclick="filterByLocation(\'' + loc.replace(/'/g, "\\'") + '\')">' + loc + '</div>';
        });
        lf.innerHTML = html;
    }
}

function setSort(val) {
    currentSort = val;
    document.querySelectorAll('#sortFilter .filter-pill').forEach(p => p.classList.remove('active'));
    const map = {'newest':0, 'price-low':1, 'price-high':2};
    const pills = document.querySelectorAll('#sortFilter .filter-pill');
    if (pills[map[val]]) pills[map[val]].classList.add('active');
    loadProducts();
}

// ===== PRODUCTS =====
async function loadFeaturedProducts() {
    const products = await api('/api/products?status=active');
    const grid = document.getElementById('featuredProducts');
    if (!grid) return;
    const featured = products.slice(0, 6);
    grid.innerHTML = featured.map(p => productCardHTML(p)).join('') ||
        '<p style="grid-column:1/-1;text-align:center;color:var(--slate-400)">Belum ada produk</p>';
    observeReveal();
}

async function loadProducts() {
    const grid = document.getElementById('allProducts');
    if (!grid) return;
    const sort = currentSort;
    let url = '/api/products?status=active';
    if (currentCategory) url += `&category=${currentCategory}`;
    if (currentLocation) url += `&location=${encodeURIComponent(currentLocation)}`;
    let products = await api(url);

    if (sort === 'price-low') products.sort((a,b) => (a.harga||0)-(b.harga||0));
    else if (sort === 'price-high') products.sort((b.harga||0)-(a.harga||0));

    grid.innerHTML = products.map(p => productCardHTML(p)).join('');
    observeReveal();
    // Apply client-side fuzzy filter
    filterProductsClientSide();
}

function docIcon(tipe) {
    const icons = {
        'jpg':'fa-file-image','jpeg':'fa-file-image','png':'fa-file-image','gif':'fa-file-image',
        'pdf':'fa-file-pdf','doc':'fa-file-word','docx':'fa-file-word',
        'xls':'fa-file-excel','xlsx':'fa-file-excel','csv':'fa-file-csv',
        'zip':'fa-file-zipper','rar':'fa-file-zipper','7z':'fa-file-zipper',
        'txt':'fa-file-alt','json':'fa-file-code','xml':'fa-file-code',
        'mp4':'fa-file-video','avi':'fa-file-video','mov':'fa-file-video',
        'mp3':'fa-file-audio','wav':'fa-file-audio','pdf':'fa-file-pdf'
    };
    return icons[tipe?.toLowerCase()] || 'fa-file';
}

function productCardHTML(p) {
    const img = p.foto || productImg(p.id_product);
    const statusClass = `status-${p.status || 'pending'}`;
    const statusText = p.status === 'active' ? 'Grade ' + (p.grade || 'A') : p.status === 'pending' ? 'Tinjau' : p.status;
    const location = p.seller_alamat ? p.seller_alamat.split(',').pop().trim() : '';
    return `
    <div class="product-card reveal" data-location="${location}" onclick="showProduct(${p.id_product})">
        <div class="product-img">
            <img src="${img}" alt="${p.nama_produk}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <span class="product-fallback-emoji" style="display:none;font-size:48px">${'🥬🧅🍅🌿🫚🥒🌱🌾'[p.id_product % 8]}</span>
            <span class="product-badge ${statusClass}">${statusText}</span>
        </div>
        <div class="product-body">
            <div class="product-name">${p.nama_produk}</div>
            <div class="product-seller"><i class="fas fa-store"></i> ${p.seller_nama || 'Petani'}</div>
            ${location ? `<div class="product-location"><i class="fas fa-location-dot"></i> ${location}</div>` : ''}
            <div class="product-price">Rp${(p.harga||0).toLocaleString()} <small>/${p.satuan||'pcs'}</small></div>
            <div class="product-footer">
                <span class="product-stock"><i class="fas fa-box"></i> ${p.stok} ${p.satuan||'pcs'}</span>
                ${p.seller_rating ? `<span class="product-rating"><i class="fas fa-star"></i> ${parseFloat(p.seller_rating).toFixed(1)}</span>` : ''}
            </div>
            <button class="btn btn-primary btn-block btn-sm" onclick="event.stopPropagation();buyNow(${p.id_product})" style="margin-top:8px">
                <i class="fas fa-cart-plus"></i> Beli Langsung
            </button>
        </div>
    </div>`;
}

// ===== PRODUCT DETAIL =====
async function showProduct(id) {
    const p = await api(`/api/products/${id}`);
    if (!p || p.error) { showToast('Produk tidak ditemukan', 'error'); return; }
    const img = p.foto || productImg(p.id_product);
    const location = p.seller_alamat || '';
    document.getElementById('productDetailContent').innerHTML = `
        <div class="product-detail-img">
            <img src="${img}" alt="${p.nama_produk}" style="width:100%;height:100%;object-fit:cover;border-radius:12px" onerror="this.style.display='none'">
        </div>
        <div class="product-detail-info">
            <h2>${p.nama_produk}</h2>
            <div class="product-detail-price">Rp${(p.harga||0).toLocaleString()} <small style="font-size:14px;color:var(--slate-500)">/${p.satuan||'pcs'}</small></div>
            <p class="product-detail-desc">${p.deskripsi||'Tidak ada deskripsi'}</p>
            <div class="product-detail-meta">
                <span><i class="fas fa-store"></i> ${p.seller_nama||'Petani'} (${p.nama_usaha||''})</span>
                ${location ? `<span><i class="fas fa-location-dot"></i> ${location}</span>` : ''}
                <span><i class="fas fa-box"></i> Stok: ${p.stok} ${p.satuan||'pcs'}</span>
                ${p.seller_rating ? `<span><i class="fas fa-star" style="color:var(--yellow-500)"></i> ${parseFloat(p.seller_rating).toFixed(1)}</span>` : ''}
                ${p.grade ? `<span><i class="fas fa-award"></i> Grade ${p.grade}</span>` : ''}
            </div>
            <span class="status-badge status-${p.status}">${p.status}</span>
            ${p.status === 'active' ? `
                <div style="margin-top:16px">
                    <button class="btn btn-primary btn-block add-to-cart-btn" style="margin-top:12px" onclick="addToCart(${p.id_product},'${p.nama_produk.replace(/'/g,"")}',${p.harga},'${p.satuan||'pcs'}')">
                        <i class="fas fa-cart-plus"></i> Tambah ke Keranjang
                    </button>
                </div>
                <div style="margin-top:16px">
                    <button class="btn btn-outline btn-block" style="margin-top:8px" onclick="chatSeller(${p.seller_id},'${(p.seller_nama||'').replace(/'/g,'')}')">
                        <i class="fas fa-comments"></i> Chat Seller
                    </button>
                </div>
                <div style="margin-top:16px;padding:16px;background:var(--slate-50);border-radius:12px;border:1px solid var(--slate-200)">
                    <h4 style="margin-bottom:12px"><i class="fas fa-handshake"></i> Nego Harga</h4>
                    <div style="margin-bottom:12px">
                        <label style="font-size:13px;color:var(--slate-600)">Harga offer Anda (Rp)</label>
                        <input type="number" id="negoOffer" value="${p.harga}" min="1" style="width:100%;padding:10px;border:1px solid var(--slate-300);border-radius:8px;margin-top:4px">
                    </div>
                    <div style="margin-bottom:12px">
                        <label style="font-size:13px;color:var(--slate-600)">Pesan ke seller</label>
                        <textarea id="negoMsg" rows="3" placeholder="Tulis pesan nego Anda..." style="width:100%;padding:10px;border:1px solid var(--slate-300);border-radius:8px;margin-top:4px;resize:vertical"></textarea>
                    </div>
                    <button class="btn btn-primary btn-block" onclick="negoProduct(${p.id_product},${p.seller_id})">
                        <i class="fas fa-paper-plane"></i> Kirim Nego
                    </button>
                </div>
            ` : ''}
        </div>`;
    document.getElementById('productModal').style.display = 'flex';
}

// ===== CART =====
function addToCart(id, name, price, satuan) {
    if (currentUser && {1:'admin',2:'seller',3:'buyer'}[currentUser.id_role] === 'seller') {
        showToast('Seller tidak bisa belanja. Silakan gunakan akun pembeli.', 'error');
        return;
    }
    const existing = cart.find(i => i.id === id);
    if (existing) {
        existing.qty++;
    } else {
        cart.push({ id, name, price, satuan, qty: 1, img: productImg(id) });
    }
    showToast('Ditambahkan ke keranjang!', 'success');
    updateCartBadge();
}

function updateCartBadge() {
    const badge = document.getElementById('cartBadge');
    if (!badge) return;
    const count = cart.reduce((s, i) => s + i.qty, 0);
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
}

function buyNow(id) {
    if (!currentUser) { showToast('Silakan login terlebih dahulu', 'error'); return; }
    if ({1:'admin',2:'seller',3:'buyer'}[currentUser.id_role] === 'seller') {
        showToast('Seller tidak bisa belanja', 'error');
        return;
    }
    api('/api/products/' + id).then(p => {
        if (!p || p.error) { showToast('Produk tidak ditemukan', 'error'); return; }
        cart.length = 0;
        cart.push({ id: p.id_product, name: p.nama_produk, price: p.harga, satuan: p.satuan || 'pcs', qty: 1, img: p.foto || productImg(id) });
        renderCart();
        updateCartBadge();
        document.getElementById('cartModal').style.display = 'flex';
    });
}

function showCart() {
    renderCart();
    document.getElementById('cartModal').style.display = 'flex';
}

function renderCart() {
    const items = document.getElementById('cartItems');
    const footer = document.getElementById('cartFooter');
    if (!cart.length) {
        items.innerHTML = '<p style="text-align:center;color:var(--slate-400);padding:20px">Keranjang kosong</p>';
        footer.style.display = 'none';
        return;
    }
    let total = 0;
    items.innerHTML = cart.map((i, idx) => {
        total += i.price * i.qty;
        return `
        <div class="cart-item">
            <div class="cart-item-img"><img src="${i.img}" style="width:48px;height:48px;border-radius:8px;object-fit:cover" onerror="this.style.display='none';this.parentElement.textContent='🥬'"></div>
            <div class="cart-item-info">
                <div class="cart-item-name">${i.name}</div>
                <div class="cart-item-price">Rp${i.price.toLocaleString()} / ${i.satuan}</div>
            </div>
            <div class="cart-item-controls">
                <button onclick="cartChange(${idx},-1)">-</button>
                <span>${i.qty}</span>
                <button onclick="cartChange(${idx},1)">+</button>
            </div>
        </div>`;
    }).join('');
    document.getElementById('cartTotal').textContent = `Rp${total.toLocaleString()}`;
    footer.style.display = 'block';
}

function cartChange(idx, delta) {
    cart[idx].qty += delta;
    if (cart[idx].qty <= 0) cart.splice(idx, 1);
    renderCart();
    updateCartBadge();
}

async function checkout() {
    if (!currentUser) { showToast('Silakan login terlebih dahulu', 'error'); return; }
    if ({1:'admin',2:'seller',3:'buyer'}[currentUser.id_role] === 'seller') {
        showToast('Seller tidak bisa belanja. Silakan gunakan akun pembeli.', 'error');
        return;
    }
    // Fetch the saved shipping address. Buyers store it on users.alamat (via
    // /api/auth/me); sellers keep using their seller_profiles.alamat.
    let savedAddr = '';
    try {
        const me = await api('/api/auth/me');
        const meUser = (me && me.user) || currentUser || {};
        savedAddr = meUser.alamat || '';
        currentUser.alamat = savedAddr; // keep client-side copy in sync
    } catch (e) {}
    // Show address selection modal instead of prompt
    const addr = await showAddressModal(savedAddr);
    if (!addr) return;
    const data = await api('/api/orders', {
        method: 'POST',
        body: JSON.stringify({
            items: cart.map(i => ({ id_product: i.id, quantity: i.qty })),
            alamat_pengiriman: addr,
            metode_pembayaran: 'transfer'
        })
    });
    if (data.success) {
        cart = [];
        closeModal('cartModal');
        updateCartBadge();
        showToast(`Order berhasil! Total: Rp${data.total.toLocaleString()}`, 'success');
    } else {
        showToast(data.error || 'Gagal membuat order', 'error');
    }
}

function showAddressModal(savedAddr) {
    return new Promise(resolve => {
        const existing = document.getElementById('addressModal');
        if (existing) existing.remove();
        const modal = document.createElement('div');
        modal.id = 'addressModal';
        modal.className = 'modal';
        modal.style.display = 'flex';
        modal.innerHTML = `
            <div class="modal-content" style="max-width:420px">
                <button class="modal-close" onclick="document.getElementById('addressModal').remove();window._addrResolve('')"><i class="fas fa-times"></i></button>
                <h2 style="margin-bottom:16px"><i class="fas fa-map-marker-alt"></i> Pilih Alamat</h2>
                ${savedAddr ? `
                <div style="margin-bottom:16px">
                    <button class="btn btn-primary btn-block address-btn" style="margin-bottom:8px;text-align:left;justify-content:flex-start" onclick="document.getElementById('addressModal').remove();window._addrResolve('${savedAddr.replace(/'/g,"\\\'")}')">
                        <i class="fas fa-home"></i> ${savedAddr}
                    </button>
                </div>
                ` : '<p style="color:var(--slate-400);margin-bottom:16px;text-align:center">Belum ada alamat tersimpan</p>'}
                <div class="form-group">
                    <label>Catatan (opsional)</label>
                    <textarea id="addrNote" rows="2" placeholder="Contoh: Patung kucing warna, depan toko sembako" style="width:100%;padding:10px;border:1px solid var(--slate-200);border-radius:8px;font-size:14px"></textarea>
                </div>
                <button class="btn btn-primary btn-block" onclick="const n=document.getElementById('addrNote').value.trim();const addr='${savedAddr?savedAddr.replace(/'/g,"\\\'"):''}';document.getElementById('addressModal').remove();window._addrResolve(n?addr+' - Note: '+n:addr)">Konfirmasi</button>
            </div>
        `;
        document.body.appendChild(modal);
        modal.addEventListener('click', e => {
            if (e.target === modal) { modal.remove(); resolve(''); }
        });
        window._addrResolve = resolve;
    });
}

/**
 * Modal to edit / save the buyer's shipping address (persisted via POST /api/auth/address).
 * Opens from the profile page so a buyer can set an address before checkout.
 */
function showAddressEditModal() {
    const existing = document.getElementById('addressEditModal');
    if (existing) existing.remove();
    const current = (currentUser && currentUser.alamat) ? currentUser.alamat : '';
    const modal = document.createElement('div');
    modal.id = 'addressEditModal';
    modal.className = 'modal';
    modal.style.display = 'flex';
    modal.innerHTML =
        '<div class="modal-content" style="max-width:460px">' +
            '<button class="modal-close" onclick="document.getElementById(\'addressEditModal\').remove()"><i class="fas fa-times"></i></button>' +
            '<h2 style="margin-bottom:16px"><i class="fas fa-map-marker-alt"></i> ' + (current ? 'Ubah Alamat' : 'Tambah Alamat') + '</h2>' +
            '<div class="form-group">' +
                '<label>Alamat Lengkap</label>' +
                '<textarea id="editAlamat" rows="3" style="width:100%;padding:10px;border:1px solid var(--slate-200);border-radius:8px;font-size:14px">' + (current || '') + '</textarea>' +
            '</div>' +
            '<button class="btn btn-primary btn-block" onclick="saveAddress()"><i class="fas fa-save"></i> Simpan Alamat</button>' +
        '</div>';
    document.body.appendChild(modal);
    modal.addEventListener('click', e => {
        if (e.target === modal) modal.remove();
    });
}

async function saveAddress() {
    const alamat = document.getElementById('editAlamat').value.trim();
    const res = await api('/api/auth/address', {
        method: 'POST',
        body: JSON.stringify({ alamat })
    });
    if (res.success) {
        if (currentUser) currentUser.alamat = alamat || '';
        document.getElementById('addressEditModal').remove();
        showToast('Alamat disimpan!', 'success');
        showProfilePage();
    } else {
        showToast(res.error || 'Gagal menyimpan alamat', 'error');
    }
}

// ===== DASHBOARD (Seller/Buyer) =====
function showDashboardTab(tab) {
    const el = document.getElementById('dashboardContent');
    const role = {1:'admin',2:'seller',3:'buyer'}[currentUser?.id_role || 3];

    // Render a role-aware sidebar (seller tabs vs buyer tabs) before each switch.
    buildDashboardSidebar(role, tab);

    if (role === 'seller') {
        if (tab === 'products') loadSellerProducts(el);
        else if (tab === 'orders') loadSellerOrders(el);
        else if (tab === 'messages') loadDashboardMessages(el);
        else if (tab === 'settings') loadSellerSettings(el);
    } else if (role === 'buyer') {
        if (tab === 'products' || tab === 'orders') loadBuyerOrders(el);
        else if (tab === 'messages') loadDashboardMessages(el);
    } else {
        // Admin: dashboard is for seller/buyer only — fall back to orders list.
        if (tab === 'overview') showAdminTab('overview');
    }
}

/**
 * Render the dashboard messages tab as a real conversation list (same data the
 * floating chat panel uses). Clicking a row opens that conversation in the
 * floating chat panel via showChatWith() so the user has one source of truth.
 */
async function loadDashboardMessages(el) {
    if (!currentUser) { el.innerHTML = '<p style="color:var(--slate-500)">Login untuk melihat pesan.</p>'; return; }
    el.innerHTML = '<div style="padding:40px;text-align:center;color:var(--slate-400)">Memuat pesan...</div>';
    const convos = await api('/api/messages/conversations');
    const rows = (Array.isArray(convos) ? convos : []).map(c => {
        const name = (c.contact_nama || 'Tanpa nama').replace(/</g, '&lt;');
        const preview = (c.pesan || '').replace(/</g, '&lt;');
        const cid = Number(c.contact_id || 0);
        return `<div class="chat-convo" onclick="showChatWith(${cid})" style="padding:12px 8px;border-bottom:1px solid var(--slate-100)">
            <div class="chat-convo-avatar">${name.charAt(0)}</div>
            <div class="chat-convo-info">
                <div class="chat-convo-name">${name}</div>
                <div class="chat-convo-preview">${preview}</div>
            </div>
        </div>`;
    }).join('');
    el.innerHTML = `
        <h3>Pesan</h3>
        <p style="color:var(--slate-500);margin:8px 0 16px">Klik salah satu percakapan untuk membuka di panel chat.</p>
        ${rows || '<p style="padding:40px;text-align:center;color:var(--slate-400)">Belum ada percakapan.</p>'}
    `;
}

/**
 * Build the dashboard sidebar tabs for the current role.
 * Sellers get the full product/order/settings set; buyers get orders/messages.
 */
function buildDashboardSidebar(role, activeTab) {
    const sb = document.getElementById('dashboardSidebar');
    if (!sb) return;
    let items;
    if (role === 'seller') {
        items = [
            ['products', 'fa-box', 'Produk Saya'],
            ['orders', 'fa-shopping-bag', 'Order Saya'],
            ['messages', 'fa-comments', 'Pesan'],
            ['settings', 'fa-cog', 'Pengaturan'],
        ];
    } else if (role === 'buyer') {
        items = [
            ['orders', 'fa-shopping-bag', 'Order Saya'],
            ['messages', 'fa-comments', 'Pesan'],
        ];
    } else {
        items = [];
    }
    sb.innerHTML = '<h3>' + (role === 'seller' ? 'Seller' : 'Pembeli') + '</h3>' +
        items.map(([id, icon, label]) =>
            `<a href="#" onclick="showDashboardTab('${id}')" class="${(activeTab === id) ? 'active' : ''}"><i class="fas ${icon}"></i> ${label}</a>`
        ).join('');
}

async function loadSellerStats(el) {
    const s = await api('/api/seller/stats');
    return `
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon" style="background:var(--green-100);color:var(--green-600)"><i class="fas fa-box"></i></div>
            <div class="stat-value">${s.total_products||0}</div><div class="stat-label">Total Produk</div></div>
        <div class="stat-card"><div class="stat-icon" style="background:var(--blue-100);color:var(--blue-500)"><i class="fas fa-shopping-bag"></i></div>
            <div class="stat-value">${s.total_orders||0}</div><div class="stat-label">Total Order</div></div>
        <div class="stat-card"><div class="stat-icon" style="background:var(--yellow-100);color:var(--yellow-500)"><i class="fas fa-star"></i></div>
            <div class="stat-value">${(s.rating||5).toFixed(1)}</div><div class="stat-label">Rating</div></div>
        <div class="stat-card"><div class="stat-icon" style="background:var(--green-100);color:var(--green-600)"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-value" style="font-size:18px">${fmtRp(s.pendapatan)}</div><div class="stat-label">Pendapatan</div></div>
        <div class="stat-card"><div class="stat-icon" style="background:var(--blue-100);color:var(--blue-500)"><i class="fas fa-receipt"></i></div>
            <div class="stat-value" style="font-size:18px">${fmtRp(s.total_revenue)}</div><div class="stat-label">Total Penjualan</div></div>
    </div>`;
}

async function loadSellerProducts(el) {
    const stats = await loadSellerStats(el);
    const products = await api(`/api/products?seller=${currentUser.id_user}`);
    const gradeColor = { A: 'var(--green-600)', B: 'var(--blue-500)', C: 'var(--yellow-500)' };
    const statusLabel = { active: 'Aktif', pending: 'Menunggu', rejected: 'Ditolak', draft: 'Draf' };
    el.innerHTML = stats + `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h3>Produk Saya (${products.length})</h3>
        <button class="btn btn-primary btn-sm" onclick="showProductForm()"><i class="fas fa-plus"></i> Tambah</button>
    </div>
    <div class="my-prod-grid">
        ${products.map(p => {
            const low = (p.stok||0) < 10;
            const gc = gradeColor[p.grade] || 'var(--slate-500)';
            const foto = p.foto ? API + p.foto : '';
            return `
            <div class="my-card">
                <div class="my-card-img">
                    ${foto ? `<img src="${foto}" onerror="this.outerHTML='<i class=\\'fas fa-image\\'></i>'">` : `<i class="fas fa-image"></i>`}
                    <span class="my-card-status ${p.status}">${statusLabel[p.status]||p.status}</span>
                </div>
                <div class="my-card-body">
                    <div class="my-card-name">${p.nama_produk||'-'}</div>
                    <div class="my-card-row">
                        <span class="my-card-price">Rp${(p.harga||0).toLocaleString()}<small> /${p.satuan||'pcs'}</small></span>
                        <span class="my-card-grade" style="background:${gc}20;color:${gc}">Grade ${p.grade||'-'}</span>
                    </div>
                    <div class="my-card-row">
                        <span>Stok:</span>
                        <span class="my-card-stock ${low?'low':''}">${(p.stok||0).toLocaleString()} ${p.satuan||'pcs'}</span>
                    </div>
                </div>
                <div class="my-card-actions">
                    <button onclick="showProductForm(${p.id_product})" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="act-toggle" onclick="toggleProductStatus(${p.id_product},'${p.status}')" title="${p.status==='active'?'Nonaktifkan':'Aktifkan'}"><i class="fas ${p.status==='active'?'fa-pause':'fa-play'}"></i></button>
                    <button class="act-delete" onclick="deleteProduct(${p.id_product})" title="Hapus"><i class="fas fa-trash"></i></button>
                </div>
            </div>`;
        }).join('') || '<p style="padding:40px;text-align:center;color:var(--slate-400);grid-column:1/-1">Belum ada produk</p>'}
    </div>`;
}

async function toggleProductStatus(id, current) {
    if (current === 'pending' || current === 'rejected') {
        showToast(current === 'pending' ? 'Produk masih menunggu verifikasi admin. Tunggu approval dulu.' : 'Produk ditolak. Edit produk untuk resubmit.', 'error');
        return;
    }
    const next = (current === 'active') ? 'draft' : 'active';
    const res = await api(`/api/products/${id}/toggle`, { method: 'POST', body: JSON.stringify({}) });
    if (res && res.success) {
        showToast(next === 'active' ? 'Produk diaktifkan' : 'Produk dinonaktifkan', 'success');
        showDashboardTab('products');
    } else {
        showToast((res && res.error) || 'Gagal', 'error');
    }
}

async function _doDeleteProduct(id) {
    const res = await api(`/api/products/${id}`, { method: 'DELETE' });
    if (res && res.success) {
        showToast('Produk dihapus', 'success');
        showDashboardTab('products');
    } else {
        showToast((res && res.error) || 'Gagal hapus', 'error');
    }
}

async function deleteProduct(id) {
    showConfirm('Yakin hapus produk?', 'Tindakan ini tidak bisa dibatalkan.', () => _doDeleteProduct(id), 'Hapus', 'Batal');
}

async function loadSellerOrders(el) {
    const orders = await api('/api/orders');
    const sellerId = currentUser?.id_user;
    // Filter for seller's orders
    const sellerOrders = orders.filter(o => o.id_seller === sellerId);
    // Separate into action-needed and completed
    const actionOrders = sellerOrders.filter(o => ['paid', 'shipped'].includes(o.status_order));
    const completedOrders = sellerOrders.filter(o => !['paid', 'shipped'].includes(o.status_order));
    
    el.innerHTML = `
        <div class="order-summary-grid" style="display:flex;flex-direction:row;flex-wrap:nowrap;gap:12px;overflow-x:auto;padding-bottom:8px">
            <div class="summary-card" style="flex:1 1 0;min-width:140px">
                <div class="summary-icon action"><i class="fas fa-box"></i></div>
                <div class="summary-info">
                    <div class="summary-count">${actionOrders.filter(o => o.status_order === 'paid').length}</div>
                    <div class="summary-label">Perlu Dikirim</div>
                </div>
            </div>
            <div class="summary-card" style="flex:1 1 0;min-width:140px">
                <div class="summary-icon shipping"><i class="fas fa-truck"></i></div>
                <div class="summary-info">
                    <div class="summary-count">${actionOrders.filter(o => o.status_order === 'shipped').length}</div>
                    <div class="summary-label">Sedang Dikirim</div>
                </div>
            </div>
            <div class="summary-card" style="flex:1 1 0;min-width:140px">
                <div class="summary-icon completed"><i class="fas fa-check-circle"></i></div>
                <div class="summary-info">
                    <div class="summary-count">${completedOrders.length}</div>
                    <div class="summary-label">Selesai</div>
                </div>
            </div>
            <div class="summary-card" style="flex:1 1 0;min-width:140px">
                <div class="summary-icon total"><i class="fas fa-shopping-bag"></i></div>
                <div class="summary-info">
                    <div class="summary-count">${sellerOrders.length}</div>
                    <div class="summary-label">Total Order</div>
                </div>
            </div>
        </div>
        
        ${actionOrders.length > 0 ? `
        <h3 style="margin:24px 0 16px"><i class="fas fa-exclamation-circle" style="color:var(--orange-500)"></i> Order Perlu Tindakan</h3>
        <div class="data-list">
            ${actionOrders.map(o => renderOrderItem(o)).join('')}
        </div>
        ` : ''}
        
        ${completedOrders.length > 0 ? `
        <h3 style="margin:24px 0 16px"><i class="fas fa-check-circle" style="color:var(--green-600)"></i> Order Selesai</h3>
        <div class="data-list">
            ${completedOrders.map(o => renderOrderItem(o, true)).join('')}
        </div>
        ` : ''}
        
        ${sellerOrders.length === 0 ? '<p style="padding:40px;text-align:center;color:var(--slate-400)">Belum ada order</p>' : ''}
    `;
}

function renderOrderItem(o, isCompleted = false) {
    const statusLabels = {
        'pending': 'Menunggu',
        'paid': 'Dibayar',
        'approved': 'Dikemas',
        'shipped': 'Dikirim',
        'confirmed': 'Selesai',
        'disputed': 'Dispute',
        'refunded': 'Refund',
        'cancelled': 'Dibatalkan'
    };
    const statusColors = {
        'pending': 'var(--slate-500)',
        'paid': 'var(--orange-500)',
        'approved': 'var(--blue-500)',
        'shipped': 'var(--purple-500)',
        'confirmed': 'var(--green-600)',
        'disputed': 'var(--red-500)',
        'refunded': 'var(--red-500)',
        'cancelled': 'var(--slate-400)'
    };
    const statusColor = statusColors[o.status_order] || 'var(--slate-500)';
    
    // SELLER actions only (seller cannot mark done - buyer controls that)
    let actionBtns = '';
    if (o.status_order === 'paid') {
        actionBtns += `<button class="btn btn-sm btn-primary" onclick="approveOrder(${o.id_order})"><i class="fas fa-box"></i> Approve & Kemas</button>`;
    } else if (o.status_order === 'approved') {
        actionBtns += `<button class="btn btn-sm btn-primary" onclick="shipOrder(${o.id_order})"><i class="fas fa-truck"></i> Kirim</button>`;
    }
    // Show "Lihat Chat" for disputed orders
    if (o.status_order === 'disputed') {
        actionBtns += `<button class="btn btn-sm btn-danger" onclick="openDisputeChat(${o.id_order})"><i class="fas fa-comments"></i> Lihat Chat</button>`;
    }
    
    let trackingHtml = '';
    if (o.tracking_number) {
        trackingHtml = `<div class="tracking-info"><i class="fas fa-barcode"></i> ${o.tracking_number}${o.shipping_service ? ' (' + o.shipping_service + ')' : ''}</div>`;
    }
    let packingHtml = '';
    if (o.packing_photo) {
        packingHtml = `<div class="packing-info"><i class="fas fa-camera"></i> Bukti packing tersedia</div>`;
    }
    
    return `<div class="data-list-item ${isCompleted ? 'completed' : 'action-needed'}">
        <div>
            <div class="item-main">Order #${o.id_order}</div>
            <div class="item-sub">${o.tanggal_order?.slice(0,10) || ''}</div>
            ${packingHtml}
            ${trackingHtml}
        </div>
        <div>Rp${(o.total_amount||0).toLocaleString()}</div>
        <div><span class="status-badge" style="background:${statusColor}">${statusLabels[o.status_order] || o.status_order}</span></div>
        <div style="display:flex;gap:6px">
            <button class="btn-icon" onclick="showOrderDetail(${o.id_order})" title="Detail"><i class="fas fa-eye"></i></button>
            ${actionBtns}
        </div>
    </div>`;
}

async function shipOrder(orderId) {
    const modal = document.getElementById('shipModal');
    const modalTitle = document.getElementById('shipModalTitle');
    const modalContent = document.getElementById('shipModalContent');
    
    modalTitle.textContent = 'Kirim Order #' + orderId;
    modalContent.innerHTML = `
        <div class="ship-form">
            <p style="margin-bottom:16px;color:var(--slate-600)">Masukkan nomor resi untuk melacak pengiriman:</p>
            <div class="form-group">
                <label for="trackingInput">Nomor Resi / Tracking</label>
                <input type="text" id="trackingInput" placeholder="Contoh: JNE001234567890" style="width:100%;padding:10px 14px;border:1px solid var(--slate-300);border-radius:8px;font-size:14px">
            </div>
            <div class="form-group">
                <label for="shippingService">Jasa Kirim</label>
                <select id="shippingService" style="width:100%;padding:10px 14px;border:1px solid var(--slate-300);border-radius:8px;font-size:14px">
                    <option value="">Pilih jasa kirim...</option>
                    <option value="JNE">JNE</option>
                    <option value="J&T">J&T Express</option>
                    <option value="SI">Sinarpack</option>
                    <option value="POS">POS Indonesia</option>
                    <option value="ANT">AnterAja</option>
                    <option value="OTHER">Lainnya</option>
                </select>
            </div>
            <div class="form-group">
                <label for="shippingCost">Biaya Kirim</label>
                <input type="number" id="shippingCost" placeholder="0" style="width:100%;padding:10px 14px;border:1px solid var(--slate-300);border-radius:8px;font-size:14px">
            </div>
            <div class="ship-actions">
                <button class="btn btn-secondary" onclick="closeModal('shipModal')">Batal</button>
                <button class="btn btn-primary" onclick="confirmShip(${orderId})"><i class="fas fa-truck"></i> Kirim Sekarang</button>
            </div>
        </div>
    `;
    
    modal.style.display = 'flex';
    setTimeout(() => document.getElementById('trackingInput')?.focus(), 100);
}

async function approveOrder(orderId) {
    const modal = document.getElementById('shipModal');
    const modalTitle = document.getElementById('shipModalTitle');
    const modalContent = document.getElementById('shipModalContent');

    modalTitle.textContent = 'Approve & Kemas Order #' + orderId;
    modalContent.innerHTML = `
        <div class="ship-form">
            <p style="margin-bottom:16px;color:var(--slate-600)">Unggah foto bukti barang saat dipacking untuk approve order ini:</p>
            <div class="form-group">
                <label for="packingPhotoInput">Foto Bukti Packing</label>
                <input type="file" id="packingPhotoInput" accept="image/*" onchange="previewPackingPhoto()" style="width:100%;padding:10px;border:1px solid var(--slate-300);border-radius:8px;font-size:14px">
                <div id="packingPreview" style="margin-top:12px;display:none">
                    <img id="packingPreviewImg" style="max-width:100%;max-height:200px;border-radius:8px;border:1px solid var(--slate-200)">
                </div>
            </div>
            <div class="ship-actions">
                <button class="btn btn-secondary" onclick="closeModal('shipModal')">Batal</button>
                <button class="btn btn-primary" onclick="confirmApprove(${orderId})"><i class="fas fa-check"></i> Approve & Kemas</button>
            </div>
        </div>
    `;

    modal.style.display = 'flex';
}

function previewPackingPhoto() {
    const input = document.getElementById('packingPhotoInput');
    const preview = document.getElementById('packingPreview');
    const img = document.getElementById('packingPreviewImg');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

async function confirmApprove(orderId) {
    const input = document.getElementById('packingPhotoInput');
    if (!input.files || !input.files[0]) {
        showToast('Foto bukti packing wajib diunggah', 'error');
        return;
    }
    const file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
        showToast('Maks 5MB', 'error');
        return;
    }

    const btn = document.querySelector('#shipModalContent .btn-primary');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengunggah...';

    const formData = new FormData();
    formData.append('packing_photo', file);
    try {
        const res = await api(`/api/orders/${orderId}/packing-photo`, {
            method: 'POST',
            headers: {},
            body: formData
        });
        if (res.success) {
            // Now approve the order with the uploaded photo URL
            const approveRes = await api(`/api/orders/${orderId}/status`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    status: 'approved',
                    packing_photo: res.packing_photo
                })
            });
            if (approveRes.success) {
                showToast('Order diapprove & dikemas! ✓', 'success');
                closeModal('shipModal');
                loadSellerOrders(document.getElementById('dashboardContent'));
            } else {
                showToast(approveRes.error || 'Gagal approve', 'error');
            }
        } else {
            showToast(res.error || 'Gagal upload foto', 'error');
        }
    } catch (e) {
        showToast('Error upload: ' + e.message, 'error');
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Approve & Kemas';
}

async function confirmShip(orderId) {
    const tracking = document.getElementById('trackingInput')?.value.trim();
    const service = document.getElementById('shippingService')?.value;
    const cost = parseInt(document.getElementById('shippingCost')?.value) || 0;
    
    if (!tracking) {
        showToast('Nomor resi wajib diisi', 'error');
        return;
    }
    if (!service) {
        showToast('Jasa kirim wajib dipilih', 'error');
        return;
    }
    
    const res = await api(`/api/orders/${orderId}/status`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            status: 'shipped',
            tracking_number: tracking,
            shipping_service: service,
            shipping_cost: cost
        })
    });
    
    if (res.success) {
        showToast('Order dikirim! ✓', 'success');
        closeModal('shipModal');
        loadSellerOrders(document.getElementById('dashboardContent'));
    } else {
        showToast(res.error || 'Gagal mengirim order', 'error');
    }
}

async function loadBuyerOrders(el) {
    const orders = await api('/api/orders');
    el.innerHTML = `<h3 style="margin-bottom:16px">Order Saya</h3>
    <div class="buyer-order-grid">
        ${orders.map(o => `
        <div class="buyer-order-card" onclick="showOrderDetail(${o.id_order})">
            <div class="buyer-order-head">
                <span class="order-id">Order #${o.id_order}</span>
                <span class="order-date">${o.tanggal_order?.slice(0,10)||''}</span>
            </div>
            <div class="buyer-order-items">
                ${(o.items||[]).map(i => `
                <div class="buyer-order-item">
                    <img src="${i.foto||'https://via.placeholder.com/60'}" alt="${i.nama_produk}" class="item-img" onerror="this.src='https://via.placeholder.com/60?text=No+Img'">
                    <div class="item-info">
                        <div class="item-name">${i.nama_produk}</div>
                        <div class="item-meta">
                            <span class="item-qty">${i.quantity||1} ${i.satuan||'pcs'}</span>
                            <span class="item-price">Rp${((i.subtotal||i.harga||0)).toLocaleString()}</span>
                        </div>
                    </div>
                </div>`).join('') || '<div class="buyer-order-empty" style="padding:20px;text-align:center;color:var(--slate-400);font-size:13px">Tidak ada item</div>'}
            </div>
            <div class="buyer-order-total">
                <span class="total-label">Total</span>
                <span class="total-amount">Rp${(o.total_amount||0).toLocaleString()}</span>
            </div>
            <div class="buyer-order-actions">
                ${o.status_order==='pending' ? `<button class="btn btn-action-primary" onclick="event.stopPropagation();payOrder(${o.id_order})"><i class="fas fa-credit-card"></i> Bayar</button>` : ''}
                ${o.tracking_number && o.status_order==='shipped' ? `<button class="btn btn-action-info" title="Tracking" onclick="event.stopPropagation();showTracking(${o.id_order})"><i class="fas fa-truck"></i></button>` : ''}
                ${o.status_order==='shipped' ? `<button class="btn btn-action-success" onclick="event.stopPropagation();updateOrderStatus(${o.id_order},'confirmed')"><i class="fas fa-check"></i> Sudah Diterima</button>` : ''}
                ${o.status_order==='confirmed' ? `<button class="btn btn-action-danger" onclick="event.stopPropagation();openDispute(${o.id_order})"><i class="fas fa-flag"></i> Ajukan Dispute</button>` : ''}
                ${o.status_order==='disputed' ? `<button class="btn btn-action-warning" onclick="event.stopPropagation();openDisputeChat(${o.id_order})"><i class="fas fa-comments"></i> Lihat Dispute</button>` : ''}
            </div>
        </div>`).join('') || '<div class="buyer-order-empty"><i class="fas fa-box"></i><h3>Belum ada order</h3><p>Belanja produk dari seller di Taniku</p></div>'}
    </div>`;
}

async function loadSellerSettings(el) {
    const profile = await api('/api/seller/profile');
    el.innerHTML = `<h3 style="margin-bottom:16px">Pengaturan Toko</h3>
    <div class="form-group"><label>Nama Usaha</label><input id="setNama" value="${profile.nama_usaha||''}"></div>
    <div class="form-group"><label>Alamat</label><textarea id="setAlamat" rows="3">${profile.alamat||''}</textarea></div>
    <div class="form-group"><label>No. Telepon</label><input id="setTelepon" value="${profile.no_telepon||''}"></div>
    <button class="btn btn-primary" onclick="saveSettings()">Simpan</button>`;
}

async function saveSettings() {
    await api('/api/seller/profile', {
        method: 'PUT',
        body: JSON.stringify({
            nama_usaha: document.getElementById('setNama').value,
            alamat: document.getElementById('setAlamat').value,
            no_telepon: document.getElementById('setTelepon').value
        })
    });
    showToast('Pengaturan disimpan!', 'success');
}

// ===== PRODUCT FORM =====
function showProductForm(id) {
    document.getElementById('productFormModal').style.display = 'flex';
    if (id) {
        document.getElementById('productFormTitle').textContent = 'Edit Produk';
        document.getElementById('productId').value = id;
        api(`/api/products/${id}`).then(p => {
            if (p && !p.error) {
                document.getElementById('prodNama').value = p.nama_produk;
                document.getElementById('prodDeskripsi').value = p.deskripsi || '';
                document.getElementById('prodHarga').value = p.harga;
                document.getElementById('prodStok').value = p.stok;
                document.getElementById('prodSatuan').value = p.satuan || 'kg';
                // Pre-fill the single selected category (GROUP_CONCAT -> single id).
                const catVal = String(p.categories || '').trim();
                if (catVal && /^\d+$/.test(catVal)) {
                    const radio = document.querySelector(`#prodCategories input[value="${catVal}"]`);
                    if (radio) radio.checked = true;
                }
            }
        });
    } else {
        document.getElementById('productFormTitle').textContent = 'Tambah Produk';
        document.getElementById('productForm').reset();
        resetBerkasPreview();
        document.getElementById('prodFoto').value = '';
        document.getElementById('fotoPreview').style.display = 'none';
        document.getElementById('fotoPreview').innerHTML = '';
    }
}

async function handleProductSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('productId').value;
    const checked = document.querySelector('#prodCategories input[type=radio]:checked');
    const formData = new FormData();
    // Append foto produk (main image) first
    const fotoInput = document.getElementById('prodFoto');
    if (fotoInput && fotoInput.files && fotoInput.files.length) {
        formData.append('foto', fotoInput.files[0]);
    }
    formData.append('nama_produk', document.getElementById('prodNama').value);
    formData.append('deskripsi', document.getElementById('prodDeskripsi').value);
    formData.append('harga', document.getElementById('prodHarga').value);
    formData.append('stok', document.getElementById('prodStok').value);
    formData.append('satuan', document.getElementById('prodSatuan').value);
    // Single-select category: send only the one radio that is checked (or omit).
    if (checked) formData.append('category', +checked.value);
    // Append berkas files
    const fileInput = document.getElementById('prodBerkas');
    if (fileInput && fileInput.files && fileInput.files.length) {
        let count = 0;
        for (const f of fileInput.files) {
            formData.append('berkas[]', f);
            count++;
        }
        if (count) showToast(`Mengunggah ${count} berkas...`, '');
    }
    const url = id ? `/api/products/${id}` : '/api/products';
    // PHP only populates $_FILES for POST, so file uploads on edit must go over
    // POST + a _method=PUT hint (Laravel's method spoofing). A raw PUT multipart
    // drops $_FILES and the foto fails isValid(). Create uses plain POST.
    const method = 'POST';
    if (id) formData.append('_method', 'PUT');
    let res;
    try {
        const rawRes = await fetch(API + url, { method, body: formData });
        res = await rawRes.json();
    } catch (err) {
        console.error('Submit error:', err);
        res = { success: false, error: 'Network error' };
    }
    if (res.success) {
        // Surface partial failures (e.g. foto/berkas validation errors) that
        // arrive under files.errors — without this the seller sees "saved"
        // even when the image/document upload was rejected.
        const errs = (res.files && res.files.errors) ? res.files.errors : [];
        if (errs.length) {
            showToast(errs.join(' · '), 'error');
        } else {
            closeModal('productFormModal');
            resetBerkasPreview();
            showToast(id ? 'Produk diupdate!' : 'Produk ditambahkan!', 'success');
            showDashboardTab('products');
        }
    } else {
        showToast(res.error || res.message || 'Gagal', 'error');
    }
}

// ===== BERKAS FILE PREVIEW =====
function renderBerkasPreview() {
    const input = document.getElementById('prodBerkas');
    const preview = document.getElementById('berkasPreview');
    if (!input || !preview) return;
    const files = Array.from(input.files || []);
    preview.innerHTML = '';
    if (!files.length) { preview.style.display = 'none'; return; }
    preview.style.display = 'flex';
    files.forEach((f, i) => {
        const ext = (f.name.split('.').pop() || '').toLowerCase();
        let iconCls = 'type-img';
        if (ext === 'pdf') iconCls = 'type-pdf';
        else if (ext === 'doc' || ext === 'docx') iconCls = 'type-doc';
        else if (ext === 'xls' || ext === 'xlsx' || ext === 'csv') iconCls = 'type-excel';
        else if (ext === 'zip' || ext === 'rar' || ext === '7z') iconCls = 'type-zip';
        else if (ext === 'txt' || ext === 'json' || ext === 'xml') iconCls = 'type-txt';
        else if (ext === 'mp4' || ext === 'avi' || ext === 'mov') iconCls = 'type-video';
        const icon = docIcon(ext);
        const item = document.createElement('div');
        item.className = 'berkas-item';
        item.innerHTML = `
            <div class="berkas-item-icon ${iconCls}"><i class="fas ${icon}"></i></div>
            <div class="berkas-item-info">
                <div class="berkas-item-name">${f.name}</div>
                <div class="berkas-item-size">${formatFileSize(f.size)}</div>
            </div>
            <button type="button" class="berkas-item-remove" onclick="removeBerkasFile(${i})" title="Hapus"><i class="fas fa-times"></i></button>
        `;
        preview.appendChild(item);
    });
}

function renderFotoPreview() {
    const input = document.getElementById('prodFoto');
    const preview = document.getElementById('fotoPreview');
    if (!input || !preview) return;
    const files = Array.from(input.files || []);
    if (!files.length) { preview.style.display = 'none'; return; }
    preview.style.display = 'block';
    preview.innerHTML = files.map((f, i) => `
        <div class="berkas-item">
            <div class="berkas-item-icon" style="background:var(--blue-100);color:var(--blue-500)"><i class="fas fa-image"></i></div>
            <div class="berkas-item-info"><div class="berkas-item-name">${f.name}</div><div class="berkas-item-size">${formatFileSize(f.size)}</div></div>
            <button type="button" class="berkas-item-remove" onclick="document.getElementById('prodFoto').value='';renderFotoPreview()"><i class="fas fa-times"></i></button>
        </div>
    `).join('');
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/(1024*1024)).toFixed(1) + ' MB';
}

function removeBerkasFile(index) {
    const input = document.getElementById('prodBerkas');
    if (!input || !input.files) return;
    const dt = new DataTransfer();
    const files = Array.from(input.files);
    files.forEach((f, i) => { if (i !== index) dt.items.add(f); });
    input.files = dt.files;
    renderBerkasPreview();
}

function resetBerkasPreview() {
    const input = document.getElementById('prodBerkas');
    const preview = document.getElementById('berkasPreview');
    if (input) input.value = '';
    if (preview) { preview.innerHTML = ''; preview.style.display = 'none'; }
}

// ===== ORDER DETAIL =====

function showProfileModal() {
    if (!currentUser) { showToast('Login terlebih dahulu', 'error'); return; }
    const avatar = document.getElementById('userAvatar');
    const hasPhoto = currentUser.avatar_url ? 'block' : 'none';
    const showFallback = currentUser.avatar_url ? 'none' : 'flex';
    const modalHTML = `
        <div id="profileModal" class="modal" style="display:flex;z-index:1000">
            <div class="modal-content" style="max-width:320px;text-align:center;padding:32px 24px">
                <button class="modal-close" onclick="document.getElementById('profileModal').remove()"><i class="fas fa-times"></i></button>
                <div style="position:relative;display:inline-block;margin-bottom:12px">
                    <img src="${currentUser.avatar_url || ''}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--green-500);display:${hasPhoto}">
                    <div style="width:96px;height:96px;border-radius:50%;background:var(--green-500);color:#fff;display:${showFallback};align-items:center;justify-content:center;font-size:36px;font-weight:700;margin:0 auto">${currentUser.nama.charAt(0)}</div>
                    <label for="profilePhoto" style="position:absolute;bottom:0;right:0;background:var(--green-500);color:#fff;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid #fff;font-size:12px;box-shadow:0 2px 6px rgba(0,0,0,.2)">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="profilePhoto" accept=".jpg,.jpeg,.png" style="display:none" onchange="uploadProfilePhoto(this)">
                </div>
                <p style="color:var(--slate-400);font-size:12px;margin-bottom:16px">Klik kamera untuk ganti foto</p>
                <button class="btn btn-primary btn-block" onclick="document.getElementById('profileModal').remove()">Selesai</button>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

async function uploadProfilePhoto(input) {
    if (!input.files || !input.files[0]) return;
    const formData = new FormData();
    formData.append('foto', input.files[0]);
    const res = await fetch(API + '/api/auth/avatar', {
        method: 'POST',
        body: formData,
        headers: {}
    });
    const data = await res.json();
    if (data.success && data.avatar_url) {
        currentUser.avatar_url = data.avatar_url;
        const avatar = document.getElementById('userAvatar');
        avatar.src = data.avatar_url;
        avatar.textContent = '';
        document.getElementById('profileAvatarSmall').textContent = '';
        document.getElementById('profileAvatarSmall').style.backgroundImage = `url(${data.avatar_url})`;
        document.getElementById('profileAvatarSmall').style.backgroundSize = 'cover';
        document.getElementById('profileAvatarSmall').style.backgroundPosition = 'center';
        showToast('Foto profil berhasil diunggah!', 'success');
        document.getElementById('profileModal').remove();
    } else {
        showToast(data.error || 'Gagal unggah foto', 'error');
    }
}

async function chatSeller(sellerId, sellerName) {
    if (!currentUser) { showToast('Login terlebih dahulu', 'error'); return; }
    closeModal('productModal');
    currentChatUser = { id: sellerId, name: sellerName };
    document.getElementById('chatContactName').textContent = sellerName;
    document.getElementById('chatConvos').style.display = 'none';
    document.getElementById('chatThread').style.display = 'flex';
    document.getElementById('chatPanel').style.display = 'flex';
    await loadMessages(sellerId);
}

async function negoProduct(productId, sellerId) {
    if (!currentUser) { showToast('Login terlebih dahulu', 'error'); return; }
    const harga = document.getElementById('negoOffer').value;
    const pesan = document.getElementById('negoMsg').value.trim();
    if (!pesan) { showToast('Tulis pesan dulu', 'error'); return; }
    // Fetch product info for the message
    const p = await api('/api/products/' + productId);
    const prodName = p.nama_produk || 'Produk';
    const prodPrice = p.harga || 0;
    const prodImg = p.foto || productImg(productId);
    const res = await api('/api/messages/negotiation', {
        method: 'POST',
        body: JSON.stringify({
            id_product: productId, seller_id: sellerId,
            harga: parseInt(harga)||0, pesan,
            nama_produk: prodName, harga_produk: prodPrice,
            foto_produk: prodImg
        })
    });
    if (res.success) {
        showToast('Nego terkirim! Seller akan dihubungi.', 'success');
        document.getElementById('negoMsg').value = '';
    } else {
        showToast(res.error || 'Gagal kirim nego', 'error');
    }
}

async function showOrderDetail(id) {
    const o = await api(`/api/orders/${id}`);
    if (!o || o.error) { showToast('Order tidak ditemukan', 'error'); return; }
    // Split alamat and note (format: "alamat - Note: note")
    let alamat = o.alamat_pengiriman || '-';
    let note = '';
    const noteMatch = alamat.match(/^(.*?)\s*-\s*Note:\s*(.*)$/);
    if (noteMatch) {
        alamat = noteMatch[1];
        note = noteMatch[2];
    }
    document.getElementById('productDetailContent').innerHTML = `
        <h2 style="margin-bottom:12px">Order #${o.id_order}</h2>
        <span class="status-badge status-${o.status_order}">${o.status_order}</span>
        <div style="margin-top:12px">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px"><span>Tanggal</span><span>${o.tanggal_order}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px"><span>Total</span><span>Rp${(o.total_amount||0).toLocaleString()}</span></div>
            <div style="margin-bottom:8px"><span style="display:block;color:var(--slate-500);font-size:12px;margin-bottom:4px">Alamat Pengiriman</span><span style="color:var(--slate-800);white-space:pre-wrap">${alamat}</span></div>
            ${note ? `<div style="margin-bottom:8px"><span style="display:block;color:var(--slate-500);font-size:12px;margin-bottom:4px">Note</span><span style="color:var(--slate-800);white-space:pre-wrap;background:var(--yellow-50);padding:8px 12px;border-radius:8px;border-left:3px solid var(--yellow-500);display:block">${note}</span></div>` : ''}
            <div style="display:flex;justify-content:space-between;margin-bottom:16px"><span>Escrow</span><span>${o.payment ? o.payment.status_escrow : '-'}</span></div>
        </div>
        <h4 style="margin-bottom:8px">Item Order</h4>
        ${o.items?.map((i,idx) => `
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--slate-100)">
            <span><img src="${productImg(i.id_product)}" style="width:32px;height:32px;border-radius:6px;object-fit:cover;display:inline-block;vertical-align:middle;margin-right:6px" onerror="this.style.display='none'"> ${i.nama_produk} <span style="color:var(--slate-500);font-size:12px">${i.quantity} ${i.satuan||'pcs'}</span></span>
            <span>Rp${(i.subtotal||0).toLocaleString()}</span>
        </div>`).join('') || '<p style="color:var(--slate-400)">Tidak ada item</p>'}
    `;
    document.getElementById('productModal').style.display = 'flex';
}

async function updateOrderStatus(id, status) {
    await api(`/api/orders/${id}/status`, { method: 'PUT', body: JSON.stringify({ status }) });
    showToast('Status diupdate!', 'success');
    if (currentUser && currentUser.id_role === 2) loadSellerOrders(document.getElementById('dashboardContent'));
    else if (currentUser && currentUser.id_role === 1) showAdminTab('orders');
    else loadBuyerOrders(document.getElementById('dashboardContent'));
}

async function adminSetStatus(orderId, status) {
    await api(`/api/orders/${orderId}/status`, { method: 'PUT', body: JSON.stringify({ status }) });
    showToast('Order #' + orderId + ' -> ' + status, 'success');
    showAdminTab('orders');
}

// Admin releases held escrow to the seller after the buyer confirmed receipt.
// This is the money-transfer step that accrues the seller's pendapatan.
async function _doReleaseEscrow(orderId) {
    const res = await api(`/api/orders/${orderId}/release`, { method: 'POST' });
    if (res && res.success) {
        showToast(`Dana Order #${orderId} dicairkan ke seller!`, 'success');
    } else {
        showToast((res && res.error) || 'Gagal mencairkan dana', 'error');
    }
    showAdminTab('orders');
}

async function adminReleaseEscrow(orderId) {
    showConfirm('Cairkan Dana Escrow', `Cairkan dana escrow untuk Order #${orderId} ke seller? (platform fee 5%)`, () => _doReleaseEscrow(orderId), 'Cairkan', 'Batal');
}

async function adminApproveOrder(orderId) {
    await api(`/api/orders/${orderId}/status`, { method: 'PUT', body: JSON.stringify({ status: 'paid' }) });
    showToast('Order #' + orderId + ' diapprove!', 'success');
    showAdminTab('orders');
}

async function adminRejectOrder(orderId) {
    showConfirm('Tolak Order', `Tolak order #${orderId}?`, () => {
        api(`/api/orders/${orderId}/status`, { method: 'PUT', body: JSON.stringify({ status: 'cancelled' }) }).then(() => {
            showToast('Order #' + orderId + ' ditolak.', 'info');
            showAdminTab('orders');
        });
    }, 'Tolak', 'Batal');
}

async function payOrder(orderId) {
    await api(`/api/payments/${orderId}/pay`, { method: 'POST' });
    showToast('Pembayaran berhasil! Dana di-escrow.', 'success');
    loadBuyerOrders(document.getElementById('dashboardContent'));
}

async function openDispute(orderId) {
    showPrompt('Buka Dispute', `Alasan dispute untuk Order #${orderId}:`, (value) => {
        const alasan = value.input.trim();
        const deskripsi = value.textarea.trim();
        if (!alasan) {
            showToast('Alasan wajib diisi', 'error');
            return;
        }
        api('/api/disputes', {
            method: 'POST',
            body: JSON.stringify({ id_order: orderId, alasan, deskripsi })
        }).then(() => {
            showToast('Dispute dibuka!', 'success');
            loadBuyerOrders(document.getElementById('dashboardContent'));
        });
    }, '', 'both');
}

let currentDisputeId = null;

async function openDisputeChat(orderId) {
    // Find the dispute for this order
    const disputes = await api('/api/disputes');
    const dispute = disputes.find(d => d.id_order === orderId && d.status === 'open');
    if (!dispute) {
        showToast('Tidak ada dispute open untuk order ini', 'error');
        return;
    }
    currentDisputeId = dispute.id_dispute;
    const modal = document.getElementById('disputeModal');
    const title = document.getElementById('disputeModalTitle');
    title.textContent = 'Dispute Order #' + orderId;
    
    // Show resolve button for admin
    const resolveBtn = document.getElementById('disputeResolveBtn');
    const isAdmin = currentUser && currentUser.id_role === 1;
    if (isAdmin) {
        resolveBtn.style.display = 'inline-block';
        resolveBtn.onclick = () => resolveDispute(currentDisputeId);
    } else {
        resolveBtn.style.display = 'none';
    }
    
    // Load messages
    await loadDisputeMessages();
    
    modal.style.display = 'flex';
}
async function loadDisputeMessages() {
    if (!currentDisputeId) return;
    const msgs = await api(`/api/disputes/${currentDisputeId}/messages`);
    const chatContent = document.getElementById('disputeChatContent');
    // Cache msgs for files modal lookup
    disputeMsgsCache = msgs || [];
    
    if (!msgs || msgs.length === 0) {
        chatContent.innerHTML = '<p style="text-align:center;color:var(--slate-400);padding:20px;font-size:13px">Belum ada pesan. Mulai obrolan di bawah.</p>';
        return;
    }
    
    chatContent.innerHTML = msgs.map(m => {
        const isMine = m.sender_id === currentUser.id_user;
        const isAdmin = currentUser.id_role === 1;
        const canDelete = isMine || isAdmin;
        const senderName = m.sender_nama || 'User';
        const time = m.sent_at ? m.sent_at.slice(5,16).replace('T',' ') : '';
        
        // Collect attachments: prefer new array, fall back to legacy single column
        let atts = [];
        if (Array.isArray(m.attachments) && m.attachments.length > 0) {
            atts = m.attachments;
        } else if (m.attachment) {
            const p = m.attachment;
            atts = [{ path: p, filename: p.split('/').pop(), mime_type: '', size: null }];
        }
        
        let attHtml = '';
        if (atts.length > 0) {
            const images = atts.filter(a => isImageType(a));
            const maxThumbs = 3;
            let inlineHtml = '';
            if (images.length > 0) {
                inlineHtml = images.slice(0, maxThumbs).map(a => {
                    const url = fullUrl(a.path);
                    return `<img class="chat-att-thumb" src="${escapeHtml(url)}" alt="${escapeHtml(a.filename||'')}" onclick="openDisputeFilesModal(${m.id_message})" onerror="this.style.display='none'">`;
                }).join('');
                if (images.length > maxThumbs) {
                    inlineHtml += `<span class="chat-att-more">+${images.length - maxThumbs}</span>`;
                }
            }
            if (atts.length > 0) {
                const label = images.length > 0 ? `+${atts.length} files` : `${atts.length} files`;
                inlineHtml += `<span class="chat-att-badge" onclick="openDisputeFilesModal(${m.id_message})"><i class="fas fa-paperclip"></i> ${escapeHtml(label)}</span>`;
            }
            if (inlineHtml) {
                attHtml = `<div class="chat-att-row">${inlineHtml}</div>`;
            }
        }
        
        const delBtn = canDelete ? `<i class="fas fa-trash-alt dispute-msg-del" title="Hapus pesan" onclick="deleteDisputeMessage(${m.id_message})"></i>` : '';
        
        return `
        <div class="dispute-msg ${isMine ? 'sent' : 'received'}" data-msg-id="${m.id_message}">
            <div class="dispute-msg-meta">
                <span>${escapeHtml(senderName)}${isMine ? ' (Anda)' : ''} • ${time}</span>
                ${delBtn}
            </div>
            <div class="dispute-bubble ${isMine ? 'sent' : 'received'}">
                ${escapeHtml(m.pesan)}
                ${attHtml}
            </div>
        </div>`;
    }).join('');
    chatContent.scrollTop = chatContent.scrollHeight;
}

function fullUrl(p) {
    if (!p) return '';
    return p.startsWith('http') ? p : window.location.origin + p;
}
function isImageType(a) {
    const m = (a.mime_type || '').toLowerCase();
    if (m.startsWith('image/')) return true;
    return /\.(jpg|jpeg|png|gif|webp|bmp|svg)$/i.test(a.path || a.filename || '');
}
function isVideoType(a) {
    const m = (a.mime_type || '').toLowerCase();
    if (m.startsWith('video/')) return true;
    return /\.(mp4|mov|webm|mkv|avi)$/i.test(a.path || a.filename || '');
}
function isAudioType(a) {
    const m = (a.mime_type || '').toLowerCase();
    if (m.startsWith('audio/')) return true;
    return /\.(mp3|wav|ogg|aac|m4a)$/i.test(a.path || a.filename || '');
}
function attIcon(a) {
    const name = (a.filename || a.path || '').toLowerCase();
    if (/\.pdf$/.test(name)) return { icon: 'fa-file-pdf', color: 'var(--red-500)' };
    if (/\.(doc|docx)$/.test(name)) return { icon: 'fa-file-word', color: 'var(--blue-500)' };
    if (/\.(xls|xlsx|csv)$/.test(name)) return { icon: 'fa-file-excel', color: 'var(--green-600)' };
    if (/\.(zip|rar|7z|tar|gz)$/.test(name)) return { icon: 'fa-file-archive', color: 'var(--amber-600)' };
    return { icon: 'fa-file', color: 'var(--slate-500)' };
}
function fileAttHtml(a) {
    const url = fullUrl(a.path);
    const { icon, color } = attIcon(a);
    const fname = a.filename || a.path.split('/').pop() || 'File';
    return `<a href="${escapeHtml(url)}" target="_blank" download class="dispute-att-file">
        <i class="fas ${icon}" style="color:${color}"></i>
        <span>${escapeHtml(fname)}</span>
        <i class="fas fa-download"></i>
    </a>`;
}

function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// ===== DISPUTE FILES MODAL (centered list of attachments for a message) =====
let disputeFilesMsgId = null;
let disputeMsgsCache = [];

function openDisputeFilesModal(msgId) {
    const msg = (disputeMsgsCache || []).find(m => m.id_message === msgId);
    if (!msg) return;
    let atts = [];
    if (Array.isArray(msg.attachments) && msg.attachments.length > 0) {
        atts = msg.attachments;
    } else if (msg.attachment) {
        const p = msg.attachment;
        atts = [{ path: p, filename: p.split('/').pop(), mime_type: '', size: null }];
    }
    if (atts.length === 0) return;
    disputeFilesMsgId = msgId;
    document.getElementById('disputeFilesModalTitle').textContent = `Files (${atts.length})`;
    const body = document.getElementById('disputeFilesModalBody');
    body.innerHTML = atts.map(a => {
        const url = fullUrl(a.path);
        const fname = a.filename || a.path.split('/').pop() || 'File';
        const isImage = isImageType(a);
        const isVideo = isVideoType(a);
        const isAudio = isAudioType(a);
        let preview = '';
        if (isImage) preview = `<img src="${escapeHtml(url)}" alt="" loading="lazy">`;
        else if (isVideo) preview = `<video src="${escapeHtml(url)}" controls preload="metadata"></video>`;
        else if (isAudio) preview = `<audio src="${escapeHtml(url)}" controls style="width:100%"></audio>`;
        else {
            const { icon, color } = attIcon(a);
            preview = `<div class="chat-file-icon"><i class="fas ${icon}" style="color:${color}"></i></div>`;
        }
        return `<div class="chat-file-item">
            <div class="chat-file-preview-box">${preview}</div>
            <div class="chat-file-info">
                <div class="chat-file-name" title="${escapeHtml(fname)}">${escapeHtml(fname)}</div>
                <div class="chat-file-meta">${escapeHtml(formatSize(a.size))}${a.mime_type ? ' • ' + escapeHtml(a.mime_type) : ''}</div>
            </div>
            <div class="chat-file-actions">
                <a href="${escapeHtml(url)}" target="_blank" download class="btn btn-sm btn-outline"><i class="fas fa-download"></i></a>
            </div>
        </div>`;
    }).join('');
    const footer = document.getElementById('disputeFilesModalFooter');
    const isMe = currentUser && msg.sender_id === currentUser.id_user;
    const isAdmin = currentUser && currentUser.id_role === 1;
    footer.innerHTML = (isMe || isAdmin)
        ? `<button class="btn btn-danger" onclick="deleteDisputeMessageFromModal()"><i class="fas fa-trash"></i> Hapus Pesan &amp; Files</button>`
        : '';
    document.getElementById('disputeFilesModal').style.display = 'flex';
}

function closeDisputeFilesModal() {
    document.getElementById('disputeFilesModal').style.display = 'none';
    disputeFilesMsgId = null;
}

function deleteDisputeMessageFromModal() {
    const msgId = disputeFilesMsgId;
    closeDisputeFilesModal();
    if (msgId) deleteDisputeMessage(msgId);
}

async function deleteDisputeMessage(msgId) {
    if (!confirm('Hapus pesan ini? Semua file terlampir juga akan dihapus.')) return;
    if (!currentDisputeId) return;
    const msg = document.querySelector(`[data-msg-id="${msgId}"]`);
    if (msg) msg.style.opacity = '0.5';
    try {
        const res = await api(`/api/disputes/${currentDisputeId}/messages/${msgId}`, { method: 'DELETE' });
        if (res.success) {
            showToast('Pesan dihapus', 'success');
            await loadDisputeMessages();
        } else {
            showToast(res.error || 'Gagal menghapus', 'error');
            await loadDisputeMessages();
        }
    } catch(e) {
        showToast('Gagal menghapus pesan', 'error');
        await loadDisputeMessages();
    }
}

// ===== MULTI-FILE UPLOAD STATE =====
let disputeAttachmentFiles = []; // array of File
let disputeAttachmentUrls = {}; // fileKey -> objectURL (for image preview)

function fileKey(f) { return f.name + '|' + f.size + '|' + f.lastModified; }

function onDisputeFileSelect() {
    const fileInput = document.getElementById('disputeFileInput');
    const preview = document.getElementById('disputeFilePreview');
    const newFiles = Array.from(fileInput.files || []);
    
    if (newFiles.length === 0) {
        renderFilePreview();
        return;
    }
    // Append to existing list (avoid duplicates by key)
    const existingKeys = new Set(disputeAttachmentFiles.map(fileKey));
    for (const f of newFiles) {
        if (!existingKeys.has(fileKey(f))) {
            disputeAttachmentFiles.push(f);
            existingKeys.add(fileKey(f));
        }
    }
    renderFilePreview();
    // Reset input so same file can be re-selected later
    fileInput.value = '';
}

function renderFilePreview() {
    const preview = document.getElementById('disputeFilePreview');
    if (disputeAttachmentFiles.length === 0) {
        preview.style.display = 'none';
        preview.innerHTML = '';
        return;
    }
    preview.innerHTML = '<div class="chat-file-preview-list">' +
        disputeAttachmentFiles.map((f, i) => {
            const key = fileKey(f);
            const isImage = /^image\//.test(f.type);
            const size = formatSize(f.size);
            let previewHtml = '';
            if (isImage) {
                if (!disputeAttachmentUrls[key]) {
                    disputeAttachmentUrls[key] = URL.createObjectURL(f);
                }
                previewHtml = `<img src="${disputeAttachmentUrls[key]}" alt="">`;
            } else {
                const { icon, color } = attIcon({ path: f.name, filename: f.name, mime_type: f.type });
                previewHtml = `<div class="fp-icon"><i class="fas ${icon}" style="color:${color}"></i></div>`;
            }
            return `<div class="chat-file-preview">
                ${previewHtml}
                <div class="fp-info">
                    <div class="fp-name">${escapeHtml(f.name)}</div>
                    <div class="fp-size">${size}</div>
                </div>
                <i class="fas fa-times fp-remove" title="Hapus" onclick="removeDisputeFile(${i})"></i>
            </div>`;
        }).join('') + '</div>';
    preview.style.display = 'block';
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function removeDisputeFile(idx) {
    const f = disputeAttachmentFiles[idx];
    if (f) {
        const key = fileKey(f);
        if (disputeAttachmentUrls[key]) {
            URL.revokeObjectURL(disputeAttachmentUrls[key]);
            delete disputeAttachmentUrls[key];
        }
    }
    disputeAttachmentFiles.splice(idx, 1);
    renderFilePreview();
}

function clearDisputeFiles() {
    for (const key in disputeAttachmentUrls) {
        URL.revokeObjectURL(disputeAttachmentUrls[key]);
    }
    disputeAttachmentUrls = {};
    disputeAttachmentFiles = [];
    renderFilePreview();
    const input = document.getElementById('disputeFileInput');
    if (input) input.value = '';
}

async function sendDisputeMessage() {
    const input = document.getElementById('disputeMessageInput');
    const pesan = input.value.trim();
    const files = disputeAttachmentFiles;
    
    if (!pesan && files.length === 0) return;
    if (!currentDisputeId) {
        showToast('Dispute tidak ditemukan', 'error');
        return;
    }
    
    let res;
    if (files.length > 0) {
        const formData = new FormData();
        files.forEach(f => formData.append('attachments[]', f));
        formData.append('pesan', pesan);
        
        res = await fetch(API + `/api/disputes/${currentDisputeId}/messages`, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData
        });
        try { res = await res.json(); } catch(e) { res = { error: 'Gagal memproses respons' }; }
    } else {
        res = await api(`/api/disputes/${currentDisputeId}/messages`, {
            method: 'POST',
            body: JSON.stringify({ pesan })
        });
    }
    
    if (res.success) {
        input.value = '';
        clearDisputeFiles();
        await loadDisputeMessages();
    } else {
        showToast(res.error || 'Gagal mengirim pesan', 'error');
    }
}

// ===== ADMIN =====
async function showAdminTab(tab) {
    const el = document.getElementById('adminContent');
    // Toggle sidebar active state
    document.querySelectorAll('#page-admin .sidebar-section a').forEach(a => a.classList.remove('active'));
    const activeLink = document.querySelector(`#page-admin .sidebar-section a[onclick*="${tab}"]`);
    if (activeLink) activeLink.classList.add('active');

    if (tab === 'overview') {
        const s = await api('/api/admin/stats');
        el.innerHTML = `<h2 style="margin-bottom:20px">Ringkasan</h2>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon" style="background:var(--green-100);color:var(--green-600)"><i class="fas fa-users"></i></div>
                <div class="stat-value">${s.total_accounts||0}</div><div class="stat-label">Akun (tanpa admin)</div></div>
            <div class="stat-card"><div class="stat-icon" style="background:var(--blue-100);color:var(--blue-500)"><i class="fas fa-store"></i></div>
                <div class="stat-value">${s.total_sellers||0}</div><div class="stat-label">Penjual</div></div>
            <div class="stat-card"><div class="stat-icon" style="background:var(--yellow-100);color:var(--yellow-500)"><i class="fas fa-box"></i></div>
                <div class="stat-value">${s.total_products||0}</div><div class="stat-label">Produk</div></div>
            <div class="stat-card"><div class="stat-icon" style="background:var(--orange-100);color:var(--orange-500)"><i class="fas fa-shopping-bag"></i></div>
                <div class="stat-value">${s.total_orders||0}</div><div class="stat-label">Order</div></div>
            <div class="stat-card"><div class="stat-icon" style="background:var(--green-100);color:var(--green-600)"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-value" style="font-size:18px">${fmtRp(s.total_revenue)}</div><div class="stat-label">Pendapatan</div></div>
            <div class="stat-card"><div class="stat-icon" style="background:var(--green-100);color:var(--green-600)"><i class="fas fa-lock"></i></div>
                <div class="stat-value" style="font-size:18px">${fmtRp(s.escrow_held)}</div><div class="stat-label">Escrow Dipegang</div></div>
            <div class="stat-card"><div class="stat-icon" style="background:var(--red-100);color:var(--red-500)"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-value">${s.open_disputes||0}</div><div class="stat-label">Dispute Terbuka</div></div>
        </div>
        <div style="margin-top:20px">
            <h4 style="margin-bottom:8px">Produk Menunggu Verifikasi: ${s.pending_products||0}</h4>
            <span class="status-badge status-pending">${s.pending_products||0} pending</span>
        </div>`;
    }
    else if (tab === 'products') {
        const products = await api('/api/products?status=pending');
        const gradeColor = { A: 'var(--green-600)', B: 'var(--blue-500)', C: 'var(--yellow-500)' };
        el.innerHTML = `<h2 style="margin-bottom:20px">Verifikasi Produk (${products.length})</h2>
        <div class="adm-grid">
            ${products.map(p => {
                const gc = gradeColor[p.grade] || 'var(--slate-400)';
                const foto = p.foto || '';
                return `
            <div class="adm-card">
                <div class="adm-card-head">
                    <span class="id">${p.nama_produk}</span>
                    <span class="date">${p.seller_nama}</span>
                </div>
                <div class="adm-card-body">
                    ${foto ? `<img src="${foto}" style="width:100%;height:120px;object-fit:cover;border-radius:8px;margin-bottom:8px" onerror="this.outerHTML='<div style=\\'height:120px;background:var(--slate-100);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--slate-300);font-size:24px;margin-bottom:8px\\'><i class=\\'fas fa-image\\'></i></div>'">` : ''}
                    <div class="row"><span>Harga</span><span class="v">Rp${(p.harga||0).toLocaleString()}<small style="font-size:11px;color:var(--slate-500)"> /${p.satuan||'pcs'}</small></span></div>
                    <div class="row"><span>Stok</span><span class="v">${(p.stok||0).toLocaleString()} ${p.satuan||'pcs'}</span></div>
                    <div class="row"><span>Grade</span><select id="grade_${p.id_product}" style="padding:4px 8px;border-radius:6px;border:1px solid var(--slate-200);font-size:12px;font-weight:600">
                        <option value="A" style="color:var(--green-600)">A (Premium)</option>
                        <option value="B" style="color:var(--blue-500)">B (Standar)</option>
                        <option value="C" style="color:var(--yellow-500)">C (Ekonomi)</option>
                    </select></div>
                </div>
                <div class="adm-card-foot">
                    <button class="btn btn-sm btn-outline" onclick="showAdminProductDetail(${p.id_product})"><i class="fas fa-eye"></i> Detail</button>
                    <button class="btn btn-sm btn-danger" onclick="rejectProduct(${p.id_product})"><i class="fas fa-times"></i> Tolak</button>
                    <button class="btn btn-sm btn-primary" onclick="approveProduct(${p.id_product})"><i class="fas fa-check"></i> Setujui</button>
                </div>
            </div>`;
            }).join('') || '<div class="adm-empty">Semua produk sudah diverifikasi</div>'}
        </div>`;
    }
    else if (tab === 'orders') {
        const orders = await api('/api/orders');
        el.innerHTML = `<h2 style="margin-bottom:20px">Semua Order (${orders.length})</h2>
        <div class="adm-grid">
            ${orders.map(o => `
            <div class="adm-card" onclick="showOrderDetail(${o.id_order})" style="cursor:pointer">
                <div class="adm-card-head">
                    <span class="id">Order #${o.id_order}</span>
                    <span class="status-badge status-${o.status_order}" style="font-size:10px;padding:2px 8px">${o.status_order}</span>
                </div>
                <div class="adm-card-body">
                    <div class="row"><span>Tanggal</span><span class="v">${o.tanggal_order?.slice(0,10)||'-'}</span></div>
                    <div class="row"><span>Buyer</span><span class="v">${o.buyer_nama||'-'}</span></div>
                    <div class="row"><span>Seller</span><span class="v">${o.seller_nama||'-'}</span></div>
                    <div class="row"><span>Total</span><span class="v" style="color:var(--green-700)">Rp${(o.total_amount||0).toLocaleString()}</span></div>
                    ${o.payment ? `<div class="row"><span>Escrow</span><span class="v">${o.payment.status_escrow||'-'}</span></div>` : ''}
                </div>
                <div class="adm-card-foot">
                    ${o.tracking_number && (o.status_order === 'shipped' || o.status_order === 'disputed') ? `<button class="btn btn-sm btn-action-info" title="Tracking" onclick="event.stopPropagation();showTracking(${o.id_order})"><i class="fas fa-truck"></i></button>` : ''}
                    ${(o.status_order === 'shipped' || o.status_order === 'confirmed') && o.payment && o.payment.status_escrow !== 'released' ? `<button class="btn btn-sm btn-action-success" onclick="event.stopPropagation();adminReleaseEscrow(${o.id_order})"><i class="fas fa-hand-holding-usd"></i> Cairkan</button>` : ''}
                </div>
            </div>`).join('') || '<div class="adm-empty">Belum ada order</div>'}
        </div>`;
    }
    else if (tab === 'disputes') {
        const disputes = await api('/api/disputes');
        el.innerHTML = `<h2 style="margin-bottom:20px">Dispute</h2>
        <div class="data-list">
            ${disputes.map(d => `
            <div class="data-list-item">
                <div><div class="item-main">Order #${d.id_order}</div><div class="item-sub">${d.alasan||''}</div></div>
                <div><span class="status-badge status-${d.status}">${d.status}</span></div>
                <div style="font-size:12px;color:var(--slate-500)">${d.deskripsi?.slice(0,50)||'-'}</div>
                <div style="display:flex;gap:6px">
                    ${d.status==='open' ? `<button class="btn btn-sm btn-primary" onclick="openDisputeChat(${d.id_order})">Lihat Chat</button>` : ''}
                    ${d.status==='open' ? `<button class="btn btn-sm btn-success" onclick="resolveDispute(${d.id_dispute})">Resolve</button>` : '<span style="font-size:12px;color:var(--green-600)">Selesai</span>'}
                </div>
            </div>`).join('') || '<p style="padding:20px;text-align:center;color:var(--slate-400)">Tidak ada dispute</p>'}
        </div>`;
    }
    else if (tab === 'categories') {
        const cats = await api('/api/categories');
        el.innerHTML = `<h2 style="margin-bottom:16px">Kategori</h2>
        <div style="display:flex;gap:8px;margin-bottom:16px">
            <input id="newCat" placeholder="Nama kategori baru" style="flex:1;padding:8px;border:1px solid var(--slate-200);border-radius:8px">
            <button class="btn btn-primary btn-sm" onclick="addCategory()"><i class="fas fa-plus"></i></button>
        </div>
        <div class="data-list">
            ${cats.map(c => `
            <div class="data-list-item">
                <div class="item-main">${c.nama_kategori}</div>
                <div>#${c.id_kategori}</div>
                <div></div>
                <button class="btn-icon" onclick="deleteCategory(${c.id_kategori})"><i class="fas fa-trash" style="color:var(--red-500)"></i></button>
            </div>`).join('')}
        </div>`;
    }
    else if (tab === 'users') {
        const data = await api('/api/admin/users');
        const users = (data && data.users) || [];
        el.innerHTML = `<h2 style="margin-bottom:20px">Pengguna</h2>
        <div class="data-list">
            ${users.map(u => `
            <div class="data-list-item">
                <div><div class="item-main">${u.nama}</div></div>
                <div><span class="status-badge status-${u.role}">${u.role}</span></div>
                <div></div>
                <button class="btn btn-sm btn-outline" onclick="showChatWith(${u.id_user})" title="Chat dengan ${u.nama}"><i class="fas fa-comment"></i> Chat</button>
            </div>`).join('') || '<p style="padding:20px;text-align:center;color:var(--slate-400)">Tidak ada akun terdaftar</p>'}
        </div>`;
    }
}

async function showAdminProductDetail(id) {
    const p = await api(`/api/products/${id}`);
    if (!p || p.error) { showToast('Produk tidak ditemukan', 'error'); return; }
    const img = p.foto || productImg(p.id_product);
    document.getElementById('productDetailContent').innerHTML = `
        <div style="display:flex;gap:20px;flex-wrap:wrap">
            <div style="flex:0 0 200px">
                <img src="${img}" alt="${p.nama_produk}" style="width:100%;height:200px;object-fit:cover;border-radius:12px" onerror="this.style.display='none'">
                <span class="status-badge status-${p.status}" style="display:inline-block;margin-top:8px">${p.status}</span>
            </div>
            <div style="flex:1;min-width:250px">
                <h2>${p.nama_produk}</h2>
                <div class="product-detail-price">Rp${(p.harga||0).toLocaleString()} <small style="font-size:14px;color:var(--slate-500)">/${p.satuan||'pcs'}</small></div>
                <p style="color:var(--slate-600);margin:8px 0">${p.deskripsi||'Tidak ada deskripsi'}</p>
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px">
                    <span><i class="fas fa-store"></i> ${p.seller_nama||'Petani'} (${p.nama_usaha||''})</span>
                    <span><i class="fas fa-box"></i> Stok: ${p.stok} ${p.satuan||'pcs'}</span>
                    ${p.grade ? `<span><i class="fas fa-award"></i> Grade ${p.grade}</span>` : '<span><i class="fas fa-award"></i> Belum ada grade</span>'}
                </div>
                <div style="margin-top:16px">
                    <h4 style="margin-bottom:8px"><i class="fas fa-folder-open"></i> Dokumen Grading</h4>
                    ${(p.documents && p.documents.length > 0) ? `
                        <div class="doc-list">
                            ${p.documents.map(d => `
                                <a href="${d.url}" target="_blank" class="doc-link">
                                    <i class="fas ${docIcon(d.tipe_berkas)}"></i>
                                    <span>${d.nama_berkas}</span>
                                    <small>${d.ukuran_formatted||''}</small>
                                </a>
                            `).join('')}
                        </div>
                    ` : '<p style="color:var(--slate-400);font-size:13px">Tidak ada dokumen terunggah</p>'}
                </div>
            </div>
        </div>
    `;
    document.getElementById('productModal').style.display = 'flex';
}

async function approveProduct(id) {
    const grade = document.getElementById(`grade_${id}`)?.value || 'A';
    await api(`/api/products/${id}/review`, {
        method: 'POST',
        body: JSON.stringify({ action: 'active', grade })
    });
    showToast('Produk disetujui!', 'success');
    showAdminTab('products');
}

async function rejectProduct(id) {
    await api(`/api/products/${id}/review`, {
        method: 'POST',
        body: JSON.stringify({ action: 'rejected' })
    });
    showToast('Produk ditolak.', 'success');
    showAdminTab('products');
}

async function resolveDispute(id) {
    const modal = document.getElementById('resolveModal');
    const title = document.getElementById('resolveModalTitle');
    const content = document.getElementById('resolveModalContent');
    title.textContent = 'Resolve Dispute #' + id;
    
    content.innerHTML = `
        <p style="margin-bottom:16px;color:var(--slate-600)">Pilih resolusi untuk dispute ini:</p>
        <div class="form-group">
            <label for="resolutionType">Jenis Resolusi</label>
            <select id="resolutionType" style="width:100%;padding:10px 14px;border:1px solid var(--slate-300);border-radius:8px;font-size:14px">
                <option value="refund">Refund penuh ke buyer</option>
                <option value="partial_release">Partial release (sebagian ke seller)</option>
                <option value="full_release">Full release (seluruhnya ke seller)</option>
            </select>
        </div>
        <div class="form-group">
            <label for="resolutionAmount">Jumlah (untuk partial refund)</label>
            <input type="number" id="resolutionAmount" placeholder="0" style="width:100%;padding:10px 14px;border:1px solid var(--slate-300);border-radius:8px;font-size:14px">
        </div>
        <div class="form-group">
            <label for="resolutionDesc">Deskripsi (opsional)</label>
            <textarea id="resolutionDesc" rows="3" placeholder="Keterangan resolusi..." style="width:100%;padding:10px 14px;border:1px solid var(--slate-300);border-radius:8px;font-size:14px"></textarea>
        </div>
        <div class="ship-actions">
            <button class="btn btn-secondary" onclick="closeModal('resolveModal')">Batal</button>
            <button class="btn btn-primary" onclick="confirmResolve(${id})"><i class="fas fa-check"></i> Konfirmasi Resolusi</button>
        </div>
    `;
    
    modal.style.display = 'flex';
}

async function confirmResolve(disputeId) {
    const resolution = document.getElementById('resolutionType')?.value;
    const amount = parseInt(document.getElementById('resolutionAmount')?.value) || 0;
    const deskripsi = document.getElementById('resolutionDesc')?.value || '';
    
    if (!resolution) {
        showToast('Jenis resolusi wajib dipilih', 'error');
        return;
    }
    
    const res = await api(`/api/disputes/${disputeId}/resolve`, {
        method: 'POST',
        body: JSON.stringify({ resolution, amount, deskripsi })
    });
    
    if (res.success) {
        showToast('Dispute di-resolve!', 'success');
        closeModal('resolveModal');
        showAdminTab('disputes');
    } else {
        showToast(res.error || 'Gagal resolve dispute', 'error');
    }
}

async function addCategory() {
    const name = document.getElementById('newCat').value.trim();
    if (!name) return;
    await api('/api/categories', { method: 'POST', body: JSON.stringify({ nama_kategori: name }) });
    showToast('Kategori ditambahkan!', 'success');
    showAdminTab('categories');
}

async function deleteCategory(id) {
    await api(`/api/categories/${id}`, { method: 'DELETE' });
    showToast('Kategori dihapus', 'success');
    showAdminTab('categories');
}

// ===== NOTIFICATIONS =====
async function showNotifications() {
    if (!currentUser) { showToast('Login terlebih dahulu', 'error'); return; }
    const notifs = await api('/api/notifications');
    const panel = document.getElementById('notifPanel');
    const list = document.getElementById('notifList');
    list.innerHTML = notifs.map(n => {
        var action = 'markRead(' + n.id_notification + ')';
        if (n.tipe === 'order') {
            action += ';navigate(\'orders\');closeNotifPanel()';
        } else if (n.tipe === 'negotiation') {
            // Safe: this notification may predate the metadata column (null).
            var meta = safeMeta(n.metadata);
            if (meta.partner_id) action += ';openNegoChat(' + meta.partner_id + ')';
            else action += ';showChat()';
            action += ';closeNotifPanel()';
        } else if (n.tipe === 'product') {
            action += ';navigate(\'products\');closeNotifPanel()';
        } else if (n.tipe === 'verification') {
            action += ';navigate(\'profile\');closeNotifPanel()';
        } else if (n.tipe === 'dispute_opened' || n.tipe === 'dispute_message' || n.tipe === 'dispute_resolved') {
            var meta = safeMeta(n.metadata);
            if (meta.order_id) {
                action += ';openDisputeChat(' + meta.order_id + ');closeNotifPanel()';
            } else {
                action += ';navigate(\'orders\');closeNotifPanel()';
            }
        }
        return '<div class="notif-item ' + (n.is_read ? '' : 'unread') + '" onclick="' + action + '">' +
            '<div class="notif-tipe">' + n.tipe + '</div>' +
            '<div class="notif-msg">' + n.pesan + '</div>' +
            '<div class="notif-time">' + n.created_at + '</div>' +
            '<div class="notif-action"><i class="fas fa-chevron-right"></i></div>' +
            '</div>';
    }).join('') || '<p style="padding:20px;text-align:center;color:var(--slate-400)">Tidak ada notifikasi</p>';
    panel.style.display = 'flex';
    // The badge reflects unread count, and a fresh fetch can arrive between
    // polls — resync so the badge matches the list right now.
    updateNotifCount();
}

// Parse notification metadata defensively. Older rows (written before the
// metadata column existed) have null metadata, and JSON.parse(null) throws.
function safeMeta(s) {
    if (!s) return {};
    try { return JSON.parse(s) || {}; } catch (e) { return {}; }
}

// Open the chat with the person who triggered a negotiation notification.
async function openNegoChat(partnerId) {
    const convos = await api('/api/messages/conversations');
    const c = convos.find(x => x.contact_id === partnerId);
    const name = c ? c.contact_nama : '';
    if (!currentUser) return;
    await loadConversations();
    openChat(partnerId, name);
    document.getElementById('chatPanel').style.display = 'flex';
}

function closeNotifPanel() {
    document.getElementById('notifPanel').style.display = 'none';
}

async function markRead(id) {
    await api(`/api/notifications/${id}/read`, { method: 'POST' });
    updateNotifCount();
}

async function markAllRead() {
    await api('/api/notifications/read-all', { method: 'POST' });
    updateNotifCount();
    showNotifications();
}

async function updateNotifCount() {
    if (!currentUser) return;
    const data = await api('/api/notifications/unread-count');
    const badge = document.getElementById('notifBadge');
    if (data.count > 0) {
        badge.textContent = data.count > 99 ? '99+' : data.count;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
}

async function startNotifPolling() {
    const poll = async () => {
        if (currentUser) {
            await updateNotifCount();
            updateChatBadge();
        }
        setTimeout(poll, 30000);
    };
    setTimeout(poll, 3000);
}

async function updateChatBadge() {
    if (!currentUser) {
        document.getElementById('chatBadge').style.display = 'none';
        return;
    }
    const convos = await api('/api/messages/conversations');
    const badge = document.getElementById('chatBadge');
    const unread = localStorage.getItem('chat_last_read') || '0';
    const latest = convos[0]?.last_msg_at || '0';
    if (latest > unread) {
        badge.textContent = convos.length;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
}

function markChatRead() {
    localStorage.setItem('chat_last_read', new Date().toISOString());
    updateChatBadge();
}

// ===== CHAT =====
async function showChat() {
    if (!currentUser) { showToast('Login terlebih dahulu', 'error'); return; }
    document.getElementById('chatPanel').style.display = 'flex';
    document.getElementById('chatThread').style.display = 'none';
    document.getElementById('chatConvos').style.display = 'block';
    markChatRead();
    await loadConversations();
}

async function showChatWith(userId) {
    if (!currentUser) { showToast('Login terlebih dahulu', 'error'); return; }
    await loadConversations();
    openChat(userId, '');
    document.getElementById('chatPanel').style.display = 'flex';
}

function closeChatPanel() {
    document.getElementById('chatPanel').style.display = 'none';
}

async function loadConversations() {
    const convos = await api('/api/messages/conversations');
    const el = document.getElementById('chatConvos');
    el.innerHTML = convos.map(c => `
        <div class="chat-convo" onclick="openChat(${c.contact_id},'${(c.contact_nama||'').replace(/'/g,"")}')">
            <div class="chat-convo-avatar">${c.contact_nama?.charAt(0)}</div>
            <div class="chat-convo-info">
                <div class="chat-convo-name">${c.contact_nama}</div>
                <div class="chat-convo-preview">${c.pesan||''}</div>
            </div>
        </div>`).join('') || '<p style="padding:20px;text-align:center;color:var(--slate-400)">Belum ada percakapan</p>';
}

async function openChat(userId, name) {
    currentChatUser = { id: userId, name };
    document.getElementById('chatContactName').textContent = name;
    document.getElementById('chatConvos').style.display = 'none';
    document.getElementById('chatThread').style.display = 'flex';
    await loadMessages(userId);
}

async function loadMessages(userId) {
    const msgs = await api(`/api/messages/${userId}`);
    const el = document.getElementById('chatMessages');
    chatMsgsCache = msgs || [];
    el.innerHTML = (msgs || []).map(m => {
        const isMe = m.sender_id === currentUser.id_user;
        const isNego = m.id_product && m.nama_produk;
        const isSellerReceiving = isNego && !isMe && currentUser.id_role === 2;

        let extra = '';
        if (isNego) {
            const bgColor = isMe ? 'rgba(34,197,94,0.15)' : 'rgba(255,255,255,0.15)';
            extra = `<div style="display:flex;gap:10px;align-items:center;background:${bgColor};padding:10px;border-radius:10px;margin-top:8px;cursor:pointer" onclick="showProduct(${m.id_product})">
                    <img src="${m.foto_produk||''}" style="width:48px;height:48px;border-radius:8px;object-fit:cover" onerror="this.style.display='none'">
                    <div style="flex:1">
                        <div style="font-size:13px;font-weight:600">${m.nama_produk}</div>
                        <div style="font-size:12px;opacity:0.8">Rp${(m.harga||0).toLocaleString()}</div>
                    </div>
                </div>`;
        }

        // Attachments — show a compact preview + a "Files" button that opens the centered modal
        let attHtml = '';
        const atts = Array.isArray(m.attachments) ? m.attachments : [];
        if (atts.length > 0) {
            const images = atts.filter(a => isImageType(a));
            const preview = images.slice(0, 3).map(a => {
                const url = fullUrl(a.path);
                return `<img class="chat-att-thumb" src="${escapeHtml(url)}" alt="${escapeHtml(a.filename||'')}" onclick="openChatFilesModal(${m.id_message})" onerror="this.style.display='none'">`;
            }).join('');
            const total = atts.length;
            const label = total === 1 ? '1 file' : `${total} files`;
            const badge = `<div class="chat-att-badge" onclick="openChatFilesModal(${m.id_message})"><i class="fas fa-paperclip"></i> ${escapeHtml(label)}</div>`;
            const extra = images.length > 3 ? `<span class="chat-att-more">+${images.length - 3}</span>` : '';
            attHtml = `<div class="chat-att-row">${preview}${extra}${badge}</div>`;
        }

        // Show action buttons for seller receiving nego
        let actionBtns = '';
        if (isSellerReceiving && !m.id_order) {
            actionBtns = `<div class="nego-actions">
                    <button class="btn btn-success btn-nego" onclick="acceptNego(${m.id_message})"><i class="fas fa-check"></i> Terima</button>
                    <button class="btn btn-danger btn-nego" onclick="rejectNego(${m.id_message})"><i class="fas fa-times"></i> Tolak</button>
                    <button class="btn btn-outline btn-nego" onclick="counterNego(${m.id_message},${m.id_product},${m.harga})"><i class="fas fa-dollar-sign"></i> Banding</button>
                </div>`;
        }

        const delBtn = isMe ? `<i class="fas fa-trash-alt chat-msg-del" title="Hapus pesan" onclick="deleteChatMessage(${m.id_message})"></i>` : '';

        return `<div class="chat-msg ${isMe ? 'sent' : 'received'}" data-msg-id="${m.id_message}">
            ${m.pesan}
            ${extra}
            ${attHtml}
            ${actionBtns}
            <div class="chat-msg-time">${m.sent_at?.slice(11,16)||''} ${delBtn}</div>
        </div>`;
    }).join('') || '<p style="text-align:center;color:var(--slate-400);padding:20px">Belum ada pesan</p>';
    el.scrollTop = el.scrollHeight;
}

// ===== CHAT FILE UPLOAD (pending attachments) =====
let chatAttachmentFiles = [];
let chatAttachmentUrls = {};

function onChatFileSelect() {
    const input = document.getElementById('chatFileInput');
    const newFiles = Array.from(input.files || []);
    const existingKeys = new Set(chatAttachmentFiles.map(fileKey));
    for (const f of newFiles) {
        if (!existingKeys.has(fileKey(f))) {
            chatAttachmentFiles.push(f);
            existingKeys.add(fileKey(f));
        }
    }
    renderChatFilePreview();
    input.value = '';
}

function renderChatFilePreview() {
    const preview = document.getElementById('chatFilePreview');
    if (chatAttachmentFiles.length === 0) {
        preview.style.display = 'none';
        preview.innerHTML = '';
        return;
    }
    preview.innerHTML = '<div class="chat-file-preview-list">' +
        chatAttachmentFiles.map((f, i) => {
            const key = fileKey(f);
            const isImage = /^image\//.test(f.type);
            const size = formatSize(f.size);
            let previewHtml = '';
            if (isImage) {
                if (!chatAttachmentUrls[key]) chatAttachmentUrls[key] = URL.createObjectURL(f);
                previewHtml = `<img src="${chatAttachmentUrls[key]}" alt="">`;
            } else {
                const icon = /^video\//.test(f.type) ? 'fa-file-video'
                           : /^audio\//.test(f.type) ? 'fa-file-audio'
                           : /pdf/i.test(f.type) ? 'fa-file-pdf'
                           : /word|doc/i.test(f.type) ? 'fa-file-word'
                           : /excel|sheet|xls/i.test(f.type) ? 'fa-file-excel'
                           : 'fa-file';
                const color = /^video\//.test(f.type) ? 'var(--purple-500)'
                             : /^audio\//.test(f.type) ? 'var(--cyan-500)'
                             : /pdf/i.test(f.type) ? 'var(--red-500)'
                             : /word|doc/i.test(f.type) ? 'var(--blue-500)'
                             : /excel|sheet|xls/i.test(f.type) ? 'var(--green-600)'
                             : 'var(--slate-500)';
                previewHtml = `<div class="fp-icon"><i class="fas ${icon}" style="color:${color}"></i></div>`;
            }
            return `<div class="chat-file-preview">
                ${previewHtml}
                <div class="fp-info">
                    <div class="fp-name">${escapeHtml(f.name)}</div>
                    <div class="fp-size">${size}</div>
                </div>
                <i class="fas fa-times fp-remove" title="Hapus" onclick="removeChatFile(${i})"></i>
            </div>`;
        }).join('') + '</div>';
    preview.style.display = 'block';
}

function removeChatFile(idx) {
    const f = chatAttachmentFiles[idx];
    if (f) {
        const key = fileKey(f);
        if (chatAttachmentUrls[key]) {
            URL.revokeObjectURL(chatAttachmentUrls[key]);
            delete chatAttachmentUrls[key];
        }
    }
    chatAttachmentFiles.splice(idx, 1);
    renderChatFilePreview();
}

function clearChatFiles() {
    for (const key in chatAttachmentUrls) URL.revokeObjectURL(chatAttachmentUrls[key]);
    chatAttachmentUrls = {};
    chatAttachmentFiles = [];
    renderChatFilePreview();
    const input = document.getElementById('chatFileInput');
    if (input) input.value = '';
}

async function sendChat() {
    const input = document.getElementById('chatInput');
    const text = input.value.trim();
    const files = chatAttachmentFiles;
    if (!text && files.length === 0) return;
    if (!currentChatUser) return;

    let res;
    if (files.length > 0) {
        const formData = new FormData();
        files.forEach(f => formData.append('attachments[]', f));
        formData.append('pesan', text);
        formData.append('receiver_id', currentChatUser.id);
        res = await fetch(API + '/api/messages', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData
        });
        try { res = await res.json(); } catch(e) { res = { error: 'Gagal memproses respons' }; }
    } else {
        res = await api('/api/messages', {
            method: 'POST',
            body: JSON.stringify({ receiver_id: currentChatUser.id, pesan: text })
        });
    }

    if (res.success) {
        input.value = '';
        clearChatFiles();
        await loadMessages(currentChatUser.id);
    } else {
        showToast(res.error || 'Gagal mengirim pesan', 'error');
    }
}

// ===== CHAT FILES MODAL (centered list of attachments for a message) =====
let chatFilesMsgId = null;

function openChatFilesModal(msgId) {
    const msg = (chatMsgsCache || []).find(m => m.id_message === msgId);
    if (!msg || !Array.isArray(msg.attachments) || msg.attachments.length === 0) return;
    chatFilesMsgId = msgId;
    document.getElementById('chatFilesModalTitle').textContent = `Files (${msg.attachments.length})`;
    const body = document.getElementById('chatFilesModalBody');
    body.innerHTML = msg.attachments.map(a => {
        const url = fullUrl(a.path);
        const fname = a.filename || a.path.split('/').pop() || 'File';
        const isImage = isImageType(a);
        const isVideo = isVideoType(a);
        const isAudio = isAudioType(a);
        let preview = '';
        if (isImage) preview = `<img src="${escapeHtml(url)}" alt="" loading="lazy">`;
        else if (isVideo) preview = `<video src="${escapeHtml(url)}" controls preload="metadata"></video>`;
        else if (isAudio) preview = `<audio src="${escapeHtml(url)}" controls style="width:100%"></audio>`;
        else {
            const { icon, color } = attIcon(a);
            preview = `<div class="chat-file-icon"><i class="fas ${icon}" style="color:${color}"></i></div>`;
        }
        return `<div class="chat-file-item">
            <div class="chat-file-preview-box">${preview}</div>
            <div class="chat-file-info">
                <div class="chat-file-name" title="${escapeHtml(fname)}">${escapeHtml(fname)}</div>
                <div class="chat-file-meta">${escapeHtml(formatSize(a.size))}${a.mime_type ? ' • ' + escapeHtml(a.mime_type) : ''}</div>
            </div>
            <div class="chat-file-actions">
                <a href="${escapeHtml(url)}" target="_blank" download class="btn btn-sm btn-outline"><i class="fas fa-download"></i></a>
            </div>
        </div>`;
    }).join('');

    const footer = document.getElementById('chatFilesModalFooter');
    const isMe = currentUser && msg.sender_id === currentUser.id_user;
    const isAdmin = currentUser && currentUser.id_role === 1;
    footer.innerHTML = (isMe || isAdmin)
        ? `<button class="btn btn-danger" onclick="deleteChatMessageFromModal()"><i class="fas fa-trash"></i> Hapus Pesan &amp; Files</button>`
        : '';

    document.getElementById('chatFilesModal').style.display = 'flex';
}

function closeChatFilesModal() {
    document.getElementById('chatFilesModal').style.display = 'none';
    chatFilesMsgId = null;
}

function deleteChatMessageFromModal() {
    const msgId = chatFilesMsgId;
    closeChatFilesModal();
    if (msgId) deleteChatMessage(msgId);
}

async function deleteChatMessage(msgId) {
    if (!confirm('Hapus pesan ini? Semua file terlampir juga akan dihapus.')) return;
    if (!currentChatUser) return;
    const msg = document.querySelector(`#chatMessages [data-msg-id="${msgId}"]`);
    if (msg) msg.style.opacity = '0.5';
    try {
        const res = await api(`/api/messages/${msgId}`, { method: 'DELETE' });
        if (res.success) {
            showToast('Pesan dihapus', 'success');
            await loadMessages(currentChatUser.id);
        } else {
            showToast(res.error || 'Gagal menghapus', 'error');
            await loadMessages(currentChatUser.id);
        }
    } catch(e) {
        showToast('Gagal menghapus pesan', 'error');
        await loadMessages(currentChatUser.id);
    }
}

async function acceptNego(messageId) {
    if (!currentUser) return;
    const msgs = await api(`/api/messages/${currentUser.id_chatUser || currentChatUser.id}`);
    const msg = msgs.find(m => m.id_message === messageId);
    if (!msg) { showToast('Pesan tidak ditemukan', 'error'); return; }
    
    const res = await api('/api/messages/negotiation/accept', {
        method: 'POST',
        body: JSON.stringify({
            message_id: messageId,
            id_product: msg.id_product,
            seller_id: currentUser.id_user,
            buyer_id: msg.sender_id,
            harga: msg.harga,
            nama_produk: msg.nama_produk
        })
    });
    if (res.success) {
        showToast('Nego diterima! Order dibuat.', 'success');
        await loadMessages(currentChatUser.id);
    } else {
        showToast(res.error || 'Gagal terima nego', 'error');
    }
}

async function rejectNego(messageId) {
    if (!currentUser) return;
    const res = await api('/api/messages/negotiation/reject', {
        method: 'POST',
        body: JSON.stringify({ message_id: messageId })
    });
    if (res.success) {
        showToast('Nego ditolak.', 'success');
        await loadMessages(currentChatUser.id);
    } else {
        showToast(res.error || 'Gagal tolak nego', 'error');
    }
}

async function counterNego(messageId, productId, originalHarga) {
    if (!currentUser) return;
    showPrompt('Bandingkan Harga', 'Masukkan harga banding Anda:', (value) => {
        const newHarga = value.input;
        if (!newHarga) {
            showToast('Harga wajib diisi', 'error');
            return;
        }
        api('/api/messages/negotiation/counter', {
            method: 'POST',
            body: JSON.stringify({
                message_id: messageId,
                id_product: productId,
                seller_id: currentUser.id_user,
                buyer_id: currentChatUser.id,
                harga: parseInt(newHarga)||0,
                nama_produk: '',
                foto_produk: ''
            })
        }).then(() => {
            showToast('Counter-offer dikirim!', 'success');
            loadMessages(currentChatUser.id);
        });
    }, originalHarga, 'input');
}

function backToConvos() {
    document.getElementById('chatConvos').style.display = 'block';
    document.getElementById('chatThread').style.display = 'none';
    currentChatUser = null;
    loadConversations();
}

// ===== MODALS =====
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

// Custom confirm modal (replace native confirm())
function showConfirm(title, msg, onConfirm, confirmText = 'Hapus', cancelText = 'Batal') {
    const modal = document.getElementById('confirmModal');
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMsg').textContent = msg;
    const okBtn = document.getElementById('confirmOk');
    const cancelBtn = document.getElementById('confirmCancel');
    okBtn.textContent = confirmText;
    cancelBtn.textContent = cancelText;
    
    // Remove old listeners
    okBtn.onclick = null;
    cancelBtn.onclick = null;
    
    okBtn.onclick = () => {
        closeModal('confirmModal');
        if (onConfirm) onConfirm();
    };
    cancelBtn.onclick = () => closeModal('confirmModal');
    
    modal.style.display = 'flex';
}

// Custom prompt modal (replace native prompt())
// mode: 'input' (default), 'textarea', or 'both' (show both input + textarea)
function showPrompt(title, msg, onConfirm, defaultValue = '', mode = 'input') {
    const modal = document.getElementById('promptModal');
    document.getElementById('promptTitle').textContent = title;
    document.getElementById('promptMsg').textContent = msg;
    const input = document.getElementById('promptInput');
    const textarea = document.getElementById('promptTextarea');
    const okBtn = document.getElementById('promptOk');
    const cancelBtn = document.getElementById('promptCancel');
    
    input.value = defaultValue || '';
    input.style.display = (mode === 'textarea') ? 'none' : 'block';
    textarea.style.display = (mode === 'input') ? 'none' : 'block';
    textarea.value = '';
    
    okBtn.onclick = null;
    cancelBtn.onclick = null;
    
    okBtn.onclick = () => {
        const value = {
            input: input.value,
            textarea: textarea.value
        };
        closeModal('promptModal');
        if (onConfirm) onConfirm(value);
    };
    cancelBtn.onclick = () => closeModal('promptModal');
    
    modal.style.display = 'flex';
    setTimeout(() => {
        if (mode === 'textarea') textarea.focus();
        else input.focus();
    }, 100);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal').forEach(m => {
        m.addEventListener('click', e => {
            if (e.target === m) m.style.display = 'none';
        });
    });
});

// ===== TOAST =====
function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show' + (type ? ' ' + type : '');
    setTimeout(() => t.classList.remove('show'), 3000);
}

// ===== ANIMATIONS =====
function initRevealObserver() {
    const obs = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('visible');
                obs.unobserve(e.target);
            }
        });
    }, { threshold: 0.1 });
    window._revealObs = obs;
    observeReveal();
}

function observeReveal() {
    document.querySelectorAll('.reveal:not(.visible)').forEach(el => {
        window._revealObs?.observe(el);
    });
}

function animateCounters() {
    document.querySelectorAll('.stat-number').forEach(el => {
        const target = +el.dataset.count;
        if (!target) return;
        let current = 0;
        const step = target / 40;
        const timer = setInterval(() => {
            current += step;
            if (current >= target) { current = target; clearInterval(timer); }
            el.textContent = Math.floor(current).toLocaleString();
        }, 30);
    });
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
        closeNotifPanel();
        closeChatPanel();
        document.getElementById('mobileMenu').style.display = 'none';
    }
});

// ===== PROFILE PAGE =====
async function showProfilePage() {
    var el = document.getElementById('profilePageContent');
    if (!currentUser) {
        el.innerHTML = '<div class="empty-state"><i class="fas fa-user-lock"></i><p>Silakan login untuk melihat profil</p><small><button class="btn btn-primary" onclick="showAuthModal()">Masuk / Daftar</button></small></div>';
        return;
    }
    var res = await api('/api/auth/me');
    var user = res.user || currentUser;
    // Keep the client-side copy in sync with the full profile (alamat, pendapatan, …)
    // so this shows correctly right after login, without a page refresh.
    currentUser = user;
    var roleLabels = {1:'Admin',2:'Seller',3:'Pembeli'};
    var roleColors = {1:'var(--red-500)',2:'var(--orange-500)',3:'var(--blue-500)'};
    var hasPhoto = user.avatar_url;
    var avatarHtml = hasPhoto
        ? '<img src="' + user.avatar_url + '" class="profile-avatar-lg">'
        : '<div class="profile-avatar-lg no-photo">' + user.nama.charAt(0) + '</div>';
    var pendapatanStat = (user.id_role === 1 || user.id_role === 2)
        ? '<div class="profile-stat"><div class="num">' + fmtRp(user.pendapatan) + '</div><div class="label">Pendapatan</div></div>'
        : '';
    el.innerHTML = '<div class="profile-hero">' + avatarHtml +
        '<div class="profile-name-lg">' + user.nama + '</div>' +
        '<div class="profile-role-badge"><i class="fas fa-circle" style="width:6px;height:6px;background:' + roleColors[user.id_role] + '"></i> ' + roleLabels[user.id_role] + '</div>' +
        '</div>' +
        '<div class="profile-stats-row">' +
        '<div class="profile-stat"><div class="num">' + user.id_user + '</div><div class="label">ID User</div></div>' +
        '<div class="profile-stat"><div class="num">' + roleLabels[user.id_role] + '</div><div class="label">Peran</div></div>' +
        '<div class="profile-stat"><div class="num">' + (user.created_at ? user.created_at.slice(0,10) : '-') + '</div><div class="label">Terdaftar</div></div>' +
        pendapatanStat +
        '</div>' +
        '<div class="profile-info-grid">' +
        '<div class="profile-info-card"><h3><i class="fas fa-address-card"></i> Informasi Akun</h3>' +
        '<div class="profile-info-row"><span class="label">Nama</span><span class="value">' + user.nama + '</span></div>' +
        '<div class="profile-info-row"><span class="label">Email</span><span class="value">' + (user.email || '-') + '</span></div>' +
        '<div class="profile-info-row"><span class="label">ID</span><span class="value">#' + user.id_user + '</span></div>' +
        '<div class="profile-info-row"><span class="label">Status</span><span class="value">' + (user.status_verifikasi === 1 ? 'Terverifikasi' : 'Belum') + '</span></div>' +
        '</div>' +
        '<div class="profile-info-card"><h3><i class="fas fa-id-card"></i> Foto Profil</h3>' +
        '<div style="display:flex;align-items:center;gap:12px;padding:8px 0">' +
        '<div style="width:56px;height:56px;border-radius:50%;overflow:hidden;background:var(--slate-100);flex-shrink:0">' +
        (hasPhoto ? '<img src="' + user.avatar_url + '" style="width:100%;height:100%;object-fit:cover">' : '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--green-200);font-weight:700;color:var(--green-800)">' + user.nama.charAt(0) + '</div>') +
        '</div><button class="btn btn-sm btn-primary" onclick="showProfileModal()"><i class="fas fa-camera"></i> Ganti Foto</button></div></div>' +
        '</div>' +
        '<div class="profile-info-card"><h3><i class="fas fa-map-marker-alt"></i> Alamat Pengiriman</h3>' +
        '<div style="padding:8px 0">' +
        (user.alamat
            ? '<div style="font-size:14px;color:var(--slate-700);white-space:pre-wrap;margin-bottom:10px">' + user.alamat + '</div>' +
            '<button class="btn btn-sm btn-outline" onclick="showAddressEditModal()"><i class="fas fa-edit"></i> Ubah Alamat</button>'
            : '<p style="color:var(--slate-400);font-size:13px;margin-bottom:10px">Belum ada alamat tersimpan</p>' +
              '<button class="btn btn-sm btn-primary" onclick="showAddressEditModal()"><i class="fas fa-plus"></i> Tambah Alamat</button>') +
        '</div></div>' +
        '</div>';
}

// ===== ORDERS PAGE =====
async function showOrdersPage() {
    var el = document.getElementById('ordersPageContent');
    if (!currentUser) {
        el.innerHTML = '<div class="empty-state"><i class="fas fa-shopping-bag"></i><p>Silakan login untuk melihat pesanan</p><small><button class="btn btn-primary" onclick="showAuthModal()">Masuk / Daftar</button></small></div>';
        return;
    }
    var orders = await api('/api/orders');
    if (!orders || orders.length === 0) {
        el.innerHTML = '<div class="buyer-order-empty"><i class="fas fa-box"></i><h3>Belum ada order</h3><p>Belanja produk dari seller di Taniku</p></div>';
        return;
    }
    el.innerHTML = '<div class="buyer-order-grid">' + orders.map(function(o) {
        return '<div class="buyer-order-card" onclick="showOrderDetail(' + o.id_order + ')">' +
            '<div class="buyer-order-head">' +
                '<span class="order-id">Order #' + o.id_order + '</span>' +
                '<span class="order-date">' + (o.tanggal_order ? o.tanggal_order.slice(0,10) : '-') + '</span>' +
            '</div>' +
            '<div class="buyer-order-items">' +
                ((o.items||[]).map(function(i) {
                    return '<div class="buyer-order-item">' +
                        '<img src="' + (i.foto || 'https://via.placeholder.com/60') + '" alt="' + (i.nama_produk || '') + '" class="item-img" onerror="this.src=\'https://via.placeholder.com/60?text=No+Img\'">' +
                        '<div class="item-info">' +
                            '<div class="item-name">' + (i.nama_produk || '') + '</div>' +
                            '<div class="item-meta">' +
                                '<span class="item-qty">' + (i.quantity || 1) + ' ' + (i.satuan || 'pcs') + '</span>' +
                                '<span class="item-price">Rp' + ((i.subtotal || i.harga || 0)).toLocaleString() + '</span>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                }).join('') || '<div class="buyer-order-empty" style="padding:20px;text-align:center;color:var(--slate-400);font-size:13px">Tidak ada item</div>') +
            '</div>' +
            '<div class="buyer-order-total">' +
                '<span class="total-label">Total</span>' +
                '<span class="total-amount">Rp' + (o.total_amount || 0).toLocaleString() + '</span>' +
            '</div>' +
            '<div class="buyer-order-actions">' +
                (o.status_order === 'pending' ? '<button class="btn btn-action-primary" onclick="event.stopPropagation();payOrder(' + o.id_order + ')"><i class="fas fa-credit-card"></i> Bayar</button>' : '') +
                (o.status_order === 'paid' ? '<button class="btn btn-action-info" onclick="event.stopPropagation();showTracking(' + o.id_order + ')"><i class="fas fa-truck"></i> Tracking</button>' : '') +
                (o.status_order === 'shipped' ? '<button class="btn btn-action-info" onclick="event.stopPropagation();showTracking(' + o.id_order + ')"><i class="fas fa-truck"></i> Tracking</button>' : '') +
                (o.status_order === 'shipped' ? '<button class="btn btn-action-success" onclick="event.stopPropagation();updateOrderStatus(' + o.id_order + ',\'confirmed\')"><i class="fas fa-check"></i> Received</button>' : '') +
                (o.status_order === 'shipped' ? '<button class="btn btn-action-danger" onclick="event.stopPropagation();openDispute(' + o.id_order + ')"><i class="fas fa-flag"></i> Dispute</button>' : '') +
                (o.status_order === 'disputed' ? '<button class="btn btn-action-warning" onclick="event.stopPropagation();openDisputeChat(' + o.id_order + ')"><i class="fas fa-comments"></i> Lihat Dispute</button>' : '') +
                (o.status_order === 'disputed' ? '<button class="btn btn-action-info" onclick="event.stopPropagation();showTracking(' + o.id_order + ')"><i class="fas fa-truck"></i> Tracking</button>' : '') +
                (o.status_order === 'confirmed' ? '<button class="btn btn-action-info" onclick="event.stopPropagation();showTracking(' + o.id_order + ')"><i class="fas fa-truck"></i> Tracking</button>' : '') +
            '</div>' +
        '</div>';
    }).join('') + '</div>';
}

async function showTracking(orderId) {
    // Load the real order data so buyer and seller see the SAME resi/service
    const o = await api(`/api/orders/${orderId}`);
    if (!o || o.error) { showToast('Order tidak ditemukan', 'error'); return; }
    const trackingNo = (o.tracking_number || '-').toString();
    const courier = (o.shipping_service || '-').toString();
    // Derive a plausible status label from the order lifecycle (no fake history)
    const statusMap = {
        'paid': 'Menunggu Pengiriman',
        'approved': 'Menunggu Pengiriman',
        'shipped': 'Dalam Perjalanan',
        'confirmed': 'Tiba di Tujuan',
        'disputed': 'Tiba di Tujuan',
        'completed': 'Selesai'
    };
    const statusMapColor = {
        'Menunggu Pengiriman': 'var(--slate-400)',
        'Dalam Perjalanan': 'var(--yellow-500)',
        'Tiba di Tujuan': 'var(--green-600)',
        'Selesai': 'var(--green-700)'
    };
    const status = statusMap[o.status_order] || 'Dalam Perjalanan';
    const color = statusMapColor[status] || 'var(--slate-500)';
    const modal = document.getElementById('productModal');
    const content = document.getElementById('productDetailContent');
    content.innerHTML = `
        <h2 style="margin-bottom:4px">Tracking Pesanan</h2>
        <p style="color:var(--slate-500);margin-bottom:16px;font-size:13px">Order #${orderId}</p>
        <div style="background:var(--slate-50);border-radius:12px;padding:16px;margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <span style="font-weight:700;color:var(--slate-800)"><i class="fas fa-truck" style="color:${color};margin-right:8px"></i>${escapeHtml(courier)}</span>
                <span style="padding:4px 12px;border-radius:20px;background:${color};color:#fff;font-size:11px;font-weight:700">${escapeHtml(status)}</span>
            </div>
            <div style="font-size:13px;color:var(--slate-600)">No. Resi: <strong style="color:var(--slate-800)">${escapeHtml(trackingNo)}</strong></div>
        </div>
        <h4 style="margin-bottom:12px"><i class="fas fa-clock"></i> Riwayat Pengiriman</h4>
        <div style="max-height:300px;overflow-y:auto">
            <div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--slate-100)">
                <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0">
                    <div style="width:12px;height:12px;border-radius:50%;background:var(--slate-300);border:2px solid #fff;box-shadow:0 0 0 2px var(--slate-300)"></div>
                    <div style="width:2px;flex:1;min-height:30px;background:var(--slate-200)"></div>
                </div>
                <div style="flex:1">
                    <div style="font-size:13px;font-weight:600;color:var(--slate-800)">Pesanan Dibuat</div>
                    <div style="font-size:12px;color:var(--slate-500);margin-top:2px">${o.tanggal_order ? new Date(o.tanggal_order).toLocaleString('id-ID') : '-'}</div>
                </div>
            </div>
            ${trackingNo !== '-' ? `
            <div style="display:flex;gap:12px;padding:12px 0;${o.status_order !== 'confirmed' && o.status_order !== 'completed' && o.status_order !== 'disputed' ? 'border-bottom:1px solid var(--slate-100)' : ''}">
                <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0">
                    <div style="width:12px;height:12px;border-radius:50%;background:var(--green-600);border:2px solid #fff;box-shadow:0 0 0 2px var(--green-600)"></div>
                    <div style="width:2px;flex:1;min-height:30px;background:var(--slate-200)"></div>
                </div>
                <div style="flex:1">
                    <div style="font-size:13px;font-weight:600;color:var(--green-700)">Pesanan Dikirim oleh ${escapeHtml(o.id_seller || 'seller')}</div>
                    <div style="font-size:12px;color:var(--slate-500);margin-top:2px">Resi ${escapeHtml(trackingNo)} via ${escapeHtml(courier)}</div>
                </div>
            </div>` : ''}
            ${(o.status_order === 'confirmed' || o.status_order === 'completed' || o.status_order === 'disputed') ? `
            <div style="display:flex;gap:12px;padding:12px 0">
                <div style="display:flex;align-items:center;flex-shrink:0">
                    <div style="width:12px;height:12px;border-radius:50%;background:var(--green-700);border:2px solid #fff;box-shadow:0 0 0 2px var(--green-700)"></div>
                </div>
                <div style="flex:1">
                    <div style="font-size:13px;font-weight:600;color:var(--green-700)">Barang Diterima Buyer</div>
                    <div style="font-size:12px;color:var(--slate-500);margin-top:2px">Status: ${escapeHtml(status)}</div>
                </div>
            </div>` : ''}
        </div>
    `;
    modal.style.display = 'flex';
}

// ===== HISTORY PAGE =====
async function showHistoryPage() {
    var el = document.getElementById('historyPageContent');
    if (!currentUser) {
        el.innerHTML = '<div class="empty-state"><i class="fas fa-clock"></i><p>Silakan login untuk melihat riwayat</p><small><button class="btn btn-primary" onclick="showAuthModal()">Masuk / Daftar</button></small></div>';
        return;
    }
    var orders = await api('/api/orders');
    var myOrders = currentUser.id_role === 1 ? orders : orders.filter(function(o) { return o.id_buyer === currentUser.id_user || o.id_seller === currentUser.id_user; });
    var history = myOrders.map(function(o) {
        return {
            title: 'Order #' + o.id_order,
            desc: 'Rp' + (o.total_amount||0).toLocaleString() + ' \u2022 ' + o.status_order,
            date: o.tanggal_order || '',
            status: o.status_order
        };
    }).sort(function(a, b) { return (b.date || '').localeCompare(a.date || ''); });
    if (history.length === 0) {
        el.innerHTML = '<div class="empty-state"><i class="fas fa-clock"></i><p>Belum ada riwayat</p></div>';
        return;
    }
    el.innerHTML = '<div class="history-timeline">' + history.map(function(h) {
        return '<div class="history-item ' + (h.status === 'cancelled' || h.status === 'rejected' ? 'cancelled' : 'done') + '">' +
            '<div class="h-title">' + h.title + '</div>' +
            '<div class="h-desc">' + h.desc + '</div>' +
            '<div class="h-date">' + (h.date ? h.date.slice(0,16).replace('T',' ') : '-') + '</div></div>';
    }).join('') + '</div>';
}
