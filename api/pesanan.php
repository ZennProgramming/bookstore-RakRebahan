<?php
require_once __DIR__ . '/_umum.php';

const GRATIS_ONGKIR_MIN = 150000;
const TARIF_ONGKIR      = ['reguler' => 15000, 'express' => 30000];
const METODE_BAYAR      = ['Transfer Bank BCA', 'QRIS', 'COD', 'GoPay / OVO'];

class PesananException extends Exception
{
}

$pengguna = wajibLogin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    tampilkanRiwayat($pengguna);
}
wajibMetode('POST');
buatPesanan($pengguna);

function tampilkanRiwayat(array $pengguna): void
{
    $stmt = db()->prepare(
        'SELECT id, order_code AS kode, total, payment_method AS pembayaran,
                status, tracking_number AS resi, created_at AS tanggal
         FROM orders
         WHERE user_id = ?
         ORDER BY id DESC'
    );
    $stmt->execute([$pengguna['id']]);
    $daftar = $stmt->fetchAll();

    $stmtItem = db()->prepare(
        'SELECT product_title AS judul, price AS harga, qty FROM order_items WHERE order_id = ?'
    );
    foreach ($daftar as &$pesanan) {
        $stmtItem->execute([$pesanan['id']]);
        $pesanan['item']  = $stmtItem->fetchAll();
        $pesanan['total'] = (int) $pesanan['total'];
        unset($pesanan['id']);
    }
    unset($pesanan);

    kirimJson($daftar);
}

function buatPesanan(array $pengguna): void
{
    $input      = ambilInput();
    $nama       = teks($input['nama'] ?? '', 100);
    $telepon    = preg_replace('/[^0-9+]/', '', teks($input['telepon'] ?? '', 20));
    $pembayaran = $input['pembayaran'] ?? '';
    $metodeKirim = $input['pengiriman'] ?? 'reguler';

    if ($nama === '') {
        gagal('Nama wajib diisi.');
    }
    if (strlen($telepon) < 9 || strlen($telepon) > 16) {
        gagal('Nomor WhatsApp tidak valid.');
    }
    if (!in_array($pembayaran, METODE_BAYAR, true)) {
        gagal('Metode pembayaran tidak valid.');
    }

    $jumlah = [];
    foreach (($input['item'] ?? []) as $item) {
        $id  = (int) ($item['id'] ?? 0);
        $qty = (int) ($item['qty'] ?? 0);
        if ($id > 0 && $qty > 0) {
            $jumlah[$id] = min(99, ($jumlah[$id] ?? 0) + $qty);
        }
    }
    if (!$jumlah) {
        gagal('Keranjang masih kosong.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $ids        = array_keys($jumlah);
        $tandaTanya = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, title, price, stock, type
             FROM products
             WHERE is_active = 1 AND id IN ($tandaTanya)
             FOR UPDATE"
        );
        $stmt->execute($ids);

        $produk = [];
        foreach ($stmt->fetchAll() as $baris) {
            $produk[(int) $baris['id']] = $baris;
        }

        $subtotal = 0;
        $adaFisik = false;
        foreach ($jumlah as $id => $qty) {
            if (!isset($produk[$id])) {
                throw new PesananException('Ada produk di keranjang yang sudah tidak tersedia.');
            }
            $p = $produk[$id];
            if ($p['type'] !== 'ebook') {
                $adaFisik = true;
                if ((int) $p['stock'] < $qty) {
                    throw new PesananException(
                        'Stok "' . $p['title'] . '" tidak cukup (sisa ' . (int) $p['stock'] . ').'
                    );
                }
            }
            $subtotal += (int) $p['price'] * $qty;
        }

        $alamat = $kota = $kodePos = null;
        if ($adaFisik) {
            if ($pembayaran === 'GoPay / OVO') {
                throw new PesananException('GoPay / OVO hanya tersedia untuk pesanan E-Book.');
            }
            if (!isset(TARIF_ONGKIR[$metodeKirim])) {
                throw new PesananException('Metode pengiriman tidak valid.');
            }
            $alamat  = teks($input['alamat'] ?? '', 255);
            $kota    = teks($input['kota'] ?? '', 80);
            $kodePos = teks($input['kode_pos'] ?? '', 10);
            if ($alamat === '' || $kota === '') {
                throw new PesananException('Alamat dan kota wajib diisi untuk buku fisik.');
            }
            $ongkir = $subtotal >= GRATIS_ONGKIR_MIN ? 0 : TARIF_ONGKIR[$metodeKirim];
        } else {
            if ($pembayaran === 'COD') {
                throw new PesananException('COD tidak tersedia untuk pesanan E-Book.');
            }
            $metodeKirim = 'none';
            $ongkir      = 0;
        }
        $total = $subtotal + $ongkir;

        $kode = 'RR' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $pdo->prepare(
            'INSERT INTO orders
                (order_code, user_id, customer_name, phone, address, city, postal_code,
                 shipping_method, shipping_cost, subtotal, total, payment_method)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $kode, $pengguna['id'], $nama, $telepon, $alamat, $kota, $kodePos ?: null,
            $metodeKirim, $ongkir, $subtotal, $total, $pembayaran,
        ]);
        $idPesanan = (int) $pdo->lastInsertId();

        $simpanItem = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, product_title, price, qty)
             VALUES (?, ?, ?, ?, ?)'
        );
        $updateFisik = $pdo->prepare(
            'UPDATE products SET stock = stock - ?, sold = sold + ? WHERE id = ? AND stock >= ?'
        );
        $updateEbook = $pdo->prepare('UPDATE products SET sold = sold + ? WHERE id = ?');

        foreach ($jumlah as $id => $qty) {
            $p = $produk[$id];
            $simpanItem->execute([$idPesanan, $id, $p['title'], $p['price'], $qty]);

            if ($p['type'] === 'ebook') {
                $updateEbook->execute([$qty, $id]);
            } else {
                $updateFisik->execute([$qty, $qty, $id, $qty]);
                if ($updateFisik->rowCount() !== 1) {
                    throw new PesananException('Stok "' . $p['title'] . '" baru saja habis.');
                }
            }
        }

        $pdo->commit();
    } catch (PesananException $e) {
        $pdo->rollBack();
        gagal($e->getMessage(), 422);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    kirimJson([
        'kode'       => $kode,
        'subtotal'   => $subtotal,
        'ongkir'     => $ongkir,
        'total'      => $total,
        'pembayaran' => $pembayaran,
    ], 201);
}
