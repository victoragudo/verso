// Shared Verso types used by the admin UI and every module.

import type { CssValueRule } from '@/lib/css-value';

// Sidebar section keys, in sidebar order. Matches the NavGroup enum.
export type VersoNavGroup =
    | 'dashboard'
    | 'content'
    | 'design'
    | 'tools'
    | 'administration'
    | 'settings';

// One sidebar link. Children point to their parent with "parent".
export type VersoNavItem = {
    key: string;
    label: string;
    href: string;
    icon: string;
    group: VersoNavGroup;
    parent?: string | null;
    keywords?: string[];
    plugin?: string | null;
    isNew?: boolean;
};

export type DashboardStat = {
    key: string;
    label: string;
    value: number;
    href: string | null;
    icon: string | null;
};

// One row in a dashboard panel, like a recent entry.
export type DashboardPanelItem = {
    key: string;
    title: string;
    meta: string | null;
    href: string | null;
    status: string | null;
};

export type DashboardPanel = {
    key: string;
    label: string;
    icon: string;
    emptyText: string;
    href: string | null;
    items: DashboardPanelItem[];
};

// A message box at the top of the dashboard, like a new Verso release.
export type DashboardNotice = {
    key: string;
    icon: string | null;
    title: string;
    description: string | null;
    href: string | null;
    action: string | null;
    tone: 'info' | 'warning' | 'success' | 'danger' | 'neutral';
};

// A Verso release from the release feed, with safe HTML notes.
export type CoreRelease = {
    version: string;
    name: string;
    notes_html: string;
    published_at: string | null;
    pre_release: boolean;
    security: boolean;
    page_url: string | null;
    package_size: number | null;
    signed: boolean;
};

export type CoreUpdateStatus =
    | 'pending'
    | 'running'
    | 'completed'
    | 'failed'
    | 'rolled_back';

export type CoreUpdateStepStatus =
    | 'pending'
    | 'running'
    | 'done'
    | 'failed'
    | 'skipped'
    | 'warning';

// One row of the core update log.
export type CoreUpdateSummary = {
    id: number;
    from_version: string;
    to_version: string;
    status: CoreUpdateStatus;
    status_label: string;
    trigger: 'admin' | 'cli' | 'automatic';
    trigger_label: string;
    user: string | null;
    error: string | null;
    created_at: string | null;
    started_at: string | null;
    finished_at: string | null;
    has_backup: boolean;
};

export type CoreUpdateStep = {
    key: string;
    label: string;
    status: CoreUpdateStepStatus;
    message: string | null;
    at: string | null;
};

// A core update run with every planned step.
export type CoreUpdateDetail = CoreUpdateSummary & {
    finished: boolean;
    steps: CoreUpdateStep[];
};

// One block of entry content. See "Blocks" in docs/ARCHITECTURE.md.
export type Block = {
    id: string;
    type: string;
    attrs: Record<string, unknown>;
    content?: string;
    children?: Block[];
};

// Block types that ship with Verso. Plugins can register more.
export type CoreBlockType =
    | 'paragraph'
    | 'heading'
    | 'list'
    | 'quote'
    | 'code'
    | 'image'
    | 'gallery'
    | 'embed'
    | 'columns'
    | 'column'
    | 'separator'
    | 'button'
    | 'html'
    | 'table'
    | 'video';

// Kinds of inputs a plugin block field can use.
export type PluginBlockFieldType =
    | 'text'
    | 'textarea'
    | 'number'
    | 'length'
    | 'url'
    | 'toggle'
    | 'select'
    | 'media'
    | 'icon'
    | 'color'
    | 'datetime';

// One attr of a plugin block, described by the plugin on the server.
export type PluginBlockField = {
    name: string;
    label: string;
    type: PluginBlockFieldType;
    default: unknown;
    options: { value: string; label: string }[];
    help: string;
    required: boolean;
    min: number | null;
    max: number | null;
    searchable?: boolean;
    // Length fields only: the units, keywords and numbers the value accepts.
    value?: CssValueRule | null;
};

