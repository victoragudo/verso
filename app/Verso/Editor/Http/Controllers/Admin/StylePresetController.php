<?php

namespace App\Verso\Editor\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Editor\Styles\BlockStyleSchema;
use App\Verso\Editor\Styles\StylePresetChanged;
use App\Verso\Editor\Styles\StylePresets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

// Saves, changes and deletes style presets from the Styles panel. Answers JSON, so the editor keeps its unsaved page.
class StylePresetController extends Controller
{
    public function __construct(protected StylePresets $presets, protected BlockStyleSchema $schema) {}

    public function index(): JsonResponse
    {
        return response()->json(['presets' => $this->presets->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, string $preset): JsonResponse
    {
        abort_if($this->presets->find($preset) === null, 404);

        return $this->save($request, $preset);
    }

    public function destroy(string $preset): JsonResponse
    {
        abort_if($this->presets->find($preset) === null, 404);

        $this->presets->delete($preset);
        StylePresetChanged::dispatch($preset);

        return response()->json(['presets' => $this->presets->toArray()]);
    }

    protected function save(Request $request, ?string $key): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'design' => ['present', 'array'],
        ]);
        $errors = $this->schema->errors($data['design']);

        if ($errors !== []) {
            throw ValidationException::withMessages(['design' => $errors[0]]);
        }

        try {
            $preset = $this->presets->save($key, trim($data['label']), $data['design']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['label' => $exception->getMessage()]);
        }

        StylePresetChanged::dispatch($preset->key);

        return response()->json(['preset' => $preset->toArray(), 'presets' => $this->presets->toArray()]);
    }
}
