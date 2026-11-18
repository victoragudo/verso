import { StatusBadge } from '@/components/verso/ui';
import type { StatusTone } from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import type { CapabilityRisk } from '@/types';

const RISKS: Record<CapabilityRisk, { tone: StatusTone; label: string }> = {
    low: { tone: 'neutral', label: msg('Low risk') },
    medium: { tone: 'info', label: msg('Medium risk') },
    high: { tone: 'warning', label: msg('High risk') },
    critical: { tone: 'danger', label: msg('Critical') },
};

// Soft badge for the risk of a capability. Low risk shows nothing to keep rows calm.
export function RiskBadge({
    risk,
    showLow = false,
}: {
    risk: CapabilityRisk;
    showLow?: boolean;
}) {
    if (risk === 'low' && !showLow) {
        return null;
    }

    const { tone, label } = RISKS[risk] ?? RISKS.low;

    return <StatusBadge tone={tone}>{t(label)}</StatusBadge>;
}
