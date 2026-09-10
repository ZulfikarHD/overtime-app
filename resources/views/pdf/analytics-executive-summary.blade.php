<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} — {{ $tab_label }}</title>
    <style>
        @page {
            margin: 25px 30px 30px 30px;
            size: a4 portrait;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 10px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        /* ISUZU Branding Header */
        .header-table {
            width: 100%;
            border-bottom: 2px solid #cc0000;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }

        .header-brand {
            font-size: 16px;
            font-weight: 900;
            color: #cc0000;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .header-subbrand {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        .header-title-box {
            text-align: right;
        }

        .header-title {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
        }

        .header-subtitle {
            font-size: 8px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Scope & Parameter Grid */
        .scope-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 16px;
        }

        .scope-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }

        .scope-table td {
            padding: 4px 6px;
            vertical-align: top;
        }

        .scope-label {
            color: #64748b;
            font-weight: 600;
            width: 25%;
        }

        .scope-value {
            color: #0f172a;
            font-weight: bold;
        }

        /* Section Headings */
        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #0f172a;
            border-left: 3px solid #cc0000;
            padding-left: 6px;
            margin-top: 14px;
            margin-bottom: 8px;
        }

        /* Executive Summary Narrative */
        .summary-box {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 12px;
            background-color: #ffffff;
            margin-bottom: 16px;
            font-size: 9.5px;
        }

        .summary-box p {
            margin: 0 0 8px 0;
        }

        .summary-box p:last-child {
            margin-bottom: 0;
        }

        /* Footer & Signatures */
        .footer-table {
            width: 100%;
            margin-top: 40px;
            border-top: 1px solid #e2e8f0;
            padding-top: 16px;
        }

        .signature-box {
            width: 45%;
            text-align: center;
        }

        .signature-line {
            margin-top: 45px;
            border-bottom: 1px solid #0f172a;
            width: 70%;
            margin-left: auto;
            margin-right: auto;
        }

        .signature-name {
            font-weight: bold;
            font-size: 10px;
            margin-top: 4px;
        }

        .signature-title {
            font-size: 8px;
            color: #64748b;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 16px;
        }

        .data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            text-align: left;
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            text-transform: uppercase;
            font-size: 8px;
        }

        .data-table td {
            padding: 4px 6px;
            border: 1px solid #e2e8f0;
            color: #0f172a;
        }

        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .kpi-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .kpi-cell {
            width: 25%;
            padding: 8px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            vertical-align: top;
        }

        .kpi-title {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .kpi-value {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            font-family: monospace;
        }

        .kpi-sub {
            font-size: 7.5px;
            color: #64748b;
            margin-top: 2px;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 50%; vertical-align: bottom;">
                <div class="header-brand">ISUZU</div>
                <div class="header-subbrand">PT Isuzu Astra Motor Indonesia &bull; Plant Operations</div>
            </td>
            <td class="header-title-box" style="width: 50%; vertical-align: bottom;">
                <div class="header-title">{{ $title }}</div>
                <div class="header-subtitle">Modul: {{ $tab_label }}</div>
            </td>
        </tr>
    </table>

    <!-- Parameter Scope -->
    <div class="scope-card">
        <table class="scope-table">
            <tr>
                <td class="scope-label">Departemen:</td>
                <td class="scope-value">{{ $department_name }}</td>
                <td class="scope-label">Waktu Unduh:</td>
                <td class="scope-value">{{ $generated_at }}</td>
            </tr>
            <tr>
                <td class="scope-label">Rentang Evaluasi:</td>
                <td class="scope-value">{{ $start_date }} s/d {{ $end_date }}</td>
                <td class="scope-label">Otoritas Pengunduh:</td>
                <td class="scope-value">{{ $generated_by }} ({{ $role_label }})</td>
            </tr>
            <tr>
                <td class="scope-label">Sub-Modul Analitik:</td>
                <td class="scope-value" colspan="3">{{ $tab_label }}</td>
            </tr>
        </table>
    </div>

    <!-- Executive Overview -->
    <div class="section-title">Ringkasan Analisis & Pedoman Keputusan</div>
    <div class="summary-box">
        <p><strong>Latar Belakang Operasional:</strong> Dokumen ringkasan eksekutif ini diterbitkan dari Modul Analitik &amp; Keputusan Lembur untuk mendukung evaluasi kapasitas shift, pengendalian beban kerja teknisi, dan kepatuhan anggaran lembur PT Isuzu Astra Motor Indonesia.</p>
        <p><strong>Cakupan Evaluasi:</strong> Analisis mencakup data realisasi lembur terverifikasi, perbandingan jam kerja normal (HKN) vs hari libur (HLR), dan pemisahan beban CapEx vs OpEx untuk departemen <em>{{ $department_name }}</em> pada periode <em>{{ $start_date }}</em> sampai dengan <em>{{ $end_date }}</em>.</p>
        <p><strong>Tindakan Manajemen:</strong> Rekomendasi operasional wajib ditindaklanjuti pada rapat koordinasi manajemen pabrik dan disinkronisasikan dengan sistem perencanaan produksi (ERP) serta kartu kontrol jam lembur masing-masing seksi.</p>
    </div>

    @if(isset($predictiveData) && $tab === 'predictive')
    <div class="section-title">Indikator Kunci Proyeksi &amp; Pola Musiman</div>
    <table class="kpi-grid">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-title">Prediksi Bulan Depan</div>
                <div class="kpi-value">{{ $predictiveData['kpi']['formatted_prediction'] }}</div>
                <div class="kpi-sub">{{ $predictiveData['kpi']['model_name'] }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Tingkat Akurasi</div>
                <div class="kpi-value" style="color: #16a34a;">{{ $predictiveData['kpi']['accuracy_label'] }}</div>
                <div class="kpi-sub">{{ $predictiveData['kpi']['accuracy_description'] }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Pola Musiman</div>
                <div class="kpi-value" style="color: #d97706;">{{ $predictiveData['kpi']['seasonal_pattern'] }}</div>
                <div class="kpi-sub">{{ $predictiveData['kpi']['seasonal_description'] }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Arah Tren</div>
                <div class="kpi-value" style="color: #0284c7;">{{ $predictiveData['kpi']['trend_label'] }}</div>
                <div class="kpi-sub">{{ $predictiveData['kpi']['trend_description'] }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Rincian Prediksi Jam Lembur per Seksi ({{ $predictiveData['scope']['target_month_name'] }})</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">Kode Seksi</th>
                <th style="width: 30%;">Nama Seksi</th>
                <th style="width: 15%; text-align: right;">Prediksi (Jam)</th>
                <th style="width: 20%; text-align: right;">Rentang Keyakinan</th>
                <th style="width: 15%;">Metode</th>
            </tr>
        </thead>
        <tbody>
            @forelse($predictiveData['section_forecast']['sections'] as $idx => $sec)
            <tr>
                <td style="text-align: center;">{{ $idx + 1 }}</td>
                <td style="font-family: monospace; font-weight: bold;">{{ $sec['section_code'] }}</td>
                <td>{{ $sec['section_name'] }}</td>
                <td style="text-align: right; font-family: monospace; font-weight: bold;">{{ number_format($sec['predicted_hours'], 1, ',', '.') }}</td>
                <td style="text-align: right; font-family: monospace;">{{ number_format($sec['ci_lower'], 1, ',', '.') }} &ndash; {{ number_format($sec['ci_upper'], 1, ',', '.') }}</td>
                <td>
                    <span style="font-size: 7.5px; padding: 1px 4px; border-radius: 2px; {{ $sec['fallback_used'] ? 'background-color: #f1f5f9; color: #475569;' : 'background-color: #f0fdf4; color: #166534;' }}">
                        {{ $sec['fallback_used'] ? 'Moving Average' : 'Supervised ML' }}
                    </span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; color: #94a3b8;">Belum ada seksi aktif dalam cakupan.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @endif

    @if(isset($costData) && $tab === 'cost')
    <div class="section-title">Indikator Kunci Biaya Lembur (Financial KPIs)</div>
    <table class="kpi-grid">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-title">Total Biaya Lembur</div>
                <div class="kpi-value" style="color: #cc0000;">{{ $costData['kpi']['formatted_total_cost'] }}</div>
                <div class="kpi-sub">Rp {{ number_format($costData['kpi']['total_cost'], 0, ',', '.') }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Sisa Anggaran</div>
                <div class="kpi-value" style="color: #16a34a;">{{ $costData['kpi']['formatted_remaining_budget'] }}</div>
                <div class="kpi-sub">Konsumsi: {{ $costData['kpi']['budget_consumption_pct'] }}%</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Rata-rata Biaya / Karyawan</div>
                <div class="kpi-value" style="color: #0284c7;">{{ $costData['kpi']['formatted_avg_cost_per_employee'] }}</div>
                <div class="kpi-sub">{{ $costData['kpi']['active_employee_count'] }} Karyawan Aktif</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Rasio Biaya CapEx</div>
                <div class="kpi-value" style="color: #7c3aed;">{{ $costData['kpi']['capex_ratio_pct'] }}%</div>
                <div class="kpi-sub">{{ $costData['kpi']['formatted_capex_cost'] }} Terkapitalisasi</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Audit Biaya &amp; Kepatuhan Anggaran per Departemen</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">Kode</th>
                <th style="width: 25%;">Departemen</th>
                <th style="width: 10%; text-align: right;">Total Jam</th>
                <th style="width: 15%; text-align: right;">Tarif (Rp/Jam)</th>
                <th style="width: 15%; text-align: right;">Total Biaya (Rp)</th>
                <th style="width: 15%; text-align: right;">Anggaran (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($costData['department_costs'] as $idx => $dept)
            <tr>
                <td style="text-align: center;">{{ $idx + 1 }}</td>
                <td style="font-family: monospace; font-weight: bold;">{{ $dept['department_code'] }}</td>
                <td>{{ $dept['department_name'] }}</td>
                <td style="text-align: right; font-family: monospace;">{{ number_format($dept['total_hours'], 1, ',', '.') }}</td>
                <td style="text-align: right; font-family: monospace;">{{ $dept['formatted_avg_rate'] }}</td>
                <td style="text-align: right; font-family: monospace; font-weight: bold;">Rp {{ number_format($dept['total_cost'], 0, ',', '.') }}</td>
                <td style="text-align: right; font-family: monospace;">Rp {{ number_format($dept['planned_cost'], 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align: center; color: #94a3b8;">Belum ada data biaya untuk periode ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @endif

    @if(isset($correlationData) && $tab === 'correlation')
    <div class="section-title">Indikator Kunci Zona Lembur Optimal &amp; Efisiensi (E09-09)</div>
    <table class="kpi-grid">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-title">Zona Wajar (Sweet Spot)</div>
                <div class="kpi-value" style="color: #0284c7;">{{ $correlationData['kpi']['sweet_spot_min'] }} &ndash; {{ $correlationData['kpi']['sweet_spot_max'] }}</div>
                <div class="kpi-sub">jam/minggu (Kapasitas ideal)</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Titik Puncak Produktivitas</div>
                <div class="kpi-value" style="color: #16a34a;">{{ $correlationData['kpi']['peak_efficiency_hours'] }}</div>
                <div class="kpi-sub">jam/minggu (Output tertinggi)</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Ambang Batas Kelelahan</div>
                <div class="kpi-value" style="color: #cc0000;">&gt; {{ $correlationData['kpi']['warning_threshold_hours'] }}</div>
                <div class="kpi-sub">jam/minggu (Batas kebijakan)</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Rata-rata Jam Saat Ini</div>
                <div class="kpi-value" style="color: #475569;">{{ $correlationData['kpi']['current_weekly_avg_hours'] }}</div>
                <div class="kpi-sub">{{ $correlationData['kpi']['current_zone_label'] }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Matriks Korelasi Bivariat Antar-Variabel</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25%;">Variabel</th>
                @foreach($correlationData['correlation_matrix']['variables'] as $var)
                <th style="text-align: center;">{{ $var['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($correlationData['correlation_matrix']['matrix'] as $rowIdx => $row)
            <tr>
                <td style="font-weight: bold;">{{ $correlationData['correlation_matrix']['variables'][$rowIdx]['label'] }}</td>
                @foreach($row as $cell)
                <td style="text-align: center; font-family: monospace;">
                    @if($cell['r'] !== null)
                        <span style="font-weight: bold; {{ $cell['color'] === 'green' ? 'color: #16a34a;' : ($cell['color'] === 'blue' ? 'color: #0284c7;' : 'color: #64748b;') }}">
                            {{ number_format($cell['r'], 2) }}
                        </span>
                    @else
                        <span style="color: #d97706; font-size: 8px;">ERP Pending</span>
                    @endif
                </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Sign-off Block -->
    <table class="footer-table">
        <tr>
            <td class="signature-box">
                <div class="signature-title">Disiapkan Oleh,</div>
                <div class="signature-line"></div>
                <div class="signature-name">{{ $generated_by }}</div>
                <div class="signature-title">{{ $role_label }}</div>
            </td>
            <td style="width: 10%;"></td>
            <td class="signature-box">
                <div class="signature-title">Mengetahui &amp; Menyetujui,</div>
                <div class="signature-line"></div>
                <div class="signature-name">Kepala Divisi / Plant General Manager</div>
                <div class="signature-title">PT Isuzu Astra Motor Indonesia</div>
            </td>
        </tr>
    </table>

</body>
</html>
