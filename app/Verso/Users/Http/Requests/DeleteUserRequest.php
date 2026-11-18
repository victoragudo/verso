<?php

namespace App\Verso\Users\Http\Requests;

use App\Models\User;
use App\Verso\Users\Support\ContentOwnership;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validates a delete. A user who owns content needs another user to receive it.
class DeleteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->target()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $target = $this->target();
        $hasContent = app(ContentOwnership::class)->hasContent($target);

        return [
            'reassign_to' => [
                $hasContent ? 'required' : 'nullable',
                'integer',
                Rule::exists(User::class, 'id'),
                Rule::notIn([$target->id]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reassign_to.required' => __('Choose who will receive the content of this user.'),
            'reassign_to.not_in' => __('Choose a different user to receive the content.'),
        ];
    }

    public function reassignTo(): ?User
    {
        return $this->filled('reassign_to') ? User::query()->find($this->integer('reassign_to')) : null;
    }

    public function target(): User
    {
        /** @var User $user */
        $user = $this->route('user');

        return $user;
    }
}
