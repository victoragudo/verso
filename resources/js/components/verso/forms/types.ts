export type FormFieldType =
    | 'text'
    | 'email'
    | 'phone'
    | 'textarea'
    | 'select'
    | 'checkboxes'
    | 'radio'
    | 'date'
    | 'file'
    | 'consent'
    | 'hidden';

// One field of a form, in the shape the builder saves.
export type FormField = {
    id: string;
    type: FormFieldType;
    name: string;
    label: string;
    placeholder: string;
    help: string;
    required: boolean;
    width: 'full' | 'half';
    options: string[];
    min_length: number | null;
    max_length: number | null;
    accept: string[];
    max_kb: number | null;
    value: string;
};

export type FormSettings = {
    submit_label: string;
    success_message: string;
    redirect_url: string;
    notify_emails: string[];
    notify_subject: string;
    notify_body: string;
    confirmation_enabled: boolean;
    confirmation_subject: string;
    confirmation_body: string;
};

export type BuilderLimits = {
    max_fields: number;
    max_options: number;
    upload_types: string[];
    upload_max_kb: number;
    upload_default_kb: number;
    textarea_max: number;
};

export type SubmissionStatus = 'new' | 'read' | 'spam' | 'archived';

export type SubmissionRow = {
    id: number;
    status: SubmissionStatus;
    email: string | null;
    name: string | null;
    preview: string;
    form: { id: number; title: string };
    created_at: string | null;
    files?: number;
    url: string;
};
