import * as SelectPrimitive from "@radix-ui/react-select"
import { CheckIcon, ChevronDownIcon, ChevronUpIcon } from "lucide-react"
import * as React from "react"

import {
  mergeRefs,
  promoteToTopLayer,
  usePortalContainer,
} from "@/components/ui/top-layer"
import { cn } from "@/lib/utils"

// Browsers with the customizable select get a real <select>. Others keep the Radix select.
function supportsBaseSelect(): boolean {
  return (
    typeof CSS !== "undefined" &&
    typeof CSS.supports === "function" &&
    CSS.supports("appearance", "base-select")
  )
}

// Inside a native select, items render as <option> and groups as <optgroup>.
const NativeSelectContext = React.createContext(false)

type TriggerElement = React.ReactElement<React.ComponentProps<typeof SelectTrigger>>
type ContentElement = React.ReactElement<React.ComponentProps<typeof SelectContent>>

// Finds the trigger and the content of a select. Null when the children have another shape.
function selectParts(
  children: React.ReactNode
): { trigger: TriggerElement; content: ContentElement } | null {
  const parts = React.Children.toArray(children).filter(React.isValidElement)
  const trigger = parts.find((part) => part.type === SelectTrigger)
  const content = parts.find((part) => part.type === SelectContent)

  if (!trigger || !content || parts.length !== 2) {
    return null
  }

  return { trigger: trigger as TriggerElement, content: content as ContentElement }
}

// The values of every item, to know when the value has no option.
function itemValues(children: React.ReactNode): string[] {
  return React.Children.toArray(children).flatMap((child) => {
    if (!React.isValidElement<{ value?: string; children?: React.ReactNode }>(child)) {
      return []
    }

    if (child.type === SelectItem) {
      return [String(child.props.value)]
    }

    return itemValues(child.props.children)
  })
}

// The placeholder given to <SelectValue> inside the trigger.
function valuePlaceholder(children: React.ReactNode): React.ReactNode {
  for (const child of React.Children.toArray(children)) {
    if (React.isValidElement<{ placeholder?: React.ReactNode }>(child) && child.type === SelectValue) {
      return child.props.placeholder
    }
  }

  return undefined
}

function Select({
  ...props
}: React.ComponentProps<typeof SelectPrimitive.Root>) {
  const parts = supportsBaseSelect() && props.open === undefined ? selectParts(props.children) : null

  if (!parts) {
    return <SelectPrimitive.Root data-slot="select" {...props} />
  }

  return <NativeSelect {...props} {...parts} />
}

type NativeSelectProps = React.ComponentProps<typeof SelectPrimitive.Root> & {
  trigger: TriggerElement
  content: ContentElement
}

// A customizable <select> with the look of the Radix select. The browser draws the
// picker in the top layer and handles the keyboard and screen readers.
function NativeSelect({
  value,
  defaultValue,
  onValueChange,
  disabled,
  name,
  required,
  dir,
  trigger,
  content,
}: NativeSelectProps) {
  const [ownValue, setOwnValue] = React.useState(defaultValue ?? "")
  const current = value ?? ownValue
  const {
    className,
    children: triggerChildren,
    size = "default",
    disabled: triggerDisabled,
    ...triggerProps
  } = trigger.props
  const items = content.props.children
  const known = itemValues(items).includes(current)

  return (
    <NativeSelectContext.Provider value>
      <select
        {...(triggerProps as React.ComponentProps<"select">)}
        data-slot="select-trigger"
        data-size={size}
        data-placeholder={known ? undefined : ""}
        dir={dir}
        name={name}
        required={required}
        disabled={disabled || triggerDisabled}
        value={known ? current : ""}
        onChange={(event) => {
          setOwnValue(event.target.value)
          onValueChange?.(event.target.value)
        }}
        className={cn(
          "border-input data-[placeholder]:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive user-invalid:ring-destructive/20 dark:user-invalid:ring-destructive/40 user-invalid:border-destructive dark:bg-input/30 not-disabled:hover:bg-accent/40 dark:hover:bg-input/50 flex w-full items-center justify-between gap-2 rounded-md border bg-card px-3 py-2 text-sm whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 data-[size=default]:h-10 md:data-[size=default]:h-9 data-[size=sm]:h-10 md:data-[size=sm]:h-8",
          className
        )}
      >
        {!known && (
          <option value="" disabled hidden>
            {valuePlaceholder(triggerChildren) ?? ""}
          </option>
        )}
        {items}
      </select>
    </NativeSelectContext.Provider>
  )
}

