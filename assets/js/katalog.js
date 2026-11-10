const NAMA_JENIS = { fisik: "Buku fisik", ebook: "E-Book", bundle: "Paket hemat" };

let daftarProduk = [];
let daftarKategori = [];
let kategoriAktif = "Semua";
let jumlahDetail = 1;

const cariProduk = (id) => daftarProduk.find((p) => p.id === id);
const stokHabis = (p) => p.tipe !== "ebook" && p.stok === 0;

async function muatProduk() {
    try {
        [daftarProduk, daftarKategori] = await Promise.all([Api.produk(), Api.kategori()]);
    } catch (err) {
        $("grid-produk").innerHTML = `<p class="text-danger">Gagal memuat produk: ${escapeHtml(err.message)}</p>`;
        return;
    }
    bersihkanKeranjang();
    tampilkanKatalog();
    perbaruiKeranjang();
}

function teksStok(p) {
    if (p.tipe === "ebook") return "Instan";
    if (p.stok === 0) return "Habis";
    if (p.stok < 10) return "Sisa " + p.stok;
    return "Stok " + p.stok;
}

function kartuProduk(p) {
    const stokRendah = p.tipe !== "ebook" && p.stok < 10;

    return `
    <div class="col">
        <div class="card h-100" role="button" data-produk="${p.id}">
            <div class="card-body p-2 d-flex flex-column">
                ${cover(p)}
                <div class="d-flex justify-content-between small text-secondary mt-2">
                    <span>${escapeHtml(p.kategori.toUpperCase())}</span>
                    <span class="${stokRendah ? "text-danger" : ""}">${teksStok(p)}</span>
                </div>
                <div class="fw-semibold">${escapeHtml(p.judul)}</div>
                <small class="text-secondary">${escapeHtml(p.penulis)}</small>
                <small class="text-secondary my-1">${p.terjual.toLocaleString("id-ID")} Terjual</small>
                <div class="border-top pt-2 mt-auto">
                    <small class="text-secondary">Mulai</small> <b>${rupiah(p.harga)}</b>
                    <button class="btn btn-dark w-100 mt-2" data-beli="${p.id}" ${stokHabis(p) ? "disabled" : ""}>
                        ${stokHabis(p) ? "Stok habis" : "Beli"}
                    </button>
                </div>
            </div>
        </div>
    </div>`;
}

function tampilkanKatalog() {
    const namaKategoriDb = daftarKategori.map((k) => k.nama);
    const kategoriDariProduk = daftarProduk.map((p) => p.kategori).filter((k) => !namaKategoriDb.includes(k));
    const semuaKategori = ["Semua", ...namaKategoriDb, ...new Set(kategoriDariProduk)];
    if (!semuaKategori.includes(kategoriAktif)) kategoriAktif = "Semua";

    $("daftar-kategori").innerHTML = semuaKategori.map((k) => {
        const jumlah = daftarProduk.filter((p) => k === "Semua" || p.kategori === k).length;
        return `
            <button class="list-group-item list-group-item-action d-flex justify-content-between ${k === kategoriAktif ? "active" : ""}" data-kategori="${escapeHtml(k)}">
                ${escapeHtml(k)}<span>${jumlah}</span>
            </button>`;
    }).join("");

    const kataKunci = $("kolom-cari").value.toLowerCase();
    const hasil = daftarProduk.filter((p) =>
        (kategoriAktif === "Semua" || p.kategori === kategoriAktif) &&
        (p.judul + p.penulis).toLowerCase().includes(kataKunci)
    );

    const urutan = $("urutan").value;
    if (urutan === "termurah") hasil.sort((a, b) => a.harga - b.harga);
    if (urutan === "termahal") hasil.sort((a, b) => b.harga - a.harga);
    if (urutan === "terlaris") hasil.sort((a, b) => b.terjual - a.terjual);

    $("judul-katalog").textContent = kategoriAktif === "Semua" ? "Semua buku" : kategoriAktif;
    $("jumlah-produk").textContent = hasil.length + " produk";
    $("grid-produk").innerHTML = hasil.map(kartuProduk).join("") || "<p>Buku tidak ditemukan.</p>";
}

