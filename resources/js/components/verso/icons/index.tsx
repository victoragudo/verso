import type { ComponentType } from 'react';
import type { IconProps } from './icon-base';
import { DashboardIcon } from './dashboard';
import { EntriesIcon } from './entries';
import { PostsIcon } from './posts';
import { PagesIcon } from './pages';
import { CategoriesIcon } from './categories';
import { TagsIcon } from './tags';
import { MediaIcon } from './media';
import { CommentsIcon } from './comments';
import { FieldsIcon } from './fields';
import { MenusIcon } from './menus';
import { SeoIcon } from './seo';
import { SearchIcon } from './search';
import { CodeIcon } from './code';
import { RedirectsIcon } from './redirects';
import { ThemesIcon } from './themes';
import { PluginsIcon } from './plugins';
import { UsersIcon } from './users';
import { SettingsIcon } from './settings';
import { ImporterIcon } from './importer';
import { ApiIcon } from './api';
import { CalendarIcon } from './calendar';
import { ClockIcon } from './clock';
import { SiteIcon } from './site';
import { SiteEditorIcon } from './site-editor';
import { DotIcon } from './dot';
import { ExtensionsIcon } from './extensions';
import { CustomizeIcon } from './customize';
import { AnalyticsIcon } from './analytics';
import { DevicesIcon } from './devices';
import { ReferrersIcon } from './referrers';
import { MembershipsIcon } from './memberships';
import { FormsIcon } from './forms';
import { NewsletterIcon } from './newsletter';
import { SparklesIcon } from './sparkles';
import { ShopIcon } from './shop';
import { OrdersIcon } from './orders';
import { ProductIcon } from './product';
import { WarningIcon } from './warning';
import { PatternsIcon } from './patterns';
import { ShieldIcon } from './shield';
import { UpdatesIcon } from './updates';
import { HelpIcon } from './help';
import { LaunchIcon } from './launch';
import { PaletteIcon } from './palette';
import { StarterIcon } from './starter';
import { RestaurantIcon } from './restaurant';
import { PersonIcon } from './person';

export type { IconProps } from './icon-base';
export { DashboardIcon } from './dashboard';
export { EntriesIcon } from './entries';
export { PostsIcon } from './posts';
export { PagesIcon } from './pages';
export { CategoriesIcon } from './categories';
export { TagsIcon } from './tags';
export { MediaIcon } from './media';
export { CommentsIcon } from './comments';
export { MenusIcon } from './menus';
export { SeoIcon } from './seo';
export { SearchIcon } from './search';
export { CodeIcon } from './code';
export { RedirectsIcon } from './redirects';
export { ThemesIcon } from './themes';
export { PluginsIcon } from './plugins';
export { UsersIcon } from './users';
export { SettingsIcon } from './settings';
export { ImporterIcon } from './importer';
export { ApiIcon } from './api';
export { CalendarIcon } from './calendar';
export { ClockIcon } from './clock';
export { SiteIcon } from './site';
export { SiteEditorIcon } from './site-editor';
export { DotIcon } from './dot';
export { ExtensionsIcon } from './extensions';
export { CustomizeIcon } from './customize';
export { AnalyticsIcon } from './analytics';
export { DevicesIcon } from './devices';
export { ReferrersIcon } from './referrers';
export { MembershipsIcon } from './memberships';
export { FormsIcon } from './forms';
export { NewsletterIcon } from './newsletter';
export { SparklesIcon } from './sparkles';
export { ShopIcon } from './shop';
export { OrdersIcon } from './orders';
export { ProductIcon } from './product';
export { WarningIcon } from './warning';
export { FieldsIcon } from './fields';
export { PatternsIcon } from './patterns';
export { ShieldIcon } from './shield';
export { UpdatesIcon } from './updates';
export { PaletteIcon } from './palette';
export { StarterIcon } from './starter';
export { RestaurantIcon } from './restaurant';
export { PersonIcon } from './person';
export { HelpIcon } from './help';
export { LaunchIcon } from './launch';

// All Verso icons by key. Nav items and page headers use these keys.
export const versoIcons = {
    dashboard: DashboardIcon,
    entries: EntriesIcon,
    posts: PostsIcon,
    pages: PagesIcon,
    categories: CategoriesIcon,
    tags: TagsIcon,
    media: MediaIcon,
    comments: CommentsIcon,
    menus: MenusIcon,
    seo: SeoIcon,
    search: SearchIcon,
    code: CodeIcon,
    redirects: RedirectsIcon,
    themes: ThemesIcon,
    plugins: PluginsIcon,
    users: UsersIcon,
    settings: SettingsIcon,
    importer: ImporterIcon,
    api: ApiIcon,
    calendar: CalendarIcon,
    clock: ClockIcon,
    site: SiteIcon,
    siteEditor: SiteEditorIcon,
    sparkles: SparklesIcon,
    dot: DotIcon,
    extensions: ExtensionsIcon,
    customize: CustomizeIcon,
    analytics: AnalyticsIcon,
    devices: DevicesIcon,
    referrers: ReferrersIcon,
    memberships: MembershipsIcon,
    forms: FormsIcon,
    newsletter: NewsletterIcon,
    shop: ShopIcon,
    orders: OrdersIcon,
    product: ProductIcon,
    warning: WarningIcon,
    fields: FieldsIcon,
    patterns: PatternsIcon,
    shield: ShieldIcon,
    updates: UpdatesIcon,
    starter: StarterIcon,
    palette: PaletteIcon,
    restaurant: RestaurantIcon,
    person: PersonIcon,
    help: HelpIcon,
    launch: LaunchIcon,
} satisfies Record<string, ComponentType<IconProps>>;

export type VersoIconName = keyof typeof versoIcons;

// True when the key belongs to the Verso icon set.
export function isVersoIconName(name: string): name is VersoIconName {
    return Object.hasOwn(versoIcons, name);
}

// Draws a Verso icon by key, with a small ring for unknown keys.
export function VersoIcon({ name, ...props }: IconProps & { name: string }) {
    const Icon = isVersoIconName(name) ? versoIcons[name] : versoIcons.dot;

    return <Icon {...props} />;
}