function SelectGroup({
  ...props
}: React.ComponentProps<typeof SelectPrimitive.Group>) {
  if (React.useContext(NativeSelectContext)) {
    return <optgroup data-slot="select-group">{props.children}</optgroup>
  }

  return <SelectPrimitive.Group data-slot="select-group" {...props} />
}

function SelectValue({
  ...props
}: React.ComponentProps<typeof SelectPrimitive.Value>) {
  return <SelectPrimitive.Value data-slot="select-value" {...props} />
}

function SelectTrigger({
  className,
  size = "default",
  children,
  ...props
}: React.ComponentProps<typeof SelectPrimitive.Trigger> & {
  size?: "sm" | "default"
}) {
  return (
    <SelectPrimitive.Trigger
      data-slot="select-trigger"
      data-size={size}
      className={cn(
        "border-input data-[placeholder]:text-muted-foreground [&_svg:not([class*='text-'])]:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 not-disabled:hover:bg-accent/40 dark:hover:bg-input/50 flex w-full items-center justify-between gap-2 rounded-md border bg-card px-3 py-2 text-sm whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 data-[size=default]:h-10 md:data-[size=default]:h-9 data-[size=sm]:h-10 md:data-[size=sm]:h-8 *:data-[slot=select-value]:line-clamp-1 *:data-[slot=select-value]:flex *:data-[slot=select-value]:items-center *:data-[slot=select-value]:gap-2 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
        className
      )}
      {...props}
    >
      {children}
      <SelectPrimitive.Icon asChild>
        <ChevronDownIcon className="size-4 opacity-50" />
      </SelectPrimitive.Icon>
    </SelectPrimitive.Trigger>
  )
}

// The option list. It portals into the open dialog or popover around it and
// shows in the top layer when the browser can.
function SelectContent({
  className,
  children,
  position = "popper",
  side = "bottom",
  sideOffset = 4,
  align = "center",
  ref,
  ...props
}: React.ComponentProps<typeof SelectPrimitive.Content>) {
  const container = usePortalContainer()

  return (
    <SelectPrimitive.Portal container={container}>
      <SelectPrimitive.Content
        ref={mergeRefs(ref, promoteToTopLayer)}
        data-slot="select-content"
        className={cn(
          "bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 transition-none data-[state=open]:duration-100 data-[state=closed]:duration-75 motion-safe:data-[state=open]:data-[side=bottom]:slide-in-from-top-1 motion-safe:data-[state=open]:data-[side=top]:slide-in-from-bottom-1 motion-safe:data-[state=open]:data-[side=left]:slide-in-from-right-1 motion-safe:data-[state=open]:data-[side=right]:slide-in-from-left-1 relative z-50 max-h-(--radix-select-content-available-height) min-w-[8rem] origin-(--radix-select-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border shadow-md",
          position === "popper" &&
            "data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1",
          className
        )}
        position={position}
        side={side}
        sideOffset={sideOffset}
        avoidCollisions={false}
        align={align}
        {...props}
      >
        <SelectScrollUpButton />
        <SelectPrimitive.Viewport
          className={cn(
            "p-1",
            position === "popper" &&
              "h-[var(--radix-select-trigger-height)] w-full min-w-[var(--radix-select-trigger-width)] scroll-my-1"
          )}
        >
          {children}
        </SelectPrimitive.Viewport>
        <SelectScrollDownButton />
      </SelectPrimitive.Content>
    </SelectPrimitive.Portal>
  )
}

