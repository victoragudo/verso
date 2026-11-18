<?php

namespace App\Verso\Importer\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Importer\Http\Presenters\ImportPresenter;
use App\Verso\Importer\Http\Requests\ImportUploadRequest;
use App\Verso\Importer\Jobs\RunWordPressImport;
use App\Verso\Importer\Models\Import;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

// Admin pages to upload a WordPress export and follow the import.
class ImportController extends Controller
{
    public function index(): Response
    {
        $imports = Import::query()
            ->with('user')
            ->latest('id')
            ->limit((int) config('verso.importer.recent_limit'))
            ->get()
            ->map(ImportPresenter::summary(...))
            ->all();

        return Inertia::render('admin/importer/index', [
            'imports' => $imports,
            'maxUploadKb' => (int) config('verso.importer.max_upload_kb'),
        ]);
    }

    public function store(ImportUploadRequest $request): RedirectResponse
    {
        $file = $request->exportFile();
        $disk = (string) config('verso.importer.disk');

        $import = new Import([
            'source' => Import::SOURCE_WORDPRESS,
            'disk' => $disk,
            'path' => (string) $file->store((string) config('verso.importer.directory'), $disk),
            'original_name' => $file->getClientOriginalName(),
        ]);
        $import->user()->associate($request->user());
        $import->save();

        RunWordPressImport::dispatch($import->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Import started. You can leave this page.')]);

        return to_route('admin.import.show', $import);
    }

    public function show(Import $import): Response
    {
        $import->load('user');

        return Inertia::render('admin/importer/show', [
            'import' => ImportPresenter::detail($import),
            'pollInterval' => (int) config('verso.importer.poll_interval'),
        ]);
    }
}
