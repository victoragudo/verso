<?php

namespace App\Verso\Easy\Starters\Core;

use App\Verso\Easy\Starters\StarterImage;
use App\Verso\Easy\Starters\StarterMenuItem;
use App\Verso\Easy\Starters\StarterPage;
use App\Verso\Easy\Starters\StarterSection;
use App\Verso\Easy\Starters\StarterSite;

// A two day design conference: programme, speakers, tickets and the venue.
class EventStarter implements CoreStarter
{
    public function site(): StarterSite
    {
        return new StarterSite(
            key: 'event',
            label: 'Event',
            description: 'The date, the programme, the speakers, tickets and how to get there.',
            icon: 'calendar',
            order: 60,
            stylePreset: 'bold',
            plugins: ['forms'],
            settings: ['site.tagline' => 'Two days of design talks by the sea, 14 and 15 May 2027'],
            images: [
                new StarterImage('event-stage', 'A full room listening to a talk under warm lights.', 'arches', ['sky' => '#3a3f44', 'horizon' => '#5b6066', 'sun' => '#f2c14e', 'hills' => ['#25292d', '#9aa3a8', '#121416']]),
                new StarterImage('event-speaker-ines', 'Portrait of Inés Lozano, speaker.', 'portrait', ['sky' => '#dfe5d3', 'horizon' => '#c5cfb2', 'sun' => '#fbfcf2', 'hills' => ['#8d5e45', '#7a4b6b', '#2a1a12']]),
                new StarterImage('event-speaker-kofi', 'Portrait of Kofi Mensah, speaker.', 'portrait', ['sky' => '#c9d8e2', 'horizon' => '#a9bfcd', 'sun' => '#f3f7fa', 'hills' => ['#a8735a', '#34495e', '#1d1d1f']]),
                new StarterImage('event-speaker-hanna', 'Portrait of Hanna Lindqvist, speaker.', 'portrait', ['sky' => '#e9d8c4', 'horizon' => '#d7b99a', 'sun' => '#fff3e2', 'hills' => ['#c08a68', '#2e5e4e', '#3b2618']]),
            ],
            // i18n-ignore-start
            pages: [
                new StarterPage('Home', 'home', [
                    new StarterSection('hero', [
                        'heading' => 'Valencia Design Days 2027',
                        'text' => '14 and 15 May at the Port Hall, Valencia. Twelve talks, two workshops and one long dinner by the sea.',
                        'button' => ['label' => 'Get your ticket', 'url' => '/tickets'],
                        'images' => ['event-stage'],
                    ]),
                    new StarterSection('about', [
                        'heading' => 'Small on purpose',
                        'text' => 'Only 300 seats, so you can meet the speakers and each other. Every talk is in English with live captions.',
                    ]),
                    new StarterSection('cta', [
                        'heading' => 'Early bird tickets end on 1 March',
                        'text' => 'Save €80 and get a seat at the speakers dinner.',
                        'button' => ['label' => 'Buy a ticket', 'url' => '/tickets'],
                    ]),
                ], front: true),
                new StarterPage('Programme', 'programme', [
                    new StarterSection('services', [
                        'heading' => 'Friday 14 May',
                        'items' => [
                            ['title' => '9:30 Doors and coffee', 'text' => 'Pick up your badge and meet people.'],
                            ['title' => '10:30 Designing for slow days', 'text' => 'Inés Lozano on calm interfaces.'],
                            ['title' => '15:00 Workshop: type that works', 'text' => 'Hands on with Hanna Lindqvist.'],
                        ],
                    ]),
                    new StarterSection('services', [
                        'heading' => 'Saturday 15 May',
                        'items' => [
                            ['title' => '10:00 Maps people can read', 'text' => 'Kofi Mensah on public signs.'],
                            ['title' => '13:00 Lunch on the terrace', 'text' => 'Local food, long tables.'],
                            ['title' => '20:00 Dinner by the sea', 'text' => 'For speakers and early bird guests.'],
                        ],
                    ]),
                ]),
                new StarterPage('Speakers', 'speakers', [
                    new StarterSection('team', [
                        'heading' => 'Speakers',
                        'items' => [
                            ['title' => 'Inés Lozano', 'meta' => 'Product designer, Madrid', 'text' => 'Designs tools for nurses and doctors.', 'image' => 'event-speaker-ines'],
                            ['title' => 'Kofi Mensah', 'meta' => 'Wayfinding designer, Accra', 'text' => 'Draws the signs of three city metros.', 'image' => 'event-speaker-kofi'],
                            ['title' => 'Hanna Lindqvist', 'meta' => 'Type designer, Malmö', 'text' => 'Makes fonts for small languages.', 'image' => 'event-speaker-hanna'],
                        ],
                    ]),
                ]),
                new StarterPage('Tickets', 'tickets', [
                    new StarterSection('pricing', [
                        'heading' => 'Tickets',
                        'items' => [
                            ['title' => 'Student', 'price' => '€90', 'text' => 'With a valid student card.', 'features' => ['Both days', 'Lunch and coffee']],
                            ['title' => 'Early bird', 'price' => '€220', 'text' => 'Until 1 March.', 'features' => ['Both days', 'Lunch and coffee', 'Speakers dinner'], 'highlight' => true],
                            ['title' => 'Regular', 'price' => '€300', 'text' => 'After 1 March.', 'features' => ['Both days', 'Lunch and coffee']],
                        ],
                        'button' => ['label' => 'Reserve a ticket', 'url' => '/venue'],
                    ]),
                    new StarterSection('faq', [
                        'items' => [
                            ['title' => 'Can I get a refund?', 'text' => 'Yes, until 30 days before the event. After that you can pass your ticket to a friend.'],
                            ['title' => 'Is the venue accessible?', 'text' => 'Yes. There is step free access, a quiet room and live captions.'],
                        ],
                    ]),
                ]),
                new StarterPage('Venue', 'venue', [
                    new StarterSection('map', [
                        'heading' => 'Port Hall, Valencia',
                        'text' => 'Ten minutes by tram from the city centre. Get off at Marina.',
                        'extra' => ['latitude' => 39.4598, 'longitude' => -0.3297, 'label' => 'Port Hall', 'address' => 'Moll de la Duana, 46024 Valencia'],
                    ]),
                    new StarterSection('contact', [
                        'heading' => 'Questions or group tickets',
                        'text' => 'Write to us and we answer within one working day.',
                        'extra' => ['email' => 'hello@designdays.example'],
                    ]),
                ]),
            ],
            primaryMenu: [
                StarterMenuItem::page('home'),
                StarterMenuItem::page('programme'),
                StarterMenuItem::page('speakers'),
                StarterMenuItem::page('tickets'),
                StarterMenuItem::page('venue'),
            ],
            footerMenu: [
                StarterMenuItem::page('tickets'),
                StarterMenuItem::page('venue', 'Contact'),
            ],
            // i18n-ignore-end
        );
    }
}
