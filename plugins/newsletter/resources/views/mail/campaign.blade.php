{{-- Layout of every campaign email. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><title>{{ $campaign->subject }}</title></head>
<body style="margin:0;padding:24px;background:#fafafa;font-family:Figtree,Arial,sans-serif;color:#3f3f46;line-height:1.6">
    @if ($campaign->preheader)
        <div style="display:none;max-height:0;overflow:hidden">{{ $campaign->preheader }}</div>
    @endif
    <div style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #e4e4e7;border-radius:8px;padding:32px">
        {!! $body !!}
    </div>
    <p style="max-width:600px;margin:16px auto 0;text-align:center;font-size:12px;color:#71717a">
        {{ __('newsletter::You get this email because you subscribed.') }} <a href="{{ $unsubscribeUrl }}" style="color:#71717a">{{ __('newsletter::Unsubscribe') }}</a>
    </p>
    @if ($pixelUrl)
        <img src="{{ $pixelUrl }}" width="1" height="1" alt="" style="display:block;border:0">
    @endif
</body>
</html>
