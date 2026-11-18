<?php

namespace App\Verso\Comments\Http\Requests;

use App\Verso\Comments\Support\CommentAvailability;
use App\Verso\Comments\Support\FormTimer;
use App\Verso\Content\Models\Entry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

// Validates a public comment, with the time trap and the honeypot field.
class SubmitCommentRequest extends FormRequest
{
    // Errors go to their own bag so they do not mix with other forms on the page.
    protected $errorBag = 'comment';

    public function authorize(): bool
    {
        return app(CommentAvailability::class)->isOpenFor($this->entry());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $guest = $this->user() === null;

        return [
            'body' => ['required', 'string', 'max:'.(int) config('verso.comments.max_length')],
            'author_name' => $guest ? ['required', 'string', 'max:255'] : ['nullable'],
            'author_email' => $guest ? ['required', 'string', 'email', 'max:255'] : ['nullable'],
            'author_url' => $guest ? ['nullable', 'string', 'url:http,https', 'max:255'] : ['nullable'],
            'parent_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => __('Please write a comment.'),
            'author_name.required' => __('Please tell us your name.'),
            'author_email.required' => __('Please give your email. It will not be shown.'),
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $timer = app(FormTimer::class);
                $token = $this->string(FormTimer::FIELD)->toString();

                if ($timer->isExpired($token)) {
                    $validator->errors()->add('form', __('This form has expired. Please reload the page and try again.'));
                } elseif ($timer->isTooFast($token)) {
                    $validator->errors()->add('form', __('That was very fast. Please wait a moment and send your comment again.'));
                }
            },
        ];
    }

    // True when the hidden honeypot field is filled. Only bots do that.
    public function isBot(): bool
    {
        return $this->filled((string) config('verso.comments.honeypot_field'));
    }

    /**
     * @return array{body: string, author_name: string|null, author_email: string|null, author_url: string|null, parent_id: int|null, ip: string|null, user_agent: string|null}
     */
    public function commentData(): array
    {
        return [
            'body' => (string) $this->validated('body'),
            'author_name' => $this->filled('author_name') ? (string) $this->validated('author_name') : null,
            'author_email' => $this->filled('author_email') ? (string) $this->validated('author_email') : null,
            'author_url' => $this->filled('author_url') ? (string) $this->validated('author_url') : null,
            'parent_id' => $this->filled('parent_id') ? $this->integer('parent_id') : null,
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
        ];
    }

    public function entry(): Entry
    {
        /** @var Entry $entry */
        $entry = $this->route('entry');

        return $entry;
    }
}
