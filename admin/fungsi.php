<?php
require_once __DIR__ . '/../config/koneksi.php';

const STATUS_PESANAN = [
    'pending'    => ['Menunggu pembayaran', 'warning'],
    'paid'       => ['Sudah dibayar', 'info'],
    'processing' => ['Diproses', 'primary'],
    'shipped'    => ['Dikirim', 'secondary'],
    'completed'  => ['Selesai', 'success'],
    'cancelled'  => ['Dibatalkan', 'danger'],
];

function e($teks): string
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

function rp($angka): string
{
    return 'Rp' . number_format((int) $angka, 0, ',', '.');
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function badgeStatus(string $status): string
{
    [$label, $warna] = STATUS_PESANAN[$status] ?? [$status, 'secondary'];
    return '<span class="badge text-bg-' . $warna . '">' . e($label) . '</span>';
}

// ambil satu nilai dari query (false kalau barisnya tidak ada)
function skalar(string $sql, array $param = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($param);
    return $stmt->fetchColumn();
}

function teksForm(string $kunci, int $maks): string
{
    return mb_substr(trim((string) ($_POST[$kunci] ?? '')), 0, $maks);
}

function bacaFormProduk(array $in): array
{
    $harga     = (int) ($in['harga'] ?? 0);
    $stok      = (int) ($in['stok'] ?? 0);
    $terjual   = (int) ($in['terjual'] ?? 0);
    $deskripsi = trim((string) ($in['deskripsi'] ?? ''));

    if ($harga <= 0) {
        throw new RuntimeException('Harga harus lebih dari 0.');
    }
    if ($stok < 0 || $terjual < 0) {
        throw new RuntimeException('Stok dan jumlah terjual tidak boleh negatif.');
    }

    return [
        'price'       => $harga,
        'stock'       => $stok,
        'sold'        => $terjual,
        'description' => $deskripsi !== '' ? $deskripsi : null,
    ];
}

function simpanCover(array $berkas): ?string
{
    if (!isset($berkas['error']) || $berkas['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($berkas['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gambar gagal (kode ' . $berkas['error'] . '). Pastikan ukurannya maksimal 2 MB.');
    }
    if ($berkas['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Ukuran gambar maksimal 2 MB.');
    }

    $info  = @getimagesize($berkas['tmp_name']);
    $jenis = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($jenis[$info[2]])) {
        throw new RuntimeException('Cover harus berupa gambar JPG, PNG, atau WEBP.');
    }

    $folder = __DIR__ . '/../uploads/covers';
    if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
        throw new RuntimeException('Folder uploads/covers tidak bisa dibuat.');
    }

    $nama = bin2hex(random_bytes(8)) . '.' . $jenis[$info[2]];
    if (!move_uploaded_file($berkas['tmp_name'], "$folder/$nama")) {
        throw new RuntimeException('Gambar gagal disimpan. Cek izin tulis folder uploads/covers.');
    }
    return 'uploads/covers/' . $nama;
}

function hapusFileCover(?string $path): void
{
    if ($path && preg_match('#^uploads/covers/[a-f0-9]{16}\.(jpg|png|webp)$#', $path)) {
        @unlink(__DIR__ . '/../' . $path);
    }
}

function kolomInput(string $label, string $nama, $nilai, string $atribut = ''): string
{
    return '<div class="col-md-6"><label class="form-label">' . $label . '</label>'
        . '<input class="form-control" name="' . $nama . '" value="' . e($nilai) . '" ' . $atribut . '></div>';
}

// kolom-kolom form buku, dipakai untuk tambah dan ubah
function fieldProduk(array $p, array $kategoriList = [], bool $baru = false): void
{
    echo kolomInput('Judul', 'judul', $p['title'], 'required maxlength="200"');
    echo kolomInput('Penulis', 'penulis', $p['author'], 'required maxlength="120"');

    echo '<div class="col-md-6"><label class="form-label">Kategori</label><select class="form-select" name="kategori" required>';
    foreach ($kategoriList as $k) {
        $dipilih = (int) ($p['category_id'] ?? 0) === (int) $k['id'] ? ' selected' : '';
        echo '<option value="' . (int) $k['id'] . '"' . $dipilih . '>' . e($k['name']) . '</option>';
    }
    echo '</select></div>';

    if ($baru) {
        echo '<div class="col-md-6"><label class="form-label">Jenis</label><select class="form-select" name="tipe">'
            . '<option value="fisik">Buku fisik</option><option value="ebook">E-Book</option><option value="bundle">Paket hemat</option>'
            . '</select></div>';
    }

    echo kolomInput('Harga (Rp)', 'harga', $p['price'] ?: '', 'type="number" min="1" required');
    echo kolomInput('Stok', 'stok', $p['stock'], 'type="number" min="0" required');
    echo kolomInput('Terjual', 'terjual', $p['sold'], 'type="number" min="0" required');
    ?>
    <div class="col-md-6">
        <label class="form-label">Gambar cover (JPG/PNG/WEBP, maks. 2 MB)</label>
        <input class="form-control" type="file" name="cover" accept="image/jpeg,image/png,image/webp">
    </div>
    <?php if (!$baru && $p['cover_image']): ?>
        <div class="col-12">
            <label class="d-flex align-items-center gap-2"><input class="form-check-input mt-0" type="checkbox" name="hapus_cover"> Hapus gambar cover</label>
        </div>
    <?php endif; ?>
    <div class="col-12">
        <label class="form-label">Deskripsi</label>
        <textarea class="form-control" name="deskripsi" rows="3"><?= e($p['description']) ?></textarea>
    </div>
    <?php
}
