<?php

namespace App\Verso\Users\Notifications;

use App\Verso\Core\Settings\Settings;
use App\Verso\I18n\Translate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Email with the link an invited user opens to choose a password.
class UserInvitation extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $url,
        public readonly int $expiresHours,
        public readonly ?string $invitedBy = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = (string) app(Settings::class)->get('site.name');
        $intro = $this->invitedBy === null
            ? __('You have been invited to join :site.', ['site' => $site])
            : __(':name has invited you to join :site.', ['name' => $this->invitedBy, 'site' => $site]);

        return (new MailMessage)
            ->subject(__('Your invitation to :site', ['site' => $site]))
            ->line($intro)
            ->line(__('Choose a password to activate your account.'))
            ->action(__('Accept invitation'), $this->url)
            ->line(Translate::icu('{hours, plural, one {This link expires in # hour.} other {This link expires in # hours.}}', ['hours' => $this->expiresHours]));
    }
}
