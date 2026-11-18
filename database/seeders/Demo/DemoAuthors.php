<?php

namespace Database\Seeders\Demo;

// The demo writers, with a short bio and the file name of their portrait from MediaSeeder.
final class DemoAuthors
{
    /** @var array<string, array{name: string, email: string, bio: string, avatar: string}> */
    public const AUTHORS = [
        'clara' => [
            'name' => 'Clara Montes',
            'email' => 'clara@verso.test',
            'bio' => 'Clara cooks, bakes bread every Sunday and grows far too many tomatoes on a fourth floor balcony in Lisbon.',
            'avatar' => 'avatar-clara-montes',
        ],
        'pablo' => [
            'name' => 'Pablo Ortega',
            'email' => 'pablo@verso.test',
            'bio' => 'Pablo takes the photos. He prefers trains to planes, film to digital and a long walk to almost anything.',
            'avatar' => 'avatar-pablo-ortega',
        ],
        'ana' => [
            'name' => 'Ana Ribeiro',
            'email' => 'ana@verso.test',
            'bio' => 'Ana keeps the journal running and writes about work, tools and the art of doing a little less.',
            'avatar' => 'avatar-ana-ribeiro',
        ],
    ];
}