function SelectLabel({
  className,
  ...props
}: React.ComponentProps<typeof SelectPrimitive.Label>) {
  if (React.useContext(NativeSelectContext)) {
    return (
      <legend data-slot="select-label" className={cn("text-muted-foreground px-2 py-1.5 text-xs", className)}>
        {props.children}
      </legend>
    )
  }

  return (
    <SelectPrimitive.Label
      data-slot="select-label"
      className={cn("text-muted-foreground px-2 py-1.5 text-xs", className)}
      {...props}
    />
  )
}

function SelectItem({
  className,
  children,
  ...props
}: React.ComponentProps<typeof SelectPrimitive.Item>) {
  if (React.useContext(NativeSelectContext)) {
    return (
      <option
        data-slot="select-item"
        value={props.value}
        disabled={props.disabled}
        className={cn(
          "hover:bg-accent focus:bg-accent focus:text-accent-foreground relative flex w-full cursor-pointer items-center gap-2 rounded-sm py-1.5 pe-2 ps-2 text-sm min-h-10 md:min-h-8 outline-hidden select-none disabled:cursor-not-allowed disabled:opacity-50",
          className
        )}
      >
        {children}
      </option>
    )
  }

  return (
    <SelectPrimitive.Item
      data-slot="select-item"
      className={cn(
        "focus:bg-accent focus:text-accent-foreground [&_svg:not([class*='text-'])]:text-muted-foreground relative flex w-full cursor-pointer items-center gap-2 rounded-sm py-1.5 pe-8 ps-2 text-sm min-h-10 md:min-h-8 outline-hidden select-none data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 *:[span]:last:flex *:[span]:last:items-center *:[span]:last:gap-2",
        className
      )}
      {...props}
    >
      <span
        data-slot="select-item-indicator"
        className="absolute end-2 flex size-3.5 items-center justify-center"
      >
        <SelectPrimitive.ItemIndicator>
          <CheckIcon className="size-4" />
        </SelectPrimitive.ItemIndicator>
      </span>
      <SelectPrimitive.ItemText>{children}</SelectPrimitive.ItemText>
    </SelectPrimitive.Item>
  )
}

function SelectSeparator({
  className,
  ...props
}: React.ComponentProps<typeof SelectPrimitive.Separator>) {
  if (React.useContext(NativeSelectContext)) {
    return <hr data-slot="select-separator" className={cn("bg-border -mx-1 my-1 h-px border-0", className)} />
  }

  return (
    <SelectPrimitive.Separator
      data-slot="select-separator"
      className={cn("bg-border pointer-events-none -mx-1 my-1 h-px", className)}
      {...props}
    />
  )
}

function SelectScrollUpButton({
  className,
  ...props
}: React.ComponentProps<typeof SelectPrimitive.ScrollUpButton>) {
  return (
    <SelectPrimitive.ScrollUpButton
      data-slot="select-scroll-up-button"
      className={cn(
        "flex cursor-pointer items-center justify-center py-1",
        className
      )}
      {...props}
    >
      <ChevronUpIcon className="size-4" />
    </SelectPrimitive.ScrollUpButton>
  )
}

function SelectScrollDownButton({
  className,
  ...props
}: React.ComponentProps<typeof SelectPrimitive.ScrollDownButton>) {
  return (
    <SelectPrimitive.ScrollDownButton
      data-slot="select-scroll-down-button"
      className={cn(
        "flex cursor-pointer items-center justify-center py-1",
        className
      )}
      {...props}
    >
      <ChevronDownIcon className="size-4" />
    </SelectPrimitive.ScrollDownButton>
  )
}

export {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectScrollDownButton,
  SelectScrollUpButton,
  SelectSeparator,
  SelectTrigger,
  SelectValue,
}
