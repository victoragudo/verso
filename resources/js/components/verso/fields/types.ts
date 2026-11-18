// Shapes of field groups and fields, as the server sends them (see FieldGroup::toArray in PHP).

export type FieldType =
    | 'text'
    | 'textarea'
    | 'rich_text'
    | 'number'
    | 'email'
    | 'url'
    | 'select'
    | 'multi_select'
    | 'checkbox'
    | 'radio'
    | 'toggle'
    | 'date'
    | 'date_time'
    | 'color'
    | 'image'
    | 'file'
    | 'gallery'
    | 'entry_relation'
    | 'term_relation'
    | 'user_relation'
    | 'repeater'
    | 'group';

export type FieldOption = { value: string; label: string };

export type ConditionValue = string | number | boolean | null;

export type FieldCondition = {
    field: string;
    operator: '==' | '!=';
    value: ConditionValue;
};

export type FieldDefinition = {
    name: string;
    label: string;
    type: FieldType;
    required: boolean;
    instructions?: string;
    default?: string | number | boolean | string[] | null;
    placeholder?: string;
    options?: FieldOption[];
    min?: number | null;
    max?: number | null;
    step?: number | null;
    max_length?: number | null;
    multiple?: boolean;
    content_types?: string[];
    taxonomy?: string | null;
    roles?: string[];
    min_rows?: number | null;
    max_rows?: number | null;
    condition?: FieldCondition | null;
    fields?: FieldDefinition[];
};

export type LocationParam =
    | 'content_type'
    | 'page_template'
    | 'taxonomy_term'
    | 'user_role';

export type LocationRule = {
    param: LocationParam;
    operator: '==' | '!=';
    value: string;
};

export type FieldPosition = 'main' | 'sidebar';

export type FieldGroupDefinition = {
    key: string;
    title: string;
    description: string;
    position: FieldPosition;
    order: number;
    active: boolean;
    location: LocationRule[][];
    fields: FieldDefinition[];
};

export type FieldValues = Record<string, unknown>;

// What the editor knows about the entry while it is being edited.
export type LocationState = {
    contentType: string;
    template: string;
    termIds: number[];
};

export type ChoiceOption = { value: string; label: string; group?: string };
