<?php

namespace Verso\Plugins\Forms\Support;

use App\Verso\Content\Actions\UpdateEntry;
use App\Verso\Content\Models\Entry;
use Illuminate\Support\Str;
use Verso\Plugins\Forms\Actions\SaveForm;
use Verso\Plugins\Forms\FormsServiceProvider;
use Verso\Plugins\Forms\Models\Form;

// Demo content: a "Contact" form, placed on the demo Contact page. Safe to run twice.
class DemoContactForm
{
    public const SLUG = 'contact';

    public function __construct(
        protected SaveForm $saveForm,
        protected UpdateEntry $updateEntry,
    ) {}

    public function seed(): Form
    {
        $form = Form::query()->where('slug', self::SLUG)->first() ?? $this->saveForm->handle([
            'title' => 'Contact',
            'slug' => self::SLUG,
            'fields' => [
                ['id' => 'f_name', 'type' => 'text', 'name' => 'name', 'label' => 'Your name', 'required' => true, 'width' => 'half', 'max_length' => 120],
                ['id' => 'f_email', 'type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true, 'width' => 'half', 'help' => 'We only use it to answer you.'],
                ['id' => 'f_topic', 'type' => 'select', 'name' => 'topic', 'label' => 'Topic', 'required' => true, 'options' => ['A question about a post', 'Working together', 'Something else']],
                ['id' => 'f_message', 'type' => 'textarea', 'name' => 'message', 'label' => 'Message', 'required' => true, 'min_length' => 10, 'max_length' => 3000, 'placeholder' => 'How can we help?'],
                ['id' => 'f_consent', 'type' => 'consent', 'name' => 'consent', 'label' => 'I agree that Verso Journal stores my message to answer it.', 'required' => true, 'help' => 'Read more in our Privacy Policy.'],
            ],
            'settings' => [
                ...Form::DEFAULT_SETTINGS,
                'submit_label' => 'Send message',
                'success_message' => 'Thank you! We got your message and will answer within a few days.',
                'notify_emails' => [(string) config('mail.from.address')],
                'notify_subject' => 'New message about {topic} from {name}',
            ],
        ]);

        $this->placeOnContactPage($form);

        return $form;
    }

    // Adds a Form block to the end of the page with the slug "contact", when it has none yet.
    protected function placeOnContactPage(Form $form): void
    {
        $page = Entry::query()->where('type', 'page')->where('slug', self::SLUG)->first();

        if ($page === null) {
            return;
        }

        $blocks = $page->blocks;

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === FormsServiceProvider::BLOCK) {
                return;
            }
        }

        $blocks[] = [
            'id' => 'b_'.Str::lower(Str::random(6)),
            'type' => FormsServiceProvider::BLOCK,
            'attrs' => ['form' => $form->slug, 'title' => 'Send us a message', 'text' => 'Fields marked with * are required.'],
            'content' => '',
            'children' => [],
        ];

        $this->updateEntry->handle($page, ['blocks' => $blocks]);
    }
}
