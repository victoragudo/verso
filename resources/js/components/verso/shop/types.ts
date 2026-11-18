import type { StatusTone } from '@/components/verso/ui';

// Shapes sent by the Shop plugin admin controllers.

export type ShopProductRow = {
    id: number;
    title: string;
    status: string;
    updated_at?: string | null;
    price: { low: number; high: number } | null;
    sku: string | null;
    stock: number | null;
    tracksStock: boolean;
    variants: number;
    hasShopData: boolean;
    editUrl: string;
    contentUrl: string;
    viewUrl: string | null;
};

export type ShopGalleryItem = {
    id: number;
    url: string;
    thumbnail: string;
    alt: string;
    filename: string;
};

export type ShopVariantForm = {
    id: number | null;
    option_name: string;
    option_value: string;
    sku: string;
    price: string;
    stock: number;
};

export type ShopProductForm = {
    price: string;
    sku: string;
    track_stock: boolean;
    stock: number;
    gallery: ShopGalleryItem[];
    variants: ShopVariantForm[];
};

export type ShopOrderRow = {
    id: number;
    number: string;
    customer: string;
    email: string;
    status: string;
    statusLabel: string;
    tone: StatusTone;
    items: number;
    total: number;
    currency: string;
    payment: string;
    createdAt: string | null;
    url: string;
};

export type ShopOrderItem = {
    id: number;
    name: string;
    variant: string | null;
    sku: string | null;
    quantity: number;
    unitPrice: number;
    total: number;
};

export type ShopOrderChange = {
    id: number;
    from: string | null;
    to: string;
    user: string | null;
    note: string | null;
    createdAt: string | null;
};

export type ShopOrder = {
    id: number;
    number: string;
    status: string;
    statusLabel: string;
    tone: StatusTone;
    currency: string;
    createdAt: string | null;
    paidAt: string | null;
    customer: { name: string; email: string; phone: string | null };
    address: string[];
    notes: string | null;
    payment: { gateway: string; reference: string | null };
    items: ShopOrderItem[];
    totals: {
        subtotal: number;
        shipping: number;
        tax: number;
        taxRate: number;
        total: number;
    };
    history: ShopOrderChange[];
    customerUrl: string;
};

export type ShopSettingsForm = {
    currency: string;
    tax_rate: string;
    tax_shipping: boolean;
    shipping_rate: string;
    free_shipping_over: string;
    notify_email: string;
    bank_transfer_enabled: boolean;
    bank_transfer_instructions: string;
    stripe_enabled: boolean;
};
