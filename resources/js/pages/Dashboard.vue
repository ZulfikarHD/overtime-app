<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    Layers,
    Shield,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import RoleBadge from '@/components/RoleBadge.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useShiftInfo } from '@/composables/useShiftInfo';
import { useTrans } from '@/composables/useTrans';
import { dashboard } from '@/routes';
import type { User, UserRole } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const { __ } = useTrans();
const { timeString, dateString, currentShift } = useShiftInfo();
const page = usePage();
const user = computed(() => page.props.auth?.user as User | undefined);

const roleCapabilities = computed(() => {
    const role: UserRole = user.value?.role ?? 'user';

    switch (role) {
        case 'admin':
            return [
                {
                    title: 'User Management',
                    desc: 'Create, update, and manage plant accounts across all roles.',
                    allowed: true,
                },
                {
                    title: 'Overtime Submission',
                    desc: 'Plant-wide emergency and master batch overtime entry.',
                    allowed: true,
                },
                {
                    title: 'Overtime Approval',
                    desc: 'Approve or reject any timesheet line item plant-wide.',
                    allowed: true,
                },
                {
                    title: 'Department & Section Scoping',
                    desc: 'Access all production lines and cost centers.',
                    allowed: true,
                },
                {
                    title: 'ML Predictive Analytics',
                    desc: 'Monitor ML models, burn trajectories, and anomaly alerts.',
                    allowed: true,
                },
                {
                    title: 'Policy Configuration',
                    desc: 'Set monthly thresholds and SPKL compliance grace periods.',
                    allowed: true,
                },
            ];
        case 'manager':
            return [
                {
                    title: 'Department Overtime Approval',
                    desc: 'Review, partial-approve, and reject section timesheets.',
                    allowed: true,
                },
                {
                    title: 'Department Scope',
                    desc: `Supervise all sections within ${user.value?.department?.name ?? 'your department'}.`,
                    allowed: true,
                },
                {
                    title: 'ML & Budget Tracking',
                    desc: 'Track departmental burn rates and ML cost predictions.',
                    allowed: true,
                },
                {
                    title: 'Personal Report',
                    desc: 'Inspect individual hours, overtime logs, and historical summary.',
                    allowed: true,
                },
            ];
        case 'team_leader':
            return [
                {
                    title: 'Daily Overtime Submission',
                    desc: `Log daily shift overtime for ${user.value?.section?.name ?? 'your assigned section'}.`,
                    allowed: true,
                },
                {
                    title: 'SPKL Document Attachment',
                    desc: 'Upload physical SPKL sign-offs or reference codes post-shift.',
                    allowed: true,
                },
                {
                    title: 'Section Roster Management',
                    desc: 'Select active shopfloor crew members from section roster.',
                    allowed: true,
                },
                {
                    title: 'Personal Report',
                    desc: 'Inspect individual hours, overtime logs, and historical summary.',
                    allowed: true,
                },
            ];
        case 'user':
        default:
            return [
                {
                    title: 'Personal Report',
                    desc: 'View individual overtime records and verified hours.',
                    allowed: true,
                },
                {
                    title: 'Shift Verification',
                    desc: 'Confirm scheduled shift and active operational line.',
                    allowed: true,
                },
            ];
    }
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <Head :title="__('Operational Dashboard')" />

        <!-- Operational Welcome Banner -->
        <div class="border-border/70 bg-card rounded-xl border p-6 shadow-xs">
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1
                        class="text-foreground text-2xl font-bold tracking-tight sm:text-3xl"
                        data-test="welcome-heading"
                    >
                        {{
                            __('Welcome back, :name!', {
                                name: user?.name ?? 'Operator',
                            })
                        }}
                    </h1>
                    <p
                        class="text-muted-foreground mt-1 flex items-center gap-2 text-sm"
                    >
                        <Calendar class="size-4 shrink-0" />
                        <span>{{ dateString }}</span>
                        <span>•</span>
                        <Clock class="size-4 shrink-0" />
                        <span>{{ timeString }} WIB</span>
                    </p>
                </div>

                <!-- Shift Indicator Pill -->
                <div class="flex items-center gap-3">
                    <div
                        class="inline-flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-3.5 py-2 text-sm font-semibold text-emerald-800 dark:text-emerald-300"
                    >
                        <span
                            class="size-2 animate-pulse rounded-full bg-emerald-500"
                        />
                        <span>{{ currentShift.badgeText }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assignment & Identity Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Assignment Card -->
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription
                        class="flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase"
                    >
                        <Building2 class="text-primary size-4" />
                        {{ __('Assignment Overview') }}
                    </CardDescription>
                    <CardTitle class="text-lg font-bold">
                        {{ user?.department?.name ?? __('Not Assigned') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-1.5 pt-1 text-sm">
                    <div
                        class="text-muted-foreground flex items-center justify-between"
                    >
                        <span class="flex items-center gap-1">
                            <Layers class="size-3.5" />
                            {{ __('Section') }}:
                        </span>
                        <span class="text-foreground font-medium">
                            {{ user?.section?.name ?? __('Not Assigned') }}
                        </span>
                    </div>
                    <div
                        class="text-muted-foreground flex items-center justify-between"
                    >
                        <span class="flex items-center gap-1">
                            <Users class="size-3.5" />
                            {{ __('NPK') }}:
                        </span>
                        <span class="text-foreground font-mono font-medium">
                            {{ user?.npk ?? '-' }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Role & Permission Status -->
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription
                        class="flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase"
                    >
                        <Shield class="text-primary size-4" />
                        {{ __('Role') }}
                    </CardDescription>
                    <CardTitle
                        class="flex items-center gap-2 text-lg font-bold"
                        data-test="user-role-card"
                    >
                        <RoleBadge
                            v-if="user?.role"
                            :role="user.role"
                            size="md"
                        />
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-1.5 pt-1 text-sm">
                    <div
                        class="text-muted-foreground flex items-center justify-between"
                    >
                        <span>{{ __('Account Status') }}:</span>
                        <span
                            class="inline-flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400"
                        >
                            <CheckCircle2 class="size-3.5" />
                            {{ user?.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div
                        class="text-muted-foreground flex items-center justify-between"
                    >
                        <span>{{ __('Timezone') }}:</span>
                        <span class="text-foreground font-mono"
                            >Asia/Jakarta (WIB)</span
                        >
                    </div>
                </CardContent>
            </Card>

            <!-- Operational Readiness -->
            <Card class="sm:col-span-2 lg:col-span-1">
                <CardHeader class="pb-2">
                    <CardDescription
                        class="flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase"
                    >
                        <Activity class="text-primary size-4" />
                        {{ __('Active Shift') }}
                    </CardDescription>
                    <CardTitle class="text-lg font-bold">
                        {{ currentShift.name }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-1.5 pt-1 text-sm">
                    <div
                        class="text-muted-foreground flex items-center justify-between"
                    >
                        <span>{{ __('Shift Hours') }}:</span>
                        <span class="text-foreground font-medium">{{
                            currentShift.hours
                        }}</span>
                    </div>
                    <div
                        class="text-muted-foreground flex items-center justify-between"
                    >
                        <span>{{ __('Next Handover') }}:</span>
                        <span class="text-foreground font-mono">
                            {{
                                currentShift.shiftNumber === 1
                                    ? '15:00 WIB'
                                    : currentShift.shiftNumber === 2
                                      ? '23:00 WIB'
                                      : '07:00 WIB'
                            }}
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Sprint 1 System Readiness Notice -->
        <div
            class="flex items-start gap-3 rounded-xl border border-blue-500/20 bg-blue-500/5 p-4 text-sm text-blue-900 sm:p-5 dark:text-blue-200"
        >
            <AlertCircle
                class="mt-0.5 size-5 shrink-0 text-blue-600 dark:text-blue-400"
            />
            <div class="space-y-1">
                <h3 class="text-foreground font-semibold">
                    {{
                        __(
                            'System Infrastructure Initialized — Sprint 1 Active. Overtime entry and SPKL workflows will activate in upcoming releases.',
                        )
                    }}
                </h3>
                <p class="text-muted-foreground text-xs leading-relaxed">
                    Authentication, database relations, and role-based scoping
                    have been fully established according to Epic E01
                    specifications. The system is operating in Asia/Jakarta
                    timezone.
                </p>
            </div>
        </div>

        <!-- Role Capability Matrix for current user -->
        <Card>
            <CardHeader>
                <CardTitle
                    class="flex items-center gap-2 text-base font-semibold"
                >
                    <FileSpreadsheet class="text-primary size-4" />
                    {{ __('Role Capabilities') }}
                </CardTitle>
                <CardDescription>
                    Authorized operational actions assigned to your account in
                    this manufacturing plant.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="cap in roleCapabilities"
                        :key="cap.title"
                        class="border-border/50 bg-background/50 flex items-start gap-2.5 rounded-lg border p-3"
                    >
                        <CheckCircle2
                            class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                        />
                        <div class="space-y-0.5">
                            <h4 class="text-foreground text-sm font-medium">
                                {{ cap.title }}
                            </h4>
                            <p
                                class="text-muted-foreground text-xs leading-relaxed"
                            >
                                {{ cap.desc }}
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
