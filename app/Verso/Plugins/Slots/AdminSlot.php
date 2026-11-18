<?php

namespace App\Verso\Plugins\Slots;

// Places in the admin screens where plugins can add React components.
enum AdminSlot: string
{
    // A card in the entry editor side panel. Core cards use orders 0 (Publish) to 100 (Revisions).
    case EntrySidebar = 'entry-sidebar';
    // A control at the end of the Publish card of the entry editor.
    case EntryPublish = 'entry-publish';
    // A button in the header of the entry editor, before Save.
    case EntryToolbar = 'entry-toolbar';
    // A column in the entries table. The label is the column header.
    case EntriesColumns = 'entries-columns';
    // An item in the "..." menu of each entries table row.
    case EntriesRowActions = 'entries-row-actions';
    // A card on the dashboard, under the stats.
    case DashboardWidgets = 'dashboard-widgets';
    // A card at the top of the dashboard, above the stats, like the launch checklist.
    case DashboardTop = 'dashboard-top';
    // A tab on the site settings page. The label is the tab name.
    case SettingsTabs = 'settings-tabs';
    // A section at the end of the user profile page.
    case ProfileSections = 'profile-sections';
    // A component mounted in the Easy mode page editor, for dialogs and tools. It shows nothing by itself.
    case PageEditor = 'page-editor';

    /**
     * The admin route names of the screen that shows this slot. Items are only shared there.
     *
     * @return list<string>
     */
    public function routes(): array
    {
        return match ($this) {
            self::EntrySidebar, self::EntryPublish, self::EntryToolbar => ['admin.entries.create', 'admin.entries.edit'],
            self::EntriesColumns, self::EntriesRowActions => ['admin.entries.index'],
            self::DashboardWidgets, self::DashboardTop => ['admin.dashboard'],
            self::SettingsTabs => ['admin.site.settings.edit'],
            self::ProfileSections => ['profile.edit'],
            self::PageEditor => ['admin.easy.pages.edit'],
        };
    }
}
