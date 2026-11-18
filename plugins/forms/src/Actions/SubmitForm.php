<?php

namespace Verso\Plugins\Forms\Actions;

use App\Verso\I18n\Translate;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Verso\Plugins\Forms\Enums\SubmissionStatus;
use Verso\Plugins\Forms\Models\Form;
use Verso\Plugins\Forms\Models\Submission;
use Verso\Plugins\Forms\Spam\CaptchaVerifier;
use Verso\Plugins\Forms\Spam\SpamGuard;
use Verso\Plugins\Forms\Spam\SpamVerdict;
use Verso\Plugins\Forms\Support\FormValidator;
use Verso\Plugins\Forms\Support\IpHash;

// Checks, stores and announces one message sent with a public form.
class SubmitForm
{
    public function __construct(
        protected FormValidator $validator,
        protected SpamGuard $spam,
        protected CaptchaVerifier $captcha,
        protected SendSubmissionEmails $emails,
    ) {}

    // Returns the stored submission, or null when a bot was dropped quietly.
    public function handle(Form $form, Request $request): ?Submission
    {
        $ipHash = IpHash::of($request->ip());
        $rateKey = 'forms:'.$form->id.':'.($ipHash ?? 'unknown');
        $this->guardRate($form, $rateKey);

        $valid = $this->validator->validate($form, $request->all());

        // Only messages that pass validation count, so typing errors never lock a person out.
        RateLimiter::hit($rateKey, max(1, (int) config('forms.spam.rate_limit_minutes')) * 60);

        if (! $this->captcha->passes($request)) {
            throw ValidationException::withMessages(['captcha' => __('forms::Please confirm that you are not a robot.')])
                ->errorBag(FormValidator::bag($form));
        }

        $check = $this->spam->check($request, $valid['values']);

        if ($check->verdict === SpamVerdict::Bot && ! config('forms.spam.keep_bot_messages')) {
            return null;
        }

        $emailField = $form->emailField();
        $email = $emailField !== null ? $valid['values'][$emailField->name] ?? null : null;

        $submission = Submission::query()->create([
            'form_id' => $form->id,
            'status' => $check->isClean() ? SubmissionStatus::New : SubmissionStatus::Spam,
            'data' => $valid['values'],
            'files' => $this->storeFiles($form, $valid['files']),
            'email' => is_string($email) && $email !== '' ? $email : null,
            'ip_hash' => $ipHash,
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'page_url' => $this->pageUrl($request),
            'spam_reason' => $check->reason,
        ]);

        if ($check->isClean()) {
            $this->emails->handle($submission->setRelation('form', $form));
        }

        return $submission;
    }

    // Each visitor (by IP hash) may send a few messages per window.
    protected function guardRate(Form $form, string $key): void
    {
        $attempts = max(1, (int) config('forms.spam.rate_limit'));

        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            throw ValidationException::withMessages([
                'form' => Translate::icu('forms::{count, plural, one {You sent many messages in a short time. Please try again in # minute.} other {You sent many messages in a short time. Please try again in # minutes.}}', ['count' => $minutes]),
            ])->errorBag(FormValidator::bag($form));
        }
    }

    /**
     * @param  array<string, UploadedFile>  $files
     * @return array<string, array{path: string, name: string, size: int, mime: string}>|null
     */
    protected function storeFiles(Form $form, array $files): ?array
    {
        if ($files === []) {
            return null;
        }

        $stored = [];

        foreach ($files as $name => $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
            $path = $file->storeAs(
                trim((string) config('forms.folder'), '/').'/'.$form->id,
                Str::uuid()->toString().'.'.$extension,
                (string) config('forms.disk'),
            );

            if ($path === false) {
                continue;
            }

            $stored[$name] = [
                'path' => $path,
                'name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
                'size' => (int) $file->getSize(),
                'mime' => (string) $file->getMimeType(),
            ];
        }

        return $stored;
    }

    // The page the form was on, only when it is a page of this site.
    protected function pageUrl(Request $request): ?string
    {
        $page = (string) $request->input('_page', $request->headers->get('referer', ''));
        $host = parse_url($page, PHP_URL_HOST);

        return $page !== '' && $host === $request->getHost() ? Str::limit($page, 2000, '') : null;
    }
}
