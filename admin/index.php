<?php
require_once __DIR__ . '/fungsi.php';

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

$admin = $_SESSION['pengguna'] ?? null;
if (!$admin || $admin['role'] !== 'admin') {
    header('Location: ../index.html#login');
    exit;
}
$_SESSION['csrf'] ??= bin2hex(random_bytes(16));

$pdo = db();
$halaman = $_GET['hal'] ?? 'dashboard';
if (!in_array($halaman, ['dashboard', 'buku', 'kategori', 'pesanan', 'pengguna', 'pesan'], true)) {
    $halaman = 'dashboard';
}
$notif = $galat = $tindakan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Token form tidak valid. Muat ulang halaman.');
    }
    $tindakan = $_POST['tindakan'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    try {
        switch ($tindakan) {
            case 'ubah_pesanan':
                $statusBaru = $_POST['status'] ?? '';
                $resi = trim($_POST['resi'] ?? '');
                if (!isset(STATUS_PESANAN[$statusBaru])) {
                    throw new RuntimeException('Status tidak valid.');
                }

                $pdo->beginTransaction();
                $statusLama = skalar('SELECT status FROM orders WHERE id = ? FOR UPDATE', [$id]);
                if ($statusLama === false) {
                    throw new RuntimeException('Pesanan tidak ditemukan.');
                }
                if ($statusLama === 'cancelled' && $statusBaru !== 'cancelled') {
                    throw new RuntimeException('Pesanan yang sudah dibatalkan tidak bisa dibuka lagi.');
                }

                // pesanan dibatalkan: stok dikembalikan, jumlah terjual dikurangi
                if ($statusBaru === 'cancelled' && $statusLama !== 'cancelled') {
                    $pdo->prepare(
                        "UPDATE products p
                         JOIN order_items oi ON oi.product_id = p.id
                         SET p.stock = IF(p.type = 'ebook', p.stock, p.stock + oi.qty),
                             p.sold  = IF(p.sold > oi.qty, p.sold - oi.qty, 0)
                         WHERE oi.order_id = ?"
                    )->execute([$id]);
                }

                $pdo->prepare('UPDATE orders SET status = ?, tracking_number = ? WHERE id = ?')
                    ->execute([$statusBaru, $resi !== '' ? $resi : null, $id]);
                $pdo->commit();
                $notif = 'Pesanan diperbarui.';
                break;

            case 'ubah_produk':
                $judul   = teksForm('judul', 200);
                $penulis = teksForm('penulis', 120);
                if ($judul === '' || $penulis === '') {
                    throw new RuntimeException('Judul dan penulis wajib diisi.');
                }
                $kategori = (int) ($_POST['kategori'] ?? 0);
                if (!skalar('SELECT COUNT(*) FROM categories WHERE id = ?', [$kategori])) {
                    throw new RuntimeException('Kategori tidak ditemukan.');
                }
                $data = bacaFormProduk($_POST);

                $coverLama = skalar('SELECT cover_image FROM products WHERE id = ?', [$id]);
                if ($coverLama === false) {
                    throw new RuntimeException('Buku tidak ditemukan.');
                }

                $cover = $coverLama;
                $coverBaru = simpanCover($_FILES['cover'] ?? []);
                if ($coverBaru !== null) {
                    $cover = $coverBaru;
                } elseif (isset($_POST['hapus_cover'])) {
                    $cover = null;
                }

                $pdo->prepare(
                    'UPDATE products
                     SET category_id = ?, title = ?, author = ?, price = ?, stock = ?, sold = ?,
                         cover_image = ?, description = ?, is_active = ?
                     WHERE id = ?'
                )->execute([
                    $kategori, $judul, $penulis, $data['price'], $data['stock'], $data['sold'],
                    $cover, $data['description'], isset($_POST['aktif']) ? 1 : 0, $id,
                ]);

                if ($cover !== $coverLama) {
                    hapusFileCover($coverLama);
                }
                $notif = "Buku \"$judul\" diperbarui.";
                break;

            case 'tambah_produk':
                $judul    = teksForm('judul', 200);
                $penulis  = teksForm('penulis', 120);
                $tipe     = $_POST['tipe'] ?? '';
                $kategori = (int) ($_POST['kategori'] ?? 0);

                if ($judul === '' || $penulis === '') {
                    throw new RuntimeException('Judul dan penulis wajib diisi.');
                }
                if (!in_array($tipe, ['fisik', 'ebook', 'bundle'], true)) {
                    throw new RuntimeException('Jenis produk tidak valid.');
                }
                if (!skalar('SELECT COUNT(*) FROM categories WHERE id = ?', [$kategori])) {
                    throw new RuntimeException('Kategori tidak ditemukan.');
                }
                $data  = bacaFormProduk($_POST);
                $cover = simpanCover($_FILES['cover'] ?? []);

                $pdo->prepare(
                    'INSERT INTO products (category_id, title, author, type, price, stock, sold, cover_image, description)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $kategori, $judul, $penulis, $tipe, $data['price'], $data['stock'],
                    $data['sold'], $cover, $data['description'],
                ]);
                $notif = "Buku \"$judul\" berhasil ditambahkan.";
                break;

            case 'hapus_produk':
                if (skalar('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [$id]) > 0) {
                    throw new RuntimeException('Buku ini sudah pernah dipesan sehingga tidak bisa dihapus. Hilangkan centang "Aktif" lalu simpan supaya tidak tampil di toko.');
                }
                $cover = skalar('SELECT cover_image FROM products WHERE id = ?', [$id]);
                $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
                hapusFileCover($cover ?: null);
                $notif = 'Buku dihapus.';
                break;

            case 'tambah_kategori':
                $nama = teksForm('nama', 60);
                $slug = substr(trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($nama)), '-'), 0, 80);
                if ($nama === '' || $slug === '') {
                    throw new RuntimeException('Nama kategori wajib diisi dan memuat huruf atau angka.');
                }
                if (skalar('SELECT COUNT(*) FROM categories WHERE name = ? OR slug = ?', [$nama, $slug]) > 0) {
                    throw new RuntimeException("Kategori \"$nama\" sudah ada.");
                }
                $pdo->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)')->execute([$nama, $slug]);
                $notif = "Kategori \"$nama\" berhasil ditambahkan.";
                break;

            case 'hapus_kategori':
                $pindah = (int) ($_POST['pindah_ke'] ?? 0);
                if (!skalar('SELECT COUNT(*) FROM categories WHERE id = ?', [$id])) {
                    throw new RuntimeException('Kategori tidak ditemukan.');
                }
                $dipakai = (int) skalar('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id]);

                $pdo->beginTransaction();
                if ($dipakai > 0) {
                    if ($pindah === 0 || $pindah === $id) {
                        throw new RuntimeException("Pilih kategori tujuan untuk memindahkan $dipakai buku sebelum menghapus kategori ini.");
                    }
                    if (!skalar('SELECT COUNT(*) FROM categories WHERE id = ?', [$pindah])) {
                        throw new RuntimeException('Kategori tujuan tidak ditemukan.');
                    }
                    $pdo->prepare('UPDATE products SET category_id = ? WHERE category_id = ?')->execute([$pindah, $id]);
                }
                $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
                $pdo->commit();
                $notif = $dipakai > 0
                    ? "Kategori dihapus. $dipakai buku dipindahkan ke kategori tujuan."
                    : 'Kategori dihapus.';
                break;

            case 'tandai_dibaca':
                $pdo->prepare('UPDATE messages SET is_read = 1 WHERE id = ?')->execute([$id]);
                break;
        }
    } catch (Throwable $err) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $galat = $err instanceof RuntimeException
            ? $err->getMessage()
            : 'Gagal menyimpan: ' . (MODE_DEBUG ? $err->getMessage() : 'kesalahan server.');
    }
}

