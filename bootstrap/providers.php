<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Verso\Access\AccessServiceProvider;
use App\Verso\Blocks\BlocksServiceProvider;
use App\Verso\Comments\CommentsServiceProvider;
use App\Verso\Content\ContentServiceProvider;
use App\Verso\Core\CoreServiceProvider;
use App\Verso\Easy\EasyServiceProvider;
use App\Verso\Editor\EditorServiceProvider;
use App\Verso\Extensions\ExtensionsServiceProvider;
use App\Verso\Fields\FieldsServiceProvider;
use App\Verso\I18n\I18nServiceProvider;
use App\Verso\Importer\ImporterServiceProvider;
use App\Verso\Layouts\LayoutsServiceProvider;
use App\Verso\Library\LibraryServiceProvider;
use App\Verso\Media\MediaServiceProvider;
use App\Verso\Plugins\PluginsServiceProvider;
use App\Verso\Seo\SeoServiceProvider;
use App\Verso\Shortcodes\ShortcodesServiceProvider;
use App\Verso\Site\SiteServiceProvider;
use App\Verso\System\SystemServiceProvider;
use App\Verso\Theme\ThemeServiceProvider;
use App\Verso\Updates\UpdatesServiceProvider;
use App\Verso\Users\UsersServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    CoreServiceProvider::class,
    AccessServiceProvider::class,
    I18nServiceProvider::class,
    ContentServiceProvider::class,
    EditorServiceProvider::class,
    MediaServiceProvider::class,
    SiteServiceProvider::class,
    UsersServiceProvider::class,
    CommentsServiceProvider::class,
    PluginsServiceProvider::class,
    ImporterServiceProvider::class,
    ShortcodesServiceProvider::class,
    ThemeServiceProvider::class,
    ExtensionsServiceProvider::class,
    FieldsServiceProvider::class,
    UpdatesServiceProvider::class,
    SystemServiceProvider::class,
    EasyServiceProvider::class,
    BlocksServiceProvider::class,
    LayoutsServiceProvider::class,
    LibraryServiceProvider::class,
    SeoServiceProvider::class,
];
