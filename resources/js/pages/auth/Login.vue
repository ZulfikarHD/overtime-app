<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { AlertTriangle, User as UserIcon } from '@lucide/vue';
import { onUnmounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/composables/useTrans';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your credentials below to log in',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const { __ } = useTrans();

const lockoutSeconds = ref<number | null>(null);
let lockoutInterval: ReturnType<typeof setInterval> | null = null;

const startCountdown = (initialSeconds: number) => {
    if (lockoutInterval) {
        clearInterval(lockoutInterval);
        lockoutInterval = null;
    }

    lockoutSeconds.value = initialSeconds;

    lockoutInterval = setInterval(() => {
        if (lockoutSeconds.value !== null && lockoutSeconds.value > 1) {
            lockoutSeconds.value -= 1;
        } else {
            lockoutSeconds.value = null;
            if (lockoutInterval) {
                clearInterval(lockoutInterval);
                lockoutInterval = null;
            }
        }
    }, 1000);
};

const handleErrors = (errors: Record<string, string | undefined>) => {
    const emailError = errors.email;
    if (!emailError) {
        return;
    }

    // Check for throttle message in either English or Indonesian
    const match = emailError.match(/(\d+)\s*(?:seconds|detik)/i);
    if (match) {
        const secs = parseInt(match[1], 10);
        if (!isNaN(secs) && secs > 0) {
            startCountdown(secs);
        }
    }
};

onUnmounted(() => {
    if (lockoutInterval) {
        clearInterval(lockoutInterval);
        lockoutInterval = null;
    }
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="__('Log in to System')" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600 dark:text-green-400"
        >
            {{ status }}
        </div>

        <!-- Lockout Warning Banner -->
        <div
            v-if="lockoutSeconds && lockoutSeconds > 0"
            class="flex items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-900 dark:text-amber-200"
            role="alert"
        >
            <AlertTriangle
                class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400"
            />
            <div class="flex-1 leading-snug">
                <span class="mb-1 block font-semibold">{{
                    __('Access Temporarily Locked')
                }}</span>
                <span>
                    {{
                        __(
                            'Too many failed login attempts. For security reasons, your account is temporarily locked for :seconds seconds. Contact HR/IT Admin if you forgot your password.',
                            { seconds: lockoutSeconds },
                        )
                    }}
                </span>
            </div>
        </div>

        <PasskeyVerify />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <!-- Check errors whenever rendered -->
            <span :class="'hidden'">{{ (handleErrors(errors), '') }}</span>

            <div class="grid gap-6">
                <!-- Dual-Identifier Input (Email or NPK) -->
                <div class="grid gap-2">
                    <Label for="email" class="text-sm font-medium">
                        {{ __('Email or NPK') }}
                    </Label>
                    <div class="relative">
                        <Input
                            id="email"
                            type="text"
                            name="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="username"
                            :placeholder="__('email@example.com or EMP-1001')"
                            class="h-12 pl-10 text-base"
                            :disabled="
                                lockoutSeconds !== null && lockoutSeconds > 0
                            "
                        />
                        <div
                            class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                        >
                            <UserIcon class="size-5" />
                        </div>
                    </div>
                    <InputError :message="errors.email" />
                </div>

                <!-- Password Input -->
                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="text-sm font-medium">
                            {{ __('Password') }}
                        </Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="5"
                        >
                            {{ __('Forgot your password?') }}
                        </TextLink>
                    </div>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        :placeholder="__('Password')"
                        class="h-12 text-base"
                        :disabled="
                            lockoutSeconds !== null && lockoutSeconds > 0
                        "
                    />
                    <InputError :message="errors.password" />
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center justify-between">
                    <Label
                        for="remember"
                        class="flex cursor-pointer items-center space-x-3 py-1"
                    >
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span class="text-sm font-normal">{{
                            __('Remember me')
                        }}</span>
                    </Label>
                </div>

                <!-- Primary Submit Action -->
                <Button
                    type="submit"
                    class="mt-2 h-12 w-full text-base font-medium shadow-sm transition-all"
                    :tabindex="4"
                    :disabled="
                        processing ||
                        (lockoutSeconds !== null && lockoutSeconds > 0)
                    "
                    data-test="login-button"
                >
                    <Spinner v-if="processing" class="mr-2 size-5" />
                    {{ __('Log in to System') }}
                </Button>
            </div>

            <!-- Registration Link -->
            <div class="text-muted-foreground text-center text-sm">
                {{ __("Don't have an account?") }}
                <TextLink
                    :href="register()"
                    :tabindex="5"
                    class="ml-1 font-medium"
                >
                    {{ __('Sign up') }}
                </TextLink>
            </div>

            <!-- Shopfloor Contact Info -->
            <div
                class="border-border/60 text-muted-foreground border-t pt-4 text-center text-xs leading-relaxed"
            >
                {{ __('Having trouble logging in? Contact HR / IT Admin') }}
            </div>
        </Form>
    </div>
</template>
