import { useState } from 'react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { hostOf, truncate } from './seo-text';

type Network = 'facebook' | 'x';

const NETWORK_NAMES: Record<Network, string> = {
    facebook: 'Facebook',
    x: 'X',
};

type Props = {
    title: string;
    description: string;
    image: string | null;
    url: string;
};

// How a shared link may look on Facebook, LinkedIn and others (Open Graph) or on X.
export function SocialPreview({ title, description, image, url }: Props) {
    const [network, setNetwork] = useState<Network>('facebook');
    const host = hostOf(url);

    return (
        <div className="grid min-w-0 gap-2">
            <div className="flex items-center justify-between gap-2">
                <p className="text-xs font-medium text-muted-foreground">
                    {t('Share preview')}
                </p>
                <ToggleGroup
                    type="single"
                    size="sm"
                    variant="outline"
                    value={network}
                    onValueChange={(value) =>
                        value && setNetwork(value as Network)
                    }
                    aria-label={t('Social network')}
                >
                    <ToggleGroupItem value="facebook" className="px-2 text-xs">
                        {NETWORK_NAMES.facebook}
                    </ToggleGroupItem>
                    <ToggleGroupItem value="x" className="px-2 text-xs">
                        {NETWORK_NAMES.x}
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>
            <figure
                className={cn(
                    'min-w-0 overflow-hidden border bg-card',
                    network === 'x' ? 'rounded-2xl' : 'rounded-md',
                )}
                aria-label={t('Share preview on {network}', {
                    network: NETWORK_NAMES[network],
                })}
                data-network={network}
            >
                {image ? (
                    <img
                        src={image}
                        alt=""
                        className="aspect-[1.91/1] w-full bg-muted object-cover"
                        loading="lazy"
                    />
                ) : (
                    <div className="grid aspect-[1.91/1] w-full place-items-center bg-muted px-4 text-center text-xs text-muted-foreground">
                        {t('No image. Add a featured or share image.')}
                    </div>
                )}
                {network === 'facebook' ? (
                    <div className="grid gap-0.5 border-t bg-muted/40 px-3 py-2">
                        <p className="truncate text-[0.7rem] text-muted-foreground uppercase">
                            {host}
                        </p>
                        <p className="line-clamp-2 text-sm font-semibold break-words text-foreground">
                            {truncate(title || t('Untitled'), 88)}
                        </p>
                        {description && (
                            <p className="line-clamp-1 text-xs break-words text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="grid gap-0.5 px-3 py-2">
                        <p className="line-clamp-1 text-sm break-words text-foreground">
                            {truncate(title || t('Untitled'), 70)}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {t('From {host}', { host })}
                        </p>
                    </div>
                )}
            </figure>
        </div>
    );
}
