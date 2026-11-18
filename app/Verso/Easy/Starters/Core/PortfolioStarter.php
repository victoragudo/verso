<?php

namespace App\Verso\Easy\Starters\Core;

use App\Verso\Easy\Starters\StarterImage;
use App\Verso\Easy\Starters\StarterMenuItem;
use App\Verso\Easy\Starters\StarterPage;
use App\Verso\Easy\Starters\StarterSection;
use App\Verso\Easy\Starters\StarterSite;

// A photographer's portfolio: big pictures, prices for sessions and a booking form.
class PortfolioStarter implements CoreStarter
{
    public function site(): StarterSite
    {
        return new StarterSite(
            key: 'portfolio',
            label: 'Portfolio',
            description: 'Show your best work in big pictures and let clients book you.',
            icon: 'media',
            order: 30,
            stylePreset: 'elegant',
            plugins: ['forms'],
            settings: ['site.tagline' => 'Weddings, portraits and quiet moments in Madrid'],
            images: [
                new StarterImage('portfolio-golden-hour', 'A couple walking through a wheat field at golden hour.', 'fields', ['sky' => '#f3d9b4', 'horizon' => '#e8c08a', 'sun' => '#fff1d6', 'hills' => ['#a4672f', '#c98d4b', '#7b4a22']]),
                new StarterImage('portfolio-sea-portrait', 'A woman looking at the sea from a rocky beach.', 'sea', ['sky' => '#8fb8d8', 'horizon' => '#e2edf2', 'sun' => '#fffbea', 'hills' => ['#4c7a8c', '#2b4d5c', '#7d8f7a']]),
                new StarterImage('portfolio-arches', 'Morning light through the arches of an old cloister.', 'arches', ['sky' => '#dfe7ea', 'horizon' => '#c7d3d6', 'sun' => '#fffbe9', 'hills' => ['#8a7a66', '#a9bfc6', '#4f6b75']]),
                new StarterImage('portfolio-mountains', 'Misty mountains above a small village.', 'landscape', ['sky' => '#8c9aa3', 'horizon' => '#d3dbd8', 'sun' => '#eef1ee', 'hills' => ['#6e8a6a', '#4a6b4f', '#2c4636']]),
                new StarterImage('portfolio-still-life', 'Wedding rings on an open book with dried flowers.', 'still-life', ['sky' => '#d8cfe0', 'horizon' => '#b8a9c9', 'sun' => '#ffe9c7', 'hills' => ['#6b5b7b', '#f4efe6', '#3d3149']]),
                new StarterImage('portfolio-marta', 'Portrait of Marta Ruiz holding her camera.', 'portrait', ['sky' => '#e9d8c4', 'horizon' => '#d7b99a', 'sun' => '#fff3e2', 'hills' => ['#c08a68', '#2e5e4e', '#3b2618']]),
            ],
            // i18n-ignore-start
            pages: [
                new StarterPage('Home', 'home', [
                    new StarterSection('hero', [
                        'heading' => 'Photos that feel like the day itself',
                        'text' => 'I am Marta Ruiz, a photographer in Madrid. I take calm, honest pictures of weddings, families and people at work.',
                        'button' => ['label' => 'Check my dates', 'url' => '/contact'],
                        'images' => ['portfolio-golden-hour'],
                    ]),
                    new StarterSection('gallery', [
                        'heading' => 'Recent work',
                        'images' => ['portfolio-sea-portrait', 'portfolio-arches', 'portfolio-mountains'],
                    ]),
                    new StarterSection('testimonials', [
                        'heading' => 'Kind words',
                        'items' => [
                            ['text' => 'We cried again when the photos came. Marta saw every little moment.', 'title' => 'Lucía and Javier', 'meta' => 'Wedding in Segovia'],
                            ['text' => 'Relaxed, fun and the kids loved her. Our best family photos ever.', 'title' => 'The Martín family', 'meta' => 'Family session'],
                        ],
                    ]),
                    new StarterSection('cta', [
                        'heading' => 'Planning something special?',
                        'text' => 'I take a small number of weddings each year. Write to me early.',
                        'button' => ['label' => 'Write to me', 'url' => '/contact'],
                    ]),
                ], front: true),
                new StarterPage('Work', 'work', [
                    new StarterSection('gallery', [
                        'heading' => 'Selected work',
                        'text' => 'Weddings, portraits and places from the last two years.',
                        'images' => ['portfolio-golden-hour', 'portfolio-sea-portrait', 'portfolio-arches', 'portfolio-mountains', 'portfolio-still-life'],
                    ]),
                ]),
                new StarterPage('About', 'about', [
                    new StarterSection('about', [
                        'heading' => 'Hello, I am Marta',
                        'text' => "I fell in love with film cameras at fifteen and never stopped. Today I shoot digital, but I still look for the same soft light.\n\nI work across Spain and Portugal, always with one assistant and no flash.",
                        'images' => ['portfolio-marta'],
                    ]),
                ]),
                new StarterPage('Prices', 'prices', [
                    new StarterSection('pricing', [
                        'heading' => 'Sessions and prices',
                        'items' => [
                            ['title' => 'Portrait session', 'price' => '€250', 'text' => 'One hour, one place.', 'features' => ['30 edited photos', 'Online gallery']],
                            ['title' => 'Wedding day', 'price' => '€2,400', 'text' => 'From getting ready to the first dance.', 'features' => ['10 hours', '500 edited photos', 'Printed album'], 'highlight' => true],
                        ],
                        'button' => ['label' => 'Check my dates', 'url' => '/contact'],
                    ]),
                    new StarterSection('faq', [
                        'items' => [
                            ['title' => 'When do we get the photos?', 'text' => 'Portraits in one week, weddings in six weeks.'],
                            ['title' => 'Do you travel?', 'text' => 'Yes. Travel inside Spain is included in the wedding price.'],
                        ],
                    ]),
                ]),
                new StarterPage('Contact', 'contact', [
                    new StarterSection('contact', [
                        'heading' => 'Let us talk',
                        'text' => 'Tell me the date, the place and a little about you. I answer within two days.',
                        'extra' => ['email' => 'hola@martaruiz.example', 'phone' => '+34 612 345 678'],
                    ]),
                ]),
            ],
            primaryMenu: [
                StarterMenuItem::page('home'),
                StarterMenuItem::page('work'),
                StarterMenuItem::page('about'),
                StarterMenuItem::page('prices'),
                StarterMenuItem::page('contact'),
            ],
            footerMenu: [
                StarterMenuItem::page('prices'),
                StarterMenuItem::page('contact'),
            ],
            // i18n-ignore-end
        );
    }
}
