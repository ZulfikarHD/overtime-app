<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { X } from "@lucide/vue"
import { reactiveOmit, useMediaQuery } from "@vueuse/core"
import {
  DialogClose,
  DialogContent,
  DialogPortal,
  useForwardPropsEmits,
} from "reka-ui"
import { computed } from "vue"
import { cn } from "@/lib/utils"
import SheetOverlay from "./SheetOverlay.vue"

interface SheetContentProps extends DialogContentProps {
  class?: HTMLAttributes["class"]
  side?: "top" | "right" | "bottom" | "left"
  /**
   * When true (default), right-side sheets render as a centered dialog at the
   * Tailwind `lg` breakpoint and above. Tablet/below keep the slide-in drawer.
   * Left/top/bottom sides always stay drawers (e.g. mobile nav).
   */
  responsive?: boolean
}

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(defineProps<SheetContentProps>(), {
  side: "right",
  responsive: true,
})
const emits = defineEmits<DialogContentEmits>()

const isDesktop = useMediaQuery("(min-width: 1024px)")

const useDialogPresentation = computed(
  () => props.responsive && props.side === "right" && isDesktop.value,
)

const delegatedProps = reactiveOmit(props, "class", "side", "responsive")

const forwarded = useForwardPropsEmits(delegatedProps, emits)

const contentClass = computed(() =>
  cn(
    "bg-background data-[state=open]:animate-in data-[state=closed]:animate-out fixed z-50 flex flex-col gap-4 shadow-lg transition ease-in-out",
    useDialogPresentation.value
      ? "data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 inset-auto top-[50%] left-[50%] max-h-[min(90vh,56rem)] w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] rounded-lg border p-6 duration-200 sm:max-w-2xl"
      : cn(
          // Default p-6 so form bodies are never edge-flush; override with p-0 when
          // using full-bleed header/footer borders and section-level px-6.
          "data-[state=closed]:duration-300 data-[state=open]:duration-500 p-6",
          props.side === "right"
            && "data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right inset-y-0 right-0 h-full w-3/4 border-l sm:max-w-sm",
          props.side === "left"
            && "data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left inset-y-0 left-0 h-full w-3/4 border-r sm:max-w-sm",
          props.side === "top"
            && "data-[state=closed]:slide-out-to-top data-[state=open]:slide-in-from-top inset-x-0 top-0 h-auto border-b",
          props.side === "bottom"
            && "data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom inset-x-0 bottom-0 h-auto border-t",
        ),
    props.class,
    // Dialog height lock must win over consumer `h-full` so lg+ stays a modal, not a stretched panel.
    useDialogPresentation.value && "h-auto max-h-[min(90vh,56rem)]",
  ),
)
</script>

<template>
  <DialogPortal>
    <SheetOverlay />
    <DialogContent
      data-slot="sheet-content"
      :data-presentation="useDialogPresentation ? 'dialog' : 'drawer'"
      :class="contentClass"
      v-bind="{ ...$attrs, ...forwarded }"
    >
      <slot />

      <DialogClose
        class="ring-offset-background focus:ring-ring data-[state=open]:bg-secondary absolute top-4 right-4 rounded-xs opacity-70 transition-opacity hover:opacity-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none"
      >
        <X class="size-4" />
        <span class="sr-only">Close</span>
      </DialogClose>
    </DialogContent>
  </DialogPortal>
</template>
