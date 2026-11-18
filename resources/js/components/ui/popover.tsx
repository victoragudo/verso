import { Slot } from "@radix-ui/react-slot"
import * as React from "react"
import { createPortal } from "react-dom"

import {
  FOCUSABLE,
  PortalContainerContext,
  PortalHost,
  focusFirst,
  isPopoverOpen,
  mergeRefs,
  supportsAnchorPositioning,
  supportsPopover,
  usePortalContainer,
} from "@/components/ui/top-layer"
import { useIsMobile } from "@/hooks/use-mobile"
import { cn } from "@/lib/utils"

type PopoverContextValue = {
  open: boolean
  setOpen: (open: boolean) => void
  triggerRef: React.RefObject<HTMLElement | null>
  contentId: string
  anchorName: string
}

const PopoverContext = React.createContext<PopoverContextValue | null>(null)

// Gives children access to the popover state.
function usePopover(): PopoverContextValue {
  const context = React.useContext(PopoverContext)

  if (!context) {
    throw new Error("Popover parts must be used inside <Popover>.")
  }

  return context
}

// Small accessible popover, controlled or uncontrolled.
function Popover({
  open: openProp,
  defaultOpen = false,
  onOpenChange,
  children,
}: {
  open?: boolean
  defaultOpen?: boolean
  onOpenChange?: (open: boolean) => void
  children: React.ReactNode
}) {
  const [uncontrolledOpen, setUncontrolledOpen] = React.useState(defaultOpen)
  const open = openProp ?? uncontrolledOpen
  const triggerRef = React.useRef<HTMLElement | null>(null)
  const contentId = React.useId()
  const anchorName = `--popover-${contentId.replace(/[^a-zA-Z0-9_-]/g, "")}`

  const setOpen = React.useCallback(
    (next: boolean) => {
      if (openProp === undefined) {
        setUncontrolledOpen(next)
      }

      onOpenChange?.(next)
    },
    [openProp, onOpenChange]
  )

  const value = React.useMemo(
    () => ({ open, setOpen, triggerRef, contentId, anchorName }),
    [open, setOpen, contentId, anchorName]
  )

  return <PopoverContext.Provider value={value}>{children}</PopoverContext.Provider>
}

// The element that opens and closes the popover. It is also the CSS anchor.
function PopoverTrigger({
  asChild = false,
  onClick,
  onPointerDown,
  style,
  ...props
}: React.ComponentProps<"button"> & { asChild?: boolean }) {
  const { open, setOpen, triggerRef, contentId, anchorName } = usePopover()
  const openAtPress = React.useRef<boolean | null>(null)
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      ref={triggerRef as React.Ref<HTMLButtonElement>}
      type={asChild ? undefined : "button"}
      data-slot="popover-trigger"
      data-state={open ? "open" : "closed"}
      aria-haspopup="dialog"
      aria-expanded={open}
      aria-controls={open ? contentId : undefined}
      style={{ ...style, anchorName } as React.CSSProperties}
      onPointerDown={(event: React.PointerEvent<HTMLButtonElement>) => {
        // Light dismiss closes the popover before the click arrives, so remember the state at the press.
        openAtPress.current = open
        onPointerDown?.(event)
      }}
      onClick={(event: React.MouseEvent<HTMLButtonElement>) => {
        onClick?.(event)

        const wasOpen = openAtPress.current ?? open
        openAtPress.current = null

        if (!event.defaultPrevented) {
          setOpen(!wasOpen)
        }
      }}
      {...props}
    />
  )
}

type Placement = { top: number; left: number; side: "top" | "bottom" }
type Align = "start" | "center" | "end"

const VIEWPORT_MARGIN = 8

// CSS anchor positioning areas for each alignment. Logical keywords follow RTL.
const POSITION_AREA: Record<Align, string> = {
  start: "block-end span-inline-end",
  center: "block-end",
  end: "block-end span-inline-start",
}