// A block type added by a plugin, shared with the entry editor.
export type PluginBlockDefinition = {
    name: string;
    label: string;
    description: string;
    fields: PluginBlockField[];
    children: boolean;
    // Only these block types may go inside, or null for any.
    childTypes?: string[] | null;
    // The block may only sit directly inside these block types, or null for anywhere.
    parents?: string[] | null;
    // Children the block starts with when people add it (without ids).
    starter?: PatternBlock[];
    // True when the block can also be written as a shortcode in text.
    shortcode?: boolean;
    // Shortcode attr names that mean a block attr, like { slug: 'form' }.
    aliases?: Record<string, string>;
    // Group of the block in the Add panel, like "text".
    category?: string;
};

// A box added by a plugin to the entry editor side panel.
// A page template the active theme offers for one content type.
export type PageTemplateOption = {
    value: string;
    label: string;
    description: string;
};

// A part with styles (like the header) and the styles a page or template can pick for it.
export type PartChoice = {
    name: string;
    label: string;
    default: string;
    options: PageTemplateOption[];
};

// Header and footer styles saved for one page template in the Site Editor.
export type TemplatePartsRow = {
    name: string;
    label: string;
    themeParts: Record<string, string>;
    saved: Record<string, string>;
    styles: Record<string, string>;
};

// A ready-made group of blocks from the active theme, inserted with the "Patterns" button.
export type BlockPattern = {
    name: string;
    title: string;
    category: string;
    description: string;
    keywords: string[];
    blocks: PatternBlock[];
};

// A block inside a pattern file. Ids are given when the pattern is inserted.
export type PatternBlock = {
    id?: string;
    type: string;
    attrs?: Record<string, unknown>;
    content?: string;
    children?: PatternBlock[];
};

export type PluginEntryPanel = {
    key: string;
    title: string;
    description: string;
    fields: PluginBlockField[];
};

// One image stored inside a gallery block.
export type GalleryImage = {
    mediaId: number | null;
    src: string;
    alt: string;
    caption?: string;
    width?: number | null;
    height?: number | null;
};

export type MediaItem = {
    id: number;
    url: string;
    filename: string;
    mime_type: string;
    size: number;
    width: number | null;
    height: number | null;
    alt: string | null;
    caption: string | null;
};

export type MediaKind = 'image' | 'video' | 'audio' | 'document';

// A media item as the media library and the picker send it.
export type MediaLibraryItem = MediaItem & {
    path: string;
    type: MediaKind;
    thumbnail_url: string | null;
    uploaded_by: string | null;
    created_at: string | null;
    can: { update: boolean; delete: boolean };
};

// An entry that uses a media file.
export type MediaUsageEntry = {
    id: number;
    title: string;
    type: string;
    trashed: boolean;
    edit_url: string;
};

export type MediaDetail = MediaLibraryItem & {
    usage: MediaUsageEntry[];
};

export type MediaLibraryPage = {
    data: MediaLibraryItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
};

export type EntryStatus = 'draft' | 'scheduled' | 'published' | 'trash';

export type ContentTypeInfo = {
    name: string;
    label: string;
    pluralLabel: string;
    hierarchical: boolean;
    hasArchive: boolean;
    taxonomies: string[];
    icon: string;
};

export type TaxonomyInfo = {
    name: string;
    label: string;
    pluralLabel: string;
    hierarchical: boolean;
    icon: string;
};

export type Term = {
    id: number;
    taxonomy: string;
    name: string;
    slug: string;
    parent_id: number | null;
    description: string | null;
    entries_count?: number;
    url?: string;
};

export type EntryAuthor = {
    id: number;
    name: string;
};

export type Entry = {
    id: number;
    type: string;
    title: string;
    slug: string;
    status: EntryStatus;
    blocks: Block[];
    excerpt: string | null;
    author: EntryAuthor | null;
    parent_id: number | null;
    menu_order: number;
    published_at: string | null;
    meta: Record<string, unknown>;
    url: string;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    terms?: Record<string, number[]>;
};

