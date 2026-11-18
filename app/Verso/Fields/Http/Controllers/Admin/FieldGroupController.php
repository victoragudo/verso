<?php

namespace App\Verso\Fields\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Core\Enums\Capability;
use App\Verso\Fields\Actions\DeleteFieldGroup;
use App\Verso\Fields\Actions\ImportFieldGroups;
use App\Verso\Fields\Actions\SaveFieldGroup;
use App\Verso\Fields\Definitions\FieldGroup;
use App\Verso\Fields\Exceptions\InvalidFieldDefinition;
use App\Verso\Fields\FieldGroupRegistry;
use App\Verso\Fields\Http\Presenters\FieldGroupPresenter;
use App\Verso\Fields\Http\Requests\FieldGroupRequest;
use App\Verso\Fields\Json\FieldGroupJson;
use App\Verso\Fields\Models\FieldGroupRecord;
use App\Verso\I18n\Translate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

// Admin pages to list, build, import and export field groups.
class FieldGroupController extends Controller
{
    public function __construct(
        protected FieldGroupRegistry $registry,
        protected FieldGroupPresenter $presenter,
    ) {}

    public function index(): Response
    {
        Gate::authorize(Capability::ManageFields->value);

        $ids = FieldGroupRecord::query()->pluck('id', 'key')->map(fn (mixed $id): int => (int) $id)->all();

        return Inertia::render('admin/fields/index', [
            'groups' => array_values(array_map(
                fn (FieldGroup $group): array => $this->presenter->row($group, $ids),
                $this->registry->all(),
            )),
            'problems' => $this->registry->problems(),
            'importMaxKb' => (int) config('verso.fields.import_max_kb'),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize(Capability::ManageFields->value);

        return Inertia::render('admin/fields/edit', [
            ...$this->presenter->builderOptions(),
            'group' => null,
        ]);
    }

    public function store(FieldGroupRequest $request, SaveFieldGroup $save): RedirectResponse
    {
        $record = $save->handle($request->definition());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Field group ":title" created.', ['title' => $record->title])]);

        return to_route('admin.fields.edit', $record);
    }

    public function edit(FieldGroupRecord $fieldGroup): Response
    {
        Gate::authorize(Capability::ManageFields->value);

        try {
            $definition = $fieldGroup->toDefinition();
        } catch (InvalidFieldDefinition) {
            $definition = null;
        }

        return Inertia::render('admin/fields/edit', [
            ...$this->presenter->builderOptions(),
            'group' => $definition === null
                ? [...$fieldGroup->only(['id', 'key', 'title', 'position', 'active', 'location', 'fields']), 'description' => (string) $fieldGroup->description, 'order' => $fieldGroup->menu_order, 'source' => FieldGroup::SOURCE_DATABASE]
                : $this->presenter->form($definition, $fieldGroup),
        ]);
    }

    public function update(FieldGroupRequest $request, SaveFieldGroup $save, FieldGroupRecord $fieldGroup): RedirectResponse
    {
        $save->handle($request->definition(), $fieldGroup);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Field group saved.')]);

        return to_route('admin.fields.edit', $fieldGroup);
    }

    public function destroy(DeleteFieldGroup $delete, FieldGroupRecord $fieldGroup): RedirectResponse
    {
        Gate::authorize(Capability::ManageFields->value);

        $delete->handle($fieldGroup);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Field group ":title" deleted.', ['title' => $fieldGroup->title])]);

        return to_route('admin.fields.index');
    }

    // Copies a group from code or a JSON file into the database, so it can be edited.
    public function copy(SaveFieldGroup $save, string $key): RedirectResponse
    {
        Gate::authorize(Capability::ManageFields->value);

        $group = $this->registry->find($key);
        abort_if($group === null, 404);
        abort_if($group->isEditable(), 409);

        $record = $save->handle($group);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('":title" can now be edited here.', ['title' => $group->title])]);

        return to_route('admin.fields.edit', $record);
    }

    // Downloads one group (?key=) or every group as JSON.
    public function export(Request $request, FieldGroupJson $json): HttpResponse
    {
        Gate::authorize(Capability::ManageFields->value);

        $key = $request->query('key');
        $found = is_string($key) && $key !== '' ? $this->registry->find($key) : null;
        $groups = is_string($key) && $key !== ''
            ? ($found === null ? [] : [$found])
            : array_values($this->registry->all());

        abort_if($groups === [], 404);

        $name = count($groups) === 1 ? $groups[0]->key : 'field-groups';

        return response($json->encode($groups), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$name}.json\"",
        ]);
    }

    public function import(Request $request, ImportFieldGroups $import): RedirectResponse
    {
        Gate::authorize(Capability::ManageFields->value);

        $request->validate([
            'file' => ['required', 'file', 'max:'.(int) config('verso.fields.import_max_kb')],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            $result = $import->handle((string) $file->get());
        } catch (InvalidFieldDefinition $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $count = count($result['created']) + count($result['updated']);
        Inertia::flash('toast', ['type' => 'success', 'message' => Translate::icu('{count, plural, one {Imported # field group.} other {Imported # field groups.}}', ['count' => $count])]);

        return to_route('admin.fields.index');
    }
}
