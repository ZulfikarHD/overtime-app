<script setup lang="ts">
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    ListTodo,
    ShieldAlert,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';

defineProps<{
    filters?: {
        department_id: string | number | null;
        start_date: string;
        end_date: string;
    };
}>();

const { __ } = useTrans();
</script>

<template>
    <div class="space-y-6" data-test="tab-insights-content">
        <!-- Risk Indicators Summary Banner (E09-11) -->
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader class="flex flex-row items-center justify-between pb-3">
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <ShieldAlert class="size-4 text-[#cc0000]" />
                        <span>{{
                            __('Indikator Risiko & Peringatan Otomatis')
                        }}</span>
                    </CardTitle>
                    <CardDescription>
                        {{
                            __(
                                'Sistem mengevaluasi deviasi anggaran, kelelahan karyawan, dan anomali lonjakan lembur secara otomatis.',
                            )
                        }}
                    </CardDescription>
                </div>
                <div class="flex items-center gap-2">
                    <Badge
                        variant="destructive"
                        class="border-red-200 bg-red-50 text-[#cc0000] dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                    >
                        1 {{ __('Kritis') }}
                    </Badge>
                    <Badge
                        variant="secondary"
                        class="border-amber-200 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                    >
                        2 {{ __('Peringatan') }}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div
                    class="flex h-48 flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center dark:border-slate-800 dark:bg-slate-950/30"
                >
                    <ListTodo
                        class="mb-2 size-8 text-slate-400 dark:text-slate-600"
                    />
                    <h4
                        class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                    >
                        {{ __('Daftar Tindakan Manajemen Terintegrasi') }}
                    </h4>
                    <p class="mt-1 text-xs text-slate-500">
                        {{
                            __(
                                'Deteksi anomali 30 hari, modal pembaruan status tindakan, dan ekspor action plan CSV disiapkan pada Story E09-11.',
                            )
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
