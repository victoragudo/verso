{{-- Admin bar on top of public pages for signed in admins. It is added after the page cache, so it is never cached. --}}
<style>
:root {
    --verso-adminbar-height: {{ $height }}px;
    /* Room that sticky headers leave above them: the bar stays fixed at the top, so they stop under it. */
    --verso-adminbar-offset: var(--verso-adminbar-height);
    padding-block-start: var(--verso-adminbar-height);
}

/* On narrow screens the bar scrolls away with the page, so sticky headers go to the very top. */
@media (max-width: 600px) {
    :root {
        --verso-adminbar-offset: 0px;
    }
}

.verso-adminbar,
.verso-adminbar *,
.verso-adminbar-skip {
    box-sizing: border-box;
}

.verso-adminbar {
    --verso-adminbar-bg: #18181b;
    --verso-adminbar-text: #f4f4f5;
    --verso-adminbar-muted: #a1a1aa;
    --verso-adminbar-hover: #27272a;
    --verso-adminbar-border: #3f3f46;
    --verso-adminbar-ring: #60a5fa;

    position: fixed;
    inset-block-start: 0;
    inset-inline: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    block-size: var(--verso-adminbar-height);
    margin: 0;
    padding-block: 0;
    padding-inline: 0.5rem;
    color-scheme: dark;
    background: var(--verso-adminbar-bg);
    color: var(--verso-adminbar-text);
    font-family: Figtree, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
    font-size: 0.8125rem;
    font-weight: 400;
    line-height: 1;
    letter-spacing: normal;
    text-align: start;
    text-transform: none;
    -webkit-font-smoothing: antialiased;
}

.verso-adminbar ul {
    display: flex;
    align-items: center;
    gap: 0.125rem;
    min-inline-size: 0;
    margin: 0;
    padding: 0;
    list-style: none;
}

.verso-adminbar li {
    min-inline-size: 0;
    margin: 0;
    padding: 0;
}

