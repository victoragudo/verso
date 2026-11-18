<?php

namespace App\Verso\Theme\Templates;

use App\Verso\Core\Enums\Capability;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Collects the template lookups of one request and shows them to admins who ask with ?verso-template-debug=1.
class TemplateDebug
{
    /** @var list<TemplateLookup> */
    protected array $lookups = [];

    public function __construct(
        protected ViewFactory $views,
        protected bool $enabled,
        protected string $queryParameter,
        protected string $basePath,
    ) {}

    public function record(TemplateLookup $lookup): void
    {
        $this->lookups[] = $lookup;
    }

    /**
     * @return list<TemplateLookup>
     */
    public function lookups(): array
    {
        return $this->lookups;
    }

    // Only logged in users who manage themes see the panel, and only when they ask for it.
    public function enabledFor(Request $request): bool
    {
        return $this->enabled
            && $request->boolean($this->queryParameter)
            && $request->user()?->can(Capability::ManageThemes->value) === true;
    }

    // Add the panel before </body> and keep the page out of every cache.
    public function inject(Response $response): void
    {
        $content = $response->getContent();

        if ($content === false || ! str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'html')) {
            return;
        }

        $panel = $this->views->make('verso-theme::template-debug', [
            'lookups' => $this->lookups,
            'basePath' => rtrim($this->basePath, '/').'/',
        ])->render();

        $position = strripos($content, '</body>');

        $response->setContent($position === false
            ? $content.$panel
            : substr($content, 0, $position).$panel.substr($content, $position));

        $response->headers->set('Cache-Control', 'no-store, private');
    }
}
