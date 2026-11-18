import * as TooltipPrimitive from "@radix-ui/react-tooltip"
import * as React from "react"

import {
  mergeRefs,
  promoteToTopLayer,
  usePortalContainer,
} from "@/components/ui/top-layer"
import { cn } from "@/lib/utils"

function TooltipProvider({
  delayDuration = 0,
  ...props
}: React.ComponentProps<typeof TooltipPrimitive.Provider>) {
  return (
    <TooltipPrimitive.Provider
      data-slot="tooltip-provider"
      delayDuration={delayDuration}
      {...props}
    />
  )
}

function Tooltip({
  ...props
}: React.ComponentProps<typeof TooltipPrimitive.Root>) {
  return <TooltipPrimitive.Root data-slot="tooltip" {...props} />
}

function TooltipTrigger({
  ...props
}: React.ComponentProps<typeof TooltipPrimitive.Trigger>) {
  return <TooltipPrimitive.Trigger data-slot="tooltip-trigger" {...props} />
}

// The tooltip bubble. It portals into the open dialog or popover around it and
// shows in the top layer when the browser can.
function TooltipContent({
  className,
  sideOffset = 4,
  children,
  ref,
  ...props
}: React.ComponentProps<typeof TooltipPrimitive.Content>) {
  const container = usePortalContainer()

  return (
    <TooltipPrimitive.Portal container={container}>
      <TooltipPrimitive.Content
        ref={mergeRefs(ref, promoteToTopLayer)}
        data-slot="tooltip-content"
        sideOffset={sideOffset}
        className={cn(
          "bg-primary text-primary-foreground animate-in fade-in-0 duration-100 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 motion-safe:data-[side=bottom]:slide-in-from-top-1 motion-safe:data-[side=left]:slide-in-from-right-1 motion-safe:data-[side=right]:slide-in-from-left-1 motion-safe:data-[side=top]:slide-in-from-bottom-1 z-50 max-w-sm rounded-md px-3 py-1.5 text-xs",
          className
        )}
        {...props}
      >
        {children}
        <TooltipPrimitive.Arrow className="bg-primary fill-primary z-50 size-2.5 translate-y-[calc(-50%_-_2px)] rotate-45 rounded-[2px]" />
      </TooltipPrimitive.Content>
    </TooltipPrimitive.Portal>
  )
}

export { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider }
