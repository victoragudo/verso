{{-- Shown in place of members only content. --}}
<div class="verso-members-teaser">
    <p class="verso-members-teaser__message">{{ $message }}</p>
    @if ($loginUrl || $joinUrl)
        <p class="verso-members-teaser__actions">
            @if ($joinUrl)
                <a class="verso-members-teaser__join" href="{{ $joinUrl }}">{{ __('memberships::Become a member') }}</a>
            @endif
            @if ($loginUrl)
                <a class="verso-members-teaser__login" href="{{ $loginUrl }}">{{ __('memberships::Log in') }}</a>
            @endif
        </p>
    @endif
</div>
