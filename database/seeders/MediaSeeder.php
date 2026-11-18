<?php

namespace Database\Seeders;

use App\Models\User;
use App\Verso\Media\Models\Media;
use Database\Seeders\Support\DemoImagePainter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

// Seeds the demo photos and author portraits: small JPEG files painted with GD and stored on the media disk.
class MediaSeeder extends Seeder
{
    /**
     * File name (without .jpg) => alt text, caption, composition and colors.
     *
     * @var array<string, array{alt: string, caption: string, style: string, palette: array{sky: string, horizon: string, sun: string, hills: list<string>}}>
     */
    public const IMAGES = [
        'porto-ribeira-at-dusk' => [
            'alt' => 'Colourful houses of the Ribeira district in Porto above the Douro river at dusk.',
            'caption' => 'The Ribeira from the Gaia side, just after sunset.',
            'style' => 'sea',
            'palette' => ['sky' => '#2b2d5c', 'horizon' => '#f2a65a', 'sun' => '#ffd38a', 'hills' => ['#3c3a63', '#1f1d38', '#7a4b6b']],
        ],
        'sourdough-loaf-on-a-board' => [
            'alt' => 'A round sourdough loaf with a dark, crackled crust cooling on a wooden board.',
            'caption' => 'Sunday bake: 75% hydration, 18 hours in the fridge.',
            'style' => 'still-life',
            'palette' => ['sky' => '#efe2cc', 'horizon' => '#d9b98b', 'sun' => '#fff4e0', 'hills' => ['#8a5528', '#b5793f', '#5b3618']],
        ],
        'coffee-and-pastel-de-nata' => [
            'alt' => 'A short black coffee and a pastel de nata on a marble counter in a Lisbon cafe.',
            'caption' => 'Breakfast in Principe Real.',
            'style' => 'still-life',
            'palette' => ['sky' => '#e9e4dc', 'horizon' => '#c9b8a2', 'sun' => '#f1c27d', 'hills' => ['#a99a88', '#d9a441', '#2f211a']],
        ],
        'balcony-cherry-tomatoes' => [
            'alt' => 'Ripe cherry tomatoes hanging from a plant on a sunny city balcony.',
            'caption' => 'The first red tomatoes of August.',
            'style' => 'landscape',
            'palette' => ['sky' => '#9ed0f0', 'horizon' => '#e8f4d9', 'sun' => '#ff6b4a', 'hills' => ['#7fb04f', '#4f8a34', '#2e5e22']],
        ],
        'pyrenees-beech-forest-after-rain' => [
            'alt' => 'A misty path through a beech forest in the Pyrenees after the rain.',
            'caption' => 'Six hours of rain and not a single other hiker.',
            'style' => 'landscape',
            'palette' => ['sky' => '#8c9aa3', 'horizon' => '#d3dbd8', 'sun' => '#eef1ee', 'hills' => ['#6e8a6a', '#4a6b4f', '#2c4636']],
        ],
        'paella-pan-for-twelve' => [
            'alt' => 'A wide paella pan with saffron rice, green beans and lemon wedges on a garden table.',
            'caption' => 'Paella for twelve, the Valencian way.',
            'style' => 'still-life',
            'palette' => ['sky' => '#fbe3a1', 'horizon' => '#f4b942', 'sun' => '#fff7d6', 'hills' => ['#6d8f3c', '#d98a2b', '#6d3b12']],
        ],
        'desk-by-the-window' => [
            'alt' => 'A tidy wooden desk with a laptop and a notebook in front of a bright window.',
            'caption' => 'Our home office, before the first call of the day.',
            'style' => 'arches',
            'palette' => ['sky' => '#dfe7ea', 'horizon' => '#c7d3d6', 'sun' => '#fffbe9', 'hills' => ['#8a7a66', '#a9bfc6', '#4f6b75']],
        ],
        'bicycle-against-a-wall' => [
            'alt' => 'A green steel bicycle with a front rack leaning against a sunny yellow wall.',
            'caption' => 'Twelve kilos of steel and the best commute in town.',
            'style' => 'arches',
            'palette' => ['sky' => '#f6d77a', 'horizon' => '#f0c75a', 'sun' => '#fff6d4', 'hills' => ['#5a6b4a', '#e7b84a', '#355a46']],
        ],
        'market-stall-with-loaves' => [
            'alt' => 'Loaves of bread stacked on a market stall under a striped awning.',
            'caption' => 'Saturday, 9 am, before the queue.',
            'style' => 'fields',
            'palette' => ['sky' => '#f3d9b4', 'horizon' => '#e8c08a', 'sun' => '#fff1d6', 'hills' => ['#a4672f', '#c98d4b', '#7b4a22']],
        ],
        'film-camera-and-rolls' => [
            'alt' => 'An old film camera next to three rolls of colour film on a dark table.',
            'caption' => 'A flea market camera and a week of film.',
            'style' => 'still-life',
            'palette' => ['sky' => '#3a3f44', 'horizon' => '#5b6066', 'sun' => '#f2c14e', 'hills' => ['#25292d', '#9aa3a8', '#121416']],
        ],
        'compost-and-seedlings' => [
            'alt' => 'Young seedlings in dark compost in a wooden box on a balcony.',
            'caption' => 'Worm compost, six months later.',
            'style' => 'fields',
            'palette' => ['sky' => '#cfe3c1', 'horizon' => '#e6eed6', 'sun' => '#fbfbe6', 'hills' => ['#3f2d1f', '#5f8b3e', '#2a1d14']],
        ],
        'open-book-and-tea' => [
            'alt' => 'An open book and a cup of tea on a blanket in soft evening light.',
            'caption' => 'Sunday reading, phone in another room.',
            'style' => 'still-life',
            'palette' => ['sky' => '#d8cfe0', 'horizon' => '#b8a9c9', 'sun' => '#ffe9c7', 'hills' => ['#6b5b7b', '#f4efe6', '#3d3149']],
        ],
        'porto-blue-tiles' => [
            'alt' => 'Blue and white azulejo tiles on the front of an old house in Porto.',
            'caption' => 'Tiles in Bonfim.',
            'style' => 'arches',
            'palette' => ['sky' => '#e8eef6', 'horizon' => '#cfdcec', 'sun' => '#ffffff', 'hills' => ['#2a4f8f', '#e8eef6', '#1b3566']],
        ],
        'porto-tram-on-a-hill' => [
            'alt' => 'A yellow vintage tram climbing a steep cobbled street in Porto.',
            'caption' => 'The 22 tram near Batalha.',
            'style' => 'fields',
            'palette' => ['sky' => '#a9c4d9', 'horizon' => '#e9dfc9', 'sun' => '#f5c542', 'hills' => ['#8c7b6b', '#b59f84', '#5e5045']],
        ],
        'porto-river-bend' => [
            'alt' => 'The Douro river bending under the iron Dom Luis bridge on a clear morning.',
            'caption' => 'The Douro from the upper deck of the bridge.',
            'style' => 'sea',
            'palette' => ['sky' => '#8fb8d8', 'horizon' => '#e2edf2', 'sun' => '#fffbea', 'hills' => ['#4c7a8c', '#2b4d5c', '#7d8f7a']],
        ],
    ];

