<?php

namespace Verso\Plugins\AiAssistant;

use App\Models\User;
use App\Verso\Core\Enums\Capability;
use App\Verso\Plugins\Filters\AltTextSuggesterFilter;
use App\Verso\Plugins\PluginManager;
use App\Verso\Plugins\PluginServiceProvider;
use App\Verso\Plugins\Slots\AdminSlot;
use App\Verso\Plugins\Slots\SlotItem;
use App\Verso\Seo\AltText\AltTextSuggester;
use App\Verso\System\Config\ConfigParameter;
use App\Verso\System\Config\Unit;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Request;
use Verso\Plugins\AiAssistant\Contracts\AiProvider;
use Verso\Plugins\AiAssistant\Providers\AnthropicProvider;
use Verso\Plugins\AiAssistant\Support\BlockText;
use Verso\Plugins\AiAssistant\Support\BlockTranslation;

// Boots the AI Assistant plugin: provider binding, routes, admin page and editor props.
class AiAssistantServiceProvider extends PluginServiceProvider
{
    // Entry editor routes that show the assistant panel.
    public const EDITOR_ROUTES = ['admin.entries.create', 'admin.entries.edit'];

    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/ai-assistant.php'), 'ai-assistant');
        $this->registerSystemSettings('AI Assistant', 'sparkles', ...$this->systemSettings());
        $this->loadPluginMigrations();

        $this->app->singleton(AiSettings::class);

        $this->app->bind(AiProvider::class, fn (Application $app): AiProvider => new AnthropicProvider(
            client: $app->make(Http::class),
            settings: $app->make(AiSettings::class),
            api: [
                'url' => (string) config('ai-assistant.anthropic.url'),
                'version' => (string) config('ai-assistant.anthropic.version'),
                'effort' => (string) config('ai-assistant.anthropic.effort'),
                'fallbacks' => (string) config('ai-assistant.anthropic.fallbacks'),
                'fallbacks_beta' => (string) config('ai-assistant.anthropic.fallbacks_beta'),
                'fallback_models' => array_values(array_map(strval(...), (array) config('ai-assistant.anthropic.fallback_models'))),
            ],
            http: [
                'timeout' => (int) config('ai-assistant.http.timeout'),
                'connect_timeout' => (int) config('ai-assistant.http.connect_timeout'),
                'retries' => (int) config('ai-assistant.http.retries'),
                'retry_delay' => (int) config('ai-assistant.http.retry_delay'),
            ],
        ));

        $this->app->bind(AiGuard::class, fn (Application $app): AiGuard => new AiGuard(
            plugins: $app->make(PluginManager::class),
            settings: $app->make(AiSettings::class),
            limiter: $app->make(RateLimiter::class),
            window: (int) config('ai-assistant.limits.rate_limit_window'),
        ));

        $this->app->bind(BlockTranslation::class, fn (): BlockTranslation => new BlockTranslation(
            contentTypes: array_values(array_map(strval(...), (array) config('ai-assistant.translation.content_types'))),
            attrs: array_values(array_map(strval(...), (array) config('ai-assistant.translation.attrs'))),
        ));

