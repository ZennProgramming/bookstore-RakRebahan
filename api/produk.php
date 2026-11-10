<?php
require_once __DIR__ . '/_umum.php';
wajibMetode('GET');

$sql = "SELECT p.id,
               p.title          AS judul,
               p.author         AS penulis,
               c.name           AS kategori,
               p.type           AS tipe,
               p.price          AS harga,
               p.stock          AS stok,
               p.sold           AS terjual,
               p.cover_image    AS gambar,
               p.description    AS deskripsi
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.is_active = 1
        ORDER BY p.id";

$daftar = db()->query($sql)->fetchAll();

foreach ($daftar as &$produk) {
    foreach (['id', 'harga', 'stok', 'terjual'] as $kolom) {
        $produk[$kolom] = (int) $produk[$kolom];
    }
}
unset($produk);

kirimJson($daftar);
