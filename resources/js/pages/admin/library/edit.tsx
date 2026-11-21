import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useId, useState } from 'react';
import type { EditorManifest } from '@/builder';
import { findBlock, replaceBlock } from '@/builder/tree';
import type { LibraryItem } from '@/builder/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { EditorSettingsTabs } from '@/components/verso/content/editor-settings-tabs';
import type { EditorSettingsTab } from '@/components/verso/content/editor-settings-tabs';
import { BlockEditor } from '@/components/verso/editor/block-editor';
import { BlockInspector } from '@/components/verso/editor/block-inspector';
import { StatusBadge } from '@/components/verso/ui';
import { BuilderWorkspace } from '@/components/verso/workspace/builder-workspace';
import { t } from '@/lib/i18n';
import { destroy, index, update } from '@/routes/admin/library';
import type { Block, PluginBlockDefinition } from '@/types';

type Props = {
    item: LibraryItem;
    siteBlocks: PluginBlockDefinition[];
    editor?: EditorManifest;
    usedIn: { id: number; title: string; type: string; edit_url: string }[];
};

// One library item: its name, group, search words and blocks. Saving a synced block changes every page that uses it.
export default function LibraryEdit({
    item,
    siteBlocks,
    editor,
    usedIn,
}: Props) {
    const id = useId();
    const [data, setData] = useState({
        title: item.title,
        category: item.category,
        keywords: item.keywords.join(', '),
        blocks: item.blocks as Block[],
    });
    const [processing, setProcessing] = useState(false);
    const [confirm, setConfirm] = useState(false);
    const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null);
    const [settingsTab, setSettingsTab] =
        useState<EditorSettingsTab>('document');
    const errors =
        usePage<{ errors?: Record<string, string> }>().props.errors ?? {};
    const set = (patch: Partial<typeof data>) => setData({ ...data, ...patch });
    const synced = item.kind === 'synced';

    const save = () => {
        setProcessing(true);
        router.put(
            update(item.id).url,
            {
                ...data,
                keywords: data.keywords
                    .split(',')
                    .map((word) => word.trim())
                    .filter(Boolean),
            } as unknown as RequestPayload,
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    // A newly picked block opens the Block tab of the settings.
    const selectBlock = (blockId: string | null) => {
        if (blockId !== null && blockId !== selectedBlockId) {
            setSettingsTab('block');
        }

        setSelectedBlockId(blockId);
    };

    const details = (
        <div className="grid gap-5 text-sm">
            <section className="grid gap-3" aria-labelledby={`${id}-details`}>
                <h2 id={`${id}-details`} className="text-base font-semibold">
                    {t('Details')}
                </h2>
                <div className="grid gap-1">
                    <Label htmlFor={`${id}-title`} className="text-xs">
                        {t('Name')}
                    </Label>
                    <Input
                        id={`${id}-title`}
                        value={data.title}
                        maxLength={120}
                        required
                        aria-invalid={errors.title ? true : undefined}
                        aria-describedby={
                            errors.title ? `${id}-title-error` : undefined
                        }
                        onChange={(event) => set({ title: event.target.value })}
                        className="h-9"
                    />
                    {errors.title && (
                        <p
                            id={`${id}-title-error`}
                            className="text-xs text-destructive"
                        >
                            {errors.title}
                        </p>
                    )}
                </div>
                <div className="grid gap-1">
                    <Label htmlFor={`${id}-category`} className="text-xs">
                        {t('Group')}
                    </Label>
                    <Input
                        id={`${id}-category`}
                        value={data.category}
                        maxLength={60}
                        onChange={(event) =>
                            set({ category: event.target.value })
                        }
                        className="h-9"
                    />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor={`${id}-keywords`} className="text-xs">
                        {t('Search words, split by commas')}
                    </Label>
                    <Input
                        id={`${id}-keywords`}
                        value={data.keywords}
                        onChange={(event) =>
                            set({ keywords: event.target.value })
                        }
                        className="h-9"
                    />
                </div>
            </section>
            {synced && (
                <section
                    className="grid gap-2"
                    aria-labelledby={`${id}-used-in`}
                >
                    <h2
                        id={`${id}-used-in`}
                        className="text-base font-semibold"
                    >
                        {t('Used in')}
                    </h2>
                    {usedIn.length === 0 ? (
                        <p className="text-muted-foreground">
                            {t('No page uses it yet.')}
                        </p>
                    ) : (
                        <ul className="grid gap-1">
                            {usedIn.map((entry) => (
                                <li key={entry.id}>
                                    <Link
                                        href={entry.edit_url}
                                        className="underline underline-offset-2 hover:decoration-2"
                                    >
                                        {entry.title}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            )}
        </div>
    );

    return (
        <>
            <Head title={t('Edit {name}', { name: item.title })} />
            <BuilderWorkspace
                name="library"
                backHref={index().url}
                backLabel={t('Back to the library')}
                status={
                    <>
                        <h1 className="truncate text-sm font-semibold">
                            {data.title || item.title}
                        </h1>
                        <StatusBadge tone="neutral">
                            {synced ? t('Synced block') : t('Pattern')}
                        </StatusBadge>
                    </>
                }
                actions={
                    <Button
                        type="button"
                        disabled={processing}
                        onClick={save}
                        data-test="save-library-item"
                    >
                        {t('Save')}
                    </Button>
                }
                menu={
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => setConfirm(true)}
                    >
                        <Trash2 aria-hidden="true" />
                        {t('Delete')}
                    </DropdownMenuItem>
                }
                settingsDescription={t(
                    'Name, group and search words of this item, and the selected block.',
                )}
                settings={
                    <EditorSettingsTabs
                        tab={settingsTab}
                        onTabChange={setSettingsTab}
                        documentLabel={t('Details')}
                        document={details}
                        block={
                            <BlockInspector
                                block={findBlock(data.blocks, selectedBlockId)}
                                onChange={(next) =>
                                    set({
                                        blocks: replaceBlock(
                                            data.blocks,
                                            next.id,
                                            next,
                                        ),
                                    })
                                }
                                editor={editor}
                            />
                        }
                    />
                }
                tip={{
                    id: `library-${item.kind}`,
                    text: synced
                        ? t(
                              'A synced block: every page that uses it shows these blocks.',
                          )
                        : t(
                              'A pattern: pages get their own copy when it is added.',
                          ),
                }}
                canvasLabel={t('Blocks of {name}', { name: data.title })}
                canvas={
                    <>
                        {errors.blocks && (
                            <p
                                role="alert"
                                className="mb-3 rounded-md bg-danger-soft p-3 text-sm text-danger-text"
                            >
                                {errors.blocks}
                            </p>
                        )}
                        <BlockEditor
                            value={data.blocks}
                            onChange={(blocks) => set({ blocks })}
                            pluginBlocks={siteBlocks}
                            editor={editor}
                            onSelectBlock={selectBlock}
                        />
                    </>
                }
            />

            <Dialog open={confirm} onOpenChange={setConfirm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('Delete {name}?', { name: item.title })}
                        </DialogTitle>
                        <DialogDescription>
                            {synced
                                ? t(
                                      'Pages that use it will show nothing in its place. Delete it?',
                                  )
                                : t(
                                      'Delete this pattern? Pages that already use it keep their copy.',
                                  )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirm(false)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => router.delete(destroy(item.id).url)}
                        >
                            {t('Delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
