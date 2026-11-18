<?php

namespace App\Verso\Layouts;

use App\Verso\Blocks\Interactive\ModalBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Icons\IconLibrary;
use App\Verso\Layouts\Models\SiteLayout;

// Draws popups and banners built with blocks: modal dialogs, slide-ins, top bars and the cookie notice.
// When and how often they show is decided in the browser (resources/blocks/popups); nothing is tracked or sent.
class PopupRenderer
{
    public const string ASSET = 'popups';

    // Key the browser uses to remember a popup. It changes when the popup is published again, so it shows again.
    public static function storageKey(SiteLayout $layout): string
    {
        return 'verso-popup-'.$layout->id.'-'.($layout->published_at?->getTimestamp() ?? 0);
    }

    public function __construct(protected LayoutResolver $resolver, protected BlockRenderer $blocks) {}

    // Top bars of the page, drawn above the header so they take their place in the page from the start.
    public function topBars(): string
    {
        $html = '';

        foreach ($this->popups(['top-bar']) as [$layout, $settings]) {
            $html .= $this->topBar($layout, $settings);
        }

        if ($html === '') {
            return '';
        }

        $assets = $this->blocks->assetsFor([self::ASSET, 'base']);

        return $assets['before'].$html.$assets['after'];
    }

    // Modal, slide-in and cookie popups, for the end of the page.
    public function footer(): string
    {
        $html = '';

        foreach ($this->popups(['modal', 'slide-in', 'cookie']) as [$layout, $settings]) {
            $html .= match ($settings['type']) {
                'modal' => $this->modal($layout, $settings),
                'cookie' => $this->cookie($layout, $settings),
                default => $this->slideIn($layout, $settings),
            };
        }

        if ($html === '') {
            return '';
        }

        $assets = $this->blocks->assetsFor([self::ASSET, 'modal', 'base']);

        return $assets['before'].$html.$assets['after'];
    }

    /**
     * Live popups of the page whose type is one of these, with their settings.
     *
     * @param  list<string>  $types
     * @return list<array{0: SiteLayout, 1: array<string, mixed>}>
     */
    protected function popups(array $types): array
    {
        $popups = [];

        foreach ($this->resolver->matching(LayoutKind::Popup) as $layout) {
            $settings = LayoutSettings::clean(LayoutKind::Popup, $layout->published_settings ?? []);

            if (in_array($settings['type'], $types, true) && $settings['devices'] !== []) {
                $popups[] = [$layout, $settings];
            }
        }

        return $popups;
    }

    /** @param array<string, mixed> $settings */
    protected function data(SiteLayout $layout, array $settings): string
    {
        return ' data-verso-popup="'.$layout->id.'" data-key="'.e(self::storageKey($layout)).'"'
            .' data-trigger="'.e($settings['trigger']).'" data-delay="'.(int) $settings['delay'].'" data-scroll="'.(int) $settings['scroll'].'"'
            .' data-frequency="'.e($settings['frequency']).'" data-days="'.(int) $settings['days'].'"'
            .' data-devices="'.e(implode(' ', $settings['devices'])).'"';
    }

    /** @param array<string, mixed> $settings */
    protected function title(SiteLayout $layout, array $settings): string
    {
        return $settings['title'] !== '' ? $settings['title'] : $layout->name;
    }

    /** @param array<string, mixed> $settings */
    protected function modal(SiteLayout $layout, array $settings): string
    {
        $id = 'verso-popup-'.$layout->id;
        $dialog = ModalBlock::dialog($id, $this->title($layout, $settings), $this->blocks->render($layout->published_blocks ?? []), $settings['size'], 'verso-popup verso-popup--modal');

        return (string) preg_replace('/^<dialog /', '<dialog'.$this->data($layout, $settings).' ', $dialog, 1);
    }

    /** @param array<string, mixed> $settings */
    protected function slideIn(SiteLayout $layout, array $settings): string
    {
        $id = 'verso-popup-'.$layout->id;

        return '<div class="verso-popup verso-popup--slide-in verso-popup--'.e($settings['position']).' verso-popup--'.e($settings['size']).'" id="'.$id.'" role="dialog" aria-labelledby="'.$id.'-title" popover="manual"'.$this->data($layout, $settings).'>'
            .'<div class="verso-popup__head"><h2 class="verso-popup__title" id="'.$id.'-title">'.e($this->title($layout, $settings)).'</h2>'
            .$this->closeButton($id).'</div>'
            .'<div class="verso-popup__body">'.$this->blocks->render($layout->published_blocks ?? []).'</div></div>';
    }

    /** @param array<string, mixed> $settings */
    protected function topBar(SiteLayout $layout, array $settings): string
    {
        $id = 'verso-popup-'.$layout->id;
        $key = self::storageKey($layout);

        // The tiny script right after the bar hides it before the first paint when the visitor closed it before,
        // so the page never moves after it shows.
        return '<div class="verso-topbar verso-popup--on-'.e(implode(' verso-popup--on-', $settings['devices'])).'" id="'.$id.'" role="region" aria-label="'.e($this->title($layout, $settings)).'"'.$this->data($layout, $settings).'>'
            .'<div class="verso-topbar__body">'.$this->blocks->render($layout->published_blocks ?? []).'</div>'
            .$this->closeButton($id).'</div>'
            .'<script>try{var b=document.currentScript.previousElementSibling,k='.json_encode($key).',v=localStorage.getItem(k)||sessionStorage.getItem(k);if(v&&(v==="dismissed"||v==="shown"||+v>Date.now()))b.hidden=true}catch(e){}</script>';
    }

    /** @param array<string, mixed> $settings */
    protected function cookie(SiteLayout $layout, array $settings): string
    {
        $id = 'verso-popup-'.$layout->id;
        $accept = $settings['acceptLabel'] !== '' ? $settings['acceptLabel'] : __('Accept');
        $decline = $settings['dismissLabel'] !== '' ? $settings['dismissLabel'] : __('Decline');

        return '<div class="verso-popup verso-popup--cookie verso-popup--'.e($settings['position']).'" id="'.$id.'" role="region" aria-labelledby="'.$id.'-title" popover="manual"'.$this->data($layout, [...$settings, 'trigger' => 'load', 'frequency' => 'dismissed']).' data-cookie>'
            .'<h2 class="verso-popup__title" id="'.$id.'-title">'.e($this->title($layout, $settings)).'</h2>'
            .'<div class="verso-popup__body">'.$this->blocks->render($layout->published_blocks ?? []).'</div>'
            .'<div class="verso-popup__actions"><button type="button" class="verso-popup__button" data-consent="declined">'.e($decline).'</button>'
            .'<button type="button" class="verso-popup__button" data-consent="accepted">'.e($accept).'</button></div></div>';
    }

    protected function closeButton(string $id): string
    {
        return '<button type="button" class="verso-popup__close" data-dismiss="'.e($id).'"><span class="verso-visually-hidden">'.e(__('Close')).'</span>'
            .app(IconLibrary::class)->svg('x').'</button>';
    }
}
