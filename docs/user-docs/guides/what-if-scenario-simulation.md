# Panduan Pengguna: Simulasi Skenario Lembur & Perencanaan Produksi (What-If Scenario Simulation)

## Apa itu Fitur Simulasi Skenario?

Fitur **Simulasi Skenario (What-If Simulation)** adalah alat bantu pengambilan keputusan strategis pada Tab 4 menu **Analitik & Keputusan (`/analytics?tab=scenario`)**. Fitur ini dirancang khusus untuk Manajer Pabrik, Kepala Departemen, dan Industrial Engineer PT Isuzu Astra Motor Indonesia guna:

1. **Merencanakan Kebutuhan Sumber Daya Produksi**: Mengetahui estimasi jam lembur, biaya upah, dan kebutuhan tenaga kerja ketika target unit kendaraan dinaikkan atau diturunkan.
2. **Mensimulasikan Dampak Perubahan Beban Kerja**: Menguji dampak kenaikan atau penurunan jam lembur (-50% hingga +50%) terhadap anggaran biaya, proyeksi _Burn Index_, dan _Skor Risiko K3_ (persentase karyawan yang berisiko melampaui ambang batas jam kerja aman).
3. **Membandingkan dan Menyimpan Skenario**: Membandingkan data aktual saat ini dengan beberapa variasi skenario perencanaan dan menyimpan hingga 10 skenario favorit untuk dievaluasi bersama tim manajemen.

---

## Cara Menggunakan Fitur

### 1. Membuka Tab Simulasi Skenario

1. Masuk ke aplikasi menggunakan akun dengan peran **Admin** atau **Manajer**.
2. Pada menu bilah samping (sidebar), klik **Analitik & Keputusan**.
3. Klik tab ke-4: **Simulasi Skenario**.

---

### 2. Menggunakan Kalkulator Perencanaan Volume Produksi (Panel Kiri)

Panel ini digunakan ketika Anda memiliki target jumlah unit kendaraan yang ingin diproduksi dan ingin mengetahui kebutuhan lembur:

1. **Masukkan Target Volume Produksi**:
    - Ketik jumlah unit kendaraan pada kotak input (contoh: `1.500`), atau klik salah satu tombol preset cepat (`500`, `1.000`, `1.500`, atau `2.000`).
2. **Pilih Periode Perencanaan**:
    - Pilih `Mingguan`, `Bulanan`, atau `Kuartalan`.
3. **Pilih Seksi Produksi**:
    - Pilih lini produksi (misal: _Trim Line_, _Chassis Line_, dsb). Sistem akan menampilkan rasio historis jam lembur per unit (`labor factor`) dari seksi tersebut secara otomatis.
4. **Klik Tombol "Hitung Kebutuhan"**:
    - Sistem akan menghitung dan menampilkan 4 Kartu KPI Ringkasan:
        - **Total Jam Lembur**: Estimasi kebutuhan jam lembur yang harus dialokasikan.
        - **Estimasi Biaya**: Perkiraan total biaya lembur dalam Rupiah (Rp).
        - **Karyawan Dibutuhkan**: Estimasi jumlah operator tambahan yang diperlukan agar jam kerja per orang tetap berada di bawah batas regulasi K3.
        - **Efisiensi**: Persentase rasio produktivitas relatif terhadap baseline historis.
    - **Tabel Rincian Alokasi Berdasarkan Kategori Beban**:
        - Menampilkan proporsi alokasi jam dan biaya untuk Produksi Reguler (OpEx), Pemeliharaan Mesin TPM (OpEx), Proyek Khusus CapEx, dan Lainnya.

---

### 3. Menggunakan Simulator Skenario Beban Kerja (Panel Kanan)

Panel ini digunakan ketika manajemen ingin mengevaluasi dampak finansial dan keselamatan jika jam lembur dinaikkan atau ditekan:

1. **Geser Penggeser (Slider) Perubahan Jam Lembur**:
    - Geser dari `-50%` (penghematan ekstrem) hingga `+50%` (peningkatan kapasitas lembur), atau klik tombol preset cepat (`-50%`, `-25%`, `0%`, `+25%`, `+50%`).
2. **Tentukan Plafon Anggaran Target (Rp)**:
    - Masukkan nominal batas pagu anggaran yang direncanakan.
3. **Pilih Departemen Target**:
    - Pilih departemen yang dianalisis (otomatis terkunci untuk peran Manajer).
4. **Klik Tombol "Jalankan Skenario"**:
    - Sistem akan menampilkan 4 Kartu Hasil Proyeksi:
        - **Dampak Biaya (Cost Impact)**: Nominal selisih biaya dibandingkan baseline aktual ($\pm\text{Rp}$).
        - **Dampak Output Produksi**: Perkiraan kenaikan atau penurunan volume kendaraan yang dihasilkan ($\pm\%$) berdasarkan korelasi empiris.
        - **Proyeksi Burn Index**: Persentase konsumsi anggaran yang diproyeksikan beserta lencana status zona (Aman, Waspada, Bahaya).
        - **Skor Risiko K3**: Estimasi persentase tenaga kerja yang berisiko melampaui batas jam kerja aman mingguan.

---

### 4. Menyimpan dan Mengelola Skenario

1. **Menyimpan Skenario**:
    - Setelah menjalankan skenario yang diinginkan, klik tombol **Simpan Skenario**.
    - Masukkan nama skenario (contoh: _Peningkatan Output Q4 2026_).
    - Klik **Simpan**. Skenario akan tersimpan ke profil Anda (maksimal 10 skenario).
2. **Membuka Panel Kelola Skenario**:
    - Klik tombol **Kelola Skenario** di bagian kanan atas panel simulator.
    - Bilah geser (_drawer_) akan muncul dari sebelah kanan menampilkan daftar semua skenario tersimpan.
3. **Menerapkan Skenario Tersimpan**:
    - Klik tombol **Terapkan** pada kartu skenario untuk memuat parameter tersebut langsung ke simulator dan grafik perbandingan.
4. **Menghapus Skenario**:
    - Klik ikon **Hapus (Tong Sampah)** untuk menghapus skenario yang sudah tidak relevan.

---

### 5. Membaca Grafik Perbandingan Skenario (Panel Bawah)

Panel bawah menyajikan visualisasi perbandingan langsung:

- **Baseline (Aktual Saat Ini)**: Data realisasi operasional terkini.
- **Skenario Aktif**: Hasil dari konfigurasi slider dan simulator yang sedang aktif.
- **Skenario Tersimpan**: Menampilkan hingga 3 skenario yang pernah Anda simpan.

Gunakan tombol pemilih metrik untuk beralih perspektif:

- **Jam Lembur**: Membandingkan total jam lembur yang dialokasikan.
- **Biaya (Jt)**: Membandingkan pengeluaran dalam jutaan Rupiah.
- **Burn Index (%)**: Membandingkan laju konsumsi anggaran terhadap batas plafon.
- **Risiko K3 (%)**: Membandingkan tingkat risiko kelelahan dan keselamatan kerja antar skenario.

Tabel ringkasan di bawah grafik memberikan gambaran komparasi terperinci dengan angka monospasi yang presisi.
