function rute() {
    const [nama, parameter] = location.hash.slice(1).split("/");
    const tujuan = $("halaman-" + nama) ? nama : "beranda";
    const menuAktif = tujuan === "produk" ? "beranda" : tujuan;

    document.querySelectorAll(".halaman").forEach((el) => {
        el.classList.toggle("d-none", el.id !== "halaman-" + tujuan);
    });
    document.querySelectorAll(".navbar-nav .nav-link").forEach((a) => {
        a.classList.toggle("active", a.getAttribute("href") === "#" + menuAktif);
    });
    $("menu").classList.remove("show");

    if (tujuan === "produk") tampilkanDetailProduk(Number(parameter));
    if (tujuan === "keranjang") tampilkanHalamanKeranjang();
    if (tujuan === "checkout") tampilkanCheckout();
    if (tujuan === "pesanan") tampilkanPesananSaya();
    window.scrollTo(0, 0);
}

function pasangEventKontak() {
    const perbaruiLinkWa = () => {
        $("kontak-wa").href = linkWhatsApp(`Halo admin, saya ${$("kontak-nama").value}. ${$("kontak-pesan").value}`);
    };
    $("kontak-nama").addEventListener("input", perbaruiLinkWa);
    $("kontak-pesan").addEventListener("input", perbaruiLinkWa);
    perbaruiLinkWa();

    $("form-kontak").addEventListener("submit", async (e) => {
        e.preventDefault();
        try {
            await Api.kirimPesan({ nama: $("kontak-nama").value, pesan: $("kontak-pesan").value });
            e.target.reset();
            perbaruiLinkWa();
            tampilkanToast("Pesan terkirim, terima kasih!");
        } catch (err) {
            tampilkanToast(err.message);
        }
    });
}

async function mulai() {
    document.querySelectorAll("[data-wa]").forEach((a) => (a.href = linkWhatsApp()));

    pasangEventKatalog();
    pasangEventAkun();
    pasangEventKontak();
    window.addEventListener("hashchange", rute);

    await Promise.all([muatPengguna(), muatProduk()]);
    rute();
}

mulai();