// Fallback without CSS anchor positioning: finds a place under the trigger,
// or above it when there is no room below.
function computePlacement(
  trigger: DOMRect,
  content: DOMRect,
  align: Align,
  sideOffset: number
): Placement {
  const viewportWidth = document.documentElement.clientWidth
  const viewportHeight = window.innerHeight
  const roomBelow = viewportHeight - trigger.bottom - sideOffset - VIEWPORT_MARGIN
  const roomAbove = trigger.top - sideOffset - VIEWPORT_MARGIN
  const side = content.height > roomBelow && roomAbove > roomBelow ? "top" : "bottom"

  const top =
    side === "bottom"
      ? trigger.bottom + sideOffset
      : trigger.top - sideOffset - content.height

  const rtl = getComputedStyle(document.documentElement).direction === "rtl"
  const startLeft = rtl ? trigger.right - content.width : trigger.left
  const endLeft = rtl ? trigger.left : trigger.right - content.width
  const fits = (x: number) =>
    x >= VIEWPORT_MARGIN && x + content.width <= viewportWidth - VIEWPORT_MARGIN
  let left =
    align === "center"
      ? trigger.left + trigger.width / 2 - content.width / 2
      : align === "start"
        ? startLeft
        : endLeft

  // Like the flip-inline fallback of anchor positioning: try the other edge first.
  if (align !== "center" && !fits(left)) {
    const other = left === startLeft ? endLeft : startLeft

    if (fits(other)) {
      left = other
    }
  }

  const maxLeft = viewportWidth - content.width - VIEWPORT_MARGIN
  left = Math.max(VIEWPORT_MARGIN, Math.min(left, maxLeft))

  return { top: Math.max(VIEWPORT_MARGIN, top), left, side }
}

