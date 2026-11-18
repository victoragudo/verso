<?php

namespace App\Verso\Easy\Starters\Core;

use App\Verso\Easy\Starters\StarterImage;
use App\Verso\Easy\Starters\StarterMenuItem;
use App\Verso\Easy\Starters\StarterPage;
use App\Verso\Easy\Starters\StarterSection;
use App\Verso\Easy\Starters\StarterSite;

// A small online shop for handmade ceramics, with the Shop plugin turned on.
class ShopStarter implements CoreStarter
{
    public function site(): StarterSite
    {
        return new StarterSite(
            key: 'shop',
            label: 'Online shop',
            description: 'Sell your products with a shop page, shipping answers and a contact page.',
            icon: 'shop',
            order: 50,
            stylePreset: 'bold',
            plugins: ['shop', 'forms'],
            settings: ['site.tagline' => 'Handmade ceramics for everyday tables'],
            images: [
                new StarterImage('shop-studio', 'Stacks of glazed bowls on shelves in a pottery studio.', 'arches', ['sky' => '#e8eef6', 'horizon' => '#cfdcec', 'sun' => '#ffffff', 'hills' => ['#2a4f8f', '#e8eef6', '#1b3566']]),
                new StarterImage('shop-mugs', 'Three speckled mugs with coffee on a linen cloth.', 'still-life', ['sky' => '#e9e4dc', 'horizon' => '#c9b8a2', 'sun' => '#f1c27d', 'hills' => ['#a99a88', '#d9a441', '#2f211a']]),
            ],
            // i18n-ignore-start
            pages: [
                new StarterPage('Home', 'home', [
                    new StarterSection('hero', [
                        'heading' => 'Made by hand, made to be used',
                        'text' => 'Lumen Ceramics makes mugs, bowls and plates in a small studio in Valencia. Every piece is a little different.',
                        'button' => ['label' => 'Visit the shop', 'url' => '/shop'],
                        'images' => ['shop-studio'],
                    ]),
                    new StarterSection('shop-products', ['heading' => 'New in the shop']),
                    new StarterSection('services', [
                        'heading' => 'Why people love our pieces',
                        'items' => [
                            ['title' => 'Safe for every day', 'text' => 'Dishwasher and microwave safe, lead free glazes.'],
                            ['title' => 'Free shipping', 'text' => 'On orders over €60 in Spain and Portugal.'],
                            ['title' => 'Packed with care', 'text' => 'Recycled paper only, no plastic.'],
                        ],
                    ]),
                    new StarterSection('testimonials', [
                        'items' => [
                            ['text' => 'My morning coffee tastes better in this mug. I bought two more.', 'title' => 'Sofia Bianchi'],
                            ['text' => 'Beautiful bowls, fast shipping and a lovely handwritten note.', 'title' => 'Daniel Weber'],
                        ],
                    ]),
                ], front: true),
                new StarterPage('About', 'about', [
                    new StarterSection('about', [
                        'heading' => 'From our studio',
                        'text' => "Lumen is Irene and her wheel. She throws, glazes and fires every piece in a studio by the old port.\n\nSmall batches mean some pieces sell out fast. Join the newsletter to hear first.",
                        'images' => ['shop-mugs'],
                    ]),
                ]),
                new StarterPage('Shipping and returns', 'shipping-and-returns', [
                    new StarterSection('faq', [
                        'heading' => 'Shipping and returns',
                        'items' => [
                            ['title' => 'How long does shipping take?', 'text' => 'Two to four working days in Spain and Portugal, one week to the rest of Europe.'],
                            ['title' => 'Can I return a piece?', 'text' => 'Yes, within 30 days, unused and in the box. We pay the return label.'],
                            ['title' => 'Something arrived broken?', 'text' => 'Send us a photo within a week and we send a new one.'],
                        ],
                    ]),
                ]),
                new StarterPage('Contact', 'contact', [
                    new StarterSection('contact', [
                        'text' => 'Questions about an order or a custom set? Write to us.',
                        'extra' => ['email' => 'studio@lumenceramics.example', 'address' => 'Carrer del Mar 8, 46011 Valencia'],
                    ]),
                ]),
            ],
            primaryMenu: [
                StarterMenuItem::page('home'),
                StarterMenuItem::route('theme.shop.index', 'Shop'),
                StarterMenuItem::page('about'),
                StarterMenuItem::page('contact'),
            ],
            footerMenu: [
                StarterMenuItem::page('shipping-and-returns'),
                StarterMenuItem::page('contact'),
            ],
            // i18n-ignore-end
        );
    }
}
