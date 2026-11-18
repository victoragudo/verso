<?php

namespace App\Verso\Media;

use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\Dashboard;
use App\Verso\Core\Admin\DashboardStat;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Media\Actions\UploadMedia;
use App\Verso\Media\Http\Controllers\ImageController;
use App\Verso\Media\Http\Presenters\MediaPresenter;
use App\Verso\Media\Images\ImageServerFactory;
use App\Verso\Media\Models\Media;
use App\Verso\Media\Support\MediaLibraryQuery;
use App\Verso\Media\Support\MediaUsage;
use App\Verso\Media\View\Components\FeaturedImage;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

// Boots the media module: uploads, the media library and the image route.
class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UploadMedia::class, fn (Application $app): UploadMedia => new UploadMedia(
            disks: $app->make('filesystem'),
            disk: $this->disk(),
            folderFormat: (string) config('verso.media.folder_format'),
            maxKilobytes: (int) config('verso.media.max_upload_kb'),
            allowedMimes: $this->stringList(config('verso.media.allowed_mimes')),
        ));

        $this->app->bind(ImageServerFactory::class, fn (Application $app): ImageServerFactory => new ImageServerFactory(
            disks: $app->make('filesystem'),
            sourceDisk: $this->disk(),
            config: (array) config('verso.media.images'),
        ));

        $this->app->bind(ImageUrl::class, fn (Application $app): ImageUrl => new ImageUrl(
            urls: $app->make(UrlGenerator::class),
            widths: array_map(intval(...), array_values((array) config('verso.media.images.srcset_widths'))),
        ));

        $this->app->bind(MediaUsage::class, fn (): MediaUsage => new MediaUsage(
            $this->stringList(config('verso.media.usage_meta_keys')),
        ));

        $this->app->scoped(ResponsiveImages::class);

        $this->app->bind(MediaPresenter::class, fn (): MediaPresenter => new MediaPresenter(
            (int) config('verso.media.images.thumbnail_size'),
        ));

        $this->app->bind(MediaLibraryQuery::class, fn (): MediaLibraryQuery => new MediaLibraryQuery(
            (int) config('verso.media.per_page'),
        ));
    }

    public function boot(): void
    {
        AdminRoutes::load(base_path('routes/verso/media.php'));

        $this->loadViewsFrom(resource_path('views/verso/media'), 'verso-media');
        Blade::component('verso-featured-image', FeaturedImage::class);

        Route::get(trim((string) config('verso.media.images.prefix'), '/').'/{path}', ImageController::class)
            ->where('path', '.*')
            ->middleware('signed:relative')
            ->name(ImageUrl::ROUTE);

        AdminNav::add(new NavItem(
            key: 'media',
            label: 'Media',
            route: 'admin.media.index',
            icon: 'media',
            order: 30,
            capability: Capability::UploadFiles,
            keywords: ['images', 'photos', 'files', 'uploads', 'library', 'imágenes', 'archivos'],
        ));

        $this->app->make(Dashboard::class)->addStat(new DashboardStat(
            key: 'media',
            label: 'Media files',
            count: fn (): int => Media::query()->count(),
            order: 70,
            capability: Capability::UploadFiles,
            route: 'admin.media.index',
            icon: 'media',
        ));
    }

    protected function disk(): string
    {
        return (string) config('verso.media.disk');
    }

    /**
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        return array_values(array_map(strval(...), (array) $value));
    }
}
