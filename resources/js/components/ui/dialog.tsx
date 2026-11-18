import { Slot } from "@radix-ui/react-slot"
import { XIcon } from "lucide-react"
import * as React from "react"
import { createPortal } from "react-dom"

import {
  PortalContainerContext,
  PortalHost,
  focusFirst,
  mergeRefs,
  supportsDialogClosedBy,
  usePortalContainer,
} from "@/components/ui/top-layer"
import { t } from "@/lib/i18n"
import { cn } from "@/lib/utils"

// Longest wait for the closing animation before the dialog closes anyway.
const EXIT_ANIMATION_LIMIT_MS = 1000

type DialogContextValue = {
  open: boolean
  setOpen: (open: boolean) => void
  titleId: string
  descriptionId: string
  hasDescription: boolean
  setHasDescription: (value: boolean) => void
}

const DialogContext = React.createContext<DialogContextValue | null>(null)

// Gives dialog parts access to the dialog state.
function useDialog(): DialogContextValue {
  const context = React.useContext(DialogContext)

  if (!context) {
    throw new Error("Dialog parts must be used inside <Dialog>.")
  }

  return context
}

// A modal dialog built on the native <dialog> element, controlled or uncontrolled.
function Dialog({
  open: openProp,
  defaultOpen = false,
  onOpenChange,
  children,
}: {
  open?: boolean
  defaultOpen?: boolean
  onOpenChange?: (open: boolean) => void
  children?: React.ReactNode
}) {
  const [uncontrolledOpen, setUncontrolledOpen] = React.useState(defaultOpen)
  const [hasDescription, setHasDescription] = React.useState(false)
  const open = openProp ?? uncontrolledOpen
  const id = React.useId()

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
    () => ({
      open,
      setOpen,
      titleId: `${id}-title`,
      descriptionId: `${id}-description`,
      hasDescription,
      setHasDescription,
    }),
    [open, setOpen, id, hasDescription]
  )

  return <DialogContext.Provider value={value}>{children}</DialogContext.Provider>
}

// Opens the dialog.
function DialogTrigger({
  asChild = false,
  onClick,
  ...props
}: React.ComponentProps<"button"> & { asChild?: boolean }) {
  const { open, setOpen } = useDialog()
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      type={asChild ? undefined : "button"}
      data-slot="dialog-trigger"
      data-state={open ? "open" : "closed"}
      aria-haspopup="dialog"
      aria-expanded={open}
      onClick={(event: React.MouseEvent<HTMLButtonElement>) => {
        onClick?.(event)

        if (!event.defaultPrevented) {
          setOpen(true)
        }
      }}
      {...props}
    />
  )
}

// Closes the dialog.
function DialogClose({
  asChild = false,
  onClick,
  ...props
}: React.ComponentProps<"button"> & { asChild?: boolean }) {
  const { setOpen } = useDialog()
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      type={asChild ? undefined : "button"}
      data-slot="dialog-close"
      onClick={(event: React.MouseEvent<HTMLButtonElement>) => {
        onClick?.(event)

        if (!event.defaultPrevented) {
          setOpen(false)
        }
      }}
      {...props}
    />
  )
}

// True when the pointer is outside the box of the dialog (on the backdrop).
function isOutside(event: PointerEvent, dialog: HTMLDialogElement): boolean {
  const rect = dialog.getBoundingClientRect()

  return (
    event.clientX < rect.left ||
    event.clientX > rect.right ||
    event.clientY < rect.top ||
    event.clientY > rect.bottom
  )
}

// Waits for the closing transition of the dialog and its backdrop.
async function waitForExit(dialog: HTMLDialogElement): Promise<void> {
  const animations = dialog.getAnimations?.({ subtree: true }) ?? []

  if (animations.length === 0) {
    return
  }

  await Promise.race([
    Promise.allSettled(animations.map((animation) => animation.finished)),
    new Promise((resolve) => setTimeout(resolve, EXIT_ANIMATION_LIMIT_MS)),
  ])
}

type DialogSurfaceProps = React.ComponentProps<"dialog"> & {
  onCloseAutoFocus?: (event: Event) => void
  closeButtonClassName?: string
}

