<?php

namespace App\Verso\Easy\Starters\Core;

use App\Verso\Easy\Starters\StarterImage;
use App\Verso\Easy\Starters\StarterMenuItem;
use App\Verso\Easy\Starters\StarterPage;
use App\Verso\Easy\Starters\StarterSection;
use App\Verso\Easy\Starters\StarterSite;

// A personal page: who you are, what you do and how to reach you, on two pages.
class PersonalStarter implements CoreStarter
{
    public function site(): StarterSite
    {
        return new StarterSite(
            key: 'personal',
            label: 'Personal page',
            description: 'One simple page about you, your work and how to reach you.',
            icon: 'person',
            order: 70,
            stylePreset: 'calm',
            settings: ['site.tagline' => 'UX writer helping apps speak plainly'],
            images: [
                new StarterImage('personal-portrait', 'Portrait of Daniel Okafor in a bright room.', 'portrait', ['sky' => '#dfe5d3', 'horizon' => '#c5cfb2', 'sun' => '#fbfcf2', 'hills' => ['#8d5e45', '#2e5e4e', '#2a1a12']]),
            ],
            // i18n-ignore-start
            pages: [
                new StarterPage('Home', 'home', [
                    new StarterSection('hero', [
                        'heading' => 'Hi, I am Daniel Okafor',
                        'text' => 'I write the words inside apps: buttons, errors and the little lines that help people get things done.',
                        'button' => ['label' => 'Work with me', 'url' => '/contact'],
                        'images' => ['personal-portrait'],
                    ]),
                    new StarterSection('services', [
                        'heading' => 'What I do',
                        'items' => [
                            ['title' => 'Interface writing', 'text' => 'Clear words for every screen of your product.'],
                            ['title' => 'Voice and tone guides', 'text' => 'So your whole team writes the same way.'],
                            ['title' => 'Workshops', 'text' => 'Half day sessions for designers and developers.'],
                        ],
                    ]),
                    new StarterSection('about', [
                        'heading' => 'A little about me',
                        'text' => "Before writing for apps, I wrote for a newspaper in Lagos and a museum in Berlin.\n\nI live in Rotterdam with my partner and a very loud cat.",
                    ]),
                    new StarterSection('cta', [
                        'heading' => 'Have a product that needs better words?',
                        'button' => ['label' => 'Say hello', 'url' => '/contact'],
                    ]),
                ], front: true),
                new StarterPage('Contact', 'contact', [
                    new StarterSection('contact', [
                        'heading' => 'Say hello',
                        'text' => 'The best way to reach me is by email. I answer within two days.',
                        'extra' => ['email' => 'daniel@okafor.example'],
                    ]),
                ]),
            ],
            primaryMenu: [
                StarterMenuItem::page('home'),
                StarterMenuItem::page('contact'),
            ],
            footerMenu: [
                StarterMenuItem::page('contact'),
            ],
            // i18n-ignore-end
        );
    }
}
