import type { AnchorHTMLAttributes, ReactNode } from 'react';
import { vi } from 'vitest';

// A light stand-in for @inertiajs/react, so components render without a server.
type PageProps = Record<string, unknown>;

const defaultProps: PageProps = {
    name: 'Verso',
    siteName: 'Harbor Lane Bakery',
    siteTimezone: 'UTC',
    auth: { user: null },
    sidebarOpen: true,
    versoNav: [],
    can: {},
    viewAs: null,
};

let pageProps: PageProps = { ...defaultProps };
let pageUrl = '/admin';
let pageFlash: Record<string, unknown> = {};

// Changes the props that usePage() returns in the next render.
export function setPageProps(props: PageProps): void {
    pageProps = { ...defaultProps, ...props };
}

export function resetPageProps(): void {
    pageProps = { ...defaultProps };
    pageUrl = '/admin';
    pageFlash = {};
}

// Changes the URL that usePage() returns, like "/admin/shop/orders".
export function setPageUrl(url: string): void {
    pageUrl = url;
}

// Changes the flash data that usePage() returns.
export function setPageFlash(flash: Record<string, unknown>): void {
    pageFlash = flash;
}

type RouterListener = (event: CustomEvent) => unknown;

const routerListeners = new Map<string, Set<RouterListener>>();

// Sends an Inertia router event to the listeners added with router.on(). Returns false when one cancels it.
export function fireRouterEvent(name: string, detail: unknown): boolean {
    const event = new CustomEvent(`inertia:${name}`, {
        cancelable: true,
        detail,
    });

    routerListeners.get(name)?.forEach((listener) => {
        if (listener(event) === false) {
            event.preventDefault();
        }
    });

    return !event.defaultPrevented;
}

export const router = {
    on: vi.fn((name: string, listener: RouterListener) => {
        const listeners = routerListeners.get(name) ?? new Set();
        listeners.add(listener);
        routerListeners.set(name, listeners);

        return () => listeners.delete(listener);
    }),
    visit: vi.fn(),
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    reload: vi.fn(),
};

type LinkProps = AnchorHTMLAttributes<HTMLAnchorElement> & {
    href: string;
    children?: ReactNode;
    method?: string;
    as?: string;
    preserveScroll?: boolean;
    preserveState?: boolean;
};

// Renders an Inertia link as a plain anchor and drops Inertia-only props.
export function Link({
    href,
    children,
    method,
    as,
    preserveScroll,
    preserveState,
    ...rest
}: LinkProps) {
    void as;
    void preserveScroll;
    void preserveState;

    return (
        <a href={href} data-method={method} {...rest}>
            {children}
        </a>
    );
}

export function usePage() {
    return {
        props: pageProps,
        url: pageUrl,
        component: 'admin/dashboard',
        flash: pageFlash,
    };
}

export function Head({ title }: { title?: string }) {
    return title ? <title>{title}</title> : null;
}

export const inertiaMock = { Link, usePage, router, Head };
