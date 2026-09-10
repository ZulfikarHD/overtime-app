# Analytics & Decision Intelligence - User Guide

## What is Analytics & Decision Intelligence?

The **Analytics & Decision Intelligence** hub is an executive workspace for Department Managers, General Managers, and Plant Administrators. It provides forward-looking forecasting, financial audits, productivity correlation curves, and what-if simulation models to help you make informed overtime decisions without manual spreadsheet calculations.

---

## How to Use

### Accessing the Hub

1. Ensure you are signed in with an **Administrator** or **Manager** role.
2. In the navigation sidebar, click **Analitik & Keputusan** (or **Analytics & Decision** in English).
3. The system opens the main analytics workspace.

> 💡 **Tip:** Team Leaders and Operators cannot access this screen as it contains plant-level financial and strategic forecasting models.

---

### Switching Analytical Views (6 Tabs)

Click on any tab in the navigation bar to immediately view its specialized charts and indicators:

1. **Prediksi Lembur (Predictive Analytics)**:
    - **4 KPI Cards**:
        - _Prediksi Bulan Depan_: Total estimated overtime hours with uncertainty margins (e.g. `380.0 jam ± 30.0 jam`).
        - _Tingkat Akurasi (MAPE)_: Reliability metric based on Machine Learning validation scores (or tagged with a _Moving Average_ badge during initial baseline periods).
        - _Pola Musiman_: Identifies whether the upcoming month is Peak (_Puncak_), Normal (_Normal_), or Low (_Rendah_) relative to annual historical averages.
        - _Arah Tren_: 3-month rolling velocity indicator (Increasing, Stable, or Decreasing).
    - **Section Forecast Bar Chart**: Displays predicted hours per section with visual error whiskers representing lower and upper confidence limits.
    - **6-Month Trend Projection Line**: Compares the last 3 months of actual overtime against 3 months of projected workload with a 90% confidence ribbon.
    - **Seasonal Analysis & Summary**: 12-month annual curve highlighting the historical peak quarter alongside summary cards (_Musim Puncak_, _Musim Rendah_, _Siklus Pola_).
2. **Analisis Biaya (Cost Analysis)**:
    - **4 Financial KPI Cards**:
        - _Total Biaya Lembur_: Total verified overtime expenditure for the selected period (e.g. `Rp 125,5 Jt`).
        - _Sisa Anggaran (Rp)_: Remaining budget balance alongside a real-time progress bar showing the percentage consumed and traffic-light burn zone status.
        - _Rata-rata Biaya per Karyawan_: Average overtime labor cost per active operator (e.g. `Rp 2,5 Jt`).
        - _Rasio Biaya CapEx_: Percentage of overtime expenditure capitalized into project-driven investment versus ongoing operational expenses (OpEx).
    - **Biaya Lembur per Departemen**: Horizontal ranking bar chart color-coded by budget compliance:
        - 🟢 **< 85% Aman (Safe)**: Minimal risk of budget overrun.
        - 🔵 **85–100% Sesuai (On Track)**: Healthy budget pacing.
        - 🟡 **100–110% Peringatan (Warning)**: Approaching or slightly surpassing target allocation.
        - 🔴 **> 110% Bahaya (Danger)**: Critical overrun requiring immediate management intervention.
    - **Tren Biaya Lembur (6 Bulan)**: Stacked area chart showing the past 6 months of OpEx (regular production & TPM) vs. CapEx (capitalized machinery and expansion projects) in Indonesian Rupiah.
    - **Anggaran vs Realisasi**: Grouped comparative bar chart per department comparing planned budget vs actual spend, highlighting deficits in red.
    - **Rincian Biaya per Departemen**: Sortable high-density audit table with real-time department search, total hours, average rate/hour (`Rp 50.000`), total cost, budget consumption percentage, month-over-month trend indicators (↑ / ↓ / →), and a consolidated Grand Total footer.
3. **Korelasi & Pola (Correlation & Patterns)**:
    - **3 Summary Cards**:
        - _Zona Lembur Wajar (Sweet Spot)_: Recommended 12.0–18.0 hours/week range where productivity remains high without fatigue defects.
        - _Titik Puncak Produktivitas_: Historical optimal output point (15.2 hours/week) delivering lowest labor cost per vehicle unit.
        - _Ambang Batas Kelelahan_: Plant safety limit (> 20.0 hours/week) derived from corporate policy thresholds.
    - **Lembur vs Volume Produksi (Scatter & Regresi)**:
        - Interactive scatter plot plotting monthly vehicle production units against overtime hours.
        - Linear regression trend line ($y = mx + c$) and Pearson correlation coefficient ($r$) badge.
        - Section filter dropdown to isolate individual production lines (e.g. Trim Line, Chassis Line).
        - Friendly ERP standby banner if vehicle feed is disconnected.
    - **Lembur vs Metrik Kualitas**:
        - External quality metric monitor with graceful fallback banner (`"Menunggu integrasi data kualitas dari ERP"`) ensuring zero system interruption during ERP maintenance.
    - **Tingkat Lembur Optimal (Area Chart)**:
        - 3-zone visual curve (Under-utilized `<12`h, Sweet Spot $12\text{--}18$h, Over-threshold $>20$h).
        - Dynamic marker badge tracking current section/department average weekly hours.
    - **Matriks Korelasi Bivariat**:
        - 5x5 color-coded correlation matrix table measuring linear association between Overtime, Production, Quality, Efficiency, and Cost with explanatory legend.
