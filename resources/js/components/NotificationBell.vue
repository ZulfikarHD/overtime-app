<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    Bell,
    Check,
    CheckCheck,
    Clock,
    FileText,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import SpklUploadSheet, {
    type SpklTargetSubmission,
} from '@/components/overtime/SpklUploadSheet.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/composables/useTrans';
import { burnIndex } from '@/routes/dashboard';
import {
    index as notificationsIndexRoute,
    read as notificationsReadRoute,
    readAll as notificationsReadAllRoute,
} from '@/routes/notifications';
import type {
    AppNotification,
    BudgetThresholdNotificationData,
    NotificationsPayload,
    SpklNotificationData,
} from '@/types/ui';

const { __ } = useTrans();
const page = usePage();

const isOpen = ref(false);
const isLoading = ref(false);
const notifications = ref<AppNotification[]>([]);
const unreadCount = ref<number>(
    (page.props.unread_notifications_count as number) ?? 0,
);

// Sync unreadCount from Inertia page props whenever page updates
watch(
    () => page.props.unread_notifications_count,
    (val) => {
        if (typeof val === 'number') {
            unreadCount.value = val;
        }
    },
);

const isSpklSheetOpen = ref(false);
const selectedSubmission = ref<SpklTargetSubmission | null>(null);
const activeNotificationId = ref<string | null>(null);

function getCsrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }
    const match = document.cookie.match(
        new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]+)'),
    );
    return match ? decodeURIComponent(match[2]) : '';
}