export type RevisionSummary = {
    id: number;
    title: string;
    author: string | null;
    created_at: string;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

// General site settings, edited on the Site settings page.
export type SiteSettings = {
    name: string;
    tagline: string;
    url: string;
    locale: string;
    timezone: string;
    posts_per_page: number;
    front_page: number | null;
    comments_enabled: boolean;
    revisions_limit: number;
};

export type SiteSettingsOptions = {
    locales: string[];
    timezones: string[];
    pages: { id: number; title: string }[];
};

// SEO fields of an entry, stored in entry.meta.seo.
export type EntrySeoFields = {
    title: string | null;
    description: string | null;
    canonical: string | null;
    noindex: boolean;
    og_image: string | null;
    sitemap_exclude: boolean;
    focus_keyphrase: string | null;
    nofollow: boolean;
    og_title: string | null;
    og_description: string | null;
    // Score of the editor checks from 0 to 100, saved for the traffic light in lists.
    score: number | null;
};

// One row of the redirects admin list.
export type RedirectRow = {
    id: number;
    from_path: string;
    to_path: string;
    status_code: number;
    hits: number;
    created_at: string | null;
};

export type MenuItemType = 'entry' | 'term' | 'custom';

// One item of a menu, as sent by the menu builder page.
export type MenuItemNode = {
    id: number | null;
    type: MenuItemType;
    entry_id: number | null;
    term_id: number | null;
    label: string | null;
    url: string | null;
    open_in_new_tab: boolean;
    // Shows the sub items as a wide panel in themes that support it.
    mega: boolean;
    // Extra rel words of the link, like "nofollow" or "sponsored".
    rel: string[];
    visibility: MenuItemVisibility;
    title: string;
    visible: boolean;
    children: MenuItemNode[];
};

// Who sees a menu item on the site.
export type MenuItemVisibility = 'everyone' | 'members' | 'guests';

export type MenuSummary = {
    id: number;
    name: string;
    location: string | null;
    items_count: number;
};

export type MenuLocation = {
    name: string;
    label: string;
};

// A group of entries or terms that can be added to a menu.
export type MenuSourceItem = { id: number; title: string };

export type MenuSource = {
    kind: 'entry' | 'term';
    name: string;
    label: string;
    items: MenuSourceItem[];
    hasMore: boolean;
};

export type UserRole =
    | 'admin'
    | 'editor'
    | 'author'
    | 'contributor'
    | 'subscriber'
    | 'shop_manager';

export type RoleOption = {
    value: UserRole;
    label: string;
};

// One row of the users admin list.
export type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    role_label: string;
    roles: { slug: string; name: string }[];
    can: { promote: boolean; delete: boolean };
    invited: boolean;
    entries_count: number;
    created_at: string | null;
    updated_at?: string | null;
    two_factor?: boolean;
    is_self: boolean;
};

export type CommentStatus = 'pending' | 'approved' | 'spam' | 'trash';

// One row of the comments moderation list.
export type AdminComment = {
    id: number;
    status: CommentStatus;
    author_name: string;
    author_email: string | null;
    author_url: string | null;
    is_member: boolean;
    body: string;
    excerpt: string;
    parent_id: number | null;
    entry: { id: number; title: string; url: string | null } | null;
    created_at: string | null;
};

export type PluginStatus = 'enabled' | 'disabled' | 'invalid' | 'incompatible';

// One plugin found in plugins/<slug>, as shown on the admin Plugins page.
export type PluginInfo = {
    slug: string;
    name: string;
    version: string | null;
    description: string | null;
    requires: string | null;
    permissions: string[];
    status: PluginStatus;
    enabled: boolean;
    problems: string[];
    removable: boolean;
    risk: RiskLevel | null;
    location?: PluginLocation | null;
};

// Where an enabled plugin is in the sidebar, like Tools > Shop.
export type PluginLocation = {
    path: string[];
    key: string;
    href: string | null;
};

// An installed theme on the Plugins and themes page.
export type InstalledTheme = {
    slug: string;
    name: string;
    version: string;
    description: string;
    parentName: string | null;
    active: boolean;
    removable: boolean;
    risk: RiskLevel | null;
};

// A plugin on its detail page, with the network hosts it declares.
export type PluginDetail = Omit<PluginInfo, 'risk'> & {
    network: string[];
};

export type RiskLevel = 'low' | 'medium' | 'high';

export type FindingSeverity = 'info' | 'low' | 'medium' | 'high';

// One problem found by the plugin checks.
export type VerificationFinding = {
    rule: string;
    severity: FindingSeverity;
    message: string;
    file: string | null;
    line: number | null;
};

