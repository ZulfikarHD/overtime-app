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