async function fetchNotifications() {
    isLoading.value = true;
    try {
        const response = await fetch(notificationsIndexRoute.url(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (response.ok) {
            const data: NotificationsPayload = await response.json();
            notifications.value = data.notifications;
            unreadCount.value = data.unread_count;
        }
    } catch (err) {
        console.error('Failed to fetch notifications:', err);
    } finally {
        isLoading.value = false;
    }
}

async function markAsRead(id: string) {
    try {
        const response = await fetch(notificationsReadRoute.url({ id }), {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
        });
        if (response.ok) {
            notifications.value = notifications.value.filter(
                (n) => n.id !== id,
            );
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        }
    } catch (err) {
        console.error('Failed to mark notification as read:', err);
    }
}

async function markAllAsRead() {
    try {
        const response = await fetch(notificationsReadAllRoute.url(), {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
        });
        if (response.ok) {
            notifications.value = [];
            unreadCount.value = 0;
        }
    } catch (err) {
        console.error('Failed to mark all notifications as read:', err);
    }
}

function isBudgetAlert(item: AppNotification): boolean {
    return (
        (item.data as any)?.notification_type === 'budget_threshold' ||
        Boolean(item.type?.includes('BudgetThresholdAlert'))
    );
}

function asBudgetData(item: AppNotification): BudgetThresholdNotificationData {
    return item.data as BudgetThresholdNotificationData;
}

function asSpklData(item: AppNotification): SpklNotificationData {
    return item.data as SpklNotificationData;
}

function handleBudgetNotificationClick(item: AppNotification) {
    void markAsRead(item.id);
    isOpen.value = false;
    const data = item.data as BudgetThresholdNotificationData;
    router.visit(
        burnIndex.url({
            query: {
                tab: 'sections',
                section: data.section_id,
            },
        }),
    );
}

function handleAttachSpkl(notification: AppNotification) {
    const spklData = notification.data as SpklNotificationData;
    activeNotificationId.value = notification.id;
    selectedSubmission.value = {
        id: spklData.submission_id,
        submission_code: spklData.submission_code,
        operational_date: spklData.operational_date,
        section_name: spklData.section_name,
        total_hours: spklData.total_hours,
        spkl_document: {
            id: spklData.spkl_document_id,
            status: 'PENDING',
            due_date: spklData.due_date,
            spkl_number: null,
            file_name: null,
        },
    };
    isOpen.value = false;
    isSpklSheetOpen.value = true;
}

async function handleSpklSuccess() {
    if (activeNotificationId.value) {
        await markAsRead(activeNotificationId.value);
        activeNotificationId.value = null;
    }
    await fetchNotifications();
}

watch(isOpen, (open) => {
    if (open) {
        void fetchNotifications();
    }
});

onMounted(() => {
    if (unreadCount.value > 0) {
        void fetchNotifications();
    }
});

const displayCount = computed(() => {
    return unreadCount.value > 99 ? '99+' : unreadCount.value.toString();
});
</script>

<template>
    <div>
        <DropdownMenu v-model:open="isOpen">
            <DropdownMenuTrigger as-child>
                <Button
                    variant="ghost"
                    size="icon"
                    class="text-muted-foreground hover:text-foreground relative cursor-pointer"
                    data-test="notification-bell-btn"
                    :aria-label="__('Notifikasi')"
                >
                    <Bell class="size-4" />
                    <span
                        v-if="unreadCount > 0"
                        class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 font-mono text-[10px] font-bold text-white shadow-xs"
                        data-test="notification-badge"
                    >
                        {{ displayCount }}
                    </span>
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align="end"
                class="w-80 p-0 shadow-lg sm:w-96"
                data-test="notification-popover"
            >
                <!-- Header -->
                <div
                    class="border-border/60 flex items-center justify-between border-b px-4 py-3"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-foreground text-sm font-semibold">
                            {{ __('Notifikasi') }}
                        </span>
                        <Badge
                            v-if="unreadCount > 0"
                            variant="secondary"
                            class="font-mono text-xs tabular-nums"
                        >
                            {{ unreadCount }}
                        </Badge>
                    </div>

                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        class="text-muted-foreground hover:text-foreground cursor-pointer text-xs transition-colors"
                        data-test="btn-mark-all-read"
                        @click="markAllAsRead"
                    >
                        {{ __('Tandai Semua Dibaca') }}
                    </button>
                </div>

                <!-- Content Body -->
                <div class="max-h-80 overflow-y-auto">
                    <div
                        v-if="isLoading && notifications.length === 0"
                        class="text-muted-foreground flex items-center justify-center gap-2 py-8 text-xs"
                    >
                        <Spinner class="size-4" />
                        <span>{{ __('Memuat notifikasi...') }}</span>
                    </div>

                    <div
                        v-else-if="notifications.length === 0"
                        class="flex flex-col items-center justify-center px-4 py-8 text-center"
                        data-test="notification-empty"
                    >
                        <div
                            class="bg-muted/60 text-muted-foreground mb-2 flex size-10 items-center justify-center rounded-full"
                        >
                            <CheckCheck class="size-5" />
                        </div>
                        <p class="text-foreground text-sm font-medium">
                            {{ __('Tidak ada notifikasi baru') }}
                        </p>
                        <p class="text-muted-foreground mt-0.5 text-xs">
                            {{
                                __(
                                    'Semua dokumen SPKL dan jadwal lembur telah terpantau.',
                                )
                            }}
                        </p>
                    </div>

                    <div v-else class="divide-border/40 divide-y">
                        <div
                            v-for="item in notifications"
                            :key="item.id"
                            class="hover:bg-muted/40 p-3 transition-colors"
                            data-test="notification-item"
                        >
                            <!-- Budget Threshold Alert Item (E05-04) -->
                            <div
                                v-if="isBudgetAlert(item)"
                                class="flex cursor-pointer items-start gap-2.5"
                                data-test="notification-budget-item"
                                @click="handleBudgetNotificationClick(item)"
                            >
                                <!-- Type Icon -->
                                <div
                                    class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full"
                                    :class="
                                        asBudgetData(item).alert_level ===
                                        'danger'
                                            ? 'bg-rose-100 text-[#cc0000] dark:bg-rose-950/60 dark:text-rose-400'
                                            : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400'
                                    "
                                >
                                    <AlertTriangle class="size-3.5" />
                                </div>

                                <!-- Body -->
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-center justify-between gap-1"
                                    >
                                        <span
                                            class="text-foreground font-mono text-xs font-semibold"
                                        >
                                            {{
                                                asBudgetData(item)
                                                    .section_code ||
                                                asBudgetData(item).section_name
                                            }}
                                        </span>
                                        <Badge
                                            v-if="
                                                asBudgetData(item)
                                                    .alert_level === 'danger'
                                            "
                                            variant="destructive"
                                            class="px-1.5 py-0 text-[10px]"
                                            data-test="badge-budget-danger"
                                        >
                                            {{
                                                __('Defisit Kritis (:pct%)', {
                                                    pct: Number(
                                                        asBudgetData(item)
                                                            .burn_index_pct,
                                                    ).toFixed(1),
                                                })
                                            }}
                                        </Badge>
                                        <Badge
                                            v-else
                                            variant="outline"
                                            class="border-amber-300 px-1.5 py-0 text-[10px] text-amber-600 dark:text-amber-400"
                                            data-test="badge-budget-warning"
                                        >
                                            {{
                                                __('Peringatan (:pct%)', {
                                                    pct: Number(
                                                        asBudgetData(item)
                                                            .burn_index_pct,
                                                    ).toFixed(1),
                                                })
                                            }}
                                        </Badge>
                                    </div>

                                    <p
                                        class="text-muted-foreground mt-0.5 line-clamp-2 text-xs"
                                    >
                                        {{
                                            asBudgetData(item).message ||
                                            asBudgetData(item).title
                                        }}
                                    </p>

                                    <div
                                        class="border-border/40 mt-2 flex items-center justify-between border-t pt-1"
                                    >
                                        <span
                                            class="text-muted-foreground text-[11px]"
                                        >
                                            {{
                                                asBudgetData(item).section_name
                                            }}
                                            <template
                                                v-if="
                                                    asBudgetData(item)
                                                        .department_name
                                                "
                                            >
                                                •
                                                {{
                                                    asBudgetData(item)
                                                        .department_name
                                                }}
                                            </template>
                                        </span>

                                        <div class="flex items-center gap-1">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="h-6 cursor-pointer border-red-200 px-2 text-[11px] font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:border-red-900 dark:hover:bg-red-950/40"
                                                data-test="btn-view-burn-from-notif"
                                                @click.stop="
                                                    handleBudgetNotificationClick(
                                                        item,
                                                    )
                                                "
                                            >
                                                <Activity class="mr-1 size-3" />
                                                {{ __('Lihat Burn Index') }}
                                            </Button>

                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                class="text-muted-foreground hover:text-foreground h-6 w-6 cursor-pointer"
                                                data-test="btn-mark-read"
                                                :title="
                                                    __('Tandai sudah dibaca')
                                                "
                                                @click.stop="
                                                    markAsRead(item.id)
                                                "
                                            >
                                                <Check class="size-3" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SPKL Document Reminder Item -->
                            <div v-else class="flex items-start gap-2.5">
                                <!-- Type Icon -->
                                <div
                                    class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full"
                                    :class="
                                        asSpklData(item).reminder_type ===
                                        'overdue'
                                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400'
                                            : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400'
                                    "
                                >
                                    <AlertTriangle
                                        v-if="
                                            asSpklData(item).reminder_type ===
                                            'overdue'
                                        "
                                        class="size-3.5"
                                    />
                                    <Clock v-else class="size-3.5" />
                                </div>

                                <!-- Body -->
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-center justify-between gap-1"
                                    >
                                        <span
                                            class="text-foreground font-mono text-xs font-semibold"
                                        >
                                            {{
                                                asSpklData(item).submission_code
                                            }}
                                        </span>
                                        <Badge
                                            v-if="
                                                asSpklData(item)
                                                    .reminder_type === 'overdue'
                                            "
                                            variant="destructive"
                                            class="px-1.5 py-0 text-[10px]"
                                        >
                                            {{
                                                __('Lewat :days hari', {
                                                    days: asSpklData(item)
                                                        .overdue_days,
                                                })
                                            }}
                                        </Badge>
                                        <Badge
                                            v-else
                                            variant="outline"
                                            class="border-amber-300 px-1.5 py-0 text-[10px] text-amber-600 dark:text-amber-400"
                                        >
                                            {{ __('Jatuh Tempo Besok') }}
                                        </Badge>
                                    </div>

                                    <p
                                        class="text-muted-foreground mt-0.5 line-clamp-2 text-xs"
                                    >
                                        {{ asSpklData(item).message }}
                                    </p>

                                    <div
                                        class="border-border/40 mt-2 flex items-center justify-between border-t pt-1"
                                    >
                                        <span
                                            class="text-muted-foreground text-[11px]"
                                        >
                                            {{ asSpklData(item).section_name }}
                                            •
                                            {{ asSpklData(item).due_date }}
                                        </span>

                                        <div class="flex items-center gap-1">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="h-6 cursor-pointer border-red-200 px-2 text-[11px] font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:border-red-900 dark:hover:bg-red-950/40"
                                                data-test="btn-attach-from-notif"
                                                @click="handleAttachSpkl(item)"
                                            >
                                                <FileText class="mr-1 size-3" />
                                                {{ __('Lampirkan') }}
                                            </Button>

                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                class="text-muted-foreground hover:text-foreground h-6 w-6 cursor-pointer"
                                                data-test="btn-mark-read"
                                                :title="
                                                    __('Tandai sudah dibaca')
                                                "
                                                @click="markAsRead(item.id)"
                                            >
                                                <Check class="size-3" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>

        <!-- Slide-in SPKL Attachment Drawer (User Journey 5) -->
        <SpklUploadSheet
            v-model:open="isSpklSheetOpen"
            :submission="selectedSubmission"
            @success="handleSpklSuccess"
        />
    </div>
</template>
