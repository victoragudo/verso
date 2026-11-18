import * as React from "react"

// Shared helpers for floating UI (dialogs, popovers, menus, tooltips) that use
// the browser top layer, with fallbacks for browsers and tests without it.

const FOCUSABLE =
  'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

// True when the browser has the Popover API (top layer and light dismiss).
function supportsPopover(): boolean {
  return (
    typeof HTMLElement !== "undefined" &&
    Object.hasOwn(HTMLElement.prototype, "popover")
  )
}

// True when the browser can place elements with CSS anchor positioning.
function supportsAnchorPositioning(): boolean {
  return (
    typeof CSS !== "undefined" &&
    typeof CSS.supports === "function" &&
    CSS.supports("anchor-name: --a")
  )
}

// True when <dialog closedby> closes the dialog on a backdrop click.
function supportsDialogClosedBy(): boolean {
  return (
    typeof HTMLDialogElement !== "undefined" &&
    "closedBy" in HTMLDialogElement.prototype
  )
}

// True when the element is an open popover. Old browsers do not know the pseudo class.
function isPopoverOpen(element: HTMLElement): boolean {
  try {
    return supportsPopover() && element.matches(":popover-open")
  } catch {
    return false
  }
}

// The element floating children portal into. A modal dialog makes everything
// outside it inert, so menus and popovers opened inside it must live inside it.
const PortalContainerContext = React.createContext<HTMLElement | null>(null)

function usePortalContainer(): HTMLElement | undefined {
  return React.useContext(PortalContainerContext) ?? undefined
}

// An empty box inside a dialog or popover where nested floating UI is placed.
// It has no layout box, so it does not change the layout of the panel.
function PortalHost({
  onHost,
}: {
  onHost: (element: HTMLDivElement | null) => void
}) {
  return (
    <div data-slot="portal-host" style={{ display: "contents" }} ref={onHost} />
  )
}

// Ref callback for Radix menu, select and tooltip content: it shows the
// positioned wrapper as a manual popover, so it sits in the top layer above
// dialogs and is never cut by an overflow parent. Radix keeps positioning,
// keyboard and dismiss behaviour. Without the Popover API nothing changes.
function promoteToTopLayer(content: HTMLElement | null): void {
  const wrapper = content?.parentElement

  if (
    !wrapper ||
    !supportsPopover() ||
    !wrapper.hasAttribute("data-radix-popper-content-wrapper")
  ) {
    return
  }

  if (!wrapper.hasAttribute("popover")) {
    wrapper.setAttribute("popover", "manual")
  }

  if (!isPopoverOpen(wrapper)) {
    wrapper.showPopover()
  }
}

// Joins several refs into one ref callback.
function mergeRefs<T>(
  ...refs: Array<React.Ref<T> | undefined>
): React.RefCallback<T> {
  return (value: T | null) => {
    for (const ref of refs) {
      if (typeof ref === "function") {
        ref(value)
      } else if (ref) {
        ;(ref as React.RefObject<T | null>).current = value
      }
    }
  }
}

// Focuses the element marked to get focus first, else the first focusable one.
function focusFirst(container: HTMLElement | null): void {
  const target =
    container?.querySelector<HTMLElement>("[autofocus], [data-autofocus]") ??
    container?.querySelector<HTMLElement>(FOCUSABLE) ??
    container

  target?.focus({ preventScroll: true })
}

export {
  FOCUSABLE,
  PortalContainerContext,
  PortalHost,
  focusFirst,
  isPopoverOpen,
  mergeRefs,
  promoteToTopLayer,
  supportsAnchorPositioning,
  supportsDialogClosedBy,
  supportsPopover,
  usePortalContainer,
}
