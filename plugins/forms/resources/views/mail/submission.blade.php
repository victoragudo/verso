{{-- Email about one form submission. Values are escaped; line breaks are kept. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $formTitle }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f4f5;font-family:system-ui,sans-serif;color:#3f3f46">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e4e4e7;border-radius:8px;padding:24px">
        <p style="margin:0 0 16px;font-size:13px;color:#71717a">{{ $formTitle }}</p>
        <div style="font-size:15px;line-height:1.6">@foreach (preg_split('/\R/', (string) $body) ?: [] as $line)@shortcodes($line, 'form_email')<br>@endforeach</div>
        @if ($adminUrl)
            <p style="margin:24px 0 0"><a href="{{ $adminUrl }}" style="color:#2563eb">{{ __('forms::Open in the inbox') }}</a></p>
        @endif
    </div>
</body>
</html>