        $this->app->bind(AiAssistant::class, fn (Application $app): AiAssistant => new AiAssistant(
            provider: $app->make(AiProvider::class),
            guard: $app->make(AiGuard::class),
            usage: $app->make(UsageLog::class),
            blockText: $app->make(BlockText::class),
            translation: $app->make(BlockTranslation::class),
            sectionPieces: new BlockTranslation(
                contentTypes: array_values(array_map(strval(...), (array) config('ai-assistant.section_text.content_types'))),
                attrs: array_values(array_map(strval(...), (array) config('ai-assistant.section_text.attrs'))),
            ),
            limits: [
                'max_input_chars' => (int) config('ai-assistant.limits.max_input_chars'),
                'max_image_bytes' => (int) config('ai-assistant.limits.max_image_bytes'),
                'max_translation_segments' => (int) config('ai-assistant.limits.max_translation_segments'),
                'max_section_pieces' => (int) config('ai-assistant.limits.max_section_pieces'),
            ],
            targets: [
                'excerpt_words' => (int) config('ai-assistant.targets.excerpt_words'),
                'seo_title_chars' => (int) config('ai-assistant.targets.seo_title_chars'),
                'seo_description_chars' => (int) config('ai-assistant.targets.seo_description_chars'),
                'alt_chars' => (int) config('ai-assistant.targets.alt_chars'),
                'headline_count' => (int) config('ai-assistant.targets.headline_count'),
                'headline_chars' => (int) config('ai-assistant.targets.headline_chars'),
            ],
            imageTypes: array_values(array_map(strval(...), (array) config('ai-assistant.image_types'))),
            locales: self::locales(),
        ));
    }

    public function boot(): void
    {
        $this->loadAdminRoutes('routes/admin.php');

        // The assistant card of the entry editor, right after the Publish card.
        $this->addAdminSlot(new SlotItem(
            slot: AdminSlot::EntrySidebar,
            key: 'ai-assistant',
            component: 'ai-assistant.panel',
            capability: Capability::UseAi,
            order: 10,
            props: fn (Request $request): ?array => $this->editorProps($request),
        ));

        // The AI helper of the Easy mode page editor: buttons, section actions and their dialogs.
        $this->addAdminSlot(new SlotItem(
            slot: AdminSlot::PageEditor,
            key: 'ai-helper',
            component: 'ai-assistant.page-helper',
            capability: Capability::UseAi,
            props: fn (Request $request): ?array => $this->pageHelperProps($request),
        ));

        // Alt text suggestions on the SEO "Add alt text" screen. They are only a preview until saved.
        $this->addFilter(AltTextSuggesterFilter::class, fn (?AltTextSuggester $suggester, User $user): ?AltTextSuggester => $user->can(Capability::UseAi->value)
            ? new AltTextSuggester(
                label: 'AI Assistant',
                endpoint: route('admin.ai-assistant.actions.alt-text'),
                ready: $this->app->make(AiGuard::class)->available(),
                settingsUrl: $user->can(Capability::ManageSettings->value) ? route('admin.ai-assistant.index') : null,
            )
            : $suggester);
    }

    /**
     * What the entry editor needs to show the assistant panel, or null when the user may not use it.
     *
     * @return array<string, mixed>|null
     */
    public function editorProps(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || ! $request->routeIs(...self::EDITOR_ROUTES) || ! $user->can(Capability::UseAi->value)) {
            return null;
        }

        return [
            'ready' => $this->app->make(AiGuard::class)->available(),
            'settingsUrl' => $user->can(Capability::ManageSettings->value) ? route('admin.ai-assistant.index') : null,
            'endpoints' => [
                'outline' => route('admin.ai-assistant.actions.outline'),
                'rewrite' => route('admin.ai-assistant.actions.rewrite'),
                'excerpt' => route('admin.ai-assistant.actions.excerpt'),
                'seo' => route('admin.ai-assistant.actions.seo'),
                'altText' => route('admin.ai-assistant.actions.alt-text'),
                'translate' => route('admin.ai-assistant.actions.translate'),
            ],
            'locales' => self::localeOptions(),
        ];
    }

    /**
     * What the page editor needs for the AI helper, or null when the user may not use it.
     *
     * @return array<string, mixed>|null
     */
    public function pageHelperProps(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || ! $user->can(Capability::UseAi->value)) {
            return null;
        }

        $settings = $this->app->make(AiSettings::class);

        return [
            'ready' => $this->app->make(AiGuard::class)->available(),
            'settingsUrl' => $user->can(Capability::ManageSettings->value) ? route('admin.ai-assistant.index') : null,
            'endpoints' => [
                'sectionText' => route('admin.ai-assistant.actions.section-text'),
                'headlines' => route('admin.ai-assistant.actions.headlines'),
                'translate' => route('admin.ai-assistant.actions.translate'),
                'altText' => route('admin.ai-assistant.actions.alt-text'),
                'business' => route('admin.ai-assistant.business.update'),
            ],
            'locales' => self::localeOptions(),
            'business' => $settings->businessDescription(),
            'businessMax' => (int) config('ai-assistant.limits.max_business_chars'),
            'maxImages' => (int) config('ai-assistant.limits.max_section_images'),
        ];
    }

    /**
     * Languages offered for translation, as options for a select.
     *
     * @return list<array{code: string, name: string}>
     */
    public static function localeOptions(): array
    {
        return array_map(
            fn (string $code, string $name): array => ['code' => $code, 'name' => $name],
            array_keys(self::locales()),
            array_values(self::locales()),
        );
    }

    /**
     * Languages offered for translation (code => name).
     *
     * @return array<string, string>
     */
    public static function locales(): array
    {
        $locales = [];

        foreach ((array) config('ai-assistant.locales') as $code => $name) {
            $locales[(string) $code] = (string) $name;
        }

        return $locales;
    }

    // Limits of this plugin that admins can change in System settings.
    /** @return list<ConfigParameter> */
    protected function systemSettings(): array
    {
        $group = $this->systemGroup();

        return [
            new ConfigParameter('ai-assistant.http.timeout', $group, 'Answer time limit', 'How long Verso waits for an answer from the AI provider.', unit: Unit::Seconds, min: 5, max: 600, env: 'AI_ASSISTANT_TIMEOUT'),
            new ConfigParameter('ai-assistant.http.connect_timeout', $group, 'Connection time limit', 'How long Verso waits for the connection to open.', unit: Unit::Seconds, min: 1, max: 60, env: 'AI_ASSISTANT_CONNECT_TIMEOUT'),
            new ConfigParameter('ai-assistant.http.retries', $group, 'Retries', 'Extra tries after a failed connection, a 429 or a 5xx answer.', unit: Unit::Items, min: 0, max: 10, env: 'AI_ASSISTANT_RETRIES'),
            new ConfigParameter('ai-assistant.http.retry_delay', $group, 'Wait between retries', 'Pause before the next try.', unit: Unit::Milliseconds, min: 0, max: 60_000, env: 'AI_ASSISTANT_RETRY_DELAY'),
            new ConfigParameter('ai-assistant.limits.rate_limit_window', $group, 'Rate limit window', 'Length of the window for requests per user.', unit: Unit::Seconds, min: 60, max: 86_400, env: 'AI_ASSISTANT_RATE_LIMIT_WINDOW'),
            new ConfigParameter('ai-assistant.limits.max_input_chars', $group, 'Longest text sent', 'Longest text sent for one action.', unit: Unit::Characters, min: 1000, max: 1_000_000, env: 'AI_ASSISTANT_MAX_INPUT_CHARS'),
            new ConfigParameter('ai-assistant.limits.max_image_bytes', $group, 'Largest image sent', 'Biggest image sent for alt text.', unit: Unit::Bytes, min: 102_400, max: 20_971_520, env: 'AI_ASSISTANT_MAX_IMAGE_BYTES'),
        ];
    }
}
