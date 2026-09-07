---
name: isuzu-frontend-development
description: Build and style UI pages, high-density timesheets, forms, and dialogs following ISUZU clean industrial design standards (ISUZU Red, tabular figures, CapEx/OpEx segregation, and 3-click ergonomics). Use when developing frontend Vue 3 components, Inertia pages, or styling factory floor layouts.
---

# ISUZU Frontend Development Skill

This skill guides the construction of manufacturing-grade UI pages, components, and forms for the ISUZU OT-CapEx system using Vue 3, Inertia v3, Tailwind CSS v4, Reka UI, and Wayfinder.

---

## 1. Core Visual Tokens & Tailwind Classes

| Element              | Specification                   | Tailwind v4 Utility Class                                                                          |
| :------------------- | :------------------------------ | :------------------------------------------------------------------------------------------------- |
| **Brand Primary**    | ISUZU Red `#cc0000`             | `bg-[#cc0000] text-white hover:bg-[#b30000]`                                                       |
| **Subtle Red**       | Tint for badges / active states | `bg-red-50 text-[#cc0000] border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900` |
| **CapEx Project**    | Fixed Asset Capitalization      | `bg-sky-100 text-sky-800 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800`  |
| **OpEx Routine**     | Operational Line Overtime       | `bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300`                                |
| **Safe Burn Index**  | BBI < 0.85                      | `bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300`   |
| **Caution Burn**     | 0.85 ≤ BBI ≤ 1.00               | `bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300`             |
| **Critical Deficit** | BBI > 1.00                      | `bg-red-50 text-[#cc0000] border-red-200 dark:bg-red-950/40 dark:text-red-300`                     |
| **Audit Figures**    | NIK, Hours, Rupiah              | `font-mono tabular-nums`                                                                           |

---

## 2. Step-by-Step Implementation Workflow

When asked to build a new page, modal, or timesheet component:

### Step 1: Verify Single Root & Wayfinder Routing

- Ensure the template has exactly **one** root HTML tag (`<div>` or `<section>`).
- Use Wayfinder route generation: `import { index, store } from '@/actions/...'` or `import { home } from '@/routes'`. Never use legacy Ziggy `route()`.

### Step 2: Structure Data Display with High-Density Layout

- Factory supervisors review 30–50 operators simultaneously. Avoid bloated padding.
- Use `px-3 py-2.5` table rows with crisp 1px borders (`border-slate-200 dark:border-slate-800`).
- Ensure all numbers (NIK, duration, currency) use `font-mono tabular-nums`.

### Step 3: Implement 3-Click Shift Ergonomics

- For daily entry: Operator quick-select &rarr; Hours stepper (`-0.5 / +0.5`) &rarr; Submit.
- Support batch actions: checkbox selector on table header + "Setujui Terpilih (Batch)" action button.

### Step 4: Apply Non-Blocking SPKL Indicators

- If physical paper SPKL is pending, display the 48-hour grace period badge (`Menunggu Fisik SPKL`) rather than blocking the overtime approval workflow.

### Step 5: Internationalization (i18n)

- Wrap all labels, titles, and tooltips in `__()` from `useTrans()`.
- Add new strings to both `lang/id.json` and `lang/en.json`.

---

## 3. Reusable Component Blueprints

### Blueprint A: High-Density Manufacturing Timesheet Table

```vue
<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const { __ } = useTrans();

interface OvertimeEntry {
    id: number;
    nik: string;
    employee_name: string;
    section: string;
    hours: number;
    is_capex: boolean;
    capex_code?: string;
    spkl_status:
        'draft' | 'pending_physical' | 'verified' | 'approved' | 'rejected';
}

defineProps<{ entries: OvertimeEntry[] }>();
</script>

<template>
    <div
        class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden shadow-xs"
    >
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr
                        class="bg-slate-50 dark:bg-slate-850 border-b border-slate-200 dark:border-slate-800 text-slate-500 font-semibold uppercase tracking-wider text-[11px]"
                    >
                        <th class="p-3 w-10 text-center">
                            <input
                                type="checkbox"
                                class="rounded border-slate-300 text-[#cc0000] focus:ring-[#cc0000]"
                            />
                        </th>
                        <th class="p-3">{{ __('NIK & Operator') }}</th>
                        <th class="p-3">{{ __('Seksi / Pos') }}</th>
                        <th class="p-3 text-right">{{ __('Jam Lembur') }}</th>
                        <th class="p-3">{{ __('Kategori Beban') }}</th>
                        <th class="p-3">{{ __('Status SPKL') }}</th>
                        <th class="p-3 text-right">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr
                        v-for="item in entries"
                        :key="item.id"
                        class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50 transition-colors"
                    >
                        <td class="p-3 text-center">
                            <input
                                type="checkbox"
                                class="rounded border-slate-300 text-[#cc0000]"
                            />
                        </td>
                        <td class="p-3">
                            <div
                                class="font-bold text-slate-900 dark:text-white"
                            >
                                {{ item.employee_name }}
                            </div>
                            <div class="text-[10px] font-mono text-slate-400">
                                {{ item.nik }}
                            </div>
                        </td>
                        <td class="p-3 text-slate-600 dark:text-slate-300">
                            {{ item.section }}
                        </td>
                        <td
                            class="p-3 text-right font-mono tabular-nums font-bold text-slate-900 dark:text-white"
                        >
                            {{ item.hours.toFixed(1) }} {{ __('Jam') }}
                        </td>
                        <td class="p-3">
                            <span
                                v-if="item.is_capex"
                                class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300"
                            >
                                CapEx · {{ item.capex_code }}
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                OpEx · {{ __('Rutin') }}
                            </span>
                        </td>
                        <td class="p-3">
                            <span
                                v-if="item.spkl_status === 'pending_physical'"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-900"
                            >
                                <span
                                    class="size-1 rounded-full bg-amber-500 animate-pulse"
                                ></span>
                                {{ __('Menunggu Fisik SPKL') }}
                            </span>
                            <span
                                v-else-if="item.spkl_status === 'approved'"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900"
                            >
                                <span
                                    class="size-1 rounded-full bg-emerald-500"
                                ></span>
                                {{ __('Disetujui SPV') }}
                            </span>
                        </td>
                        <td class="p-3 text-right">
                            <button
                                class="text-[#cc0000] dark:text-red-400 hover:underline font-semibold cursor-pointer"
                            >
                                {{ __('Rincian') }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
```