4. **Simulasi Skenario (Scenario Simulation)**:
    - **Kalkulator Perencanaan Volume Produksi**: Mengestimasi kebutuhan total jam lembur, biaya (Rp), kebutuhan tambahan tenaga kerja, dan rincian proporsi kategori beban (Produksi, TPM, CapEx, Lainnya) berdasarkan target unit kendaraan dan rasio historis seksi.
    - **Simulator Skenario Beban Kerja**: Menyesuaikan persentase perubahan jam lembur (-50% s/d +50%) dan batas plafon anggaran untuk melihat dampak finansial ($\pm\text{Rp}$), fluktuasi output produksi ($\pm\%$), proyeksi Burn Index, serta Skor Risiko K3 keselamatan kerja.
    - **Perbandingan Skenario & Preset**: Membandingkan baseline aktual, skenario aktif, dan hingga 3 skenario tersimpan dalam bentuk grafik batang dan tabel matriks, dilengkapi drawer slide-in untuk mengelola preset skenario.
5. **Wawasan Kunci (Key Insights)**: Automated risk warnings, consecutive shift fatigue indicators, and compliance alerts.
6. **Perbandingan Periode (Period Comparison)**:
    - **Tipe Perbandingan**:
        - _Year-over-Year (YoY)_: Membandingkan bulan terpilih dengan bulan yang sama pada tahun sebelumnya (misal: September 2026 vs September 2025).
        - _Month-over-Month (MoM)_: Membandingkan bulan terpilih dengan bulan sebelumnya (misal: September 2026 vs Agustus 2026).
        - _Quarter-over-Quarter (QoQ)_: Membandingkan kuartal berjalan dengan 3 bulan sebelumnya.
        - _Benchmarking Departemen_: Membandingkan kinerja lembur antar seksi/departemen pada periode yang sama.
    - **4 Kartu Ringkasan KPI Komparasi**:
        - _Perubahan Jam Lembur_: Perbedaan jam lembur aktual vs periode pembanding dengan indikator panah kenaikan/penurunan (↑ / ↓) dan persentase perubahan.
        - _Perubahan Biaya_: Nilai efisiensi penghematan atau peningkatan biaya lembur dalam Rupiah (Rp).
        - _Perubahan Efisiensi_: Rasio unit kendaraan per jam lembur dengan penanda kesiapan integrasi ERP.
        - _Headcount Lembur_: Perubahan jumlah personel aktif yang melaksanakan lembur.
    - **Grafik Batang Perbandingan Periode**: Menampilkan perbandingan berdampingan antar bulan dengan garis persentase varians (%) pada sumbu Y sekunder.
    - **Grafik Benchmarking Departemen**: Diagram batang horizontal yang mengurutkan seluruh departemen berdasarkan beban lembur beserta badge Burn Index zona kepatuhan (Aman, Sesuai, Peringatan, Bahaya).
    - **Kartu Ringkasan Kinerja (3 Kartu)**:
        - _Departemen Terbaik_: Departemen dengan efisiensi Burn Index terbaik.
        - _Rata-rata Pabrik_: Nilai acuan benchmark tengah konsolidasi pabrik.
        - _Perlu Perhatian_: Departemen dengan potensi risiko pembengkakan lembur tertinggi.
    - **Praktik Terbaik Strategis (2 Kartu Insight)**: Narasi otomatis dalam bahasa Indonesia yang merangkum strategi operasional terunggul dari departemen terbaik untuk dijadikan acuan pembelajaran lintas lini.

> 💡 **Tip:** When you switch tabs, the web address in your browser updates automatically. You can copy the link from your address bar to share an exact analytical view with other managers.

---

### Using Global Filters

Use the filter bar at the top of the page to customize your analysis scope:

1. **Department Selector**:
    - **Administrators**: Choose any specific department or select **Semua Departemen (Lintas Pabrik)** to analyze the entire plant.
    - **Managers**: Automatically locked to your assigned department.
2. **Date Range**:
    - Select the target month/year or specify custom dates to analyze historical trends.
3. **Reset Filters**:
    - Click the reset button to restore the default current-month view.

---

### Exporting Reports (PDF & CSV)

To share analytics with leadership or analyze raw numbers in spreadsheet software:

1. Click the **Ekspor Laporan** (or **Export Report**) button located at the top-right of the page.
2. Review the active export scope (selected Department, Date Range, and Tab).
3. Choose your desired format:
    - **PDF (Executive 1-Page Summary)**: Generates a clean A4 portrait summary formatted according to ISUZU standards for executive meetings.
    - **CSV (Data Mentah Tab Ini)**: Downloads structured raw data ready for Microsoft Excel or BI tools.
4. The download will start automatically in your browser.

---

## Frequently Asked Questions (FAQ)

**Q: Why don't I see the "Analitik & Keputusan" menu in my sidebar?**  
A: This menu is only visible to users with the **Administrator** or **Manager** role. Team Leaders and Operators access the operational dashboard instead.

**Q: Can a Manager export data for another department?**  
A: No. Managers are strictly scoped to their own department. Only Administrators can review and export plant-wide cross-departmental data.

**Q: Will changing filters reload the entire page?**  
A: No. The application updates your analytical data seamlessly without full page refreshes, preserving your selected tab and scroll position.

---

## Troubleshooting

| Issue                                       | Solution                                                                                                                    |
| :------------------------------------------ | :-------------------------------------------------------------------------------------------------------------------------- |
| **Page shows "Access Restricted" (403)**    | Your account role is not Manager or Administrator. Contact HR / IT Admin if you require executive access.                   |
| **Export button does not trigger download** | Verify that popup blockers are not blocking downloads from the application domain, or try selecting the alternative format. |
| **Department selector is disabled**         | Managers are scoped to their assigned department by company policy. Only Administrators can change departments.             |