// The floating panel. It uses the native popover (top layer, light dismiss,
// Escape) and CSS anchor positioning when the browser has them, and falls back
// to fixed positioning from script. Under md it can open as a bottom sheet.
function PopoverContent({
  className,
  align = "start",
  sideOffset = 6,
  sheetOnMobile = false,
  children,
  ref,
  ...props
}: React.ComponentProps<"div"> & {
  align?: Align
  sideOffset?: number
  sheetOnMobile?: boolean
}) {
  const { open, setOpen, triggerRef, contentId, anchorName } = usePopover()
  const container = usePortalContainer()
  const isMobile = useIsMobile()
  const asSheet = sheetOnMobile && isMobile
  const native = supportsPopover()
  const anchored = native && !asSheet && supportsAnchorPositioning()
  const contentRef = React.useRef<HTMLDivElement | null>(null)
  const [host, setHost] = React.useState<HTMLDivElement | null>(null)
  const [placement, setPlacement] = React.useState<Placement | null>(null)
  const [anchoredSide, setAnchoredSide] = React.useState<"top" | "bottom">("bottom")

  const updatePlacement = React.useCallback(() => {
    const trigger = triggerRef.current
    const content = contentRef.current

    if (!trigger || !content || asSheet || anchored) {
      return
    }

    setPlacement(
      computePlacement(
        trigger.getBoundingClientRect(),
        content.getBoundingClientRect(),
        align,
        sideOffset
      )
    )
  }, [triggerRef, align, sideOffset, asSheet, anchored])

  React.useLayoutEffect(() => {
    if (!open) {
      setPlacement(null)

      return
    }

    const content = contentRef.current

    if (native && content && !isPopoverOpen(content)) {
      content.showPopover()
    }

    if (anchored) {
      // Read the side the browser picked (it flips when there is no room), before the first paint.
      const trigger = triggerRef.current?.getBoundingClientRect()
      const panel = content?.getBoundingClientRect()

      if (trigger && panel) {
        setAnchoredSide(panel.top < trigger.top ? "top" : "bottom")
      }

      return
    }

    updatePlacement()
    window.addEventListener("resize", updatePlacement)
    window.addEventListener("scroll", updatePlacement, true)

    return () => {
      window.removeEventListener("resize", updatePlacement)
      window.removeEventListener("scroll", updatePlacement, true)
    }
  }, [open, native, anchored, triggerRef, updatePlacement])

  React.useEffect(() => {
    if (!open) {
      return
    }

    const trigger = triggerRef.current
    const content = contentRef.current

    focusFirst(content)

    // The native popover closes itself on Escape and outside clicks; keep React in sync.
    const onToggle = (event: Event) => {
      if ((event as ToggleEvent).newState === "closed" && content?.isConnected) {
        setOpen(false)
      }
    }

    // Fallback light dismiss for browsers without the Popover API.
    const onPointerDown = (event: PointerEvent) => {
      const node = event.target as Node

      if (contentRef.current?.contains(node) || trigger?.contains(node)) {
        return
      }

      setOpen(false)
    }

    if (native) {
      content?.addEventListener("toggle", onToggle)
    } else {
      document.addEventListener("pointerdown", onPointerDown)
    }

    return () => {
      content?.removeEventListener("toggle", onToggle)
      document.removeEventListener("pointerdown", onPointerDown)

      if (trigger && document.activeElement !== trigger) {
        const active = document.activeElement

        if (!active || active === document.body || contentRef.current?.contains(active)) {
          trigger.focus({ preventScroll: true })
        }
      }
    }
  }, [open, native, setOpen, triggerRef])

  // Closes on Escape and keeps Tab inside the panel. Handling Escape here also
  // stops it from closing a dialog around the popover.
  const onKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
    props.onKeyDown?.(event)

    if (event.defaultPrevented) {
      return
    }

    if (event.key === "Escape") {
      event.preventDefault()
      event.stopPropagation()
      setOpen(false)

      return
    }

    if (event.key !== "Tab" || !contentRef.current) {
      return
    }

    const focusable = Array.from(
      contentRef.current.querySelectorAll<HTMLElement>(FOCUSABLE)
    )

    if (focusable.length === 0) {
      return
    }

    const first = focusable[0]
    const last = focusable[focusable.length - 1]

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault()
      last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault()
      first.focus()
    }
  }

  if (!open || typeof document === "undefined") {
    return null
  }

  const side = asSheet ? "sheet" : anchored ? anchoredSide : (placement?.side ?? "bottom")

  const positionStyle: React.CSSProperties = asSheet
    ? {}
    : anchored
      ? ({
          positionAnchor: anchorName,
          positionArea: POSITION_AREA[align],
          positionTryFallbacks: "flip-block, flip-inline, flip-block flip-inline",
          marginBlock: sideOffset,
        } as React.CSSProperties)
      : {
          top: placement?.top ?? 0,
          left: placement?.left ?? 0,
          opacity: placement ? undefined : 0,
        }

  const panel = (
    <div
      ref={mergeRefs(contentRef, ref)}
      id={contentId}
      role="dialog"
      tabIndex={-1}
      popover={native ? "auto" : undefined}
      data-slot="popover-content"
      data-state="open"
      data-side={side}
      {...props}
      onKeyDown={onKeyDown}
      style={{ ...props.style, ...positionStyle }}
      className={cn(
        "bg-popover text-popover-foreground z-50 outline-none",
        asSheet
          ? "fixed inset-x-0 top-auto bottom-0 m-0 w-auto max-w-none transition-none max-h-[90dvh] overflow-y-auto overscroll-contain rounded-t-2xl border-t pb-[env(safe-area-inset-bottom)] shadow-lg animate-in fade-in-0 motion-safe:slide-in-from-bottom-8 duration-200 ease-out backdrop:bg-black/40 backdrop:animate-in backdrop:fade-in-0 backdrop:duration-200"
          : "fixed inset-auto m-0 overflow-visible rounded-lg border shadow-md transition-none animate-in fade-in-0 motion-safe:data-[side=bottom]:slide-in-from-top-1 motion-safe:data-[side=top]:slide-in-from-bottom-1 duration-[120ms] ease-out data-[side=bottom]:origin-top data-[side=top]:origin-bottom",
        className
      )}
    >
      <PortalHost onHost={setHost} />
      <PortalContainerContext.Provider value={host}>
        {children}
      </PortalContainerContext.Provider>
    </div>
  )

  return createPortal(
    asSheet && !native ? (
      <>
        <div
          aria-hidden="true"
          data-slot="popover-backdrop"
          className="fixed inset-0 z-50 bg-black/40 animate-in fade-in-0 duration-200"
        />
        {panel}
      </>
    ) : (
      panel
    ),
    container ?? document.body
  )
}

export { Popover, PopoverTrigger, PopoverContent, usePopover }
