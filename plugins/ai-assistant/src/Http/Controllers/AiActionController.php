<?php

namespace Verso\Plugins\AiAssistant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Media\Models\Media;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Verso\Plugins\AiAssistant\AiAssistant;
use Verso\Plugins\AiAssistant\AiSettings;
use Verso\Plugins\AiAssistant\Exceptions\AiException;
use Verso\Plugins\AiAssistant\Exceptions\AiRateLimited;
use Verso\Plugins\AiAssistant\Http\Requests\AltTextRequest;
use Verso\Plugins\AiAssistant\Http\Requests\BusinessRequest;
use Verso\Plugins\AiAssistant\Http\Requests\EntryTextRequest;
use Verso\Plugins\AiAssistant\Http\Requests\HeadlinesRequest;
use Verso\Plugins\AiAssistant\Http\Requests\OutlineRequest;
use Verso\Plugins\AiAssistant\Http\Requests\RewriteRequest;
use Verso\Plugins\AiAssistant\Http\Requests\SectionTextRequest;
use Verso\Plugins\AiAssistant\Http\Requests\TranslateRequest;

// JSON endpoints used by the editor panel. They return a preview and never save anything.
class AiActionController extends Controller
{
    public function __construct(protected AiAssistant $assistant) {}

    public function outline(OutlineRequest $request): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->assistant->outline(
            $user,
            $request->string('title')->trim()->toString(),
            $request->string('notes')->trim()->toString(),
        ));
    }

    public function rewrite(RewriteRequest $request): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->assistant->rewrite(
            $user,
            $request->string('text')->trim()->toString(),
            $request->string('mode')->toString(),
        ));
    }

    public function excerpt(EntryTextRequest $request): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->assistant->excerpt(
            $user,
            $request->string('title')->trim()->toString(),
            $request->blocks(),
        ));
    }

    public function seo(EntryTextRequest $request): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->assistant->seo(
            $user,
            $request->string('title')->trim()->toString(),
            $request->blocks(),
        ));
    }

    public function altText(AltTextRequest $request): JsonResponse
    {
        $media = Media::query()->findOrFail($request->integer('media_id'));

        Gate::authorize('view', $media);

        return $this->respond($request, fn (User $user): array => $this->assistant->altText($user, $media));
    }

    public function translate(TranslateRequest $request): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->assistant->translate(
            $user,
            $request->string('title')->trim()->toString(),
            $request->blocks(),
            $request->string('locale')->toString(),
        ));
    }

    // New texts for one section of the page editor, as a copy of the section.
    public function sectionText(SectionTextRequest $request, AiSettings $settings): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->assistant->sectionText(
            $user,
            $request->section(),
            $request->string('business')->trim()->toString(),
            $settings->site(),
        ));
    }

    public function headlines(HeadlinesRequest $request): JsonResponse
    {
        return $this->respond($request, fn (User $user): array => $this->assistant->headlines(
            $user,
            $request->string('headline')->trim()->toString(),
            $request->string('context')->trim()->toString(),
            $request->string('business')->trim()->toString(),
        ));
    }

    // Keeps the business description for the next time. It is a helper setting, not page content.
    public function saveBusiness(BusinessRequest $request, AiSettings $settings): JsonResponse
    {
        $settings->saveBusinessDescription($request->string('business')->toString());

        return response()->json(['result' => ['business' => $settings->businessDescription()]]);
    }

    /**
     * Run the action and turn assistant errors into JSON with a clear message.
     *
     * @param  Closure(User): array<string, mixed>  $action
     */
    protected function respond(Request $request, Closure $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $result = $action($user);
        } catch (AiException $exception) {
            $headers = $exception instanceof AiRateLimited ? ['Retry-After' => (string) $exception->retryAfter] : [];

            return response()->json(['message' => $exception->getMessage()], $exception->status(), $headers);
        }

        return response()->json([
            'result' => $result,
            'model' => app(AiSettings::class)->model(),
        ]);
    }
}
