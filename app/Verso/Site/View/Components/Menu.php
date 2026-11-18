<?php

namespace App\Verso\Site\View\Components;

use App\Verso\Site\Menus\MenuRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;

// Blade component <x-verso-menu location="primary" /> that prints the menu of a theme location as nested lists.
class Menu extends Component
{
    /** @var list<array<string, mixed>> */
    public array $links;

    public function __construct(
        MenuRenderer $renderer,
        Request $request,
        public string $location,
        public string $label = '',
    ) {
        $this->links = $renderer->links($location, $request->url());
    }

    // Nothing is printed when the location has no menu.
    public function shouldRender(): bool
    {
        return $this->links !== [];
    }

    public function render(): View
    {
        return view('verso-site::menu');
    }
}
