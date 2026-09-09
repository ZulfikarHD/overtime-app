# Panduan Pengguna: Pemantauan Jam Lembur Proyek CapEx (Cockpit)

## Ringkasan Fitur

Dashboard Cockpit Pemantauan Proyek CapEx (**Story E07-02**) memungkinkan Manajer Departemen dan Project Manager memantau penyerapan jam kerja lembur yang dikapitalisasi ke dalam proyek aset tetap. Melalui cockpit ini, Anda dapat memantau indikator indeks burn jam kerja, membandingkan kemajuan fisik aktual lapangan dengan laju konsumsi jam lembur, memperbarui progres fisik secara langsung (_in-place_), membaca kurva burndown per minggu, dan meninjau daftar teknisi yang berkontribusi.

---

## 1. Mengakses Cockpit Proyek CapEx

1. Buka menu navigasi utama di bilah samping (_sidebar_), lalu pilih **Proyek CapEx** (`/admin/capex-projects`).
2. Pada tab **Portofolio & Master Data**, cari proyek yang ingin Anda periksa.
3. Klik tombol **Lihat Detail** pada baris proyek.
4. Anda akan diarahkan ke halaman detail cockpit proyek (`/admin/capex-projects/{id}`).

---

## 2. Membaca 4 Kartu KPI Makro

Di bagian atas cockpit, terdapat 4 kartu indikator utama:

| Kartu KPI                      | Keterangan & Rumus                                                                                            | Indikator Visual                                                                                                                                                                                 |
| :----------------------------- | :------------------------------------------------------------------------------------------------------------ | :----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Jam Tenaga Kerja**           | Membandingkan jam lembur aktual yang telah disetujui (_Approved_) terhadap plafon alokasi jam kerja.          | Menampilkan bilah progres biru (_controlled_) atau merah (_overrun_). Menunjukkan sisa jam alokasi.                                                                                              |
| **Biaya Terkapitalisasi**      | Menghitung akumulasi biaya lembur (_snapshot cost_) terhadap plafon anggaran Rupiah proyek.                   | Format mata uang Rupiah (`Rp 12.345.000`). Menampilkan sisa anggaran tersedia.                                                                                                                   |
| **Indeks Burn CapEx**          | Persentase konsumsi jam kerja: `(Jam Konsumsi / Jam Alokasi) × 100%`.                                         | <ul><li>**< 85%**: Hijau (Aman)</li><li>**85% - 100%**: Biru Muda (Mendekati Plafon)</li><li>**> 100%**: Kuning/Oranye (Peringatan Overrun)</li><li>**> 115%**: Merah (Defisit Kritis)</li></ul> |
| **Kemajuan Fisik & Milestone** | Menampilkan persentase kemajuan fisik lapangan dan **Rasio Burn Milestone** (`Indeks Burn / Kemajuan Fisik`). | Jika Rasio > 1.20, muncul badge peringatan: `⚠️ Pembakaran jam lebih cepat dibanding kemajuan fisik!`                                                                                            |

---

## 3. Memperbarui Kemajuan Fisik Lapangan (_In-Place_)

Kemajuan fisik proyek dapat diperbarui langsung dari cockpit tanpa harus membuka dialog terpisah atau memuat ulang halaman:

1. Pada kartu **Pembaruan Kemajuan Fisik Proyek (In-Place)**, klik tombol **Ubah Kemajuan**.
2. Anda dapat menyesuaikan persentase dengan dua cara:
    - Geser **Slider Persentase** antara 0% hingga 100%.
    - Atau ketik nilai presisi pada kotak **Input Manual (%)** (misalnya `75.5`).
3. Klik tombol **Simpan**. Sistem akan menyimpan perubahan ke basis data dan merekam jejak audit (_audit trail_).
4. Rasio Burn Milestone dan indikator terkait akan langsung diperbarui.

> **Pemberitahuan Milestone 100%**: Ketika kemajuan fisik mencapai 100%, sistem akan menampilkan spanduk khusus di bagian atas:
> `🎉 Kemajuan Fisik Mencapai 100% — Pekerjaan fisik proyek telah selesai 100%. Apakah Anda ingin memperbarui status proyek menjadi COMPLETED?`
> Anda dapat langsung mengklik tombol **Ubah Status ke COMPLETED Sekarang** untuk memperbarui status proyek ke tahap penutupan.

---

## 4. Membaca Kurva Burndown Mingguan

Grafik **Kurva Akumulasi Jam Tenaga Kerja (Burndown)** menampilkan tren konsumsi jam lembur:

- **Garis Putus-Putus Abu-Abu**: Target akumulasi jam alokasi secara linear dari tanggal mulai hingga target selesai.
- **Garis Tebal Berwarna**: Akumulasi aktual jam lembur yang telah disetujui setiap minggunya.
    - Warna garis akan otomatis berubah sesuai status burn index (Hijau, Biru, Kuning, atau Merah).

---

## 5. Meninjau Roster Kontribusi Teknisi

Tabel **Roster Kontribusi Tenaga Kerja Proyek** memuat daftar seluruh operator dan teknisi yang jam lemburnya dibebankan ke proyek ini:

- Diurutkan dari teknisi dengan jam kontribusi tertinggi.
- Kolom mencakup: No, NPK (dapat diklik untuk membuka berkas _Employee Dossier_), Nama Karyawan, Seksi/Pos, Total Jam Lembur Disetujui, Biaya Snapshot (Rp), dan Persentase Kontribusi terhadap total proyek.

---

## 6. Notifikasi Peringatan Burn (>80%)

Sistem secara otomatis mengevaluasi ambang batas penggunaan jam lembur proyek CapEx:

- Jika Indeks Burn CapEx melebihi **80%**, sistem akan mengirimkan notifikasi peringatan bertanda ⚠️ ke ikon lonceng (_Notification Bell_) Manajer Departemen dan Administrator.
- Notifikasi dilengkapi tombol **Buka Proyek** yang membawa Anda langsung ke cockpit proyek terkait.
- Notifikasi ini dideduplikasi sehingga hanya dikirimkan maksimal 1 kali per bulan kalender per proyek guna mencegah banjir notifikasi.
