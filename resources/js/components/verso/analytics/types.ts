// Data shapes sent by the Analytics plugin controller.

export type TrafficPoint = {
    date: string;
    visitors: number;
    views: number;
};

export type RankedItem = {
    value: string;
    views: number;
    visitors: number;
};

export type DeviceShare = {
    device: string;
    label: string;
    views: number;
    visitors: number;
    share: number;
};

export type AnalyticsReport = {
    from: string;
    to: string;
    totals: { visitors: number; views: number };
    series: TrafficPoint[];
    pages: RankedItem[];
    referrers: RankedItem[];
    devices: DeviceShare[];
};

export type AnalyticsSettings = {
    retention_days: number;
    ignore_roles: string[];
};

export type RoleOption = { value: string; label: string };
