{{ $formTitle }}

{{ $textBody }}
@if ($adminUrl)

{{ __('forms::Open in the inbox: :url', ['url' => $adminUrl]) }}
@endif
