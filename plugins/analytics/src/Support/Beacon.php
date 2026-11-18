<?php

namespace Verso\Plugins\Analytics\Support;

use Verso\Plugins\Analytics\Enums\Device;

// Builds the tiny inline script that reports a page view. It sets no cookie and stores nothing in the browser.
class Beacon
{
    /**
     * @param  list<string>  $sensitiveKeys  the beacon stays quiet when the page URL has one of these query keys
     */
    public function __construct(
        protected string $endpoint,
        protected array $sensitiveKeys,
        protected int $tabletFrom,
        protected int $desktopFrom,
    ) {}

    // Sends the path, the referrer host and a screen size bucket. It stops when the visitor asked not to be tracked.
    public function script(): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $endpoint = json_encode($this->endpoint, $flags);
        $keys = json_encode($this->sensitiveKeys, $flags);

        return '<script>(function(n,s,d,l){'
            .'if(n.doNotTrack=="1"||window.doNotTrack=="1"||n.globalPrivacyControl||!n.sendBeacon)return;'
            .'var q=new URLSearchParams(l.search.toLowerCase());'
            .'if('.$keys.'.some(function(k){return q.has(k)}))return;'
            .'var r="",w=s.width;'
            .'try{r=d.referrer?new URL(d.referrer).host:""}catch(e){}'
            .'if(r==l.host)r="";'
            .'n.sendBeacon('.$endpoint.',new URLSearchParams({p:l.pathname,r:r,'
            .'w:w<'.$this->tabletFrom.'?"'.Device::Mobile->value.'":w<'.$this->desktopFrom.'?"'.Device::Tablet->value.'":"'.Device::Desktop->value.'"}))'
            .'})(navigator,screen,document,location)</script>';
    }
}
