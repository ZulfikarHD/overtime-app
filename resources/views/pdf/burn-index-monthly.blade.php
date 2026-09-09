<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} — {{ $department_label }} ({{ $period_label }})</title>
    <style>
        @page {
            margin: 20px 25px 25px 25px;
            size: a4 portrait;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 9px;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #cc0000;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .header-brand {
            font-size: 14px;
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
        }

        .header-title-box {
            text-align: right;
        }

        .header-title {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
        }

        .header-subtitle {
            font-size: 8px;
            color: #64748b;
        }

        .meta-table {
            width: 100%;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            margin-bottom: 10px;
            padding: 5px 8px;
        }

        .meta-label {
            font-size: 7.5px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }

        .meta-value {
            font-size: 9.5px;
            color: #0f172a;
            font-weight: bold;
        }

        .section-heading {
            font-size: 10px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin-top: 10px;
            margin-bottom: 5px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 2px;
        }

        .kpi-table {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: separate;
            border-spacing: 5px 0;
        }

        .kpi-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
            width: 25%;
        }

        .kpi-title {
            font-size: 7px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .kpi-main-val {
            font-size: 12px;
            font-weight: bold;
            font-family: 'Courier New', Courier, monospace;
            color: #0f172a;
        }

        .kpi-sub-val {
            font-size: 7.5px;
            color: #64748b;
            margin-top: 2px;
        }

        .status-safe { color: #059669; }
        .status-on-track { color: #0284c7; }
        .status-warning { color: #d97706; }
        .status-danger { color: #cc0000; }

        .badge-safe { background-color: #d1fae5; color: #065f46; padding: 1px 4px; border-radius: 2px; font-weight: bold; font-size: 7.5px; }
        .badge-on-track { background-color: #e0f2fe; color: #0369a1; padding: 1px 4px; border-radius: 2px; font-weight: bold; font-size: 7.5px; }
        .badge-warning { background-color: #fef3c7; color: #92400e; padding: 1px 4px; border-radius: 2px; font-weight: bold; font-size: 7.5px; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; padding: 1px 4px; border-radius: 2px; font-weight: bold; font-size: 7.5px; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-bottom: 10px;
        }

        .data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 7px;
            padding: 4px 5px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #94a3b8;
            text-align: left;
        }

        .data-table th.text-right { text-align: right; }
        .data-table th.text-center { text-align: center; }

        .data-table td {
            padding: 4px 5px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .data-table td.text-right { text-align: right; }
        .data-table td.text-center { text-align: center; }

        .data-table tr.row-warning { background-color: #fffbeb; }
        .data-table tr.row-danger { background-color: #fef2f2; }
        .data-table tr:nth-child(even):not(.row-warning):not(.row-danger) { background-color: #f8fafc; }

        .font-mono {
            font-family: 'Courier New', Courier, monospace;
        }

        .code-pill {
            font-family: 'Courier New', Courier, monospace;
            background-color: #e2e8f0;
            color: #334155;
            padding: 1px 3px;
            border-radius: 2px;
            font-size: 7px;
        }

        .capex-pill {
            font-family: 'Courier New', Courier, monospace;
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            padding: 1px 3px;
            border-radius: 2px;
            font-size: 7px;
            font-weight: bold;
        }

        .sign-table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
        }

        .sign-cell {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 4px 10px;
        }

        .sign-title {
            font-size: 7.5px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 30px;
        }

        .sign-line {
            border-top: 1px solid #64748b;
            padding-top: 3px;
            font-size: 8px;
            font-weight: bold;
            color: #0f172a;
        }

        .sign-role {
            font-size: 7px;
            color: #64748b;
        }

        .footer {
            margin-top: 12px;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            font-size: 7px;
            color: #94a3b8;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="vertical-align: middle;">
                <div class="header-brand">PT ISUZU ASTRA MOTOR INDONESIA</div>
                <div class="header-subbrand">Overtime & CapEx Labor Management System</div>
            </td>
            <td class="header-title-box" style="vertical-align: middle;">
                <div class="header-title">{{ $title }}</div>
                <div class="header-subtitle">Laporan Penutupan Bulanan & Analisis Biaya • {{ $generated_at }}</div>
            </td>
        </tr>
    </table>

    <!-- Metadata Grid -->
    <table class="meta-table" cellpadding="2" cellspacing="0">
        <tr>
            <td style="width: 28%;">
                <div class="meta-label">Departemen / Lingkup:</div>
                <div class="meta-value">{{ $department_label }}</div>
            </td>
            <td style="width: 22%;">
                <div class="meta-label">Periode Fiskal:</div>
                <div class="meta-value">{{ $period_label }}</div>
            </td>
            <td style="width: 25%;">
                <div class="meta-label">Waktu Pembaruan (WIB):</div>
                <div class="meta-value font-mono">{{ $generated_at }}</div>
            </td>
            <td style="width: 25%;">
                <div class="meta-label">Dicetak Oleh:</div>
                <div class="meta-value">{{ $printed_by }}</div>
            </td>
        </tr>
    </table>

    <!-- Macro KPI Cards -->
    <table class="kpi-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="kpi-card">
                <div class="kpi-title">Total Alokasi Jam</div>
                <div class="kpi-main-val">{{ number_format($summary['total_planned_hours'], 1, ',', '.') }} <span style="font-size: 8px; font-family: sans-serif; font-weight: normal; color: #64748b;">jam</span></div>
                <div class="kpi-sub-val">Target kuota disetujui</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-title">Realisasi Jam Lembur</div>
                <div class="kpi-main-val">{{ number_format($summary['total_actual_hours'], 1, ',', '.') }} <span style="font-size: 8px; font-family: sans-serif; font-weight: normal; color: #64748b;">jam</span></div>
                <div class="kpi-sub-val">Sisa kuota: <strong class="font-mono {{ $summary['total_remaining_hours'] < 0 ? 'status-danger' : 'status-safe' }}">{{ number_format($summary['total_remaining_hours'], 1, ',', '.') }} jam</strong></div>
            </td>
            <td class="kpi-card">
                <div class="kpi-title">Indeks Burn Departemen</div>
                <div class="kpi-main-val {{ $summary['department_burn_index_pct'] > 115 ? 'status-danger' : ($summary['department_burn_index_pct'] > 100 ? 'status-warning' : ($summary['department_burn_index_pct'] >= 85 ? 'status-on-track' : 'status-safe')) }}">
                    {{ number_format($summary['department_burn_index_pct'], 1, ',', '.') }}%
                </div>
                <div class="kpi-sub-val">Zona: <strong>{{ str_replace('_', ' ', $summary['department_burn_zone']) }}</strong></div>
            </td>
            <td class="kpi-card">
                <div class="kpi-title">Rasio CapEx / OpEx</div>
                @if(isset($capex_opex['summary']))
                    <div class="kpi-main-val font-mono" style="font-size: 11px;">
                        <span class="status-on-track">{{ number_format($capex_opex['summary']['capex_ratio_pct'], 1, ',', '.') }}%</span> / 
                        <span style="color: #475569;">{{ number_format($capex_opex['summary']['opex_ratio_pct'], 1, ',', '.') }}%</span>
                    </div>
                    <div class="kpi-sub-val">CapEx: {{ number_format($capex_opex['summary']['capex_hours'], 1, ',', '.') }} jam • OpEx: {{ number_format($capex_opex['summary']['opex_hours'], 1, ',', '.') }} jam</div>
                @else
                    <div class="kpi-main-val">-</div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Ranked Sections Table -->
    <div class="section-heading">1. Ringkasan Kinerja & Indeks Burn Seluruh Seksi</div>
    <table class="data-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th class="text-center" style="width: 3%;">#</th>
                <th style="width: 25%;">Seksi & Kode</th>
                <th class="text-right" style="width: 9%;">Rencana</th>
                <th class="text-right" style="width: 9%;">Realisasi</th>
                <th class="text-right" style="width: 9%;">Sisa</th>
                <th class="text-center" style="width: 10%;">Indeks Burn</th>
                <th class="text-center" style="width: 10%;">Zona</th>
                <th class="text-right" style="width: 8%;">CapEx (j)</th>
                <th class="text-right" style="width: 8%;">OpEx (j)</th>
                <th class="text-right" style="width: 9%;">Kecepatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($snapshots as $item)
                @php
                    $isDanger = $item['burn_zone'] === 'ZONE_4_POOR' || ($item['is_budget_configured'] && $item['burn_index_pct'] > 115);
                    $isWarning = $item['burn_zone'] === 'ZONE_3_WARNING' || ($item['is_budget_configured'] && $item['burn_index_pct'] > 100 && ! $isDanger);
                    $rowClass = $isDanger ? 'row-danger' : ($isWarning ? 'row-warning' : '');
                @endphp
                <tr class="{{ $rowClass }}">
                    <td class="text-center font-mono" style="font-weight: bold; color: #64748b;">{{ $item['rank'] }}</td>
                    <td>
                        <span style="font-weight: bold; color: #0f172a;">{{ $item['section_name'] }}</span>
                        <span class="code-pill">{{ $item['section_code'] }}</span>
                    </td>
                    <td class="text-right font-mono">
                        {{ $item['is_budget_configured'] ? number_format($item['planned_budget_hours'], 1, ',', '.') : '-' }}
                    </td>
                    <td class="text-right font-mono" style="font-weight: bold;">
                        {{ number_format($item['cumulative_actual_hours'], 1, ',', '.') }}
                    </td>
                    <td class="text-right font-mono {{ $item['remaining_budget_hours'] < 0 ? 'status-danger font-bold' : '' }}">
                        {{ $item['is_budget_configured'] ? number_format($item['remaining_budget_hours'], 1, ',', '.') : '-' }}
                    </td>
                    <td class="text-center">
                        @if($item['is_budget_configured'])
                            @if($item['burn_index_pct'] > 115)
                                <span class="badge-danger font-mono">{{ number_format($item['burn_index_pct'], 1, ',', '.') }}%</span>
                            @elseif($item['burn_index_pct'] > 100)
                                <span class="badge-warning font-mono">{{ number_format($item['burn_index_pct'], 1, ',', '.') }}%</span>
                            @elseif($item['burn_index_pct'] >= 85)
                                <span class="badge-on-track font-mono">{{ number_format($item['burn_index_pct'], 1, ',', '.') }}%</span>
                            @else
                                <span class="badge-safe font-mono">{{ number_format($item['burn_index_pct'], 1, ',', '.') }}%</span>
                            @endif
                        @else
                            <span style="color: #64748b;">-</span>
                        @endif
                    </td>
                    <td class="text-center" style="font-size: 7px;">
                        @if($item['burn_zone'] === 'ZONE_1_EXCELLENT')
                            <span class="status-safe font-bold">Zona 1 (Aman)</span>
                        @elseif($item['burn_zone'] === 'ZONE_2_GOOD')
                            <span class="status-on-track font-bold">Zona 2 (Baik)</span>
                        @elseif($item['burn_zone'] === 'ZONE_3_WARNING')
                            <span class="status-warning font-bold">Zona 3 (Peringatan)</span>
                        @else
                            <span class="status-danger font-bold">Zona 4 (Defisit)</span>
                        @endif
                    </td>
                    <td class="text-right font-mono">{{ number_format($item['cumulative_capex_hours'], 1, ',', '.') }}</td>
                    <td class="text-right font-mono">{{ number_format($item['cumulative_opex_hours'], 1, ',', '.') }}</td>
                    <td class="text-right font-mono">{{ number_format($item['burn_velocity'], 1, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 12px; color: #64748b;">
                        Tidak ada data snapshot seksi pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- CapEx Projects Performance Table (E05-03 Capitalization Tracking) -->
    @if(!empty($capex_opex['projects']))
        <div class="section-heading" style="margin-top: 12px;">2. Kinerja & Deviasi Proyek Kapitalisasi (CapEx Labor Asset)</div>
        <table class="data-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 20%;">Kode & Aset Proyek</th>
                    <th style="width: 25%;">Nama Proyek</th>
                    <th class="text-right" style="width: 10%;">Alokasi (j)</th>
                    <th class="text-right" style="width: 10%;">Periode Ini</th>
                    <th class="text-right" style="width: 10%;">Akumulasi</th>
                    <th class="text-right" style="width: 10%;">Deviasi (j)</th>
                    <th class="text-center" style="width: 8%;">Progres</th>
                    <th class="text-center" style="width: 7%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($capex_opex['projects'] as $p)
                    <tr>
                        <td>
                            <span class="capex-pill">{{ $p['project_code'] }}</span>
                            @if(!empty($p['asset_code']))
                                <div style="font-size: 6.5px; color: #64748b;">{{ $p['asset_code'] }}</div>
                            @endif
                        </td>
                        <td style="font-weight: 600; color: #0f172a;">{{ $p['name'] }}</td>
                        <td class="text-right font-mono">{{ number_format($p['allocated_labor_hours'], 1, ',', '.') }}</td>
                        <td class="text-right font-mono">{{ number_format($p['period_logged_hours'], 1, ',', '.') }}</td>
                        <td class="text-right font-mono" style="font-weight: bold;">{{ number_format($p['cumulative_logged_hours'], 1, ',', '.') }}</td>
                        <td class="text-right font-mono {{ $p['variance_hours'] > 0 ? 'status-danger font-bold' : 'status-safe' }}">
                            {{ $p['variance_hours'] > 0 ? '+' : '' }}{{ number_format($p['variance_hours'], 1, ',', '.') }}
                        </td>
                        <td class="text-center font-mono">{{ number_format($p['physical_progress_pct'], 0) }}%</td>
                        <td class="text-center" style="font-size: 7px; font-weight: bold;">{{ $p['status'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- Cross-Department Summary if plant-wide / Admin view -->
    @if(count($departments_summary) > 1)
        <div class="section-heading" style="margin-top: 12px;">3. Konsolidasi Antar-Departemen (Lintas Pabrik)</div>
        <table class="data-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 30%;">Departemen</th>
                    <th class="text-right" style="width: 14%;">Alokasi Jam</th>
                    <th class="text-right" style="width: 14%;">Realisasi Jam</th>
                    <th class="text-right" style="width: 14%;">Sisa Kuota</th>
                    <th class="text-center" style="width: 14%;">Indeks Burn</th>
                    <th class="text-center" style="width: 14%;">Zona Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($departments_summary as $d)
                    <tr>
                        <td>
                            <strong style="color: #0f172a;">{{ $d['name'] }}</strong>
                            <span class="code-pill">{{ $d['code'] }}</span>
                        </td>
                        <td class="text-right font-mono">{{ number_format($d['total_planned_hours'], 1, ',', '.') }}</td>
                        <td class="text-right font-mono font-bold">{{ number_format($d['total_actual_hours'], 1, ',', '.') }}</td>
                        <td class="text-right font-mono {{ $d['total_remaining_hours'] < 0 ? 'status-danger font-bold' : '' }}">
                            {{ number_format($d['total_remaining_hours'], 1, ',', '.') }}
                        </td>
                        <td class="text-center font-mono font-bold {{ $d['burn_index_pct'] > 115 ? 'status-danger' : ($d['burn_index_pct'] > 100 ? 'status-warning' : ($d['burn_index_pct'] >= 85 ? 'status-on-track' : 'status-safe')) }}">
                            {{ number_format($d['burn_index_pct'], 1, ',', '.') }}%
                        </td>
                        <td class="text-center font-bold" style="font-size: 7px;">
                            {{ str_replace('_', ' ', $d['department_burn_zone']) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- Verification & Signatures Block -->
    <table class="sign-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="sign-cell">
                <div class="sign-title">Dibuat Oleh (Finance/Admin)</div>
                <div class="sign-line">{{ $printed_by }}</div>
                <div class="sign-role">Controller Administrator</div>
            </td>
            <td class="sign-cell">
                <div class="sign-title">Ditinjau (Kepala Departemen)</div>
                <div class="sign-line">...................................................</div>
                <div class="sign-role">Department Manager</div>
            </td>
            <td class="sign-cell">
                <div class="sign-title">Disetujui (Direktur Pabrik)</div>
                <div class="sign-line">...................................................</div>
                <div class="sign-role">Plant General Manager</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        <table style="width: 100%;">
            <tr>
                <td>Laporan Resmi Penutupan Finansial Lembur Pabrik — PT ISUZU ASTRA MOTOR INDONESIA</td>
                <td style="text-align: right;">Dokumen Audit Terkendali</td>
            </tr>
        </table>
    </div>

</body>
</html>
