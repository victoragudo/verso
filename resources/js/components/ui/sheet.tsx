import * as React from "react"

import {
  Dialog,
  DialogClose,
  DialogDescription,
  DialogSurface,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog"
import { cn } from "@/lib/utils"

// A side panel. It is the same native modal <dialog> as Dialog, placed on one edge.
function Sheet(props: React.ComponentProps<typeof Dialog>) {
  return <Dialog {...props} />
}

function SheetTrigger(props: React.ComponentProps<typeof DialogTrigger>) {
  return <DialogTrigger data-slot="sheet-trigger" {...props} />
}

function SheetClose(props: React.ComponentProps<typeof DialogClose>) {
  return <DialogClose data-slot="sheet-close" {...props} />
}

// "left" and "right" mean the start and end side, so they follow RTL.
function SheetContent({
  className,
  children,
  side = "right",
  ...props
}: Omit<React.ComponentProps<typeof DialogSurface>, "closeButtonClassName"> & {
  side?: "top" | "right" | "bottom" | "left"
}) {
  return (
    <DialogSurface
      data-slot="sheet-content"
      data-side={side}
      className={cn(
        "bg-card text-inherit fixed m-0 max-h-none max-w-none open:flex flex-col gap-4 shadow-lg",
        side === "right" &&
          "inset-y-0 end-0 start-auto h-full w-3/4 border-s sm:max-w-sm",
        side === "left" &&
          "inset-y-0 start-0 end-auto h-full w-3/4 border-e sm:max-w-sm",
        side === "top" && "inset-x-0 top-0 bottom-auto h-auto w-auto border-b",
        side === "bottom" && "inset-x-0 bottom-0 top-auto h-auto w-auto border-t",
        className
      )}
      closeButtonClassName="ring-offset-background focus-visible:ring-ring absolute top-3 end-3 inline-grid size-6 place-items-center rounded-xs opacity-70 transition-opacity hover:opacity-100 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:cursor-not-allowed [&_svg]:size-4"
      {...props}
    >
      {children}
    </DialogSurface>
  )
}

function SheetHeader({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="sheet-header"
      className={cn("flex flex-col gap-1.5 p-4", className)}
      {...props}
    />
  )
}

function SheetFooter({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="sheet-footer"
      className={cn("mt-auto flex flex-col gap-2 p-4", className)}
      {...props}
    />
  )
}

function SheetTitle({
  className,
  ...props
}: React.ComponentProps<typeof DialogTitle>) {
  return (
    <DialogTitle
      data-slot="sheet-title"
      className={cn("text-foreground text-base leading-normal font-semibold", className)}
      {...props}
    />
  )
}

function SheetDescription({
  className,
  ...props
}: React.ComponentProps<typeof DialogDescription>) {
  return (
    <DialogDescription
      data-slot="sheet-description"
      className={cn("text-muted-foreground text-sm", className)}
      {...props}
    />
  )
}

export {
  Sheet,
  SheetTrigger,
  SheetClose,
  SheetContent,
  SheetHeader,
  SheetFooter,
  SheetTitle,
  SheetDescription,
}
