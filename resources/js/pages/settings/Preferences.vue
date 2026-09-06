<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Bell,
    Check,
    Clock,
    DollarSign,
    Globe2,
    Info,
    Monitor,
    Moon,
    ShieldAlert,
    Sun,
} from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useAppearance } from '@/composables/useAppearance';
import { useTrans } from '@/composables/useTrans';
import { edit, update } from '@/routes/preferences';
import type { Appearance } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Preferences',
                href: edit(),
            },
        ],
    },
});

type PreferencesData = {
    theme: Appearance;
    spkl_pending_reminder: boolean;
    budget_threshold_alert: boolean;
    approval_status_notification: boolean;
};

const props = defineProps<{
    preferences: PreferencesData;
}>();

const { __ } = useTrans();
const { updateAppearance } = useAppearance();

const form = useForm<PreferencesData>({
    theme: props.preferences?.theme ?? 'system',
    spkl_pending_reminder: props.preferences?.spkl_pending_reminder ?? true,
    budget_threshold_alert: props.preferences?.budget_threshold_alert ?? true,
    approval_status_notification:
        props.preferences?.approval_status_notification ?? true,
});

const themeOptions = [
    {
        id: 'light' as const,
        label: __('Light Mode'),
        description: __('Clean bright interface suitable for daytime work'),
        icon: Sun,
    },
    {
        id: 'dark' as const,
        label: __('Dark Mode'),
        description: __('High-contrast dark theme optimized for night shifts'),
        icon: Moon,
    },
    {
        id: 'system' as const,
        label: __('System Default'),
        description: __('Automatically adapt to operating system settings'),
        icon: Monitor,
    },
];

function selectTheme(theme: Appearance) {
    form.theme = theme;
    updateAppearance(theme);
}

