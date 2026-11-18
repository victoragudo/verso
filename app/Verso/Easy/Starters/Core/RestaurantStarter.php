<?php

namespace App\Verso\Easy\Starters\Core;

use App\Verso\Easy\Starters\StarterImage;
use App\Verso\Easy\Starters\StarterMenuItem;
use App\Verso\Easy\Starters\StarterPage;
use App\Verso\Easy\Starters\StarterSection;
use App\Verso\Easy\Starters\StarterSite;

// A bakery and cafe: the menu with prices, opening hours, a map and a booking form.
class RestaurantStarter implements CoreStarter
{
    public function site(): StarterSite
    {
        return new StarterSite(
            key: 'restaurant',
            label: 'Restaurant or cafe',
            description: 'Your menu, opening hours, a map and a way to book a table.',
            icon: 'restaurant',
            order: 40,
            stylePreset: 'playful',
            plugins: ['forms'],
            settings: ['site.tagline' => 'Fresh bread and good coffee by the harbour'],
            images: [
                new StarterImage('restaurant-loaves', 'Golden sourdough loaves cooling on a wooden shelf.', 'still-life', ['sky' => '#efe2cc', 'horizon' => '#d9b98b', 'sun' => '#fff4e0', 'hills' => ['#8a5528', '#b5793f', '#5b3618']]),
                new StarterImage('restaurant-coffee', 'A cappuccino and a cinnamon bun on a marble table.', 'still-life', ['sky' => '#e9e4dc', 'horizon' => '#c9b8a2', 'sun' => '#f1c27d', 'hills' => ['#a99a88', '#d9a441', '#2f211a']]),
                new StarterImage('restaurant-harbour', 'Fishing boats in the harbour seen from the cafe terrace.', 'sea', ['sky' => '#2b2d5c', 'horizon' => '#f2a65a', 'sun' => '#ffd38a', 'hills' => ['#3c3a63', '#1f1d38', '#7a4b6b']]),
                new StarterImage('restaurant-counter', 'The bakery counter with cakes under glass domes.', 'arches', ['sky' => '#f6d77a', 'horizon' => '#f0c75a', 'sun' => '#fff6d4', 'hills' => ['#5a6b4a', '#e7b84a', '#355a46']]),
                new StarterImage('restaurant-baker', 'Portrait of Nuno Ferreira, head baker.', 'portrait', ['sky' => '#c9d8e2', 'horizon' => '#a9bfcd', 'sun' => '#f3f7fa', 'hills' => ['#a8735a', '#34495e', '#1d1d1f']]),
            ],
            // i18n-ignore-start
            pages: [
                new StarterPage('Home', 'home', [
                    new StarterSection('hero', [
                        'heading' => 'Baked at five, gone by noon',
                        'text' => 'Harbor Lane Bakery bakes sourdough, buns and cakes every morning, and pours good coffee all day.',
                        'button' => ['label' => 'See the menu', 'url' => '/menu'],
                        'images' => ['restaurant-loaves'],
                    ]),
                    new StarterSection('about', [
                        'heading' => 'A small bakery with a big oven',
                        'text' => 'We use flour from a mill two hours away, butter from local farms and a lot of time. Sit on the terrace and watch the boats come in.',
                        'images' => ['restaurant-harbour'],
                    ]),
                    new StarterSection('gallery', [
                        'heading' => 'From our counter',
                        'images' => ['restaurant-coffee', 'restaurant-counter', 'restaurant-loaves'],
                    ]),
                    new StarterSection('testimonials', [
                        'heading' => 'Our regulars say',
                        'items' => [
                            ['text' => 'The best cinnamon buns on the coast. I come every Saturday.', 'title' => 'Inês Carvalho'],
                            ['text' => 'Great coffee, kind staff and a view of the harbour.', 'title' => 'Mark Jensen'],
                        ],
                    ]),
                    new StarterSection('cta', [
                        'heading' => 'Coming with friends?',
                        'text' => 'Book a table on the terrace for brunch at the weekend.',
                        'button' => ['label' => 'Book a table', 'url' => '/visit-us'],
                    ]),
                ], front: true),
                new StarterPage('Menu', 'menu', [
                    new StarterSection('services', [
                        'heading' => 'Bread and pastries',
                        'items' => [
                            ['title' => 'Country sourdough · €5.50', 'text' => 'Our daily loaf, 24 hours of slow rising.'],
                            ['title' => 'Cinnamon bun · €3.20', 'text' => 'Soft, sticky and still warm before ten.'],
                            ['title' => 'Almond croissant · €3.80', 'text' => 'Filled with our own almond cream.'],
                        ],
                    ]),
                    new StarterSection('services', [
                        'heading' => 'Coffee and drinks',
                        'items' => [
                            ['title' => 'Flat white · €3.00', 'text' => 'Beans roasted in Porto.'],
                            ['title' => 'Fresh orange juice · €3.50', 'text' => 'Squeezed when you order.'],
                            ['title' => 'Chai latte · €3.40', 'text' => 'With oat milk if you like.'],
                        ],
                    ]),
                ]),
                new StarterPage('About', 'about', [
                    new StarterSection('about', [
                        'heading' => 'Our story',
                        'text' => "Nuno and Clara opened Harbor Lane in 2016 in an old fish shop. The oven came from Clara's grandmother.\n\nWe still bake everything here, every day.",
                    ]),
                    new StarterSection('team', [
                        'heading' => 'In the kitchen',
                        'items' => [
                            ['title' => 'Nuno Ferreira', 'meta' => 'Head baker', 'text' => 'Up at four, every day.', 'image' => 'restaurant-baker'],
                        ],
                    ]),
                ]),
                new StarterPage('Visit us', 'visit-us', [
                    new StarterSection('contact', [
                        'heading' => 'Visit or book a table',
                        'text' => 'Walk in any time. For groups of six or more, please book a day ahead.',
                        'extra' => [
                            'address' => 'Rua do Cais 12, 4050-201 Porto',
                            'phone' => '+351 220 123 456',
                            'email' => 'hello@harborlane.example',
                            'hours' => 'Tuesday to Sunday, 7:30 to 17:00',
                            'form_title' => 'Book a table',
                        ],
                    ]),
                    new StarterSection('map', [
                        'heading' => 'Find us by the harbour',
                        'extra' => ['latitude' => 41.1405, 'longitude' => -8.6133, 'label' => 'Harbor Lane Bakery', 'address' => 'Rua do Cais 12, Porto'],
                    ]),
                ]),
            ],
            primaryMenu: [
                StarterMenuItem::page('home'),
                StarterMenuItem::page('menu'),
                StarterMenuItem::page('about'),
                StarterMenuItem::page('visit-us'),
            ],
            footerMenu: [
                StarterMenuItem::page('menu'),
                StarterMenuItem::page('visit-us', 'Opening hours'),
            ],
            // i18n-ignore-end
        );
    }
}