### Blueprint B: Overtime Stepper & CapEx Switcher Form

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';

const { __ } = useTrans();
const hours = ref(2.5);
const isCapex = ref(false);
const capexCode = ref('');

function stepHours(delta: number) {
    hours.value = Math.max(0.5, Math.min(8.0, hours.value + delta));
}
</script>

<template>
    <div
        class="space-y-4 p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900"
    >
        <!-- Duration Stepper -->
        <div class="space-y-1.5">
            <label
                class="text-xs font-medium text-slate-700 dark:text-slate-300"
                >{{ __('Durasi Jam Lembur') }}</label
            >
            <div class="flex items-center gap-1 w-48">
                <button
                    type="button"
                    @click="stepHours(-0.5)"
                    class="size-9 rounded-md border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 font-bold cursor-pointer"
                >
                    -
                </button>
                <div
                    class="flex-1 h-9 text-center font-mono tabular-nums text-sm font-bold flex items-center justify-center rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800"
                >
                    {{ hours.toFixed(1) }} {{ __('Jam') }}
                </div>
                <button
                    type="button"
                    @click="stepHours(0.5)"
                    class="size-9 rounded-md border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 font-bold cursor-pointer"
                >
                    +
                </button>
            </div>
        </div>

        <!-- CapEx Allocation Switch -->
        <div
            class="p-3 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-850 flex items-center justify-between"
        >
            <div>
                <div class="text-xs font-bold text-slate-900 dark:text-white">
                    {{ __('Alokasi Tenaga Kerja CapEx') }}
                </div>
                <p class="text-[11px] text-slate-500">
                    {{ __('Kapitalisasi lembur ke nomor aktiva tetap (CIP).') }}
                </p>
            </div>
            <input
                type="checkbox"
                v-model="isCapex"
                class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 size-4"
            />
        </div>

        <!-- CapEx Project Field (Progressive Disclosure) -->
        <div
            v-if="isCapex"
            class="p-3 rounded-lg border border-sky-200 dark:border-sky-900/50 bg-sky-50/40 dark:bg-sky-950/20 space-y-1"
        >
            <label class="text-xs font-bold text-sky-900 dark:text-sky-300">{{
                __('Nomor Proyek Aktiva Tetap (CIP)')
            }}</label>
            <input
                type="text"
                v-model="capexCode"
                placeholder="CIP-2026-ELF-WELD-042"
                class="w-full h-9 px-3 rounded-md border border-sky-300 dark:border-sky-800 bg-white dark:bg-slate-800 text-xs font-mono"
            />
        </div>

        <Button
            class="w-full bg-[#cc0000] text-white hover:bg-[#b30000] active:scale-95 transition-all"
        >
            {{ __('Ajukan Lembur') }}
        </Button>
    </div>
</template>
```

---

## 4. Verification & Quality Checklist

Before finalizing any frontend Vue change:

1. [ ] **Single Root**: Component template has exactly one root element.
2. [ ] **Wayfinder Only**: No `route()` from Ziggy used; only typed Wayfinder functions.
3. [ ] **Tabular Audit Columns**: Employee NIK, overtime hours, and currency amounts have `font-mono tabular-nums`.
4. [ ] **ISUZU Brand Standards**: Primary buttons and main accents use `#cc0000`. CapEx badges use `#0284c7` (sky blue).
5. [ ] **i18n Translations**: All displayed text uses `__()` with keys updated in `lang/id.json` and `lang/en.json`.
6. [ ] **Quality Checks**: Run `pnpm lint` and `pnpm build` to verify clean compilation.