function submit() {
    form.patch(update.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="__('Preferences')" />

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="__('User Preferences & Display Standards')"
            :description="
                __(
                    'Customize your visual theme, alert notifications, and view company display standards.',
                )
            "
        />

        <form
            @submit.prevent="submit"
            class="space-y-8"
            data-test="preferences-form"
        >
            <!-- Theme Selection Section -->
            <div class="space-y-3">
                <div>
                    <h3
                        class="text-foreground text-sm font-semibold tracking-tight"
                    >
                        {{ __('Theme Mode') }}
                    </h3>
                    <p class="text-muted-foreground text-xs">
                        {{
                            __(
                                'Choose how the overtime management interface looks on your device.',
                            )
                        }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <button
                        v-for="option in themeOptions"
                        :key="option.id"
                        type="button"
                        :data-test="`theme-option-${option.id}`"
                        @click="selectTheme(option.id)"
                        :class="[
                            'focus-visible:ring-ring relative flex cursor-pointer flex-col items-start rounded-lg border p-3.5 text-left transition-all outline-none focus-visible:ring-2',
                            form.theme === option.id
                                ? 'border-primary bg-primary/5 ring-primary shadow-xs ring-1'
                                : 'border-border bg-card hover:bg-muted/50 text-foreground',
                        ]"
                    >
                        <div
                            class="mb-2 flex w-full items-center justify-between"
                        >
                            <div
                                :class="[
                                    'rounded-md p-2',
                                    form.theme === option.id
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground',
                                ]"
                            >
                                <component :is="option.icon" class="size-4" />
                            </div>
                            <span
                                v-if="form.theme === option.id"
                                class="bg-primary text-primary-foreground flex size-4 items-center justify-center rounded-full"
                            >
                                <Check class="size-2.5 stroke-[3]" />
                            </span>
                        </div>
                        <span class="text-foreground text-sm font-medium">
                            {{ option.label }}
                        </span>
                        <span
                            class="text-muted-foreground mt-0.5 line-clamp-2 text-xs leading-relaxed"
                        >
                            {{ option.description }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Notification Channels Section -->
            <div class="space-y-3 pt-2">
                <div>
                    <h3
                        class="text-foreground flex items-center gap-2 text-sm font-semibold tracking-tight"
                    >
                        <Bell class="text-primary size-4" />
                        {{ __('Notification Channels & Alerts') }}
                    </h3>
                    <p class="text-muted-foreground text-xs">
                        {{
                            __(
                                'Configure operational alert notifications sent to your session and dashboard.',
                            )
                        }}
                    </p>
                </div>

                <div
                    class="border-border bg-card space-y-3 rounded-lg border p-4"
                >
                    <!-- SPKL Pending Reminder -->
                    <div class="flex items-start gap-3">
                        <Checkbox
                            id="pref-spkl-reminder"
                            data-test="checkbox-spkl-reminder"
                            :checked="form.spkl_pending_reminder"
                            @update:checked="
                                (val: boolean) =>
                                    (form.spkl_pending_reminder = val)
                            "
                            class="mt-0.5"
                        />
                        <div
                            class="grid cursor-pointer gap-0.5 leading-none"
                            @click="
                                form.spkl_pending_reminder =
                                    !form.spkl_pending_reminder
                            "
                        >
                            <Label
                                for="pref-spkl-reminder"
                                class="cursor-pointer text-sm font-medium"
                            >
                                {{ __('SPKL Document Pending Reminders') }}
                            </Label>
                            <p
                                class="text-muted-foreground text-xs leading-normal"
                            >
                                {{
                                    __(
                                        'Receive reminders when overtime submissions have pending physical SPKL documents nearing the grace period.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="border-border/60 border-t" />

                    <!-- Budget Threshold Alert -->
                    <div class="flex items-start gap-3">
                        <Checkbox
                            id="pref-budget-alert"
                            data-test="checkbox-budget-alert"
                            :checked="form.budget_threshold_alert"
                            @update:checked="
                                (val: boolean) =>
                                    (form.budget_threshold_alert = val)
                            "
                            class="mt-0.5"
                        />
                        <div
                            class="grid cursor-pointer gap-0.5 leading-none"
                            @click="
                                form.budget_threshold_alert =
                                    !form.budget_threshold_alert
                            "
                        >
                            <Label
                                for="pref-budget-alert"
                                class="cursor-pointer text-sm font-medium"
                            >
                                {{
                                    __('Overtime Budget Threshold & Burn Alert')
                                }}
                            </Label>
                            <p
                                class="text-muted-foreground text-xs leading-normal"
                            >
                                {{
                                    __(
                                        'Receive alerts when department or section overtime hours reach warning (100%) or danger (115%) levels.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="border-border/60 border-t" />

                    <!-- Approval Status Notification -->
                    <div class="flex items-start gap-3">
                        <Checkbox
                            id="pref-approval-status"
                            data-test="checkbox-approval-status"
                            :checked="form.approval_status_notification"
                            @update:checked="
                                (val: boolean) =>
                                    (form.approval_status_notification = val)
                            "
                            class="mt-0.5"
                        />
                        <div
                            class="grid cursor-pointer gap-0.5 leading-none"
                            @click="
                                form.approval_status_notification =
                                    !form.approval_status_notification
                            "
                        >
                            <Label
                                for="pref-approval-status"
                                class="cursor-pointer text-sm font-medium"
                            >
                                {{
                                    __('Overtime Approval Status Notifications')
                                }}
                            </Label>
                            <p
                                class="text-muted-foreground text-xs leading-normal"
                            >
                                {{
                                    __(
                                        'Receive updates when your submitted overtime requests are verified, approved, or revised.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Plant Standards Banner (Read-Only Trust Signal) -->
            <Card class="border-primary/20 bg-primary/5">
                <CardHeader class="pb-3">
                    <div class="flex items-center gap-2">
                        <Globe2 class="text-primary size-4" />
                        <CardTitle class="text-sm font-semibold">
                            {{ __('Plant Standards & Global Formats') }}
                        </CardTitle>
                    </div>
                    <CardDescription class="text-xs">
                        {{
                            __(
                                'To ensure audit consistency across manufacturing operations, these formatting standards are enforced globally.',
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent
                    class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-3"
                >
                    <div
                        class="border-border/60 bg-background/80 rounded-md border p-2.5"
                    >
                        <div
                            class="text-muted-foreground mb-1 flex items-center gap-1.5"
                        >
                            <Clock class="size-3.5" />
                            <span class="font-medium">{{
                                __('Timezone')
                            }}</span>
                        </div>
                        <div class="text-foreground font-mono font-semibold">
                            WIB (Asia/Jakarta)
                        </div>
                        <div class="text-muted-foreground mt-0.5 text-[11px]">
                            UTC+07:00 (Indonesia Barat)
                        </div>
                    </div>

                    <div
                        class="border-border/60 bg-background/80 rounded-md border p-2.5"
                    >
                        <div
                            class="text-muted-foreground mb-1 flex items-center gap-1.5"
                        >
                            <Info class="size-3.5" />
                            <span class="font-medium">{{
                                __('Date Format')
                            }}</span>
                        </div>
                        <div class="text-foreground font-mono font-semibold">
                            DD/MM/YYYY
                        </div>
                        <div class="text-muted-foreground mt-0.5 text-[11px]">
                            Contoh: 07/09/2026
                        </div>
                    </div>

                    <div
                        class="border-border/60 bg-background/80 rounded-md border p-2.5"
                    >
                        <div
                            class="text-muted-foreground mb-1 flex items-center gap-1.5"
                        >
                            <DollarSign class="size-3.5" />
                            <span class="font-medium">{{
                                __('Currency Format')
                            }}</span>
                        </div>
                        <div class="text-foreground font-mono font-semibold">
                            Rupiah (IDR)
                        </div>
                        <div class="text-muted-foreground mt-0.5 text-[11px]">
                            Contoh: Rp 25.000,00
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Save Action Button -->
            <div class="flex items-center gap-4 pt-2">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="save-preferences-button"
                    class="cursor-pointer"
                >
                    <Spinner v-if="form.processing" class="mr-2 size-4" />
                    <span>{{ __('Save Preferences') }}</span>
                </Button>
                <span
                    v-if="form.recentlySuccessful"
                    class="text-muted-foreground text-xs transition-opacity"
                    data-test="save-success-indicator"
                >
                    {{ __('Preferences saved.') }}
                </span>
            </div>
        </form>
    </div>
</template>
