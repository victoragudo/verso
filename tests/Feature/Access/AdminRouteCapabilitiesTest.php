<?php

use App\Models\User;
use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Http\Middleware\ApplyViewAs;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\Support\PluginFixtures;
use Tests\Support\ShopFixtures;

require_once __DIR__.'/Helpers.php';

// Admin routes that every signed-in user may open: their own home page, list view choices and leaving a preview.
const OPEN_ADMIN_ROUTES = ['admin.dashboard', 'admin.list-views.update', ApplyViewAs::EXIT_ROUTE];

// Plugin abilities that come from plugin config, like forms.capability.
beforeEach(function () {
    foreach (['forms', 'newsletter', 'memberships', 'analytics', 'ai-assistant'] as $plugin) {
        PluginFixtures::boot($plugin);
    }

    ShopFixtures::boot();
});

/**
 * Every admin route with the abilities of its "can:" middleware.
 *
 * @return array<string, list<array{0: string, 1: list<string>}>>
 */
function adminRouteAbilities(): array
{
    $routes = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        /** @var RoutingRoute $route */
        $name = (string) $route->getName();

        if (! str_starts_with($name, 'admin.')) {
            continue;
        }

        $abilities = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
                $parts = explode(',', substr($middleware, 4));
                $abilities[] = [array_shift($parts), $parts];
            }
        }

        $routes[$name] = $abilities;
    }

    return $routes;
}

// Fill the "can:" arguments like Laravel does: class names stay, route parameters become test models.
function abilityArguments(array $arguments): array
{
    return array_map(fn (string $argument): mixed => match (true) {
        class_exists($argument) => $argument,
        $argument === 'contentType' => app(ContentTypeRegistry::class)->get('post'),
        $argument === 'entry' => Entry::factory()->create(),
        default => throw new RuntimeException("No test value for the route parameter {$argument}."),
    }, $arguments);
}

it('makes every admin route name a capability, apart from a short allow list', function () {
    $missing = array_keys(array_filter(
        adminRouteAbilities(),
        fn (array $abilities, string $name): bool => $abilities === [] && ! in_array($name, OPEN_ADMIN_ROUTES, true),
        ARRAY_FILTER_USE_BOTH,
    ));

    expect(count(adminRouteAbilities()))->toBeGreaterThan(150)
        ->and($missing)->toBe([]);
});

it('only names abilities that exist: capabilities, gate abilities or policy abilities', function () {
    $registry = app(CapabilityRegistry::class);

    foreach (adminRouteAbilities() as $name => $abilities) {
        foreach ($abilities as [$ability, $arguments]) {
            $model = $arguments === [] ? null : (class_exists($arguments[0]) ? $arguments[0] : ['entry' => Entry::class][$arguments[0]] ?? null);
            $known = $registry->has($ability) || Gate::has($ability) || ($model !== null && Gate::getPolicyFor($model) !== null);

            expect($known)->toBeTrue("The route {$name} names the unknown ability {$ability}.");
        }
    }
});

it('denies every admin route to a subscriber and allows it to an administrator', function () {
    $subscriber = User::factory()->create(['name' => 'Vasco Gil']);
    $admin = User::factory()->admin()->create(['name' => 'Lara Simões']);

    foreach (adminRouteAbilities() as $name => $abilities) {
        foreach ($abilities as [$ability, $arguments]) {
            $arguments = abilityArguments($arguments);

            expect(Gate::forUser($subscriber)->allows($ability, $arguments))->toBeFalse("A subscriber may open {$name}.")
                ->and(Gate::forUser($admin)->allows($ability, $arguments))->toBeTrue("An administrator may not open {$name}.");
        }
    }
});

it('allows each capability route to someone who has only that capability', function () {
    $registry = app(CapabilityRegistry::class);
    $checked = 0;

    foreach (adminRouteAbilities() as $name => $abilities) {
        foreach ($abilities as [$ability]) {
            if (! $registry->has($ability)) {
                continue;
            }

            $holder = userWith([$ability], 'Holder of '.$ability);

            expect(Gate::forUser($holder)->allows($ability))->toBeTrue("Only {$ability} does not open {$name}.");
            $checked++;
        }
    }

    expect($checked)->toBeGreaterThan(100);
});

it('answers 403 to a subscriber and opens for a holder on every admin page without parameters', function () {
    $registry = app(CapabilityRegistry::class);
    $subscriber = User::factory()->create(['name' => 'Vasco Gil']);
    $opened = 0;

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = (string) $route->getName();

        if (! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true) || $route->parameterNames() !== [] || in_array($name, OPEN_ADMIN_ROUTES, true)) {
            continue;
        }

        $this->actingAs($subscriber)->get(route($name))->assertForbidden();

        $abilities = adminRouteAbilities()[$name];
        $capabilities = array_values(array_filter(array_map(fn (array $ability): string => $ability[0], $abilities), fn (string $key): bool => $registry->has($key)));

        if (count($capabilities) === count($abilities)) {
            $status = $this->actingAs(userWith($capabilities, 'Holder of '.$name))->get(route($name))->getStatusCode();

            expect($status)->not->toBe(403, "Only {$name} capabilities do not open it.")->and($status)->toBeLessThan(500);
            $opened++;
        }
    }

    expect($opened)->toBeGreaterThan(25);
});
