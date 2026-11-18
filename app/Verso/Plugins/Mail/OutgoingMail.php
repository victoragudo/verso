<?php

namespace App\Verso\Plugins\Mail;

use Symfony\Component\Mime\Email;

// A mail that is about to be sent. Plugins change the Email object or cancel the mail.
final readonly class OutgoingMail
{
    /**
     * @param  array<array-key, mixed>  $data  the view data of the mail
     */
    public function __construct(
        public Email $message,
        public array $data = [],
        public bool $cancelled = false,
    ) {}

    // A copy that will not be sent.
    public function cancel(): self
    {
        return new self($this->message, $this->data, true);
    }

    public function subject(): string
    {
        return (string) $this->message->getSubject();
    }

    /** @return list<string> */
    public function recipients(): array
    {
        return array_values(array_map(fn ($address): string => $address->getAddress(), $this->message->getTo()));
    }
}
