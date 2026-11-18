<?php

use App\Models\User;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\Editor\Styles\BlockStyleCompiler;
use App\Verso\Editor\Styles\BlockStyleSchema;
use Illuminate\Support\Facades\Validator;

function styledParagraph(array $style, ?string $customCss = null, string $id = 'p1'): array
{
    $attrs = ['design' => $style];

    if ($customCss !== null) {
        $attrs['customCss'] = $customCss;
    }

    return ['id' => $id, 'type' => 'paragraph', 'attrs' => $attrs, 'content' => 'Opening hours: 7 to 18'];
}

function blocksAreValid(array $blocks, ?User $user = null): bool
{
    return Validator::make(['blocks' => $blocks], ['blocks' => [new ValidBlocks($user)]])->passes();
}

it('accepts plain and responsive values of known options', function () {
    $errors = app(BlockStyleSchema::class)->errors([
        'spacing' => ['padding' => ['top' => ['base' => '2rem', 'mobile' => '1rem'], 'left' => 'var:spacing.md']],
        'layout' => ['display' => 'flex', 'justify' => 'between', 'columns' => ['base' => 4, 'tablet' => 2, 'mobile' => 1]],
        'typography' => ['fontWeight' => 600, 'lineHeight' => 1.4, 'transform' => 'uppercase', 'fontFamily' => 'var:font.heading'],
        'color' => ['text' => '#1F2A24', 'background' => 'var:color.surface', 'overlay' => '#00000080'],
        'border' => ['radius' => '12px', 'style' => 'dashed'],
        'shadow' => 'var:shadow.md',
        'position' => ['sticky' => true, 'top' => '0'],
        'visibility' => ['hidden' => ['mobile' => true]],
    ]);

    expect($errors)->toBe([]);
});

it('rejects unknown options, unknown screen sizes and unsafe values', function (array $style, string $message) {
    expect(app(BlockStyleSchema::class)->errors($style)[0] ?? null)->toBe($message);
})->with([
    'unknown option' => [['spacing' => ['margins' => '1rem']], 'spacing.margins: is not a style option.'],
    'unknown size' => [['size' => ['width' => ['desktop' => '50%']]], 'size.width: screen sizes must be base, tablet or mobile.'],
    'css injection in a length' => [['size' => ['width' => '10px;background:url(x)']], 'size.width: the value is not allowed.'],
    'named color' => [['color' => ['text' => 'red; position: fixed']], 'color.text: the value is not allowed.'],
    'gradient that is not a token' => [['color' => ['gradient' => 'linear-gradient(red, blue)']], 'color.gradient: the value is not allowed.'],
    'too many columns' => [['layout' => ['columns' => 13]], 'layout.columns: the value is not allowed.'],
    'odd font weight' => [['typography' => ['fontWeight' => 450]], 'typography.fontWeight: the value is not allowed.'],
    'token for a keyword' => [['layout' => ['display' => 'var:layout.flex']], 'layout.display: the value is not allowed.'],
    'list instead of object' => [['1rem', '2rem'], 'style: must be an object.'],
]);

it('compiles styles to one scoped class with media queries per screen size', function () {
    $style = app(BlockStyleCompiler::class)->compile(['design' => [
        'spacing' => ['padding' => ['top' => ['base' => '3rem', 'tablet' => '2rem', 'mobile' => '1rem']]],
        'color' => ['background' => 'var:color.surface'],
        'visibility' => ['hidden' => ['mobile' => true]],
    ]]);

    expect($style)->not->toBeNull()
        ->and($style->class)->toStartWith('vs-')
        ->and($style->css)->toBe(
            ".{$style->class}{padding-top:3rem;background-color:var(--verso-color-surface)}"
            ."@media (max-width:1024px){.{$style->class}{padding-top:2rem}}"
            ."@media (max-width:640px){.{$style->class}{padding-top:1rem;display:none}}"
        );
});

it('uses the breakpoints from the config', function () {
    config(['verso.editor.breakpoints' => ['tablet' => 900, 'mobile' => 480]]);
    app()->forgetInstance(BlockStyleCompiler::class);

    $style = app(BlockStyleCompiler::class)->compile(['design' => ['size' => ['maxWidth' => ['tablet' => '720px', 'mobile' => '100%']]]]);

    expect($style?->css)->toContain('@media (max-width:900px)')->toContain('@media (max-width:480px)');
});

