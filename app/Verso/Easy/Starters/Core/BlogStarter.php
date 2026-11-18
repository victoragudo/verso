<?php

namespace App\Verso\Easy\Starters\Core;

use App\Verso\Easy\Starters\StarterImage;
use App\Verso\Easy\Starters\StarterMenuItem;
use App\Verso\Easy\Starters\StarterPage;
use App\Verso\Easy\Starters\StarterSection;
use App\Verso\Easy\Starters\StarterSite;

// A personal blog about slow travel and food. The home page keeps the latest posts.
class BlogStarter implements CoreStarter
{
    public function site(): StarterSite
    {
        return new StarterSite(
            key: 'blog',
            label: 'Blog',
            description: 'Write about what you love. Posts on the home page, an about page and a way to reach you.',
            icon: 'posts',
            order: 10,
            stylePreset: 'calm',
            plugins: ['newsletter'],
            settings: ['site.tagline' => 'Slow travel, good food and the people in between'],
            images: [
                new StarterImage('blog-writer-portrait', 'Portrait of Elena Navarro smiling in a sunny street.', 'portrait', ['sky' => '#e9d8c4', 'horizon' => '#d7b99a', 'sun' => '#fff3e2', 'hills' => ['#c08a68', '#2e5e4e', '#3b2618']]),
                new StarterImage('blog-lisbon-tram', 'A yellow tram climbing a narrow street in Lisbon.', 'fields', ['sky' => '#a9c4d9', 'horizon' => '#e9dfc9', 'sun' => '#f5c542', 'hills' => ['#8c7b6b', '#b59f84', '#5e5045']]),
                new StarterImage('blog-market-breakfast', 'Fresh bread, figs and coffee on a market table.', 'still-life', ['sky' => '#efe2cc', 'horizon' => '#d9b98b', 'sun' => '#fff4e0', 'hills' => ['#8a5528', '#b5793f', '#5b3618']]),
            ],
            // i18n-ignore-start
            pages: [
                new StarterPage('About', 'about', [
                    new StarterSection('about', [
                        'heading' => 'Hi, I am Elena',
                        'text' => "I write about slow trips by train, small markets and the cooks who make a place feel like home.\n\nNew stories come every two weeks. Grab a coffee and stay a while.",
                        'images' => ['blog-writer-portrait'],
                    ]),
                    new StarterSection('newsletter', ['heading' => 'Get new stories by email', 'text' => 'One email every two weeks. No spam, ever.']),
                ]),
                new StarterPage('Contact', 'contact', [
                    new StarterSection('contact', [
                        'heading' => 'Say hello',
                        'text' => 'Tips for a market I should visit? A recipe from your grandmother? I read every message.',
                        'extra' => ['email' => 'hello@fieldnotes.example'],
                    ]),
                ]),
                new StarterPage('Three days in Lisbon by tram', 'three-days-in-lisbon-by-tram', [
                    new StarterSection('about', [
                        'heading' => 'Why the tram?',
                        'text' => "The 28 tram is slow, loud and always full. It is also the best way to see the hills of Lisbon without climbing them.\n\nHere is the route I took, the stops worth a walk and where to eat on the way.",
                        'images' => ['blog-lisbon-tram'],
                    ]),
                ], type: 'post', excerpt: 'The route, the best stops and where to eat on the way.'),
                new StarterPage('A Saturday at the market', 'a-saturday-at-the-market', [
                    new StarterSection('about', [
                        'heading' => 'Go early, bring cash',
                        'text' => "The best stalls sell out by ten. The baker on the corner keeps a loaf for regulars, so say hello twice and you are one.\n\nThis is what I bought for twelve euros, and what I cooked with it.",
                        'images' => ['blog-market-breakfast'],
                    ]),
                ], type: 'post', excerpt: 'What twelve euros buy at a city market, and what to cook with it.'),
            ],
            primaryMenu: [
                StarterMenuItem::link('Home', '/'),
                StarterMenuItem::page('about'),
                StarterMenuItem::page('contact'),
            ],
            footerMenu: [
                StarterMenuItem::page('about'),
                StarterMenuItem::link('RSS feed', '/feed'),
                StarterMenuItem::page('contact'),
            ],
            // i18n-ignore-end
        );
    }
}
