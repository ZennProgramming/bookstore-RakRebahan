const STATUS_PESANAN = {
    pending:    ["Menunggu pembayaran", "warning"],
    paid:       ["Sudah dibayar", "info"],
    processing: ["Diproses", "primary"],
    shipped:    ["Dikirim", "secondary"],
    completed:  ["Selesai", "success"],
    cancelled:  ["Dibatalkan", "danger"],
};

function pilihan(nama, nilai, label, { checked = false, harga = "", metode = "" } = {}) {
    return `
        <label class="list-group-item d-flex gap-2">
            <input class="form-check-input" type="radio" name="${nama}" value="${nilai}"
                ${metode ? `data-metode="${metode}"` : ""} ${checked ? "checked" : ""}>
            ${label}<b class="ms-auto">${harga}</b>
        </label>`;
}

function tarifTerpilih() {
    const kirim = document.querySelector("input[name=pengiriman]:checked");
    return kirim ? hitungOngkir(Number(kirim.value)) : 0;
}

function tampilkanRingkasanCheckout() {
    const ongkir = tarifTerpilih();
    const subtotal = hitungSubtotal();

    $("ringkasan-checkout").innerHTML = `
        <h5>Pesananmu</h5>
        ${itemKeranjang().map(({ produk, jumlah }) =>
            baris(`${escapeHtml(produk.judul)} ×${jumlah}`, rupiah(produk.harga * jumlah))).join("")}
        ${baris("Subtotal", rupiah(subtotal), "border-top pt-2")}
        ${baris("Ongkir", ongkir ? rupiah(ongkir) : "Gratis")}
        ${baris("Total", rupiah(subtotal + ongkir), "fw-bold border-top pt-2")}`;
}

function tampilkanCheckout() {
    if (!itemKeranjang().length) {
        location.hash = "keranjang";
        return;
    }
    if (!pengguna) {
        tujuanSetelahLogin = "checkout";
        tampilkanToast("Silakan masuk dulu untuk checkout");
        location.hash = "login";
        return;
    }

    const fisik = adaBukuFisik();
    const labelOngkir = (tarif) => (hitungSubtotal() >= GRATIS_ONGKIR_MIN ? "Gratis" : rupiah(tarif));

    const bagianAlamat = fisik ? `
        <div class="mb-3">
            <label class="form-label" for="co-alamat">Alamat lengkap</label>
            <textarea class="form-control" id="co-alamat" rows="3" required></textarea>
        </div>
        <div class="row">
            <div class="col-md-6">${isian("Kota", "co-kota", "required")}</div>
            <div class="col-md-6">${isian("Kode pos", "co-kodepos")}</div>
        </div>
        <h5>Pengiriman</h5>
        <div class="list-group mb-3">
            ${pilihan("pengiriman", TARIF_ONGKIR.reguler, "Reguler (3–5 hari)", { checked: true, metode: "reguler", harga: labelOngkir(TARIF_ONGKIR.reguler) })}
            ${pilihan("pengiriman", TARIF_ONGKIR.express, "Express (1–2 hari)", { metode: "express", harga: labelOngkir(TARIF_ONGKIR.express) })}
        </div>`
        : `<p class="text-secondary">Pesananmu hanya berisi E-Book. File dikirim setelah pembayaran, tanpa ongkir.</p>`;

    $("isi-checkout").innerHTML = `
    <div class="row g-3">
        <form class="col-lg-8" id="form-checkout">
            <div class="card card-body">
                <h5>Data pemesan</h5>
                <div class="row">
                    <div class="col-md-6">${isian("Nama lengkap", "co-nama", `required value="${escapeHtml(pengguna.nama)}"`)}</div>
                    <div class="col-md-6">${isian("No. WhatsApp", "co-telepon", 'required inputmode="tel" placeholder="08xxxxxxxxxx"')}</div>
                </div>
                ${bagianAlamat}
                <h5>Pembayaran</h5>
                <div class="list-group mb-3">
                    ${pilihan("pembayaran", "Transfer Bank BCA", "Transfer Bank BCA", { checked: true })}
                    ${pilihan("pembayaran", "QRIS", "QRIS")}
                    ${fisik ? pilihan("pembayaran", "COD", "Bayar di tempat (COD)") : pilihan("pembayaran", "GoPay / OVO", "GoPay / OVO")}
                </div>
                <div class="text-danger small mb-2" id="co-galat"></div>
                <button class="btn btn-dark" id="co-kirim">Buat Pesanan</button>
            </div>
        </form>
        <div class="col-lg-4"><div class="card card-body" id="ringkasan-checkout"></div></div>
    </div>`;

    tampilkanRingkasanCheckout();
    document.querySelectorAll("input[name=pengiriman]").forEach((r) => (r.onchange = tampilkanRingkasanCheckout));
    $("form-checkout").addEventListener("submit", kirimPesanan);
}