// The native <dialog> shared by Dialog and Sheet. It opens with showModal(),
// so the browser gives the top layer, the inert page, focus containment and
// Escape. It closes on a backdrop click (closedby="any", with a fallback),
// plays the closing transition before close(), and gives focus back.
function DialogSurface({
  className,
  children,
  onCloseAutoFocus,
  closeButtonClassName,
  ref,
  ...props
}: DialogSurfaceProps) {
  const { open, setOpen, titleId, descriptionId, hasDescription } = useDialog()
  const container = usePortalContainer()
  const dialogRef = React.useRef<HTMLDialogElement | null>(null)
  const returnFocusTo = React.useRef<HTMLElement | null>(null)
  const openRef = React.useRef(open)
  const onCloseAutoFocusRef = React.useRef(onCloseAutoFocus)
  const [present, setPresent] = React.useState(open)
  const [host, setHost] = React.useState<HTMLDivElement | null>(null)

  openRef.current = open
  onCloseAutoFocusRef.current = onCloseAutoFocus

  if (open && !present) {
    setPresent(true)
  }

  React.useLayoutEffect(() => {
    const dialog = dialogRef.current

    if (!open || !dialog || dialog.open) {
      return
    }

    const active = document.activeElement
    returnFocusTo.current =
      active instanceof HTMLElement && active !== document.body ? active : null

    if (typeof dialog.showModal === "function") {
      dialog.showModal()
    } else {
      dialog.setAttribute("open", "")
    }

    focusFirst(dialog)
  }, [open, present])

  React.useEffect(() => {
    const dialog = dialogRef.current

    if (open || !present || !dialog) {
      return
    }

    let cancelled = false

    const finish = () => {
      if (cancelled) {
        return
      }

      if (dialog.open) {
        if (typeof dialog.close === "function") {
          dialog.close()
        } else {
          dialog.removeAttribute("open")
        }
      }

      const event = new Event("closeAutoFocus", { cancelable: true })
      onCloseAutoFocusRef.current?.(event)

      const target = returnFocusTo.current
      returnFocusTo.current = null
      const active = document.activeElement

      if (
        !event.defaultPrevented &&
        target?.isConnected &&
        (!active || active === document.body || dialog.contains(active))
      ) {
        target.focus({ preventScroll: true })
      }

      setPresent(false)
    }

    const animations = dialog.getAnimations?.({ subtree: true }) ?? []

    if (animations.length === 0) {
      finish()
    } else {
      void waitForExit(dialog).then(finish)
    }

    return () => {
      cancelled = true
    }
  }, [open, present])

  React.useEffect(() => {
    const dialog = dialogRef.current

    if (!present || !dialog) {
      return
    }

    // Escape and light dismiss go through React state, so the close animation runs.
    const onCancel = (event: Event) => {
      event.preventDefault()
      setOpen(false)
    }

    // The browser may close the dialog without a cancel event (a repeated Escape).
    const onClose = () => {
      if (openRef.current) {
        setOpen(false)
      }
    }

    // Without showModal() (old browsers, tests) there is no close request, so handle Escape here.
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape" && !event.defaultPrevented) {
        event.preventDefault()
        setOpen(false)
      }
    }

    // Fallback light dismiss for browsers without closedby: a press and a release on the backdrop.
    let pressedOutside = false
    const onPointerDown = (event: PointerEvent) => {
      pressedOutside = event.target === dialog && isOutside(event, dialog)
    }
    const onPointerUp = (event: PointerEvent) => {
      if (pressedOutside && event.target === dialog && isOutside(event, dialog)) {
        setOpen(false)
      }

      pressedOutside = false
    }

    const nativeModal = typeof dialog.showModal === "function"
    const lightDismissFallback = nativeModal && !supportsDialogClosedBy()

    dialog.addEventListener("cancel", onCancel)
    dialog.addEventListener("close", onClose)

    if (!nativeModal) {
      dialog.addEventListener("keydown", onKeyDown)
    }

    if (lightDismissFallback) {
      dialog.addEventListener("pointerdown", onPointerDown)
      dialog.addEventListener("pointerup", onPointerUp)
    }

    return () => {
      dialog.removeEventListener("cancel", onCancel)
      dialog.removeEventListener("close", onClose)
      dialog.removeEventListener("keydown", onKeyDown)
      dialog.removeEventListener("pointerdown", onPointerDown)
      dialog.removeEventListener("pointerup", onPointerUp)
    }
  }, [present, setOpen])

  if (!present || typeof document === "undefined") {
    return null
  }

  return createPortal(
    <dialog
      ref={mergeRefs(dialogRef, ref)}
      closedby="any"
      aria-labelledby={titleId}
      aria-describedby={hasDescription ? descriptionId : undefined}
      data-state={open ? "open" : "closed"}
      {...props}
      className={className}
    >
      <PortalHost onHost={setHost} />
      <PortalContainerContext.Provider value={host}>
        {children}
      </PortalContainerContext.Provider>
      <button
        type="button"
        data-slot="dialog-close"
        onClick={() => setOpen(false)}
        className={closeButtonClassName}
      >
        <XIcon />
        <span className="sr-only">{t("Close")}</span>
      </button>
    </dialog>,
    container ?? document.body
  )
}

function DialogContent({
  className,
  children,
  ...props
}: Omit<DialogSurfaceProps, "closeButtonClassName">) {
  return (
    <DialogSurface
      data-slot="dialog-content"
      className={cn(
        "bg-card text-inherit m-auto open:grid w-full max-w-[calc(100%-2rem)] gap-4 rounded-lg border p-6 shadow-lg sm:max-w-lg",
        className
      )}
      closeButtonClassName="ring-offset-background focus-visible:ring-ring absolute top-3 end-3 inline-grid size-6 place-items-center rounded-xs opacity-70 transition-opacity hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:cursor-not-allowed [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
      {...props}
    >
      {children}
    </DialogSurface>
  )
}

function DialogHeader({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="dialog-header"
      className={cn("flex flex-col gap-2 text-center sm:text-start", className)}
      {...props}
    />
  )
}

function DialogFooter({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="dialog-footer"
      className={cn(
        "flex flex-col-reverse gap-2 sm:flex-row sm:justify-end",
        className
      )}
      {...props}
    />
  )
}

// The dialog name. The dialog points to it with aria-labelledby.
function DialogTitle({ className, ...props }: React.ComponentProps<"h2">) {
  const { titleId } = useDialog()

  return (
    <h2
      id={titleId}
      data-slot="dialog-title"
      className={cn("text-lg leading-none font-semibold", className)}
      {...props}
    />
  )
}

// The dialog description. The dialog points to it with aria-describedby.
function DialogDescription({ className, ...props }: React.ComponentProps<"p">) {
  const { descriptionId, setHasDescription } = useDialog()

  React.useLayoutEffect(() => {
    setHasDescription(true)

    return () => setHasDescription(false)
  }, [setHasDescription])

  return (
    <p
      id={descriptionId}
      data-slot="dialog-description"
      className={cn("text-muted-foreground text-sm", className)}
      {...props}
    />
  )
}

export {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogSurface,
  DialogTitle,
  DialogTrigger,
  useDialog,
}