it('gives the same class to the same styles and nothing to blocks without styles', function () {
    $compiler = app(BlockStyleCompiler::class);
    $attrs = ['design' => ['border' => ['width' => '1px', 'color' => '#d9d2c5']]];

    expect($compiler->compile($attrs)?->class)->toBe($compiler->compile($attrs)?->class)
        ->and($compiler->compile([]))->toBeNull()
        ->and($compiler->compile(['design' => ['size' => ['width' => 'calc(100% + url(x))']]]))->toBeNull();
});

it('keeps only safe declarations of custom css', function () {
    $css = app(BlockStyleCompiler::class)->sanitizeCustomCss(
        "outline: 2px solid #b4532a; background: url(https://tracker.example/x.png); } body { color: red; letter-spacing: .02em;\n behavior: url(x.htc); @import 'x.css'; color: #1f2a24 !important"
    );

    expect($css)->toBe('outline:2px solid #b4532a;letter-spacing:.02em;color:#1f2a24 !important');
});

it('adds the class to the first tag of the block and prints the rules once', function () {
    $html = app(BlockRenderer::class)->render([
        styledParagraph(['color' => ['text' => '#7a2e12']], id: 'p1'),
        styledParagraph(['color' => ['text' => '#7a2e12']], id: 'p2'),
        ['id' => 'p3', 'type' => 'paragraph', 'content' => 'No style here'],
    ]);

    preg_match('/class="(vs-[a-f0-9]+)"/', $html, $match);

    expect($match)->not->toBeEmpty()
        ->and($html)->toStartWith('<style data-verso-block-styles>.'.$match[1].'{color:#7a2e12}</style>')
        ->and(substr_count($html, '{color:#7a2e12}'))->toBe(1)
        ->and(substr_count($html, 'class="'.$match[1].'"'))->toBe(2)
        ->and($html)->not->toContain(' style=');
});

it('joins the style class with a class the block already has', function () {
    $html = app(BlockRenderer::class)->render([[
        'id' => 's1', 'type' => 'section', 'attrs' => ['tag' => 'header', 'label' => 'Site header', 'design' => ['layout' => ['display' => 'flex', 'justify' => 'between']]],
        'children' => [['id' => 'p1', 'type' => 'paragraph', 'content' => 'Kvarnholmen Bakery']],
    ]]);

    expect($html)->toMatch('/<header class="verso-section vs-[a-f0-9]+" aria-label="Site header"><p>Kvarnholmen Bakery<\/p><\/header>/')
        ->toContain('display:flex;justify-content:space-between');
});

it('nests sections and refuses a lone column inside one', function () {
    $nested = [[
        'id' => 'outer', 'type' => 'section', 'attrs' => ['tag' => 'footer'],
        'children' => [['id' => 'inner', 'type' => 'section', 'children' => [['id' => 'p1', 'type' => 'paragraph', 'content' => 'Storgatan 12, Nacka']]]],
    ]];

    expect(blocksAreValid($nested, User::factory()->admin()->create()))->toBeTrue()
        ->and(app(BlockRenderer::class)->render($nested))->toBe('<footer class="verso-section"><div class="verso-section"><p>Storgatan 12, Nacka</p></div></footer>')
        ->and(blocksAreValid([['id' => 's', 'type' => 'section', 'children' => [['id' => 'c', 'type' => 'column']]]], User::factory()->admin()->create()))->toBeFalse()
        ->and(blocksAreValid([['id' => 's', 'type' => 'section', 'attrs' => ['tag' => 'script']]], User::factory()->admin()->create()))->toBeFalse();
});

it('validates block styles when blocks are saved', function () {
    $author = User::factory()->author()->create();

    expect(blocksAreValid([styledParagraph(['spacing' => ['margin' => ['top' => '2rem']]])], $author))->toBeTrue()
        ->and(blocksAreValid([styledParagraph(['spacing' => ['margin' => ['top' => 'expression(alert(1))']]])], $author))->toBeFalse();
});

it('keeps the button look in its own style attr', function () {
    $button = ['id' => 'b1', 'type' => 'button', 'attrs' => ['url' => '/menu', 'label' => 'See the menu', 'style' => 'outline', 'design' => ['spacing' => ['margin' => ['top' => '1rem']]]]];

    expect(blocksAreValid([$button], User::factory()->author()->create()))->toBeTrue()
        ->and(app(BlockRenderer::class)->render([$button]))->toContain('verso-button--outline')->toMatch('/<div class="verso-buttons vs-[a-f0-9]+">/');
});

it('lets only admins add custom css', function () {
    $block = [styledParagraph([], 'outline: 1px solid #b4532a')];

    expect(blocksAreValid($block, User::factory()->author()->create()))->toBeFalse()
        ->and(blocksAreValid($block, User::factory()->admin()->create()))->toBeTrue();
});
