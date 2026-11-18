{{-- Template debug panel: the theme views tried for this page, shown only to users who manage themes. --}}
<aside id="verso-template-debug" aria-label="Template debug" style="position:fixed;right:12px;bottom:12px;z-index:2147483647;max-width:min(460px,calc(100vw - 24px));max-height:70vh;overflow:auto;padding:12px 14px;border:1px solid #d4d4d8;border-radius:8px;background:#fff;color:#27272a;font:13px/1.45 ui-sans-serif,system-ui,sans-serif;box-shadow:0 1px 2px rgba(0,0,0,.08)">
    <p style="margin:0 0 8px;font-weight:600;color:#18181b">Template debug</p>
    @forelse ($lookups as $lookup)
        <section style="margin:0 0 10px">
            <p style="margin:0 0 4px;color:#71717a;text-transform:uppercase;font-size:11px;letter-spacing:.04em">{{ $lookup->context->value }}</p>
            <ol style="margin:0;padding-left:18px">
                @foreach ($lookup->candidates as $candidate)
                    <li data-template-candidate="{{ $candidate['name'] }}" data-template-status="{{ $candidate['name'] === $lookup->chosen ? 'used' : ($candidate['checked'] ? 'missing' : 'skipped') }}" style="color:{{ $candidate['name'] === $lookup->chosen ? '#15803d' : ($candidate['checked'] ? '#3f3f46' : '#a1a1aa') }}">
                        <code>{{ $candidate['name'] }}</code>
                        @if ($candidate['name'] === $lookup->chosen)
                            · used
                            @if ($candidate['file'] !== null)
                                <br><small style="color:#71717a">{{ str_starts_with($candidate['file'], $basePath) ? substr($candidate['file'], strlen($basePath)) : $candidate['file'] }}</small>
                            @endif
                        @elseif ($candidate['checked'])
                            · not found
                        @else
                            · not checked
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @empty
        <p style="margin:0">No theme template was looked up for this page.</p>
    @endforelse
</aside>
