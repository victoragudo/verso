<?php

namespace Verso\Plugins\Forms\View;

use App\Verso\Comments\Support\FormTimer;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Verso\Plugins\Forms\Http\Controllers\PublicController;
use Verso\Plugins\Forms\Models\Form;
use Verso\Plugins\Forms\Spam\CaptchaVerifier;
use Verso\Plugins\Forms\Spam\SpamGuard;
use Verso\Plugins\Forms\Support\FormValidator;

// Renders a public form with its errors, old values and spam protection. Used by the block and the Blade component.
class FormRenderer
{
    // The style and script are printed once per page, before the first form.
    protected bool $assetsPrinted = false;

    public function __construct(
        protected SpamGuard $spam,
        protected CaptchaVerifier $captcha,
    ) {}

    public function render(?Form $form, string $heading = '', string $intro = ''): string
    {
        if ($form === null) {
            return '';
        }

        $session = request()->hasSession() ? request()->session() : null;
        $active = $session?->get(PublicController::ACTIVE_KEY) === $form->slug;
        $bags = $session?->get('errors');
        $errors = $bags instanceof ViewErrorBag ? $bags->getBag(FormValidator::bag($form)) : new MessageBag;
        $old = $active ? $session->getOldInput('fields', []) : [];

        $html = view('forms::form', [
            'form' => $form,
            'fields' => $form->formFields(),
            'heading' => $heading,
            'intro' => $intro,
            'errors' => $errors,
            'old' => is_array($old) ? $old : [],
            'sent' => $session?->get(PublicController::SENT_KEY) === $form->slug,
            'captcha' => $this->captcha->widget(),
            'tokenUrl' => route('forms.token'),
            'timerField' => FormTimer::FIELD,
            'timerToken' => $this->spam->timerToken(),
            'honeypotField' => SpamGuard::honeypotField(),
            'pageUrl' => url()->current(),
            'withAssets' => ! $this->assetsPrinted,
        ])->render();

        $this->assetsPrinted = true;

        return $html;
    }
}