function tampilkanDetailProduk(id) {
    const wadah = $("isi-produk");
    const p = cariProduk(id);
    jumlahDetail = 1;

    if (!p) {
        wadah.innerHTML = `<div class="card card-body">${pesanKosong("Produk tidak ditemukan.", "Kembali ke katalog")}</div>`;
        return;
    }

    wadah.dataset.id = p.id;
    const keterangan = p.deskripsi || (p.tipe === "ebook"
        ? "Buku digital (PDF/ePub) dikirim otomatis setelah pembayaran."
        : "Buku original, baru, dan dikemas aman.");

    wadah.innerHTML = `
        <a class="d-inline-block mb-3 text-secondary text-decoration-none" href="#beranda">← Kembali ke produk</a>
        <div class="row g-4">
            <div class="col-md-4 col-lg-3">${cover(p)}</div>

            <div class="col-md-8 col-lg-9">
                <small class="text-secondary">${escapeHtml(p.kategori.toUpperCase())}</small>
                <h2 class="h3 mb-0">${escapeHtml(p.judul)}</h2>
                <p class="text-secondary mb-1">oleh ${escapeHtml(p.penulis)}</p>
                <small class="text-secondary">${p.terjual.toLocaleString("id-ID")} Terjual</small>

                <div class="fs-3 fw-bold my-3">${rupiah(p.harga)}</div>
                <p>${escapeHtml(keterangan)}</p>

                <ul class="list-group mb-3">
                    <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Jenis</span>${NAMA_JENIS[p.tipe] || escapeHtml(p.tipe)}</li>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Kategori</span>${escapeHtml(p.kategori)}</li>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Ketersediaan</span>${teksStok(p)}</li>
                </ul>

                ${stokHabis(p) ? `<p class="text-danger">Maaf, stok buku ini sedang habis.</p>` : `
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span>Jumlah</span>
                    ${kontrolJumlah(1, 'data-aksi="kurang"', 'data-aksi="tambah"')}
                </div>
                <div class="row g-2">
                    <div class="col-6"><button class="btn btn-outline-dark w-100" data-aksi="keranjang">Masukkan Keranjang</button></div>
                    <div class="col-6"><button class="btn btn-dark w-100" data-aksi="beli">Beli Sekarang</button></div>
                </div>`}
            </div>
        </div>`;
}

function aksiDetailProduk(e) {
    const aksi = e.target.closest("button[data-aksi]")?.dataset.aksi;
    const id = Number($("isi-produk").dataset.id);
    const p = cariProduk(id);
    if (!aksi || !p) return;

    if (aksi === "kurang" || aksi === "tambah") {
        const jumlahBaru = jumlahDetail + (aksi === "tambah" ? 1 : -1);
        jumlahDetail = Math.max(1, Math.min(batasJumlah(p), jumlahBaru));
        $("isi-produk").querySelector(".jumlah").textContent = jumlahDetail;
    } else if (aksi === "keranjang") {
        tambahKeKeranjang(id, jumlahDetail);
    } else if (aksi === "beli" && tambahKeKeranjang(id, jumlahDetail)) {
        location.hash = "checkout";
    }
}

function pasangEventKatalog() {
    $("daftar-kategori").addEventListener("click", (e) => {
        const tombol = e.target.closest("[data-kategori]");
        if (!tombol) return;
        kategoriAktif = tombol.dataset.kategori;
        tampilkanKatalog();
    });

    $("grid-produk").addEventListener("click", (e) => {
        const beli = e.target.closest("[data-beli]");
        if (beli) return tambahKeKeranjang(Number(beli.dataset.beli));

        const kartu = e.target.closest("[data-produk]");
        if (kartu) location.hash = "produk/" + kartu.dataset.produk;
    });

    $("isi-produk").addEventListener("click", aksiDetailProduk);
    $("kolom-cari").addEventListener("input", tampilkanKatalog);
    $("urutan").addEventListener("change", tampilkanKatalog);
}
