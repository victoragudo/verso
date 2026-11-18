{{-- Double opt-in email. --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body style="margin:0;padding:24px;background:#fafafa;font-family:Figtree,Arial,sans-serif;color:#3f3f46">
    <div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e4e4e7;border-radius:8px;padding:32px">
        <h1 style="margin:0 0 16px;font-size:20px;color:#18181b">{{ __('newsletter::Confirm your subscription') }}</h1>
        <p>{{ __('newsletter::Thanks for signing up to the :site newsletter. Please confirm your email address.', ['site' => $siteName]) }}</p>
        <p style="margin:24px 0"><a href="{{ $confirmUrl }}" style="background:#18181b;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none">{{ __('newsletter::Confirm my subscription') }}</a></p>
        <p style="font-size:13px;color:#71717a">{{ __('newsletter::If you did not sign up, ignore this email and nothing will happen.') }}</p>
    </div>
</body>
</html>
