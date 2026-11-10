<?php
require_once __DIR__ . '/_umum.php';
wajibMetode('GET');

$daftar = db()->query('SELECT id, name AS nama FROM categories ORDER BY name')->fetchAll();

foreach ($daftar as &$k) {
    $k['id'] = (int) $k['id'];
}
unset($k);

kirimJson($daftar);