async function kirimPesanan(e) {
    e.preventDefault();
    const tombol = $("co-kirim");
    const galat = $("co-galat");
    galat.textContent = "";

    const kirim = document.querySelector("input[name=pengiriman]:checked");
    const data = {
        nama: $("co-nama").value.trim(),
        telepon: $("co-telepon").value.trim(),
        pembayaran: document.querySelector("input[name=pembayaran]:checked").value,
        pengiriman: kirim ? kirim.dataset.metode : "none",
        alamat: $("co-alamat")?.value.trim() ?? "",
        kota: $("co-kota")?.value.trim() ?? "",
        kode_pos: $("co-kodepos")?.value.trim() ?? "",
        item: itemKeranjang().map(({ produk, jumlah }) => ({ id: produk.id, qty: jumlah })),
    };
    // keranjang dikosongkan setelah sukses, jadi daftar barang disimpan dulu untuk pesan WhatsApp
    const daftarBarang = itemKeranjang().map(({ produk, jumlah }) => `- ${produk.judul} x${jumlah}`);

    tombol.disabled = true;
    tombol.textContent = "Memproses…";

    try {
        const hasil = await Api.buatPesanan(data);
        tampilkanPesananBerhasil(hasil, data.nama, daftarBarang);
        kosongkanKeranjang();
        muatProduk();
    } catch (err) {
        if (err.status === 401) {
            pengguna = null;
            tampilkanAkun();
            tujuanSetelahLogin = "checkout";
            location.hash = "login";
            return;
        }
        galat.textContent = err.message;
        tombol.disabled = false;
        tombol.textContent = "Buat Pesanan";
    }
}

function tampilkanPesananBerhasil(hasil, nama, daftarBarang) {
    const pesanWa =
        `Halo Rak Rebahan, pesanan ${hasil.kode} atas nama ${nama}:\n` +
        daftarBarang.join("\n") +
        `\nTotal: ${rupiah(hasil.total)} (${hasil.pembayaran})`;

    $("isi-checkout").innerHTML = `
        <div class="card mx-auto text-center" style="max-width: 400px">
            <div class="card-body d-grid gap-2">
                <h2 class="h4">Pesanan berhasil dibuat</h2>
                <p class="text-secondary">Nomor pesanan <b>${escapeHtml(hasil.kode)}</b><br>
                    Total ${rupiah(hasil.total)} · ${escapeHtml(hasil.pembayaran)}</p>
                <a class="btn btn-dark" target="_blank" href="${linkWhatsApp(pesanWa)}">Konfirmasi via WhatsApp</a>
                <a class="btn btn-outline-dark" href="#pesanan">Lihat pesanan saya</a>
                <a class="btn btn-outline-dark" href="#beranda">Kembali belanja</a>
            </div>
        </div>`;
}

async function tampilkanPesananSaya() {
    const wadah = $("daftar-pesanan");

    if (!pengguna) {
        tujuanSetelahLogin = "pesanan";
        tampilkanToast("Silakan masuk dulu");
        location.hash = "login";
        return;
    }

    wadah.innerHTML = "<p>Memuat…</p>";
    try {
        const daftar = await Api.riwayatPesanan();
        if (!daftar.length) {
            wadah.innerHTML = `<div class="card card-body">${pesanKosong("Belum ada pesanan.", "Mulai belanja")}</div>`;
            return;
        }

        wadah.innerHTML = daftar.map((p) => {
            const [label, warna] = STATUS_PESANAN[p.status] || [p.status, "secondary"];
            return `
            <div class="card card-body mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <b>${escapeHtml(p.kode)}</b>
                    <span class="badge text-bg-${warna}">${escapeHtml(label)}</span>
                </div>
                <small class="text-secondary">${escapeHtml(p.tanggal)} · ${escapeHtml(p.pembayaran)}</small>
                <div class="mt-2">
                    ${p.item.map((i) => baris(`${escapeHtml(i.judul)} ×${i.qty}`, rupiah(i.harga * i.qty))).join("")}
                </div>
                ${p.resi ? `<p class="mb-2">No. resi: <b>${escapeHtml(p.resi)}</b></p>` : ""}
                ${baris("Total", rupiah(p.total), "fw-bold border-top pt-2 mb-0")}
            </div>`;
        }).join("");
    } catch (err) {
        wadah.innerHTML = `<p class="text-danger">${escapeHtml(err.message)}</p>`;
    }
}
