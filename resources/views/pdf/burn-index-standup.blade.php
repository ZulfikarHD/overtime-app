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
            font-size: 10px;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }

        /* ISUZU Branding Header */
        .header-table {
            width: 100%;
            border-bottom: 2px solid #cc0000;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .header-brand {
            font-size: 15px;
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
            margin-top: 1px;
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
            margin-top: 1px;
        }

        /* Metadata Grid */
        .meta-table {
            width: 100%;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            margin-bottom: 12px;
            padding: 6px 10px;
        }

        .meta-label {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }

        .meta-value {
            font-size: 10px;
            color: #0f172a;
            font-weight: bold;
        }

        /* KPI Macro Summary Blocks */
        .kpi-table {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: separate;
            border-spacing: 6px 0;
        }

        .kpi-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
            width: 25%;
        }

        .kpi-title {
            font-size: 7.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .kpi-main-val {
            font-size: 14px;
            font-weight: bold;
            font-family: 'Courier New', Courier, monospace;
            color: #0f172a;
        }

        .kpi-sub-val {
            font-size: 8px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Status Colors */
        .status-safe { color: #059669; }
        .status-on-track { color: #0284c7; }
        .status-warning { color: #d97706; }
        .status-danger { color: #cc0000; }

        .badge-safe { background-color: #d1fae5; color: #065f46; padding: 1px 5px; border-radius: 3px; font-weight: bold; font-size: 8px; }
        .badge-on-track { background-color: #e0f2fe; color: #0369a1; padding: 1px 5px; border-radius: 3px; font-weight: bold; font-size: 8px; }
        .badge-warning { background-color: #fef3c7; color: #92400e; padding: 1px 5px; border-radius: 3px; font-weight: bold; font-size: 8px; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; padding: 1px 5px; border-radius: 3px; font-weight: bold; font-size: 8px; }
        .badge-neutral { background-color: #f1f5f9; color: #475569; padding: 1px 5px; border-radius: 3px; font-size: 8px; }

        /* Alert Callout for Standup */
        .alert-box {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 6px 10px;
            margin-bottom: 12px;
            font-size: 8.5px;
            color: #92400e;
        }

        .alert-box-danger {
            background-color: #fef2f2;
            border-left: 4px solid #cc0000;
            padding: 6px 10px;
            margin-bottom: 12px;
            font-size: 8.5px;
            color: #991b1b;
        }

        /* Main Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            margin-bottom: 14px;
        }

        .data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 7.5px;
            letter-spacing: 0.3px;
            padding: 5px 6px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #94a3b8;
            text-align: left;
        }

        .data-table th.text-right { text-align: right; }
        .data-table th.text-center { text-align: center; }

        .data-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .data-table td.text-right { text-align: right; }
        .data-table td.text-center { text-align: center; }

        .data-table tr.row-warning { background-color: #fffbeb; }
        .data-table tr.row-danger { background-color: #fef2f2; }

        .data-table tr:nth-child(even):not(.row-warning):not(.row-danger) {
            background-color: #f8fafc;
        }

        .font-mono {
            font-family: 'Courier New', Courier, monospace;
        }

        .code-pill {
            font-family: 'Courier New', Courier, monospace;
            background-color: #e2e8f0;
            color: #334155;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 7.5px;
        }

        /* Verification & Signature Section */
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
            font-size: 8px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 35px;
        }

        .sign-line {
            border-top: 1px solid #64748b;
            padding-top: 3px;
            font-size: 8.5px;
            font-weight: bold;
            color: #0f172a;
        }

        .sign-role {
            font-size: 7.5px;
            color: #64748b;
        }

        /* Footer */
        .footer {
            margin-top: 15px;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
            font-size: 7.5px;
            color: #94a3b8;
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
                <div class="header-subtitle">Standup Briefing Executive Digest • {{ $generated_at }}</div>
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
                <div class="kpi-main-val">{{ number_format($summary['total_planned_hours'], 1, ',', '.') }} <span style="font-size: 9px; font-family: sans-serif; font-weight: normal; color: #64748b;">jam</span></div>
                <div class="kpi-sub-val">Target kuota disetujui</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-title">Realisasi Jam Lembur</div>
                <div class="kpi-main-val">{{ number_format($summary['total_actual_hours'], 1, ',', '.') }} <span style="font-size: 9px; font-family: sans-serif; font-weight: normal; color: #64748b;">jam</span></div>
                <div class="kpi-sub-val">Sisa kuota: <strong class="font-mono {{ $summary['total_remaining_hours'] < 0 ? 'status-danger' : 'status-safe' }}">{{ number_format($summary['total_remaining_hours'], 1, ',', '.') }} jam</strong></div>
            </td>
            <td class="kpi-card">
                <div class="kpi-title">Indeks Burn Departemen</div>
                <div class="kpi-main-val {{ $summary['department_burn_index_pct'] > 115 ? 'status-danger' : ($summary['department_burn_index_pct'] > 100 ? 'status-warning' : ($summary['department_burn_index_pct'] >= 85 ? 'status-on-track' : 'status-safe')) }}">
                    {{ number_format($summary['department_burn_index_pct'], 1, ',', '.') }}%
                </div>
                <div class="kpi-sub-val">
                    Zona: <strong>{{ str_replace('_', ' ', $summary['department_burn_zone']) }}</strong>
                </div>
            </td>
            <td class="kpi-card">
                <div class="kpi-title">Status Risiko Seksi</div>
                <div style="font-size: 11px; font-weight: bold; margin-top: 2px;">
                    <span class="status-danger font-mono">{{ $summary['danger_sections_count'] }}</span> Defisit • 
                    <span class="status-warning font-mono">{{ $summary['warning_sections_count'] }}</span> Peringatan • 
                    <span class="status-safe font-mono">{{ max(0, $summary['total_sections_count'] - $summary['warning_sections_count'] - $summary['danger_sections_count']) }}</span> Aman
                </div>
                <div class="kpi-sub-val">{{ $summary['configured_sections_count'] }} dari {{ $summary['total_sections_count'] }} seksi aktif</div>
            </td>
        </tr>
    </table>

    <!-- High-Risk Intervention Alert Callout if any warning or danger sections -->
    @if(count($high_risk_sections) > 0)
        <div class="{{ $summary['danger_sections_count'] > 0 ? 'alert-box-danger' : 'alert-box' }}">
            <strong>PERHATIAN STANDUP PAGI:</strong> Ditemukan {{ count($high_risk_sections) }} seksi berstatus risiko tinggi (Burn Index &gt; 100%).
            Harap evaluasi alokasi shift dan tindakan preventif pada seksi: 
            <strong>
                @foreach($high_risk_sections as $hrs)
                    {{ $hrs['section_name'] }} ({{ number_format($hrs['burn_index_pct'], 1, ',', '.') }}%){{ ! $loop->last ? ', ' : '' }}
                @endforeach
            </strong>.
        </div>
    @endif

    <!-- Ranked Sections Table -->
    <table class="data-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th class="text-center" style="width: 4%;">#</th>
                <th style="width: 24%;">Seksi / Departemen</th>
                <th class="text-right" style="width: 10%;">Rencana</th>
                <th class="text-right" style="width: 10%;">Realisasi</th>
                <th class="text-right" style="width: 10%;">Sisa</th>
                <th class="text-center" style="width: 11%;">Indeks Burn</th>
                <th class="text-center" style="width: 11%;">Zona</th>
                <th class="text-right" style="width: 10%;">Kecepatan</th>
                <th class="text-right" style="width: 10%;">Proyeksi</th>
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
                        <div style="font-weight: bold; color: #0f172a;">{{ $item['section_name'] }}</div>
                        <span class="code-pill">{{ $item['section_code'] }}</span>
                        @if(!empty($item['department_code']))
                            <span style="font-size: 7px; color: #64748b;">• {{ $item['department_code'] }}</span>
                        @endif
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
                            <span class="badge-neutral">Belum Diatur</span>
                        @endif
                    </td>
                    <td class="text-center" style="font-size: 7.5px;">
                        @if($item['burn_zone'] === 'ZONE_1_EXCELLENT')
                            <span class="status-safe font-bold">Zona 1 (Aman)</span>
                        @elseif($item['burn_zone'] === 'ZONE_2_GOOD')
                            <span class="status-on-track font-bold">Zona 2 (Baik)</span>
                        @elseif($item['burn_zone'] === 'ZONE_3_WARNING')
                            <span class="status-warning font-bold">Zona 3 (Waspada)</span>
                        @else
                            <span class="status-danger font-bold">Zona 4 (Kritis)</span>
                        @endif
                    </td>
                    <td class="text-right font-mono">
                        {{ number_format($item['burn_velocity'], 1, ',', '.') }} <span style="font-size: 7px; color: #94a3b8;">j/mg</span>
                    </td>
                    <td class="text-right font-mono">
                        {{ number_format($item['projected_total_hours'], 1, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 18px; color: #64748b;">
                        Tidak ada data snapshot seksi pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Verification & Signature Block -->
    <table class="sign-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="sign-cell">
                <div class="sign-title">Disiapkan Oleh (Standup Lead)</div>
                <div class="sign-line">{{ $printed_by }}</div>
                <div class="sign-role">Section Head / Team Leader</div>
            </td>
            <td class="sign-cell">
                <div class="sign-title">Ditinjau & Disetujui</div>
                <div class="sign-line">...................................................</div>
                <div class="sign-role">Kepala Departemen (Manager)</div>
            </td>
            <td class="sign-cell">
                <div class="sign-title">Pengendali Biaya & Anggaran</div>
                <div class="sign-line">...................................................</div>
                <div class="sign-role">Finance & Plant Controller</div>
            </td>
        </tr>
    </table>

    <!-- Confidential Footer -->
    <div class="footer">
        <table style="width: 100%;">
            <tr>
                <td>Laporan Resmi Manajemen Lembur & CapEx Pabrik — Dokumen Terkendali ISUZU Standar ISO 9001/14001</td>
                <td style="text-align: right;">Halaman 1 dari 1</td>
            </tr>
        </table>
    </div>

</body>
</html>
