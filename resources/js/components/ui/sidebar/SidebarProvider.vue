<script setup lang="ts">
import type { HTMLAttributes, Ref } from "vue"
import { defaultDocument, useEventListener, useMediaQuery, useVModel } from "@vueuse/core"
import { TooltipProvider } from "reka-ui"
import { computed, ref, watch } from "vue"
import { cn } from "@/lib/utils"
import { provideSidebarContext, SIDEBAR_COOKIE_MAX_AGE, SIDEBAR_COOKIE_NAME, SIDEBAR_KEYBOARD_SHORTCUT, SIDEBAR_WIDTH, SIDEBAR_WIDTH_ICON } from "./utils"

const props = withDefaults(defineProps<{
  defaultOpen?: boolean
  open?: boolean
  class?: HTMLAttributes["class"]
}>(), {
  defaultOpen: undefined,
  open: undefined,
})

const emits = defineEmits<{
  "update:open": [open: boolean]
}>()

const isMobile = useMediaQuery("(max-width: 767px)")
const isMd = useMediaQuery("(min-width: 768px) and (max-width: 1023px)")
const isDesktop = useMediaQuery("(min-width: 1024px)")
const openMobile = ref(false)

const hasCookie = Boolean(defaultDocument?.cookie.includes(`${SIDEBAR_COOKIE_NAME}=`))
const cookieValue = hasCookie
  ? !defaultDocument?.cookie.includes(`${SIDEBAR_COOKIE_NAME}=false`)
  : undefined

const hasManualToggle = ref(hasCookie)

const computeInitialOpen = (): boolean => {
  if (cookieValue !== undefined) {
    return cookieValue
  }

  if (typeof window !== "undefined") {
    // In md breakpoint (768px - 1023px), collapse by default
    if (window.innerWidth >= 768 && window.innerWidth < 1024) {
      return false
    }
    // md to up (>= 1024px), open by default
    if (window.innerWidth >= 1024) {
      return true
    }
  }

  return props.defaultOpen ?? true
}

const open = useVModel(props, "open", emits, {
  defaultValue: computeInitialOpen(),
  passive: (props.open === undefined) as false,
}) as Ref<boolean>

// Auto-adjust default state on responsive breakpoint transitions if user hasn't explicitly toggled
watch(isMd, (val) => {
  if (val && !hasManualToggle.value) {
    open.value = false
  }
})

watch(isDesktop, (val) => {
  if (val && !hasManualToggle.value) {
    open.value = true
  }
})

function setOpen(value: boolean) {
  hasManualToggle.value = true
  open.value = value
  document.cookie = `${SIDEBAR_COOKIE_NAME}=${open.value}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`
}

function setOpenMobile(value: boolean) {
  openMobile.value = value
}

// Helper to toggle the sidebar.
function toggleSidebar() {
  return isMobile.value ? setOpenMobile(!openMobile.value) : setOpen(!open.value)
}

useEventListener("keydown", (event: KeyboardEvent) => {
  if (event.key === SIDEBAR_KEYBOARD_SHORTCUT && (event.metaKey || event.ctrlKey)) {
    event.preventDefault()
    toggleSidebar()
  }
})

// We add a state so that we can do data-state="expanded" or "collapsed".
// This makes it easier to style the sidebar with Tailwind classes.
const state = computed(() => open.value ? "expanded" : "collapsed")

provideSidebarContext({
  state,
  open,
  setOpen,
  isMobile,
  openMobile,
  setOpenMobile,
  toggleSidebar,
})
</script>

<template>
  <TooltipProvider :delay-duration="0">
    <div
      data-slot="sidebar-wrapper"
      :style="{
        '--sidebar-width': SIDEBAR_WIDTH,
        '--sidebar-width-icon': SIDEBAR_WIDTH_ICON,
      }"
      :class="cn('group/sidebar-wrapper has-data-[variant=inset]:bg-sidebar flex min-h-svh w-full', props.class)"
      v-bind="$attrs"
    >
      <slot />
    </div>
  </TooltipProvider>
</template>
