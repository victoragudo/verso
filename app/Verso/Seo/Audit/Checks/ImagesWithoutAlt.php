<?php

namespace App\Verso\Seo\Audit\Checks;

use App\Verso\Media\Enums\MediaType;
use App\Verso\Media\Models\Media;
use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\IssueFix;
use App\Verso\Seo\Audit\IssueItem;
use App\Verso\Seo\Audit\Severity;
use Illuminate\Database\Eloquent\Builder;

// Images without alt text: in the media library, and images in content that come from other sites.
class ImagesWithoutAlt extends BaseCheck
{
    protected const MESSAGES = [
        'library' => ['message' => '{count, plural, one {# image needs} other {# images need}} alt text.'],
        'content' => ['message' => '{count, plural, one {# image from another site has} other {# images from other sites have}} no alt text.'],
    ];

    public function key(): string
    {
        return 'images-without-alt';
    }

    public function label(): string
    {
        return __('Images without alt text');
    }

    public function description(): string
    {
        return __('Alt text tells search engines and people who cannot see the image what it shows. Describe each image in a short sentence.');
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function fix(): ?IssueFix
    {
        return IssueFix::link(__('Add alt text'), route('admin.seo.alt-text.index'));
    }

    // Library images that still need alt text.
    /** @return Builder<Media> */
    public static function missingQuery(): Builder
    {
        return Media::query()->ofType(MediaType::Image)
            ->where(fn (Builder $query) => $query->whereNull('alt')->orWhere('alt', ''));
    }

    public function run(AuditContext $context): CheckResult
    {
        $images = Media::query()->ofType(MediaType::Image)->count();
        $missing = self::missingQuery()->count();
        $items = [];

        if ($missing > 0) {
            $items[] = new IssueItem(
                key: 'media',
                title: 'Media library',
                detail: self::MESSAGES['library']['message'],
                editUrl: route('admin.seo.alt-text.index'),
                detailValues: ['count' => $missing],
                translateTitle: true,
            );
        }

        $inContent = 0;
        foreach ($context->entries() as $entry) {
            if ($entry->imagesWithoutAlt > 0) {
                $inContent += $entry->imagesWithoutAlt;
                $items[] = $entry->item(self::MESSAGES['content']['message'], ['count' => $entry->imagesWithoutAlt]);
            }
        }

        $affected = $missing + $inContent;

        return new CheckResult($affected, max($affected, $images + $inContent), array_slice($items, 0, $context->maxItems));
    }
}
