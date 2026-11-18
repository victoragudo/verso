<?php

namespace App\Verso\Easy\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Easy\Actions\SaveSiteDetails;
use App\Verso\Easy\Details\SiteDetails;
use App\Verso\Easy\Http\Requests\SiteDetailsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

// "Your site details": how to reach you, your social profiles and what search engines show.
class SiteDetailsController extends Controller
{
    public function show(SiteDetails $details): Response
    {
        return Inertia::render('admin/easy/details', [
            'details' => $details->values(),
            'limits' => [
                'phone' => (int) config('verso.easy.details.phone_max'),
                'address' => (int) config('verso.easy.details.address_max'),
                'hours' => (int) config('verso.easy.details.hours_max'),
                'description' => (int) config('verso.seo.description_max_length'),
                'social' => (int) config('verso.seo.same_as_max'),
            ],
        ]);
    }

    public function update(SiteDetailsRequest $request, SaveSiteDetails $save): RedirectResponse
    {
        /** @var array{email?: string|null, phone?: string|null, address?: string|null, hours?: string|null, tagline?: string|null, description?: string|null, social?: list<string>|null} $values */
        $values = $request->validated();
        $save->handle($values);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your site details are saved.')]);

        return to_route('admin.easy.details');
    }
}
