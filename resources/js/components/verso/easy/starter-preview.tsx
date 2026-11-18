import { previewShape } from '@/lib/easy/starters';
import type { PreviewShape } from '@/lib/easy/starters';
import { cn } from '@/lib/utils';

type Props = {
    sections: string[];
    className?: string;
};

// Bars used inside the drawing of each shape.
function Bar({ className }: { className?: string }) {
    return <span className={cn('block h-1.5 rounded-full', className)} />;
}

// One section drawn as a tiny block, so the card shows the shape of the home page.
function Shape({ shape }: { shape: PreviewShape }) {
    switch (shape) {
        case 'banner':
            return (
                <span className="flex flex-col gap-1.5 rounded-sm bg-zinc-200 p-2.5 dark:bg-zinc-700">
                    <Bar className="w-2/3 bg-zinc-500" />
                    <Bar className="w-1/2 bg-zinc-400" />
                    <span className="mt-0.5 block h-2.5 w-10 rounded-sm bg-brand" />
                </span>
            );
        case 'split':
            return (
                <span className="grid grid-cols-2 gap-2">
                    <span className="flex flex-col gap-1">
                        <Bar className="w-3/4 bg-zinc-400" />
                        <Bar className="bg-zinc-300 dark:bg-zinc-600" />
                        <Bar className="w-5/6 bg-zinc-300 dark:bg-zinc-600" />
                    </span>
                    <span className="block h-7 rounded-sm bg-zinc-300 dark:bg-zinc-600" />
                </span>
            );
        case 'cards':
        case 'grid':
            return (
                <span className="grid grid-cols-3 gap-1.5">
                    {[0, 1, 2].map((index) => (
                        <span
                            key={index}
                            className={cn(
                                'block rounded-sm bg-zinc-200 dark:bg-zinc-700',
                                shape === 'grid' ? 'h-7' : 'h-5',
                            )}
                        />
                    ))}
                </span>
            );
        case 'quotes':
            return (
                <span className="grid grid-cols-2 gap-1.5">
                    {[0, 1].map((index) => (
                        <span
                            key={index}
                            className="flex flex-col gap-1 rounded-sm bg-zinc-100 p-1.5 dark:bg-zinc-800"
                        >
                            <Bar className="bg-zinc-300 dark:bg-zinc-600" />
                            <Bar className="w-1/2 bg-zinc-400" />
                        </span>
                    ))}
                </span>
            );
        case 'list':
            return (
                <span className="flex flex-col gap-1">
                    <Bar className="w-1/2 bg-zinc-400" />
                    <Bar className="w-5/6 bg-zinc-300 dark:bg-zinc-600" />
                    <Bar className="w-2/3 bg-zinc-300 dark:bg-zinc-600" />
                </span>
            );
        case 'form':
            return (
                <span className="flex flex-col gap-1 rounded-sm border border-zinc-200 p-1.5 dark:border-zinc-700">
                    <span className="block h-2 rounded-sm bg-zinc-100 dark:bg-zinc-800" />
                    <span className="block h-2 rounded-sm bg-zinc-100 dark:bg-zinc-800" />
                    <span className="block h-2 w-8 rounded-sm bg-brand" />
                </span>
            );
        case 'map':
            return (
                <span className="relative block h-7 rounded-sm bg-emerald-100 dark:bg-emerald-950">
                    <span className="absolute top-1/2 left-1/2 block size-2 -translate-1/2 rounded-full bg-brand" />
                </span>
            );
        default:
            return (
                <span className="flex items-center justify-between gap-2 rounded-sm bg-zinc-100 p-1.5 dark:bg-zinc-800">
                    <Bar className="w-1/2 bg-zinc-400" />
                    <span className="block h-2.5 w-8 rounded-sm bg-brand" />
                </span>
            );
    }
}

// A small drawing of the home page of a starter site: one shape per section, top to bottom.
export function StarterPreview({ sections, className }: Props) {
    return (
        <span
            aria-hidden="true"
            data-testid="starter-preview"
            className={cn(
                'flex h-40 flex-col gap-2 overflow-hidden rounded-md border bg-white p-3 dark:bg-zinc-900',
                className,
            )}
        >
            <span className="flex items-center justify-between">
                <Bar className="w-10 bg-zinc-500" />
                <span className="flex gap-1">
                    <Bar className="w-4 bg-zinc-300 dark:bg-zinc-600" />
                    <Bar className="w-4 bg-zinc-300 dark:bg-zinc-600" />
                    <Bar className="w-4 bg-zinc-300 dark:bg-zinc-600" />
                </span>
            </span>
            {sections.slice(0, 4).map((section, index) => (
                <span
                    key={`${section}-${index}`}
                    data-shape={previewShape(section)}
                    className="block"
                >
                    <Shape shape={previewShape(section)} />
                </span>
            ))}
        </span>
    );
}