    /**
     * Square author photos: file name => alt text and colors.
     *
     * @var array<string, array{alt: string, palette: array{sky: string, horizon: string, sun: string, hills: list<string>}}>
     */
    public const AVATARS = [
        'avatar-clara-montes' => [
            'alt' => 'Portrait of Clara Montes.',
            'palette' => ['sky' => '#e9d8c4', 'horizon' => '#d7b99a', 'sun' => '#fff3e2', 'hills' => ['#c08a68', '#2e5e4e', '#3b2618']],
        ],
        'avatar-pablo-ortega' => [
            'alt' => 'Portrait of Pablo Ortega.',
            'palette' => ['sky' => '#c9d8e2', 'horizon' => '#a9bfcd', 'sun' => '#f3f7fa', 'hills' => ['#a8735a', '#34495e', '#1d1d1f']],
        ],
        'avatar-ana-ribeiro' => [
            'alt' => 'Portrait of Ana Ribeiro.',
            'palette' => ['sky' => '#dfe5d3', 'horizon' => '#c5cfb2', 'sun' => '#fbfcf2', 'hills' => ['#8d5e45', '#7a4b6b', '#2a1a12']],
        ],
    ];

    public function run(): void
    {
        $width = (int) config('verso.demo.image_width');
        $height = (int) config('verso.demo.image_height');
        $avatar = (int) config('verso.demo.avatar_size');
        $painter = new DemoImagePainter($width, $height, (int) config('verso.demo.image_quality'));
        $uploader = User::query()->where('role', 'admin')->value('id');
        $seed = 0;

        foreach (self::IMAGES as $name => $image) {
            $bytes = $painter->paint($image['palette'], ++$seed, $image['style']);
            $this->store($name, $bytes, $width, $height, $image['alt'], $image['caption'], $uploader);
        }

        foreach (self::AVATARS as $name => $image) {
            $bytes = $painter->paintSized($image['palette'], ++$seed, 'portrait', $avatar, $avatar);
            $this->store($name, $bytes, $avatar, $avatar, $image['alt'], '', $uploader);
        }
    }

    // Writes the file and saves its media row. A second run updates the same row, so seeding twice adds nothing.
    protected function store(string $name, string $bytes, int $width, int $height, string $alt, string $caption, mixed $uploader): void
    {
        $disk = (string) config('verso.media.disk');
        $path = self::path($name);

        Storage::disk($disk)->put($path, $bytes);

        $media = Media::query()->firstOrNew(['disk' => $disk, 'path' => $path]);
        $media->fill([
            'filename' => $name.'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => strlen($bytes),
            'width' => $width,
            'height' => $height,
            'alt' => $alt,
            'caption' => $caption,
            'meta' => [],
        ]);
        $media->uploaded_by ??= is_numeric($uploader) ? (int) $uploader : null;
        $media->save();
    }

    // Path of a demo image on the media disk, like "demo/paella-pan-for-twelve.jpg".
    public static function path(string $name): string
    {
        return trim((string) config('verso.demo.media_folder'), '/').'/'.$name.'.jpg';
    }
}
