import { Save, Trash, Undo2 } from 'lucide-react';
import { useId, useState, useSyncExternalStore } from 'react';
import { deletePreset, savePreset, stylePresets } from '@/builder/presets';
import {
    blockDesign,
    blockPreset,
    mergeDesign,
    withDesign,
    withPreset,
} from '@/builder/styles';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { OptionSelect } from '@/components/verso/ui/option-select';
import { t } from '@/lib/i18n';
import { destroy, store, update } from '@/routes/admin/editor/presets';
import type { Block } from '@/types';

type Props = {
    block: Block;
    onChange: (block: Block) => void;
    canEdit: boolean;
};

// Picks the style preset of a block. People who may edit presets can also save the block styles as a preset,
// put the block styles into its preset, or delete a saved preset.
export function StylePresetsBar({ block, onChange, canEdit }: Props) {
    const id = useId();
    const presets = useSyncExternalStore(
        stylePresets.subscribe,
        stylePresets.all,
        stylePresets.all,
    );
    const current = blockPreset(block);
    const preset = stylePresets.find(current);
    const design = blockDesign(block);
    const [naming, setNaming] = useState(false);
    const [name, setName] = useState('');
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState<string | null>(null);

    const run = async (task: () => Promise<void>, done: string) => {
        setBusy(true);
        setMessage(null);

        try {
            await task();
            setMessage(done);
        } catch (error) {
            setMessage(
                t('Could not save the preset: {reason}', {
                    reason: error instanceof Error ? error.message : '',
                }),
            );
        } finally {
            setBusy(false);
        }
    };

    const saveNew = () =>
        run(async () => {
            const saved = await savePreset(
                { store: store.url(), update: (key) => update(key).url },
                null,
                name.trim(),
                preset ? mergeDesign(preset.design, design) : design,
            );

            if (saved) {
                onChange(withDesign(withPreset(block, saved.key), {}));
            }

            setNaming(false);
            setName('');
        }, t('Preset saved. Other blocks can use it now.'));

    const updateCurrent = () =>
        preset &&
        run(async () => {
            await savePreset(
                { store: store.url(), update: (key) => update(key).url },
                preset.key,
                preset.label,
                mergeDesign(preset.design, design),
            );
            onChange(withDesign(block, {}));
        }, t('Preset updated on every block that uses it.'));

    const remove = () =>
        preset &&
        (preset.changed ||
            window.confirm(
                t('Delete the preset “{name}”?', {
                    name: preset.label,
                }),
            )) &&
        run(
            async () => {
                await deletePreset(destroy(preset.key).url);

                if (!stylePresets.find(preset.key)) {
                    onChange(withPreset(block, null));
                }
            },
            preset.changed
                ? t('Preset is back to its original.')
                : t('Preset deleted.'),
        );

    return (
        <div
            className="grid gap-2 rounded-md border p-3"
            data-test="style-presets"
        >
            <div className="grid gap-1">
                <Label htmlFor={`${id}-preset`} className="text-xs">
                    {t('Style preset')}
                </Label>
                <OptionSelect
                    id={`${id}-preset`}
                    size="sm"
                    value={current ?? ''}
                    onValueChange={(key) =>
                        onChange(withPreset(block, key === '' ? null : key))
                    }
                    options={[
                        { value: '', label: t('No preset') },
                        ...presets.map((item) => ({
                            value: item.key,
                            label: item.label,
                        })),
                        ...(current !== null && !preset
                            ? [
                                  {
                                      value: current,
                                      label: t('{name} (deleted)', {
                                          name: current,
                                      }),
                                  },
                              ]
                            : []),
                    ]}
                    searchPlaceholder={t('Search presets')}
                />
                <p className="text-xs text-muted-foreground">
                    {t('The block styles below go on top of the preset.')}
                </p>
            </div>
            {canEdit && (
                <div className="flex flex-wrap gap-1.5">
                    {naming ? (
                        <form
                            className="flex w-full flex-wrap items-end gap-1.5"
                            onSubmit={(event) => {
                                event.preventDefault();

                                if (name.trim() !== '') {
                                    void saveNew();
                                }
                            }}
                        >
                            <div className="grid min-w-0 flex-1 gap-1">
                                <Label
                                    htmlFor={`${id}-name`}
                                    className="text-xs"
                                >
                                    {t('Preset name')}
                                </Label>
                                <Input
                                    id={`${id}-name`}
                                    value={name}
                                    maxLength={60}
                                    autoFocus
                                    onChange={(event) =>
                                        setName(event.target.value)
                                    }
                                    className="h-8"
                                />
                            </div>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={busy || name.trim() === ''}
                            >
                                {t('Save')}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => setNaming(false)}
                            >
                                {t('Cancel')}
                            </Button>
                        </form>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={
                                busy ||
                                (Object.keys(design).length === 0 && !preset)
                            }
                            onClick={() => setNaming(true)}
                        >
                            <Save />
                            {t('Save as new preset')}
                        </Button>
                    )}
                    {preset && !naming && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={busy || Object.keys(design).length === 0}
                            onClick={() => void updateCurrent()}
                        >
                            <Save />
                            {t('Update {name}', { name: preset.label })}
                        </Button>
                    )}
                    {preset &&
                        !naming &&
                        (preset.source === 'saved' || preset.changed) && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={busy}
                                onClick={() => void remove()}
                            >
                                {preset.changed ? <Undo2 /> : <Trash />}
                                {preset.changed
                                    ? t('Back to original')
                                    : t('Delete preset')}
                            </Button>
                        )}
                </div>
            )}
            <p
                role="status"
                className="text-xs text-muted-foreground empty:hidden"
            >
                {message}
            </p>
        </div>
    );
}