$belumDibaca = (int) skalar('SELECT COUNT(*) FROM messages WHERE is_read = 0');
$menunggu    = (int) skalar("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'paid')");

switch ($halaman) {
    case 'dashboard':
        $stat = [
            'buku'       => skalar('SELECT COUNT(*) FROM products'),
            'aktif'      => skalar('SELECT COUNT(*) FROM products WHERE is_active = 1'),
            'kategori'   => skalar('SELECT COUNT(*) FROM categories'),
            'pesanan'    => skalar('SELECT COUNT(*) FROM orders'),
            'pendapatan' => skalar("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status IN ('paid', 'processing', 'shipped', 'completed')"),
        ];
        $terbaru = $pdo->query('SELECT order_code, customer_name, total, status FROM orders ORDER BY id DESC LIMIT 5')->fetchAll();
        $menipis = $pdo->query("SELECT title, stock FROM products WHERE is_active = 1 AND type <> 'ebook' AND stock <= 5 ORDER BY stock, id LIMIT 5")->fetchAll();
        break;

    case 'buku':
        $kategoriList = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
        $produk = $pdo->query(
            'SELECT p.*, c.name AS kategori FROM products p JOIN categories c ON c.id = p.category_id ORDER BY p.id'
        )->fetchAll();
        break;

    case 'kategori':
        $kategoriList = $pdo->query(
            'SELECT c.id, c.name, c.slug, COUNT(p.id) AS jumlah
             FROM categories c LEFT JOIN products p ON p.category_id = c.id
             GROUP BY c.id, c.name, c.slug ORDER BY c.name'
        )->fetchAll();
        break;

    case 'pengguna':
        $cari    = trim($_GET['q'] ?? '');
        $tanggal = (bool) $pdo->query("SHOW COLUMNS FROM users LIKE 'created_at'")->fetch();
        $kolom   = 'u.id, u.name, u.email, u.role' . ($tanggal ? ', u.created_at' : '');
        $total   = (int) skalar('SELECT COUNT(*) FROM users');
        $stmt = $pdo->prepare(
            "SELECT $kolom, COUNT(o.id) AS jumlah_pesanan
             FROM users u LEFT JOIN orders o ON o.user_id = u.id
             WHERE u.name LIKE ? OR u.email LIKE ?
             GROUP BY $kolom ORDER BY u.id DESC LIMIT 200"
        );
        $stmt->execute(["%$cari%", "%$cari%"]);
        $daftarUser = $stmt->fetchAll();
        break;

    case 'pesan':
        $pesan = $pdo->query('SELECT * FROM messages ORDER BY is_read, id DESC LIMIT 100')->fetchAll();
        break;

    case 'pesanan':
        $filter = $_GET['status'] ?? '';
        if (!isset(STATUS_PESANAN[$filter])) {
            $filter = '';
        }
        $jumlahStatus = $pdo->query('SELECT status, COUNT(*) FROM orders GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
        $stmt = $pdo->prepare(
            "SELECT o.*,
                    (SELECT GROUP_CONCAT(CONCAT(oi.product_title, ' x', oi.qty) SEPARATOR ', ')
                     FROM order_items oi WHERE oi.order_id = o.id) AS ringkasan
             FROM orders o " . ($filter !== '' ? 'WHERE o.status = ? ' : '') . 'ORDER BY o.id DESC LIMIT 100'
        );
        $stmt->execute($filter !== '' ? [$filter] : []);
        $pesanan = $stmt->fetchAll();
        break;
}

$menu = [
    'dashboard' => ['Dashboard', 0],
    'buku'      => ['Kelola Buku', 0],
    'kategori'  => ['Kategori', 0],
    'pesanan'   => ['Status Pesanan', $menunggu],
    'pengguna'  => ['Pengguna', 0],
    'pesan'     => ['Pesan', $belumDibaca],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — Rak Rebahan</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-body-tertiary">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="?hal=dashboard">Rak Rebahan · Admin</a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#menu" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <div class="navbar-nav ms-auto">
                <?php foreach ($menu as $kode => [$label, $angka]): ?>
                    <a class="nav-link <?= $halaman === $kode ? 'active' : '' ?>" href="?hal=<?= $kode ?>">
                        <?= e($label) ?>
                        <?php if ($angka): ?><span class="badge text-bg-danger"><?= (int) $angka ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <a class="nav-link" href="../index.html">Lihat Toko ↗</a>
            </div>
        </div>
    </div>
</nav>

<main class="container py-4">
    <?php if ($notif): ?><div class="alert alert-success"><?= e($notif) ?></div><?php endif; ?>
    <?php if ($galat): ?><div class="alert alert-danger"><?= e($galat) ?></div><?php endif; ?>

    <?php if ($halaman === 'dashboard'): ?>
        <h1 class="h3">Dashboard</h1>
        <p class="text-secondary">Halo, <?= e($admin['nama']) ?>. Ini ringkasan toko kamu.</p>

        <div class="row row-cols-2 row-cols-lg-5 g-3 mb-4">
            <?php foreach ([
                ['Total buku', $stat['buku'], $stat['aktif'] . ' aktif di toko', 'buku'],
                ['Kategori', $stat['kategori'], 'Kelola kategori', 'kategori'],
                ['Total pesanan', $stat['pesanan'], $menunggu . ' perlu diproses', 'pesanan'],
                ['Pendapatan', rp($stat['pendapatan']), 'Pesanan yang sudah dibayar', 'pesanan'],
                ['Pesan baru', $belumDibaca, 'Belum dibaca', 'pesan'],
            ] as [$judul, $nilai, $ket, $tujuan]): ?>
                <div class="col">
                    <a class="card card-body h-100 text-decoration-none text-reset" href="?hal=<?= $tujuan ?>">
                        <small class="text-secondary"><?= $judul ?></small>
                        <b class="fs-4"><?= e($nilai) ?></b>
                        <small class="text-secondary"><?= e($ket) ?></small>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card card-body">
                    <h5>Pesanan terbaru</h5>
                    <?php if (!$terbaru): ?><p class="text-secondary mb-0">Belum ada pesanan.</p><?php else: ?>
                        <table class="table table-sm align-middle">
                            <tr><th>Kode</th><th>Pembeli</th><th>Total</th><th>Status</th></tr>
                            <?php foreach ($terbaru as $o): ?>
                                <tr>
                                    <td><?= e($o['order_code']) ?></td>
                                    <td><?= e($o['customer_name']) ?></td>
                                    <td><?= rp($o['total']) ?></td>
                                    <td><?= badgeStatus($o['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        <a href="?hal=pesanan">Lihat semua pesanan →</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card card-body">
                    <h5>Stok menipis</h5>
                    <?php if (!$menipis): ?><p class="text-secondary mb-0">Semua stok masih aman.</p><?php else: ?>
                        <table class="table table-sm">
                            <tr><th>Buku</th><th>Stok</th></tr>
                            <?php foreach ($menipis as $b): ?>
                                <tr><td><?= e($b['title']) ?></td><td><b><?= (int) $b['stock'] ?></b></td></tr>
                            <?php endforeach; ?>
                        </table>
                        <a href="?hal=buku">Atur stok →</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($halaman === 'pesanan'): ?>
        <h1 class="h3">Status Pesanan</h1>
        <p class="text-secondary">Perbarui status pesanan pembeli dan isi nomor resi saat pesanan dikirim.</p>

        <div class="nav nav-pills gap-2 mb-3">
            <a class="nav-link py-1 border <?= $filter === '' ? 'active' : 'bg-white text-dark' ?>" href="?hal=pesanan">Semua (<?= array_sum($jumlahStatus) ?>)</a>
            <?php foreach (STATUS_PESANAN as $kode => [$label]): ?>
                <a class="nav-link py-1 border <?= $filter === $kode ? 'active' : 'bg-white text-dark' ?>" href="?hal=pesanan&amp;status=<?= $kode ?>"><?= e($label) ?> (<?= (int) ($jumlahStatus[$kode] ?? 0) ?>)</a>
            <?php endforeach; ?>
        </div>

        <?php if (!$pesanan): ?><p>Tidak ada pesanan<?= $filter !== '' ? ' dengan status ini' : '' ?>.</p><?php endif; ?>
        <?php foreach ($pesanan as $o): ?>
            <form method="post" class="card card-body mb-3">
                <?= csrfField() ?>
                <input type="hidden" name="tindakan" value="ubah_pesanan">
                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                    <b><?= e($o['order_code']) ?></b>
                    <?= badgeStatus($o['status']) ?>
                    <span class="text-secondary"><?= e($o['created_at']) ?></span>
                    <b><?= rp($o['total']) ?></b>
                    <span><?= e($o['payment_method']) ?></span>
                </div>
                <p class="mb-1"><?= e($o['ringkasan']) ?></p>
                <small class="text-secondary d-block mb-3">
                    <?= e($o['customer_name']) ?> · <?= e($o['phone']) ?>
                    <?php if ($o['address']): ?><br><?= e($o['address']) ?>, <?= e($o['city']) ?> <?= e($o['postal_code']) ?> (<?= e($o['shipping_method']) ?>)<?php endif; ?>
                </small>
                <div class="row g-2">
                    <div class="col-md-4">
                        <select class="form-select" name="status">
                            <?php foreach (STATUS_PESANAN as $kode => [$label]): ?>
                                <option value="<?= $kode ?>" <?= $o['status'] === $kode ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5"><input class="form-control" name="resi" placeholder="Nomor resi" value="<?= e($o['tracking_number']) ?>"></div>
                    <div class="col-md-3"><button class="btn btn-dark w-100">Perbarui status</button></div>
                </div>
            </form>
        <?php endforeach; ?>

    <?php elseif ($halaman === 'buku'): ?>
        <h1 class="h3">Kelola Buku</h1>

        <details class="card card-body mb-3" <?= $galat && $tindakan === 'tambah_produk' ? 'open' : '' ?>>
            <summary class="fw-bold">+ Tambah buku baru</summary>
            <?php if (!$kategoriList): ?>
                <p class="text-secondary mt-3 mb-0">Belum ada kategori. Tambahkan dulu lewat menu <a href="?hal=kategori">Kategori</a>.</p>
            <?php else: ?>
                <form method="post" enctype="multipart/form-data" class="row g-3 mt-1">
                    <?= csrfField() ?>
                    <input type="hidden" name="tindakan" value="tambah_produk">
                    <?php fieldProduk([
                        'title' => '', 'author' => '', 'price' => 0, 'stock' => 10,
                        'sold' => 0, 'cover_image' => null, 'description' => '',
                    ], $kategoriList, true); ?>
                    <div class="col-12"><button class="btn btn-dark">Simpan buku</button></div>
                </form>
            <?php endif; ?>
        </details>

        <?php foreach ($produk as $p): ?>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <b>#<?= (int) $p['id'] ?> · <?= e($p['kategori']) ?> · <?= e($p['type']) ?></b>
                    <?php if (!$p['is_active']): ?><span class="badge text-bg-secondary">Tidak aktif</span><?php endif; ?>
                </div>
                <div class="card-body d-flex flex-column flex-md-row gap-3">
                    <div style="width: 110px; flex: none">
                        <div class="ratio" style="--bs-aspect-ratio: 133%">
                            <?php if ($p['cover_image']): ?>
                                <img class="rounded object-fit-cover" src="../<?= e($p['cover_image']) ?>" alt="Cover <?= e($p['title']) ?>">
                            <?php else: ?>
                                <div class="bg-secondary-subtle text-secondary small text-center rounded d-flex align-items-center justify-content-center">Belum ada cover</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form method="post" enctype="multipart/form-data" class="row g-3 flex-grow-1">
                        <?= csrfField() ?>
                        <input type="hidden" name="tindakan" value="ubah_produk">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <?php fieldProduk($p, $kategoriList); ?>
                        <div class="col-12">
                            <label class="d-flex align-items-center gap-2"><input class="form-check-input mt-0" type="checkbox" name="aktif" <?= $p['is_active'] ? 'checked' : '' ?>> Aktif (tampil di toko)</label>
                        </div>
                        <div class="col-12"><button class="btn btn-dark">Simpan perubahan</button></div>
                    </form>
                </div>
                <form method="post" class="card-footer text-end">
                    <?= csrfField() ?>
                    <input type="hidden" name="tindakan" value="hapus_produk">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button class="btn btn-danger btn-sm" onclick="return confirm('Hapus buku ini? Tindakan ini tidak bisa dibatalkan.')">Hapus buku</button>
                </form>
            </div>
        <?php endforeach; ?>

    <?php elseif ($halaman === 'kategori'): ?>
        <h1 class="h3">Kategori</h1>
        <p class="text-secondary">Tambah kategori baru, lalu pilih kategori itu saat menambah atau mengubah buku di menu Kelola Buku. Kategori lama yang masih dipakai buku juga bisa dihapus: buku di dalamnya dipindahkan ke kategori lain.</p>

        <form method="post" class="card card-body mb-3">
            <?= csrfField() ?>
            <input type="hidden" name="tindakan" value="tambah_kategori">
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Nama kategori baru</label>
                    <input class="form-control" name="nama" required maxlength="60" placeholder="Contoh: Sejarah"
                           value="<?= e($tindakan === 'tambah_kategori' && $galat ? ($_POST['nama'] ?? '') : '') ?>">
                </div>
                <div class="col-md-4"><button class="btn btn-dark w-100">+ Tambah kategori</button></div>
            </div>
        </form>

        <table class="table table-bordered bg-white align-middle">
            <tr><th>Nama</th><th>Slug</th><th>Jumlah buku</th><th></th></tr>
            <?php foreach ($kategoriList as $k): ?>
                <tr>
                    <td><b><?= e($k['name']) ?></b></td>
                    <td class="text-secondary"><?= e($k['slug']) ?></td>
                    <td><?= (int) $k['jumlah'] ?></td>
                    <td>
                        <?php $lain = array_filter($kategoriList, fn($x) => (int) $x['id'] !== (int) $k['id']); ?>
                        <?php if ((int) $k['jumlah'] > 0 && !$lain): ?>
                            <span class="text-secondary small">Tambahkan kategori lain dulu agar buku bisa dipindahkan</span>
                        <?php else: ?>
                            <form method="post" class="d-flex flex-wrap gap-2">
                                <?= csrfField() ?>
                                <input type="hidden" name="tindakan" value="hapus_kategori">
                                <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                                <?php if ((int) $k['jumlah'] > 0): ?>
                                    <select class="form-select form-select-sm w-auto" name="pindah_ke" required>
                                        <option value="">Pindahkan <?= (int) $k['jumlah'] ?> buku ke…</option>
                                        <?php foreach ($lain as $x): ?>
                                            <option value="<?= (int) $x['id'] ?>"><?= e($x['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm" onclick="return confirm('Hapus kategori ini?<?= (int) $k['jumlah'] > 0 ? ' Buku di dalamnya akan dipindahkan ke kategori tujuan.' : '' ?>')">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

    <?php elseif ($halaman === 'pengguna'): ?>
        <h1 class="h3">Pengguna Terdaftar</h1>
        <p class="text-secondary"><?= $total ?> akun terdaftar di toko.</p>

        <form class="row g-2 mb-3">
            <input type="hidden" name="hal" value="pengguna">
            <div class="col-md-9"><input class="form-control" name="q" placeholder="Cari nama atau email" value="<?= e($cari) ?>"></div>
            <div class="col-md-3"><button class="btn btn-dark w-100">Cari</button></div>
        </form>

        <?php if (!$daftarUser): ?>
            <p>Tidak ada pengguna<?= $cari !== '' ? ' yang cocok dengan pencarian' : '' ?>.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered bg-white align-middle">
                    <tr>
                        <th>#</th><th>Nama</th><th>Email</th><th>Peran</th><th>Pesanan</th>
                        <?php if ($tanggal): ?><th>Terdaftar</th><?php endif; ?>
                    </tr>
                    <?php foreach ($daftarUser as $u): ?>
                        <tr>
                            <td><?= (int) $u['id'] ?></td>
                            <td><b><?= e($u['name']) ?></b></td>
                            <td><?= e($u['email']) ?></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?><span class="badge text-bg-dark">Admin</span>
                                <?php else: ?><span class="badge text-bg-secondary">Pelanggan</span><?php endif; ?>
                            </td>
                            <td><?= (int) $u['jumlah_pesanan'] ?></td>
                            <?php if ($tanggal): ?><td class="text-secondary"><?= e($u['created_at']) ?></td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <h1 class="h3">Pesan masuk</h1>
        <?php if (!$pesan): ?><p>Belum ada pesan.</p><?php endif; ?>
        <?php foreach ($pesan as $m): ?>
            <form method="post" class="card card-body mb-3 <?= $m['is_read'] ? 'opacity-50' : '' ?>">
                <?= csrfField() ?>
                <input type="hidden" name="tindakan" value="tandai_dibaca">
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <div class="d-flex gap-3"><b><?= e($m['name']) ?></b><span class="text-secondary"><?= e($m['created_at']) ?></span></div>
                <p class="my-2"><?= nl2br(e($m['message'])) ?></p>
                <?php if (!$m['is_read']): ?><div><button class="btn btn-dark btn-sm">Tandai sudah dibaca</button></div><?php endif; ?>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
