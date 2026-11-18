<?php

namespace App\Verso\Install\Wizard;

// The steps of the install wizard, in order.
enum WizardStep: string
{
    case Welcome = 'welcome';
    case Requirements = 'requirements';
    case Database = 'database';
    case Site = 'site';
    case Services = 'services';
    case Content = 'content';
    case Review = 'review';
    case Install = 'install';

    public function label(): string
    {
        return match ($this) {
            self::Welcome => __('Welcome'),
            self::Requirements => __('Server check'),
            self::Database => __('Database'),
            self::Site => __('Site and admin'),
            self::Services => __('Services'),
            self::Content => __('Content'),
            self::Review => __('Review'),
            self::Install => __('Install'),
        };
    }

    public function index(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    public function next(): ?self
    {
        return self::cases()[$this->index() + 1] ?? null;
    }

    public function previous(): ?self
    {
        $index = $this->index();

        return $index > 0 ? self::cases()[$index - 1] ?? null : null;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function list(): array
    {
        return array_map(fn (self $step): array => ['key' => $step->value, 'label' => $step->label()], self::cases());
    }
}