// What happened when the plugin was booted in the sandbox process.
export type SandboxRun = {
    status: 'passed' | 'failed' | 'timeout' | 'crashed' | 'skipped';
    seconds: number;
    migrated: string[];
    rolledBack: string[];
    routes: { uri: string; name: string | null; methods: string[] }[];
    errors: {
        stage: string;
        message: string;
        file: string | null;
        line: number | null;
    }[];
    output: string;
    // Pages a theme rendered with demo content, with their HTML when it was kept.
    pages?: PreviewPage[];
};

// One page a theme rendered in the sandbox.
export type PreviewPage = {
    // "home", "entry" or "archive".
    name: string;
    path: string;
    status: number;
    seconds: number;
    bytes: number;
    html: string;
};

// What the theme checks learned about a theme: its manifest, outside hosts and quality markers.
export type ThemeReportDetails = {
    parent: string | null;
    requires: string | null;
    php: string | null;
    screenshot: string | null;
    network: string[];
    hosts: string[];
    quality: Record<ThemeQualityMarker, boolean>;
};

export type ThemeQualityMarker =
    | 'seo'
    | 'head'
    | 'footer'
    | 'skipLink'
    | 'lang';

// A stored verification report of a plugin or theme.
export type VerificationReport = {
    id: number;
    kind: ExtensionKind;
    // Rules that stop the install whatever the risk, like missing theme views.
    blocking: string[];
    slug: string;
    version: string;
    risk: RiskLevel;
    score: number;
    counts: Record<FindingSeverity, number>;
    findings: VerificationFinding[];
    details: {
        name?: string;
        files?: number;
        permissions?: {
            declared: string[];
            used: string[];
            network: string[];
            hosts: string[];
        };
        sandbox?: SandboxRun;
        theme?: ThemeReportDetails;
        blocking?: string[];
    };
    source: 'upload' | 'registry' | 'installed';
    checkedAt: string | null;
    installedAt: string | null;
};

// One server check of the installer, like "PHP 8.4 or newer".
export type InstallRequirement = {
    group: 'php' | 'extensions' | 'folders';
    label: string;
    passed: boolean;
    detail: string;
    fix: string;
};

export type ImportStatus = 'pending' | 'running' | 'completed' | 'failed';

// One import run, as listed on the import page.
export type ImportSummary = {
    id: number;
    status: ImportStatus;
    status_label: string;
    original_name: string;
    processed: number;
    total: number | null;
    percent: number | null;
    user: string | null;
    created_at: string | null;
    finished_at: string | null;
};

// Counts for one kind of content in an import report.
export type ImportReportRow = {
    kind: string;
    created: number;
    updated: number;
    skipped: number;
    failed: number;
};

export type ImportReport = {
    rows: ImportReportRow[];
    messages: string[];
    dropped_messages: number;
};

export type ImportDetail = ImportSummary & {
    report: ImportReport | null;
    error: string | null;
    started_at: string | null;
    duration: number | null;
};

// An installed theme, as listed on the admin themes page.
export type ThemeInfo = {
    slug: string;
    name: string;
    version: string;
    description: string;
    author: string;
    active: boolean;
    removable: boolean;
    customizable: boolean;
    // Slug of the parent theme, its name when installed, and the slug when it is missing.
    parent: string | null;
    parentName: string | null;
    missingParent: string | null;
    // How many page templates, block patterns and template parts the theme ships.
    templates: number;
    patterns: number;
    parts: number;
    // Risk level of the newest verification report, or null when the theme was never checked.
    risk: RiskLevel | null;
};

// One installed theme on its detail page.
export type ThemeDetail = {
    slug: string;
    name: string;
    version: string;
    description: string;
    author: string;
    parent: string | null;
    parentName: string | null;
    missingParent: string | null;
    missingViews: string[];
    active: boolean;
    removable: boolean;
    customizable: boolean;
};

export type ExtensionKind = 'plugin' | 'theme';

// A plugin or theme zip that was checked, as shown on the consent screen.
export type ExtensionPackage = {
    kind: ExtensionKind;
    slug: string;
    name: string;
    version: string;
    description: string;
    author: string;
    requires: string;
    permissions: string[];
    network: string[];
    fileCount: number;
    unpackedBytes: number;
};

