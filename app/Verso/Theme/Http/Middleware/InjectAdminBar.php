<?php

namespace App\Verso\Theme\Http\Middleware;

use App\Verso\Theme\AdminBar\AdminBar;
use App\Verso\Theme\Support\HtmlResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Adds the admin bar right after <body> on public HTML pages.
// It runs outside the page cache, so the bar is never stored in a cached page.
class InjectAdminBar
{
    // The next comment start, raw text element (whose text may say "<body>") or real <body> tag.
    protected const TOKEN_PATTERN = '/<!--|<(script|style|template|textarea|title|noscript)\b[^>]*>|<body\b[^>]*>/i';

    public function __construct(protected AdminBar $bar) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $this->applies($request, $response)) {
            return $response;
        }

        $content = (string) $response->getContent();
        $position = $this->bodyEnd($content);

        if ($position === null) {
            return $response;
        }

        $response->setContent(substr($content, 0, $position).$this->bar->render($request, $response).substr($content, $position));
        $response->headers->remove('Content-Length');

        return $response;
    }

    // Offset right after the real <body> tag. Text inside comments, styles and scripts is skipped.
    public function bodyEnd(string $html): ?int
    {
        $offset = 0;

        while (preg_match(self::TOKEN_PATTERN, $html, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $end = $match[0][1] + strlen($match[0][0]);
            $close = match (true) {
                $match[0][0] === '<!--' => '-->',
                isset($match[1]) && $match[1][1] >= 0 => '</'.$match[1][0],
                default => null,
            };

            if ($close === null) {
                return $end;
            }

            $found = stripos($html, $close, $end);

            if ($found === false) {
                return null;
            }

            $offset = $found + strlen($close);
        }

        return null;
    }

    // Only HTML pages for users who may see the bar. Never redirects, JSON, Inertia visits or the admin.
    protected function applies(Request $request, Response $response): bool
    {
        return ! $response->isRedirection()
            && ! $request->expectsJson()
            && ! $request->headers->has('X-Inertia')
            && ! $request->is('admin', 'admin/*')
            && HtmlResponse::is($response)
            && $this->bar->shows($request->user());
    }
}
