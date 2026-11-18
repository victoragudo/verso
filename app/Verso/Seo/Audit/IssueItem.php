<?php

namespace App\Verso\Seo\Audit;

use App\Verso\I18n\Translate;

// One thing to fix in the list of an issue, like a page or an image.
final readonly class IssueItem
{
    /**
     * @param  array<string, string|int|float>  $detailValues
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $detail = '',
        public ?string $editUrl = null,
        public ?string $viewUrl = null,
        public ?string $type = null,
        public ?string $typeLabel = null,
        public array $detailValues = [],
        public bool $translateTitle = false,
    ) {}

    public function displayTitle(): string
    {
        if ($this->title === '') {
            return __('Untitled');
        }

        return $this->translateTitle ? Translate::text($this->title) : $this->title;
    }

    public function displayDetail(): string
    {
        return $this->detail === '' ? '' : Translate::icu($this->detail, $this->detailValues);
    }

    /**
     * @return array{key: string, title: string, detail: string, editUrl: string|null, viewUrl: string|null, type: string|null, typeLabel: string|null}
     */
    public function display(?string $typeLabel = null): array
    {
        return [
            'key' => $this->key,
            'title' => $this->displayTitle(),
            'detail' => $this->displayDetail(),
            'editUrl' => $this->editUrl,
            'viewUrl' => $this->viewUrl,
            'type' => $this->type,
            'typeLabel' => $typeLabel ?? $this->typeLabel,
        ];
    }

    /**
     * @return array{key: string, title: string, detail: string, editUrl: string|null, viewUrl: string|null, type: string|null, typeLabel: string|null, detailValues: array<string, string|int|float>, translateTitle: bool}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'detail' => $this->detail,
            'editUrl' => $this->editUrl,
            'viewUrl' => $this->viewUrl,
            'type' => $this->type,
            'typeLabel' => $this->typeLabel,
            'detailValues' => $this->detailValues,
            'translateTitle' => $this->translateTitle,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $text = fn (string $key): ?string => is_string($data[$key] ?? null) ? $data[$key] : null;

        return new self(
            key: (string) $text('key'),
            title: (string) $text('title'),
            detail: (string) $text('detail'),
            editUrl: $text('editUrl'),
            viewUrl: $text('viewUrl'),
            type: $text('type'),
            typeLabel: $text('typeLabel'),
            detailValues: CheckResult::values($data['detailValues'] ?? null),
            translateTitle: ($data['translateTitle'] ?? false) === true,
        );
    }
}