// An uploaded package that waits for the admin to accept it.
export type StagedExtension = {
    token: string;
    package: ExtensionPackage;
    source: 'upload' | 'registry';
    sha256: string;
    installedVersion: string | null;
    isUpdate: boolean;
    isDowngrade: boolean;
};

// One package listed in the registry index.
export type RegistryExtension = {
    kind: ExtensionKind;
    name: string;
    slug: string;
    version: string;
    description: string;
    downloadUrl: string;
    sha256: string;
    installedVersion: string | null;
};

export type RegistryListing = {
    configured: boolean;
    error: string | null;
    entries: RegistryExtension[];
};

export type PageCacheInfo = {
    enabled: boolean;
    ttl: number;
};

export type ThemeSettingType =
    | 'color'
    | 'font'
    | 'length'
    | 'media'
    | 'size'
    | 'text'
    | 'toggle';

// One customizer field from the theme.json settings schema.
export type ThemeSettingField = {
    key: string;
    type: ThemeSettingType;
    label: string;
    help: string;
    default: string | number | boolean | null;
    options: string[];
    min: number;
    max: number;
    unit: string;
    maxLength: number;
    // Units, keywords and numbers of a size or length field. Null for other fields.
    value?: CssValueRule | null;
};

export type ThemeSettingValue = string | number | boolean | null;

// The URL structure form of the "Permalinks and SEO" page.
export type PermalinkValues = {
    structures: Record<string, string>;
    archive_bases: Record<string, string>;
    taxonomy_bases: Record<string, string>;
    trailing_slash: boolean;
    lowercase: boolean;
};

// A content type as the permalink page shows it, with token values of its newest entry.
export type PermalinkType = {
    name: string;
    label: string;
    pluralLabel: string;
    inlineLabel: string;
    inlinePluralLabel: string;
    hierarchical: boolean;
    hasArchive: boolean;
    defaultStructure: string;
    defaultArchiveBase: string;
    previous: string[];
    sample: Record<string, string>;
};

export type PermalinkTaxonomy = {
    name: string;
    label: string;
    defaultBase: string;
    sample: string;
};

export type SitemapOptions = {
    enabled: boolean;
    types: string[];
    taxonomies: string[];
    excluded_entries: number[];
    include_images: boolean;
    max_urls: number;
    last_modified: 'updated' | 'published';
};

export type PickedMedia = { id: number; url: string; filename: string };

export type SearchEngineSettings = {
    discourage: boolean;
    robots_txt: string;
    title_format: string;
    title_separator: string;
    default_description: string;
    default_image: PickedMedia | null;
    schema_type: 'organization' | 'person';
    schema_name: string;
    schema_logo: PickedMedia | null;
    schema_same_as: string[];
    verification_google: string;
    verification_bing: string;
    indexnow_enabled: boolean;
    indexnow_key: string;
};

// Where a header, footer or other part of the theme stands in the Site Editor.
export type SitePartStatus = 'original' | 'unpublished' | 'published';

// One part of the active theme, as listed in the Site Editor.
export type SitePartSummary = {
    key: string;
    label: string;
    status: SitePartStatus;
    editedAt: string | null;
    revisions: number;
};

// Preview sizes of the Site Editor. Null fills the space.
export type SitePreviewWidths = {
    desktop: number | null;
    tablet: number | null;
    phone: number | null;
};

// One style a theme offers for a part, like the "Centered" header.
export type SitePartVariant = {
    key: string;
    label: string;
    description: string;
    part: string;
};

// A part with more than one style, and the style in use.
export type SitePartStyle = {
    name: string;
    label: string;
    chosen: string;
    variants: SitePartVariant[];
};

// One design token of the theme, like the accent color or the heading font.
export type StyleToken = {
    id: string;
    kind: 'color' | 'font' | 'length';
    label: string;
    help: string;
    default: string;
    // Units, keywords and numbers a size token accepts. Null for colors and fonts.
    value?: CssValueRule | null;
};

// Colors, fonts and the other tokens of the theme, for the Styles panel.
export type GlobalStylesData = {
    groups: { name: string; tokens: StyleToken[] }[];
    values: Record<string, string>;
    fonts: { value: string; label: string; stack: string }[];
    // Units the theme allows, null for every unit.
    units?: string[] | null;
};
