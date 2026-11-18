<?php

namespace App\Verso\Theme\Templates;

use App\Verso\Theme\ThemeManager;
use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;

// Finds the first theme view that exists in a candidate list, and tells the debug panel about it.
class TemplateResolver
{
    public function __construct(
        protected ViewFactory $views,
        protected TemplateDebug $debug,
    ) {}

    // Check the candidates in order. Candidates after the chosen one are not checked.
    public function resolve(TemplateCandidates $candidates): TemplateLookup
    {
        $checked = [];
        $chosen = null;

        foreach ($candidates->names as $name) {
            if ($chosen !== null) {
                $checked[] = ['name' => $name, 'file' => null, 'checked' => false];

                continue;
            }

            $file = $this->file($name);
            $checked[] = ['name' => $name, 'file' => $file, 'checked' => true];

            if ($file !== null) {
                $chosen = $name;
            }
        }

        $lookup = new TemplateLookup($candidates->context, $checked, $chosen);
        $this->debug->record($lookup);

        return $lookup;
    }

    // Full view name in the theme namespace, like "theme::entry-post".
    public function viewName(string $name): string
    {
        return ThemeManager::NAMESPACE.'::'.$name;
    }

    // The file of a theme view, or null when no theme folder has it.
    protected function file(string $name): ?string
    {
        try {
            return $this->views->getFinder()->find($this->viewName($name));
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
