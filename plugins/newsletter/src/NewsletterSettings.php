<?php

namespace Verso\Plugins\Newsletter;

use App\Verso\Core\Settings\Settings;

// Newsletter options: saved settings first, then the plugin config.
class NewsletterSettings
{
    // Option names that the settings page can change.
    public const KEYS = ['batch_size', 'batch_delay', 'from_address', 'from_name', 'track_opens', 'track_clicks'];

    public function __construct(protected Settings $settings) {}

    public function get(string $key): mixed
    {
        return $this->settings->get('newsletter.'.$key, config('newsletter.'.$key));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        foreach (array_intersect_key($values, array_flip(self::KEYS)) as $key => $value) {
            $this->settings->set('newsletter.'.$key, $value);
        }
    }

    public function batchSize(): int
    {
        return max(1, (int) $this->get('batch_size'));
    }

    public function batchDelay(): int
    {
        return max(0, (int) $this->get('batch_delay'));
    }

    public function fromAddress(): string
    {
        return (string) ($this->get('from_address') ?: config('mail.from.address'));
    }

    public function fromName(): string
    {
        return (string) ($this->get('from_name') ?: config('mail.from.name'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'batch_size' => $this->batchSize(),
            'batch_delay' => $this->batchDelay(),
            'from_address' => (string) $this->get('from_address'),
            'from_name' => (string) $this->get('from_name'),
            'track_opens' => (bool) $this->get('track_opens'),
            'track_clicks' => (bool) $this->get('track_clicks'),
        ];
    }
}
