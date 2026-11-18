<?php

namespace App\Verso\Access\Capabilities;

use App\Verso\Access\Enums\RiskLevel;
use App\Verso\Core\Enums\Capability;

// The capabilities of Verso itself, with their group, help text and risk.
final class CoreCapabilities
{
    /** @return list<CapabilityDefinition> */
    public static function all(): array
    {
        $content = CapabilityGroup::CONTENT;
        $design = CapabilityGroup::DESIGN;
        $tools = CapabilityGroup::TOOLS;
        $administration = CapabilityGroup::ADMINISTRATION;
        $settings = CapabilityGroup::SETTINGS;

        return [
            self::make(Capability::EditOwnEntries, label: 'Create and edit own entries', description: 'Write new entries of every content type and change their own drafts.', group: $content, risk: RiskLevel::Low, section: 'All content types'),
            self::make(Capability::EditAnyEntry, label: 'Edit entries of others', description: 'Change entries written by anyone, of every content type.', group: $content, risk: RiskLevel::High, section: 'All content types'),
            self::make(Capability::PublishEntries, label: 'Publish entries', description: 'Make entries live or schedule them.', group: $content, risk: RiskLevel::Medium, section: 'All content types'),
            self::make(Capability::DeleteOwnEntries, label: 'Delete own entries', description: 'Move their own entries to the trash and restore them.', group: $content, risk: RiskLevel::Medium, section: 'All content types'),
            self::make(Capability::DeleteEntries, label: 'Delete entries of others', description: 'Move anyone\'s entries to the trash and restore them.', group: $content, risk: RiskLevel::High, section: 'All content types'),
            self::make(Capability::ReadPrivateEntries, label: 'See unpublished entries of others', description: 'See drafts, scheduled and trashed entries of other people in the lists.', group: $content, risk: RiskLevel::Low, section: 'All content types'),
            self::make(Capability::UploadFiles, label: 'Upload and use media', description: 'Upload files and change their own media.', group: $content, risk: RiskLevel::Low, section: 'Media'),
            self::make(Capability::EditOthersMedia, label: 'Edit and delete media of others', description: 'Change or delete files that other people uploaded.', group: $content, risk: RiskLevel::Medium, section: 'Media'),
            self::make(Capability::ManageTerms, label: 'Manage categories and tags', description: 'Create, rename and delete terms of every taxonomy.', group: $content, risk: RiskLevel::Low, section: 'Taxonomies'),
            self::make(Capability::ModerateComments, label: 'Moderate comments', description: 'Approve, mark as spam, trash and delete comments.', group: $content, risk: RiskLevel::Medium, section: 'Comments'),
            self::make(Capability::UseAi, label: 'Use the AI assistant', description: 'Ask the AI assistant for outlines, rewrites and alt text in the editor.', group: $content, risk: RiskLevel::Low, section: 'Editor tools'),

            self::make(Capability::EditSite, label: 'Edit the site design', description: 'Change headers, footers and templates in the Site Editor.', group: $design, risk: RiskLevel::High, implies: [Capability::EditTemplates, Capability::EditStyles, Capability::EditPopups, Capability::ManageLibrary]),
            self::make(Capability::EditTemplates, label: 'Edit templates, headers and footers', description: 'Build page templates, headers and footers with blocks and choose where they show.', group: $design, risk: RiskLevel::High, section: 'Site Editor'),
            self::make(Capability::EditStyles, label: 'Edit style presets', description: 'Save, change and delete style presets like "Card" that every block can use.', group: $design, risk: RiskLevel::Medium, section: 'Site Editor'),
            self::make(Capability::EditPopups, label: 'Edit popups and banners', description: 'Build popups, slide-ins, top bars and the cookie notice, and choose when they show.', group: $design, risk: RiskLevel::Medium, section: 'Site Editor'),
            self::make(Capability::ManageLibrary, label: 'Manage the design library', description: 'Save blocks as synced blocks or patterns, change them and import or export them.', group: $design, risk: RiskLevel::Medium, section: 'Site Editor'),
            self::make(Capability::EditCustomCss, label: 'Add custom CSS to blocks', description: 'Write CSS for one block in the Styles panel. The CSS is cleaned before it is saved.', group: $design, risk: RiskLevel::High, section: 'Site Editor'),
            self::make(Capability::ManageThemes, label: 'Manage themes', description: 'Switch, customize and check themes, and clear the page cache.', group: $design, risk: RiskLevel::High, implies: [Capability::EditSite, Capability::ManageMenus]),
            self::make(Capability::ManageMenus, label: 'Manage menus', description: 'Create menus and change their links.', group: $design, risk: RiskLevel::Medium),

            self::make(Capability::ImportContent, label: 'Import content', description: 'Import a WordPress export file with its posts, media and users.', group: $tools, risk: RiskLevel::High),
            self::make(Capability::ManageShop, label: 'Manage the shop', description: 'See orders, change their status and edit products and shop settings.', group: $tools, risk: RiskLevel::Medium, section: 'Shop'),

            self::make(Capability::ListUsers, label: 'See the list of users', description: 'Open the users list with names and email addresses.', group: $administration, risk: RiskLevel::Medium, section: 'Users'),
            self::make(Capability::CreateUsers, label: 'Add and invite users', description: 'Create accounts and send invitations.', group: $administration, risk: RiskLevel::High, section: 'Users'),
            self::make(Capability::EditUsers, label: 'Edit users', description: 'Change other people\'s accounts.', group: $administration, risk: RiskLevel::High, section: 'Users'),
            self::make(Capability::PromoteUsers, label: 'Change user roles', description: 'Give and take roles and single capabilities. Only capabilities they have themselves.', group: $administration, risk: RiskLevel::Critical, section: 'Users'),
            self::make(Capability::DeleteUsers, label: 'Delete users', description: 'Delete accounts and move their content to someone else.', group: $administration, risk: RiskLevel::Critical, section: 'Users'),
            self::make(Capability::ManageUsers, label: 'Manage users', description: 'Everything on the users page: list, add, edit, change roles and delete.', group: $administration, risk: RiskLevel::Critical, section: 'Users', implies: [Capability::ListUsers, Capability::CreateUsers, Capability::EditUsers, Capability::PromoteUsers, Capability::DeleteUsers]),
            self::make(Capability::ManageRoles, label: 'Manage roles', description: 'Create, change and delete roles, and preview the admin as another role.', group: $administration, risk: RiskLevel::Critical, section: 'Users'),
            self::make(Capability::InstallPlugins, label: 'Install plugins and themes', description: 'Upload or download new plugins and themes, and remove them.', group: $administration, risk: RiskLevel::Critical, section: 'Plugins'),
            self::make(Capability::ActivatePlugins, label: 'Turn plugins on and off', description: 'Enable, disable and check installed plugins.', group: $administration, risk: RiskLevel::Critical, section: 'Plugins'),
            self::make(Capability::ManagePlugins, label: 'Manage plugins', description: 'Everything on the plugins page: install, turn on and off, check.', group: $administration, risk: RiskLevel::Critical, section: 'Plugins', implies: [Capability::InstallPlugins, Capability::ActivatePlugins]),
            self::make(Capability::ManageUpdates, label: 'Update Verso', description: 'Check for and install new versions of Verso.', group: $administration, risk: RiskLevel::Critical, section: 'System'),
            self::make(Capability::ManageSystem, label: 'Manage system settings and server health', description: 'Change limits, PHP files and folder permissions on the server.', group: $administration, risk: RiskLevel::Critical, section: 'System'),

            self::make(Capability::ManageGeneralSettings, label: 'Change general settings', description: 'Site name, tagline, time zone, reading and comments settings.', group: $settings, risk: RiskLevel::Medium),
            self::make(Capability::ManagePermalinks, label: 'Change permalinks, SEO and redirects', description: 'URL structure, sitemaps, search engines and redirects.', group: $settings, risk: RiskLevel::High),
            self::make(Capability::ManageEditorSettings, label: 'Change editor settings', description: 'Which blocks and editor tools each role may use.', group: $settings, risk: RiskLevel::High),
            self::make(Capability::ViewSeo, label: 'See the SEO overview', description: 'See the site SEO score, the list of issues and the 404 monitor.', group: $settings, risk: RiskLevel::Low, section: 'SEO'),
            self::make(Capability::ManageSeo, label: 'Fix SEO issues', description: 'Run the link check, apply one click fixes, add alt text and turn 404 addresses into redirects.', group: $settings, risk: RiskLevel::Medium, section: 'SEO', implies: [Capability::ViewSeo]),
            self::make(Capability::ManageFields, label: 'Manage custom fields', description: 'Create, import and export field groups.', group: $settings, risk: RiskLevel::Medium),
            self::make(Capability::ManageSettings, label: 'Manage all settings', description: 'Every settings page, also plugin settings and custom code.', group: $settings, risk: RiskLevel::High, implies: [Capability::ManageGeneralSettings, Capability::ManagePermalinks, Capability::ManageEditorSettings, Capability::ManageFields, Capability::EditCustomCss]),
            self::make(Capability::ViewAdminBar, label: 'See the admin bar on the site', description: 'Show the admin bar on public pages while signed in.', group: $settings, risk: RiskLevel::Low, section: 'Profile'),
        ];
    }

    /** @param  list<Capability>  $implies */
    protected static function make(Capability $capability, string $label, string $description, string $group, RiskLevel $risk, ?string $section = null, array $implies = []): CapabilityDefinition
    {
        return new CapabilityDefinition(
            key: $capability->value,
            label: $label,
            description: $description,
            group: $group,
            risk: $risk,
            section: $section,
            implies: array_map(fn (Capability $implied): string => $implied->value, $implies),
        );
    }
}