.verso-adminbar__item {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    max-inline-size: 100%;
    block-size: 1.75rem;
    margin: 0;
    padding-block: 0;
    padding-inline: 0.5rem;
    border: 0;
    border-radius: 0.375rem;
    background: transparent;
    color: inherit;
    font: inherit;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

.verso-adminbar__item[aria-expanded="true"] {
    background: var(--verso-adminbar-hover);
}

.verso-adminbar :is(a, button):focus-visible,
.verso-adminbar-skip:focus-visible {
    outline: 2px solid var(--verso-adminbar-ring);
    outline-offset: -2px;
}

.verso-adminbar__site {
    font-weight: 600;
}

.verso-adminbar__mark {
    inline-size: 1.125rem;
    block-size: 1.125rem;
    flex: none;
}

.verso-adminbar__chevron {
    inline-size: 0.625rem;
    block-size: 0.625rem;
    flex: none;
    opacity: 0.7;
}

.verso-adminbar__name {
    overflow: hidden;
    max-inline-size: 12rem;
    text-overflow: ellipsis;
}

.verso-adminbar__phone-only {
    display: none;
}

.verso-adminbar__cache {
    display: inline-block;
    padding-inline: 0.5rem;
    color: var(--verso-adminbar-muted);
    font-size: 0.75rem;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

/* The menus open in the top layer, under their button. */
.verso-adminbar .verso-adminbar__menu {
    position: fixed;
    inset: auto;
    inset-block-start: var(--verso-adminbar-height);
    display: revert;
    min-inline-size: 11rem;
    max-block-size: calc(100dvh - var(--verso-adminbar-height) - 1rem);
    margin: 0.25rem 0 0;
    padding: 0.25rem;
    overflow: auto;
    overscroll-behavior: contain;
    border: 1px solid var(--verso-adminbar-border);
    border-radius: 0.5625rem;
    background: var(--verso-adminbar-bg);
    color: var(--verso-adminbar-text);
    box-shadow: 0 8px 24px rgb(0 0 0 / 0.35);
}

.verso-adminbar .verso-adminbar__menu[data-align="end"] {
    inset-inline-end: 0.5rem;
}

@supports (position-area: block-end) {
    .verso-adminbar .verso-adminbar__menu {
        inset-block-start: auto;
        position-area: block-end span-inline-end;
        position-try-fallbacks: flip-inline;
    }

    .verso-adminbar .verso-adminbar__menu[data-align="end"] {
        inset-inline-end: auto;
        position-area: block-end span-inline-start;
    }
}

.verso-adminbar .verso-adminbar__menu li {
    display: block;
}

.verso-adminbar__menu-item {
    display: flex;
    align-items: center;
    inline-size: 100%;
    min-block-size: 2rem;
    margin: 0;
    padding-block: 0.375rem;
    padding-inline: 0.625rem;
    border: 0;
    border-radius: 0.25rem;
    background: transparent;
    color: inherit;
    font: inherit;
    text-align: start;
    text-decoration: none;
    cursor: pointer;
}

.verso-adminbar__menu form {
    margin: 0;
}

.verso-adminbar-skip {
    position: absolute;
    inset-block-start: 0.25rem;
    inset-inline-start: 0.25rem;
    z-index: 100000;
    padding-block: 0.5rem;
    padding-inline: 0.75rem;
    border-radius: 0.375rem;
    background: #f4f4f5;
    color: #18181b;
    font: 600 0.8125rem/1 Figtree, ui-sans-serif, system-ui, sans-serif;
    text-decoration: none;
    translate: 0 -200%;
}

.verso-adminbar-skip:focus {
    translate: 0 0;
}

/* Hover only on devices with a real pointer, so taps do not leave a stuck state. */
@media (hover: hover) {
    .verso-adminbar__item:hover,
    .verso-adminbar__menu-item:hover {
        background: var(--verso-adminbar-hover);
    }
}

@media (prefers-reduced-motion: no-preference) {
    .verso-adminbar__item,
    .verso-adminbar__menu-item {
        transition: background-color 150ms ease-out;
    }
}

/* Menus fade in. They also slide a little when motion is fine for the visitor. */
@supports selector(:popover-open) {
    .verso-adminbar .verso-adminbar__menu {
        opacity: 0;
        transition: opacity 150ms ease-out, display 150ms, overlay 150ms;
        transition-behavior: allow-discrete;
    }

    .verso-adminbar .verso-adminbar__menu:popover-open {
        opacity: 1;
        transition-duration: 100ms;
    }

    @starting-style {
        .verso-adminbar .verso-adminbar__menu:popover-open {
            opacity: 0;
        }
    }

    @media (prefers-reduced-motion: no-preference) {
        .verso-adminbar .verso-adminbar__menu {
            translate: 0 -0.25rem;
            transition: opacity 150ms ease-out, translate 150ms ease-out, display 150ms, overlay 150ms;
            transition-behavior: allow-discrete;
        }

        .verso-adminbar .verso-adminbar__menu:popover-open {
            translate: 0 0;
        }

        @starting-style {
            .verso-adminbar .verso-adminbar__menu:popover-open {
                translate: 0 -0.25rem;
            }
        }
    }
}

@media (max-width: 600px) {
    .verso-adminbar {
        position: absolute;
    }
}

/* Touch screens get a taller bar and bigger buttons, so a finger hits the right one. */
@media (pointer: coarse) {
    :root {
        --verso-adminbar-height: max({{ $height }}px, 2.75rem);
    }

    .verso-adminbar__item {
        justify-content: center;
        block-size: calc(var(--verso-adminbar-height) - 0.25rem);
        min-inline-size: 2.75rem;
    }

    .verso-adminbar__menu-item {
        min-block-size: 2.75rem;
    }
}

/* Phones keep the mark, Dashboard, Edit and the user menu. */
@media (width < 40rem) {
    .verso-adminbar__wide {
        display: none;
    }

    .verso-adminbar__phone-hidden {
        position: absolute;
        inline-size: 1px;
        block-size: 1px;
        overflow: hidden;
        clip-path: inset(50%);
        white-space: nowrap;
    }

    .verso-adminbar__name {
        max-inline-size: 7rem;
    }

    .verso-adminbar__phone-only {
        display: inline;
    }
}

@media (forced-colors: active) {
    .verso-adminbar {
        border-block-end: 1px solid CanvasText;
    }

    .verso-adminbar .verso-adminbar__menu {
        border-color: CanvasText;
    }

    .verso-adminbar__item[aria-expanded="true"] {
        outline: 1px solid CanvasText;
    }

    .verso-adminbar :is(a, button):focus-visible,
    .verso-adminbar-skip:focus-visible {
        outline-color: Highlight;
    }
}

@media print {
    :root {
        padding-block-start: 0;
    }

    .verso-adminbar,
    .verso-adminbar-skip {
        display: none;
    }
}
</style>
<a class="verso-adminbar-skip" href="#{{ $skipTarget }}">{{ __('Skip to content') }}</a>
<nav class="verso-adminbar" aria-label="{{ __('Site administration') }}">
    <ul>
        <li>
            <a class="verso-adminbar__item verso-adminbar__site" href="{{ $dashboardUrl }}">
                <svg class="verso-adminbar__mark" viewBox="0 0 32 32" aria-hidden="true" focusable="false"><path fill="currentColor" d="M2.5 6 10.5 4l4.75 12.5V28ZM16.75 16.5 21.5 4l8 2-12.75 22Z"/></svg>
                <span class="verso-adminbar__phone-hidden verso-adminbar__name">{{ $siteName }}</span>
            </a>
        </li>
        <li><a class="verso-adminbar__item" href="{{ $dashboardUrl }}">{{ __('Dashboard') }}</a></li>
        @if ($newLinks !== [])
            <li class="verso-adminbar__wide">
                <button class="verso-adminbar__item" type="button" popovertarget="verso-adminbar-new" aria-controls="verso-adminbar-new" aria-expanded="false">
                    {{ __('New') }}
                    <svg class="verso-adminbar__chevron" viewBox="0 0 10 10" aria-hidden="true" focusable="false"><path d="M2 3.75 5 6.75l3-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <ul id="verso-adminbar-new" class="verso-adminbar__menu" popover="auto" data-verso-adminbar-menu data-align="start">
                    @foreach ($newLinks as $link)
                        <li><a class="verso-adminbar__menu-item" href="{{ $link->url }}">{{ $link->label }}</a></li>
                    @endforeach
                </ul>
            </li>
        @endif
        @if ($editLink !== null && $visualEditUrl !== null)
            <li><a class="verso-adminbar__item" href="{{ $visualEditUrl }}" data-verso-visual-edit><span class="verso-adminbar__phone-only" aria-hidden="true">{{ __('Edit') }}</span><span class="verso-adminbar__phone-hidden">{{ __('Edit :type', ['type' => $editLink->label]) }}</span></a></li>
            <li class="verso-adminbar__wide">
                <button class="verso-adminbar__item" type="button" popovertarget="verso-adminbar-edit" aria-controls="verso-adminbar-edit" aria-expanded="false" aria-label="{{ __('More ways to edit') }}" title="{{ __('More ways to edit') }}">
                    <svg class="verso-adminbar__chevron" viewBox="0 0 10 10" aria-hidden="true" focusable="false"><path d="M2 3.75 5 6.75l3-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <ul id="verso-adminbar-edit" class="verso-adminbar__menu" popover="auto" data-verso-adminbar-menu data-align="start">
                    <li><a class="verso-adminbar__menu-item" href="{{ $editLink->url }}">{{ __('Advanced editor') }}</a></li>
                </ul>
            </li>
        @elseif ($editLink !== null)
            <li><a class="verso-adminbar__item" href="{{ $editLink->url }}"><span class="verso-adminbar__phone-only" aria-hidden="true">{{ __('Edit') }}</span><span class="verso-adminbar__phone-hidden">{{ __('Edit :type', ['type' => $editLink->label]) }}</span></a></li>
        @endif
        @if ($customizeUrl !== null)
            <li class="verso-adminbar__wide"><a class="verso-adminbar__item" href="{{ $customizeUrl }}">{{ __('Customize') }}</a></li>
        @endif
    </ul>
    <ul>
        <li class="verso-adminbar__wide"><span class="verso-adminbar__cache">{{ __('Cache: :status', ['status' => $cacheStatus]) }}</span></li>
        <li>
            <button class="verso-adminbar__item" type="button" popovertarget="verso-adminbar-user" aria-controls="verso-adminbar-user" aria-expanded="false">
                <span class="verso-adminbar__name">{{ $userName }}</span>
                <svg class="verso-adminbar__chevron" viewBox="0 0 10 10" aria-hidden="true" focusable="false"><path d="M2 3.75 5 6.75l3-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <ul id="verso-adminbar-user" class="verso-adminbar__menu" popover="auto" data-verso-adminbar-menu data-align="end">
                <li><a class="verso-adminbar__menu-item" href="{{ $profileUrl }}">{{ __('Profile') }}</a></li>
                <li>
                    <form method="POST" action="{{ $logoutUrl }}">
                        <input type="hidden" name="_token" value="{{ $csrfToken }}">
                        <button class="verso-adminbar__menu-item" type="submit">{{ __('Log out') }}</button>
                    </form>
                </li>
            </ul>
        </li>
    </ul>
</nav>
@if ($skipTarget === $fallbackTarget)
    <span id="{{ $fallbackTarget }}" tabindex="-1"></span>
@endif
<script>
// Keeps aria-expanded in sync with the menus, and places them when the browser has no anchor positioning.
(() => {
    const bar = document.querySelector('.verso-adminbar');
    if (!bar) return;
    const native = Object.hasOwn(HTMLElement.prototype, 'popover');
    const anchored = CSS.supports('position-area', 'block-end');
    for (const menu of bar.querySelectorAll('[data-verso-adminbar-menu]')) {
        const trigger = bar.querySelector('[aria-controls="' + menu.id + '"]');
        if (!trigger) continue;
        if (!native) {
            menu.hidden = true;
            trigger.addEventListener('click', () => {
                menu.hidden = !menu.hidden;
                trigger.setAttribute('aria-expanded', String(!menu.hidden));
            });
            continue;
        }
        menu.addEventListener('toggle', (event) => {
            const open = event.newState === 'open';
            trigger.setAttribute('aria-expanded', String(open));
            if (!open || anchored) return;
            const box = trigger.getBoundingClientRect();
            const width = menu.getBoundingClientRect().width;
            const left = menu.dataset.align === 'end' ? box.right - width : box.left;
            menu.style.insetInlineEnd = 'auto';
            menu.style.left = Math.max(0, Math.min(left, innerWidth - width)) + 'px';
            menu.style.top = box.bottom + 'px';
        });
    }
})();
</script>
