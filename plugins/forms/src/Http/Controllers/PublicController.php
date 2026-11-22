<?php

namespace Verso\Plugins\Forms\Http\Controllers;

use App\Verso\Comments\Support\FormTimer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Verso\Plugins\Forms\Actions\SubmitForm;
use Verso\Plugins\Forms\Models\Form;
use Verso\Plugins\Forms\Spam\SpamGuard;

// Public endpoints of the forms: a fresh token for cached pages and the submit target.
class PublicController
{
    // Session key that tells the page which form was just sent.
    public const SENT_KEY = 'forms.sent';

    // Session key that tells the page which form the old input and errors belong to.
    public const ACTIVE_KEY = 'forms.active';

    // A fresh CSRF token and time trap token. Never cached, because they belong to the visitor.
    public function token(SpamGuard $spam): JsonResponse
    {
        return new JsonResponse([
            'token' => csrf_token(),
            'timer' => $spam->timerToken(),
            'timer_field' => FormTimer::FIELD,
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    public function submit(Request $request, Form $form, SubmitForm $submit): JsonResponse|RedirectResponse
    {
        try {
            $submit->handle($form, $request);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return redirect()->to($this->pageUrl($request, $form))
                ->withErrors($exception->errors(), $exception->errorBag)
                ->withInput($request->except(['_token', SpamGuard::honeypotField(), FormTimer::FIELD]))
                ->with(self::ACTIVE_KEY, $form->slug);
        }

        // Bots that were dropped get the same answer as people, so they learn nothing.
        $message = $form->visitorText('success_message');
        $redirect = trim((string) $form->setting('redirect_url'));

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $message, 'redirect' => $redirect !== '' ? $redirect : null]);
        }

        if ($redirect !== '') {
            return redirect()->to($redirect);
        }

        return redirect()->to($this->pageUrl($request, $form))->with(self::SENT_KEY, $form->slug);
    }

    // Back to the form on the page it was sent from, or to the home page.
    protected function pageUrl(Request $request, Form $form): string
    {
        $page = (string) $request->input('_page', '');
        $host = parse_url($page, PHP_URL_HOST);
        $base = $page !== '' && $host === $request->getHost() ? strtok($page, '#') : url()->previous(url('/'));

        return $base.'#'.$form->anchor();
    }
}
