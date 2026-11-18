<?php

use App\Models\User;

// The admin page head follows the appearance cookie before any script runs.

it('marks the page dark and says so in the color-scheme meta tag', function () {
    $this->actingAs(User::factory()->admin()->create(['name' => 'Marta Quintana']))
        ->withUnencryptedCookie('appearance', 'dark')
        ->get('/admin')
        ->assertOk()
        ->assertSee('<meta name="color-scheme" content="dark">', false)
        ->assertSee('class="dark"', false);
});

it('lets the system choose with the system appearance', function () {
    $this->actingAs(User::factory()->admin()->create(['name' => 'Tomás Ibarra']))
        ->withUnencryptedCookie('appearance', 'system')
        ->get('/admin')
        ->assertOk()
        ->assertSee('<meta name="color-scheme" content="light dark">', false)
        ->assertDontSee('class="dark"', false);
});

it('falls back to light for an unknown cookie value', function () {
    $this->actingAs(User::factory()->admin()->create(['name' => 'Ines Okafor']))
        ->withUnencryptedCookie('appearance', "dark';alert(1)//")
        ->get('/admin')
        ->assertOk()
        ->assertSee('<meta name="color-scheme" content="light">', false)
        ->assertSee("const appearance = 'light';", false)
        ->assertDontSee('alert(1)', false);
});

it('paints the page background with light-dark() before the styles load', function () {
    $this->actingAs(User::factory()->admin()->create(['name' => 'Ruth Adeyemi']))
        ->get('/admin')
        ->assertOk()
        ->assertSee('light-dark(oklch(0.985 0 0), oklch(0.141 0.005 285.823))', false);
});
