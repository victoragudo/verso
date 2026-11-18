<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Core\Support\SiteTime;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Enums\BlockFieldType;
use Carbon\CarbonImmutable;
use Throwable;

// Days, hours, minutes and seconds until a date in the site time zone. The page shows the date itself,
// so it makes sense without JavaScript; the script ticks every second and says the end once, politely.
class CountdownBlock extends ModuleBlock implements CacheableBlock
{
    public const NAME = 'countdown';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Countdown');
    }

    public function description(): string
    {
        return __('Time left until a date, like an opening or a sale.');
    }

    public function fields(): array
    {
        return [
            new BlockField('until', __('Date and time'), BlockFieldType::DateTime, null, required: true, help: __('In the time zone of the site.')),
            new BlockField('done', __('Text when the time is up'), BlockFieldType::Text, '', help: __('Like "We are open!".'), max: 200),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        try {
            $until = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->text($block, 'until'), app(SiteTime::class)->timezone());
        } catch (Throwable) {
            return '';
        }

        if ($until === null) {
            return '';
        }

        $units = '';

        foreach (['days' => __('days'), 'hours' => __('hours'), 'minutes' => __('minutes'), 'seconds' => __('seconds')] as $unit => $label) {
            $units .= '<span class="verso-countdown__unit"><span class="verso-countdown__number" data-unit="'.$unit.'">00</span><span class="verso-countdown__label">'.e($label).'</span></span>';
        }

        $done = $this->text($block, 'done', __('The time is up.'));

        return '<div class="verso-countdown" data-verso-countdown data-until="'.e($until->utc()->toIso8601String()).'">'
            .'<p class="verso-countdown__date">'.e(__('Until :date', ['date' => $until->isoFormat('LLL')])).'</p>'
            .'<p class="verso-countdown__units" aria-hidden="true" hidden>'.$units.'</p>'
            .'<p class="verso-countdown__done" role="status" hidden data-done="'.e($done).'"></p></div>';
    }
}
