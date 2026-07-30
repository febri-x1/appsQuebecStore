<?php
require_once 'config/database.php';
require_once 'includes/auth_check.php';
require_once 'includes/functions.php';

$user = current_user();
if (!$user || $user['role'] !== 'kasir') {
    redirect('dashboard.php', 'Akses ditolak. Halaman ini khusus untuk Kasir.', 'error');
}

$pageTitle = 'Katalog Produk Tersedia';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 24;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$kategori_filter = $_GET['kategori'] ?? '';

// Hanya ambil produk yang statusnya 'di_rak' 
$where = ["b.status = 'di_rak'"];
$params = [];

if ($search !== '') {
    $where[] = "(b.merek LIKE ? OR b.kode_item LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($kategori_filter !== '') {
    $where[] = "b.kategori_id = ?";
    $params[] = $kategori_filter;
}

$whereClause = "WHERE " . implode(" AND ", $where);

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM produk b $whereClause");
$stmtCount->execute($params);
$totalData = $stmtCount->fetchColumn();
$totalPages = ceil($totalData / $limit);

$sql = "SELECT b.*, kb.nama_kategori AS kategori 
        FROM produk b 
        LEFT JOIN kategori_produk kb ON b.kategori_id = kb.id 
        $whereClause 
        ORDER BY b.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produkList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data kategori untuk filter
$kategoriList = $pdo->query("SELECT id, nama_kategori FROM kategori_produk ORDER BY nama_kategori ASC")->fetchAll();

include 'includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>
    body {
        font-family: Arial, Helvetica, sans-serif;
        background-color: #f8f9fa;
    }

    .product-card {
        transition: transform 0.2s, box-shadow 0.2s;
        border: none;
        border-radius: 10px;
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
    }

    .badge-kondisi-A {
        background-color: #198754;
    }

    .badge-kondisi-B {
        background-color: #ffc107;
        color: #000;
    }

    .badge-kondisi-C {
        background-color: #dc3545;
    }

    .price-tag {
        font-size: 1.25rem;
        font-weight: bold;
        color: #0d6efd;
    }
</style>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-tags"></i> Katalog Produk Tersedia</h2>
        <div>
            <a href="transaksi/jual.php" class="btn btn-success fw-bold"><i class="bi bi-cart-plus"></i> Buka Mesin Kasir</a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-3">
                    <select name="kategori" class="form-select border-2">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategoriList as $kat): ?>
                            <option value="<?= $kat['id'] ?>" <?= $kategori_filter == $kat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-7">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-2"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-2 border-start-0 ps-0" placeholder="Cari Merek atau Kode Item..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Cari</button>
                    <?php if ($search || $kategori_filter): ?>
                        <a href="katalog_kasir.php" class="btn btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Product Grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4 mb-4">
        <?php if (count($produkList) > 0): ?>
            <?php foreach ($produkList as $b):
                $fotoUrl = !empty($b['foto']) ? $b['foto'] : 'assets/img/no-photo.png';

                // Menentukan class kondisi
                $kondisiClass = 'badge-kondisi-C';
                if ($b['kondisi'] === 'A') $kondisiClass = 'badge-kondisi-A';
                elseif ($b['kondisi'] === 'B') $kondisiClass = 'badge-kondisi-B';
            ?>
                <div class="col">
                    <div class="card h-100 shadow-sm product-card">
                        <div class="position-relative">
                            <img src="<?= htmlspecialchars($fotoUrl) ?>" class="card-img-top p-2 rounded" alt="<?= htmlspecialchars($b['merek']) ?>" style="height: 200px; object-fit: cover;" onerror="this.src='https://via.placeholder.com/400x300?text=No+Photo'">
                            <span class="position-absolute top-0 end-0 m-3 badge <?= $kondisiClass ?> fs-6">Kondisi <?= $b['kondisi'] ?></span>
                        </div>
                        <div class="card-body border-top">
                            <div class="text-muted small mb-1 d-flex justify-content-between">
                                <span><?= htmlspecialchars($b['kode_item']) ?></span>
                                <span class="badge bg-light text-dark border"><?= ucfirst(str_replace('_', ' ', $b['kategori'])) ?></span>
                            </div>
                            <h5 class="card-title fw-bold text-truncate mb-2" title="<?= htmlspecialchars($b['merek']) ?>"><?= htmlspecialchars($b['merek']) ?></h5>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div>
                                    <div class="text-muted small">Ukuran</div>
                                    <strong class="fs-5"><?= htmlspecialchars($b['ukuran']) ?></strong>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small">Harga</div>
                                    <div class="price-tag"><?= formatRupiah($b['harga_jual']) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-0 pb-3">
                            <!-- Menambahkan kode ke link form jual agar auto search -->
                            <button class="btn btn-outline-success w-100" onclick="navigator.clipboard.writeText('<?= $b['kode_item'] ?>'); alert('Kode item <?= $b['kode_item'] ?> telah disalin!');">
                                <i class="bi bi-clipboard"></i> Salin Kode Item
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div class="display-1 text-muted mb-3"><i class="bi bi-inbox"></i></div>
                <h4 class="text-muted">Tidak ada produk yang tersedia</h4>
                <p>Coba sesuaikan filter pencarian Anda.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&kategori=<?= urlencode($kategori_filter) ?>&search=<?= urlencode($search) ?>">« Prev</a>
                </li>

                <?php for ($i = 1; $i <= $totalPages; $i++):
                    // Simple pagination display logic to limit number of visible pages
                    if ($totalPages > 7) {
                        if ($i != 1 && $i != $totalPages && abs($i - $page) > 2) {
                            if (abs($i - $page) == 3) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            continue;
                        }
                    }
                ?>
                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&kategori=<?= urlencode($kategori_filter) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&kategori=<?= urlencode($kategori_filter) ?>&search=<?= urlencode($search) ?>">Next »</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include 'includes/footer.php'; ?>