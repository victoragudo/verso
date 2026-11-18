<?php

namespace App\Verso\Core\Enums;

// A core permission a user can have through their roles. Plugins and content types add more as plain strings.
enum Capability: string
{
    case ManageSettings = 'manage_settings';
    case ManageUsers = 'manage_users';
    case EditAnyEntry = 'edit_any_entry';
    case EditOwnEntries = 'edit_own_entries';
    case PublishEntries = 'publish_entries';
    case DeleteEntries = 'delete_entries';
    case DeleteOwnEntries = 'delete_own_entries';
    case UploadFiles = 'upload_files';
    case ManageTerms = 'manage_terms';
    case ModerateComments = 'moderate_comments';
    case ManagePlugins = 'manage_plugins';
    case ManageThemes = 'manage_themes';
    case ImportContent = 'import_content';
    case UseAi = 'ai.use';
    case ManageShop = 'shop.manage';
    case ManageUpdates = 'manage_updates';
    case ViewAdminBar = 'view_admin_bar';
    case ManageSystem = 'manage_system';
    case ReadPrivateEntries = 'read_private_entries';
    case EditOthersMedia = 'edit_others_media';
    case EditSite = 'edit_site';
    case ManageMenus = 'manage_menus';
    case EditTemplates = 'edit_templates';
    case EditStyles = 'edit_styles';
    case EditCustomCss = 'edit_custom_css';
    case EditPopups = 'edit_popups';
    case ManageLibrary = 'manage_library';
    case ListUsers = 'list_users';
    case CreateUsers = 'create_users';
    case EditUsers = 'edit_users';
    case PromoteUsers = 'promote_users';
    case DeleteUsers = 'delete_users';
    case ManageRoles = 'manage_roles';
    case InstallPlugins = 'install_plugins';
    case ActivatePlugins = 'activate_plugins';
    case ManageGeneralSettings = 'manage_general_settings';
    case ManagePermalinks = 'manage_permalinks';
    case ManageEditorSettings = 'manage_editor_settings';
    case ManageFields = 'manage_fields';
    case ViewSeo = 'seo.view';
    case ManageSeo = 'seo.manage';

    // A short name for people, like "Manage settings".
    public function label(): string
    {
        return match ($this) {
            self::UseAi => __('Use the AI assistant'),
            self::ManageShop => __('Manage the shop'),
            self::ViewAdminBar => __('See the admin bar on the site'),
            self::ManageSystem => __('Manage system settings and server health'),
            self::ViewSeo => __('See the SEO overview and the 404 monitor'),
            self::ManageSeo => __('Fix SEO issues, check links and turn 404s into redirects'),
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }

    /**
     * Every capability value with its label.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $capability) {
            $labels[$capability->value] = $capability->label();
        }

        return $labels;
    }

    // Find a capability from an ability name, or null when it is not one.
    public static function fromAbility(string $ability): ?self
    {
        return self::tryFrom($ability);
    }
}
