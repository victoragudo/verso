<?php

namespace App\Verso\Fields;

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Core\Support\SiteTime;
use App\Verso\Editor\Support\HtmlSanitizer;
use App\Verso\Fields\Json\FieldGroupJson;
use App\Verso\Fields\Theme\CurrentEntry;
use App\Verso\Fields\Theme\EntryFieldValues;
use App\Verso\Fields\Theme\FieldPrinter;
use App\Verso\Fields\Values\FieldFormatter;
use App\Verso\Fields\Values\FieldValidator;
use App\Verso\Media\ImageUrl;
use App\Verso\Theme\ThemeManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Throwable;

// Boots custom fields: field groups, entry values, the builder pages and theme helpers.
class FieldsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once __DIR__.'/helpers.php';

        $this->app->singleton(FieldGroupRegistry::class, function (Application $app): FieldGroupRegistry {
            $registry = new FieldGroupRegistry($app->make(FieldGroupJson::class));
            $registry->addFolder(fn (): ?string => $this->themeFolder($app));

            return $registry;
        });
        $this->app->bind(FieldValidator::class, fn (Application $app): FieldValidator => new FieldValidator(
            sanitizer: $app->make(HtmlSanitizer::class),
            textMax: (int) config('verso.fields.text_max'),
            textareaMax: (int) config('verso.fields.textarea_max'),
            maxRows: (int) config('verso.fields.max_rows'),
        ));
        $this->app->bind(FieldFormatter::class, fn (Application $app): FieldFormatter => new FieldFormatter(
            images: $app->make(ImageUrl::class),
            time: $app->make(SiteTime::class),
            imageSizes: (string) config('verso.fields.image_sizes'),
        ));
        $this->app->scoped(CurrentEntry::class);
        $this->app->scoped(EntryFieldValues::class);
        $this->app->bind(FieldPrinter::class, fn (Application $app): FieldPrinter => new FieldPrinter(
            values: $app->make(EntryFieldValues::class),
            current: $app->make(CurrentEntry::class),
            formatter: $app->make(FieldFormatter::class),
            time: $app->make(SiteTime::class),
            dateFormat: (string) config('verso.fields.date_format'),
            dateTimeFormat: (string) config('verso.fields.date_time_format'),
        ));
    }

    public function boot(): void
    {
        AdminRoutes::load(base_path('routes/verso/fields.php'));

        Blade::directive('field', fn (string $expression): string => '<?php echo app(\\'.FieldPrinter::class.'::class)->print($entry ?? null, '.$expression.'); ?>');

        View::composer(['theme::entry', 'theme::entry-*'], function (ViewInstance $view): void {
            $entry = $view->getData()['entry'] ?? null;

            if ($entry instanceof Entry) {
                $this->app->make(CurrentEntry::class)->set($entry);
            }
        });

        $this->app->booted(fn () => AdminNav::add(new NavItem(
            key: 'fields',
            label: 'Custom fields',
            route: 'admin.fields.index',
            icon: 'fields',
            order: 40,
            capability: Capability::ManageFields,
            group: NavGroup::Settings,
            keywords: ['acf', 'meta', 'field groups', 'campos'],
        )));
    }

    // The JSON folder of the active theme, when the theme system is ready.
    protected function themeFolder(Application $app): ?string
    {
        try {
            return $app->make(ThemeManager::class)->active()->filePath((string) config('verso.fields.json_folder'));
        } catch (Throwable) {
            return null;
        }
    }
}
