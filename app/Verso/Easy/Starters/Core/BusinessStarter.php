<?php

namespace App\Verso\Easy\Starters\Core;

use App\Verso\Easy\Starters\StarterImage;
use App\Verso\Easy\Starters\StarterMenuItem;
use App\Verso\Easy\Starters\StarterPage;
use App\Verso\Easy\Starters\StarterSection;
use App\Verso\Easy\Starters\StarterSite;

// A small local business: a plumbing and heating company with services, prices and a contact form.
class BusinessStarter implements CoreStarter
{
    public function site(): StarterSite
    {
        return new StarterSite(
            key: 'business',
            label: 'Business',
            description: 'Show what you offer, why people trust you and how to reach you.',
            icon: 'site',
            order: 20,
            stylePreset: 'bold',
            plugins: ['forms'],
            settings: ['site.tagline' => 'Plumbing and heating in Bristol since 2009'],
            images: [
                new StarterImage('business-van', 'The blue Northwind van parked in front of a terraced house.', 'arches', ['sky' => '#cfe0ee', 'horizon' => '#e8eef6', 'sun' => '#ffffff', 'hills' => ['#2a4f8f', '#7d8f9a', '#1b3566']]),
                new StarterImage('business-owner', 'Portrait of Sam Whitfield, owner of Northwind.', 'portrait', ['sky' => '#c9d8e2', 'horizon' => '#a9bfcd', 'sun' => '#f3f7fa', 'hills' => ['#a8735a', '#34495e', '#1d1d1f']]),
                new StarterImage('business-engineer', 'Portrait of Priya Shah, heating engineer.', 'portrait', ['sky' => '#dfe5d3', 'horizon' => '#c5cfb2', 'sun' => '#fbfcf2', 'hills' => ['#8d5e45', '#7a4b6b', '#2a1a12']]),
            ],
            // i18n-ignore-start
            pages: [
                new StarterPage('Home', 'home', [
                    new StarterSection('hero', [
                        'heading' => 'Warm homes, no leaks, no fuss',
                        'text' => 'Northwind fixes boilers, leaks and bathrooms across Bristol. Fair prices, clean work and a ten year guarantee.',
                        'button' => ['label' => 'Ask for a free quote', 'url' => '/contact'],
                        'images' => ['business-van'],
                    ]),
                    new StarterSection('services', [
                        'heading' => 'What we do',
                        'items' => [
                            ['title' => 'Boiler service', 'text' => 'A yearly check that keeps your boiler safe and your bills low.'],
                            ['title' => 'Emergency repairs', 'text' => 'Burst pipe or no hot water? We come the same day.'],
                            ['title' => 'New bathrooms', 'text' => 'From the first sketch to the last tile, done in two weeks.'],
                        ],
                        'button' => ['label' => 'See all services', 'url' => '/services'],
                    ]),
                    new StarterSection('testimonials', [
                        'heading' => 'What our customers say',
                        'items' => [
                            ['text' => 'Sam came on a Sunday when our heating broke. Fixed in an hour, fair price.', 'title' => 'Helen Morris', 'meta' => 'Clifton'],
                            ['text' => 'Our new bathroom looks great and the team left everything spotless.', 'title' => 'Jamal Adeyemi', 'meta' => 'Redland'],
                        ],
                    ]),
                    new StarterSection('cta', [
                        'heading' => 'Need a hand at home?',
                        'text' => 'Tell us what is wrong and we will call you back within two hours.',
                        'button' => ['label' => 'Contact us', 'url' => '/contact'],
                    ]),
                ], front: true),
                new StarterPage('Services', 'services', [
                    new StarterSection('services', [
                        'heading' => 'Our services',
                        'text' => 'Every job comes with a written quote before we start.',
                        'items' => [
                            ['title' => 'Boiler service', 'text' => 'Safety check, cleaning and a gas safety record.'],
                            ['title' => 'Boiler install', 'text' => 'New A rated boilers with a ten year guarantee.'],
                            ['title' => 'Emergency repairs', 'text' => 'Leaks, blocked drains and no hot water, seven days a week.'],
                        ],
                    ]),
                    new StarterSection('pricing', [
                        'heading' => 'Simple prices',
                        'items' => [
                            ['title' => 'Boiler service', 'price' => '£89', 'text' => 'Once a year.', 'features' => ['Safety check', 'Gas safety record']],
                            ['title' => 'Home care plan', 'price' => '£14 a month', 'text' => 'Our most chosen plan.', 'features' => ['Yearly service', 'Free call outs', 'Parts included'], 'highlight' => true],
                        ],
                        'button' => ['label' => 'Ask for a quote', 'url' => '/contact'],
                    ]),
                    new StarterSection('faq', [
                        'items' => [
                            ['title' => 'Do you work on weekends?', 'text' => 'Yes, for emergencies. Normal jobs are Monday to Saturday.'],
                            ['title' => 'Are you Gas Safe registered?', 'text' => 'Yes. Every engineer carries a Gas Safe card.'],
                        ],
                    ]),
                ]),
                new StarterPage('About', 'about', [
                    new StarterSection('about', [
                        'heading' => 'A family company',
                        'text' => "Sam started Northwind in 2009 with one van and a lot of patience. Today we are a team of six, still answering the phone ourselves.\n\nWe live here too, so we treat every home like our own.",
                    ]),
                    new StarterSection('team', [
                        'items' => [
                            ['title' => 'Sam Whitfield', 'meta' => 'Owner', 'text' => 'Twenty years of fixing boilers.', 'image' => 'business-owner'],
                            ['title' => 'Priya Shah', 'meta' => 'Heating engineer', 'text' => 'Our expert in heat pumps.', 'image' => 'business-engineer'],
                        ],
                    ]),
                ]),
                new StarterPage('Contact', 'contact', [
                    new StarterSection('contact', [
                        'text' => 'Tell us what you need. We answer within two hours on working days.',
                        'extra' => ['address' => '14 Harbour Road, Bristol BS1 5TT', 'phone' => '0117 496 0321', 'email' => 'hello@northwind.example', 'hours' => 'Monday to Saturday, 8:00 to 18:00'],
                    ]),
                    new StarterSection('map', ['extra' => ['latitude' => 51.4545, 'longitude' => -2.5879, 'label' => 'Northwind Plumbing and Heating']]),
                ]),
            ],
            primaryMenu: [
                StarterMenuItem::page('home'),
                StarterMenuItem::page('services'),
                StarterMenuItem::page('about'),
                StarterMenuItem::page('contact'),
            ],
            footerMenu: [
                StarterMenuItem::page('services'),
                StarterMenuItem::page('contact'),
            ],
            // i18n-ignore-end
        );
    }
}
