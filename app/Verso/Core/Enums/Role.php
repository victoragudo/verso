<?php

namespace App\Verso\Core\Enums;

// The role of a user. Each role gives a fixed list of capabilities.
enum Role: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Author = 'author';
    case Contributor = 'contributor';
    case Subscriber = 'subscriber';
    case ShopManager = 'shop_manager';

    // The default capabilities of this built-in role. Admins can change them on the Roles page.
    /** @return list<Capability> */
    public function capabilities(): array
    {
        return match ($this) {
            self::Admin => Capability::cases(),
            self::Editor => [
                Capability::EditAnyEntry,
                Capability::EditOwnEntries,
                Capability::PublishEntries,
                Capability::DeleteEntries,
                Capability::DeleteOwnEntries,
                Capability::UploadFiles,
                Capability::ManageTerms,
                Capability::ModerateComments,
                Capability::UseAi,
                Capability::ReadPrivateEntries,
                Capability::EditOthersMedia,
                Capability::ViewSeo,
                Capability::ManageSeo,
                Capability::ManageLibrary,
            ],
            self::Author => [
                Capability::EditOwnEntries,
                Capability::PublishEntries,
                Capability::DeleteOwnEntries,
                Capability::UploadFiles,
                Capability::ReadPrivateEntries,
            ],
            self::Contributor => [
                Capability::EditOwnEntries,
                Capability::ReadPrivateEntries,
            ],
            self::Subscriber => [],
            self::ShopManager => [
                Capability::ManageShop,
                Capability::EditOwnEntries,
                Capability::PublishEntries,
                Capability::DeleteOwnEntries,
                Capability::UploadFiles,
                Capability::ManageTerms,
                Capability::ReadPrivateEntries,
            ],
        };
    }

    public function has(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Administrator'),
            self::Editor => __('Editor'),
            self::Author => __('Author'),
            self::Contributor => __('Contributor'),
            self::Subscriber => __('Subscriber'),
            self::ShopManager => __('Shop manager'),
        };
    }

    // A short sentence that explains the role on the Roles page.
    public function description(): string
    {
        return match ($this) {
            self::Admin => __('Full access to everything, including users, roles, plugins and system settings.'),
            self::Editor => __('Writes, publishes and edits everyone\'s content, media and comments.'),
            self::Author => __('Writes and publishes their own content and uploads media.'),
            self::Contributor => __('Writes their own drafts. Someone else publishes them.'),
            self::Subscriber => __('Can sign in and edit their own profile.'),
            self::ShopManager => __('Runs the shop: orders, products and shop settings.'),
        };
    }

    // Optional roles only show up when a plugin turns them on (like the shop manager with the Shop plugin).
    public function isOptional(): bool
    {
        return $this === self::ShopManager;
    }
}
