import { SectionCard } from '@/components/verso/ui';
import { FieldList } from './field-input';
import type { FieldGroupDefinition, FieldPosition, FieldValues } from './types';

type Props = {
    groups: FieldGroupDefinition[];
    position: FieldPosition;
    values: FieldValues;
    onChange: (values: FieldValues) => void;
    errors: Record<string, string | undefined>;
};

// Field groups of one editor column, each in its own section card.
export function FieldGroupsPanel({
    groups,
    position,
    values,
    onChange,
    errors,
}: Props) {
    return (
        <>
            {groups
                .filter((group) => group.position === position)
                .map((group) => (
                    <SectionCard
                        key={group.key}
                        title={group.title}
                        description={group.description || undefined}
                        icon="fields"
                        contentClassName="grid gap-4"
                    >
                        <FieldList
                            fields={group.fields}
                            values={values}
                            onChange={onChange}
                            errors={errors}
                            path="meta.fields"
                        />
                    </SectionCard>
                ))}
        </>
    );
}
