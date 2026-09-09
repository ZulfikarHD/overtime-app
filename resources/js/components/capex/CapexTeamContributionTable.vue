<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ExternalLink, Users } from '@lucide/vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';
import { show as showEmployeeDossier } from '@/routes/reports/employees';

export interface ContributorItem {
    employee_id: number;
    npk: string;
    name: string;
    section: string;
    hours: number;
    cost_idr: number;
    percentage: number;
}

defineProps<{
    contributors: ContributorItem[];
    totalConsumedHours: number;
}>();

const { __ } = useTrans();
</script>

<template>
    <Card
        class="border-border bg-card"
        data-test="capex-team-contribution-card"
    >
        <CardHeader class="border-border border-b pb-3">
            <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                    <CardTitle
                        class="text-foreground flex items-center gap-2 text-sm font-bold"
                    >
                        <Users class="size-4 text-sky-600" />
                        {{ __('Roster Kontribusi Tenaga Kerja Proyek') }}
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{
                            __(
                                'Daftar teknisi dan operator yang berkontribusi jam lembur pada proyek ini, diurutkan berdasarkan jam tertinggi.',
                            )
                        }}
                    </CardDescription>
                </div>

                <div
                    class="text-muted-foreground font-mono text-xs font-semibold tabular-nums"
                >
                    {{ contributors.length }} {{ __('Teknisi') }}
                </div>
            </div>
        </CardHeader>

        <CardContent class="p-0">
            <div
                v-if="contributors.length === 0"
                class="text-muted-foreground p-8 text-center text-xs"
                data-test="empty-contributors"
            >
                <Users class="text-muted-foreground/40 mx-auto mb-2 size-8" />
                <p class="font-medium">
                    {{
                        __(
                            'Belum ada kontributor tenaga kerja tercatat untuk proyek ini.',
                        )
                    }}
                </p>
                <p class="mt-0.5 text-[11px]">
                    {{
                        __(
                            'Jam lembur yang disetujui akan otomatis mengatribusikan teknisi ke daftar ini.',
                        )
                    }}
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table
                    class="w-full border-collapse text-left text-xs"
                    data-test="table-contributors"
                >
                    <thead>
                        <tr
                            class="bg-muted/40 border-border text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase"
                        >
                            <th class="w-12 py-2.5 pr-2 pl-4 text-center">
                                {{ __('#') }}
                            </th>
                            <th class="px-3 py-2.5">{{ __('NPK') }}</th>
                            <th class="px-3 py-2.5">
                                {{ __('Nama Karyawan') }}
                            </th>
                            <th class="px-3 py-2.5">{{ __('Seksi / Pos') }}</th>
                            <th class="px-3 py-2.5 text-right">
                                {{ __('Jam Disetujui') }}
                            </th>
                            <th class="px-3 py-2.5 text-right">
                                {{ __('Biaya Snapshot') }}
                            </th>
                            <th class="py-2.5 pr-4 pl-3 text-right">
                                {{ __('% Kontribusi') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-border/60 divide-y">
                        <tr
                            v-for="(item, idx) in contributors"
                            :key="item.employee_id"
                            class="hover:bg-muted/50 transition-colors"
                            :data-test="`contributor-row-${item.employee_id}`"
                        >
                            <td
                                class="text-muted-foreground py-2.5 pr-2 pl-4 text-center font-mono text-[11px] font-medium"
                            >
                                {{ idx + 1 }}
                            </td>
                            <td class="px-3 py-2.5">
                                <Link
                                    v-if="item.npk && item.npk !== '—'"
                                    :href="
                                        showEmployeeDossier.url({
                                            npk: item.npk,
                                        })
                                    "
                                    class="text-foreground inline-flex items-center gap-1 font-mono text-xs font-semibold underline-offset-2 hover:text-sky-600 hover:underline dark:hover:text-sky-400"
                                    data-test="link-contributor-dossier"
                                >
                                    {{ item.npk }}
                                    <ExternalLink class="size-2.5 opacity-60" />
                                </Link>
                                <span
                                    v-else
                                    class="text-muted-foreground font-mono text-xs"
                                >
                                    {{ item.npk }}
                                </span>
                            </td>
                            <td class="text-foreground px-3 py-2.5 font-medium">
                                {{ item.name }}
                            </td>
                            <td
                                class="text-muted-foreground px-3 py-2.5 text-[11px]"
                            >
                                {{ item.section }}
                            </td>
                            <td
                                class="text-foreground px-3 py-2.5 text-right font-mono text-xs font-bold tabular-nums"
                            >
                                {{
                                    item.hours.toLocaleString('id-ID', {
                                        minimumFractionDigits: 1,
                                        maximumFractionDigits: 1,
                                    })
                                }}
                                jam
                            </td>
                            <td
                                class="text-muted-foreground px-3 py-2.5 text-right font-mono text-xs tabular-nums"
                            >
                                {{ formatRupiah(item.cost_idr) }}
                            </td>
                            <td class="py-2.5 pr-4 pl-3 text-right">
                                <div
                                    class="inline-flex items-center gap-1.5 font-mono text-xs font-bold text-sky-700 tabular-nums dark:text-sky-300"
                                >
                                    <span
                                        >{{ item.percentage.toFixed(1) }}%</span
                                    >
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
