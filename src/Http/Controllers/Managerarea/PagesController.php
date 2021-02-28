<?php

declare(strict_types=1);

namespace Cortex\Pages\Http\Controllers\Managerarea;

use Exception;
use Illuminate\Http\Request;
use Cortex\Pages\Models\Page;
use Cortex\Foundation\Http\FormRequest;
use Cortex\Foundation\DataTables\LogsDataTable;
use Cortex\Foundation\Importers\DefaultImporter;
use Cortex\Foundation\DataTables\ImportLogsDataTable;
use Cortex\Foundation\Http\Requests\ImportFormRequest;
use Cortex\Pages\DataTables\Managerarea\PagesDataTable;
use Cortex\Foundation\DataTables\ImportRecordsDataTable;
use Cortex\Pages\Http\Requests\Managerarea\PageFormRequest;
use Cortex\Foundation\Http\Controllers\AuthorizedController;

class PagesController extends AuthorizedController
{
    /**
     * {@inheritdoc}
     */
    protected $resource = 'rinvex.pages.models.page';

    /**
     * List all pages.
     *
     * @param \Cortex\Pages\DataTables\Managerarea\PagesDataTable $pagesDataTable
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function index(PagesDataTable $pagesDataTable)
    {
        return $pagesDataTable->with([
            'id' => 'managerarea-cortex-pages-pages-index',
            'pusher' => ['entity' => 'page', 'channel' => 'cortex.pages.pages.index'],
        ])->render('cortex/foundation::managerarea.pages.datatable-index');
    }

    /**
     * List page logs.
     *
     * @param \Cortex\Pages\Models\Page                   $page
     * @param \Cortex\Foundation\DataTables\LogsDataTable $logsDataTable
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function logs(Page $page, LogsDataTable $logsDataTable)
    {
        return $logsDataTable->with([
            'resource' => $page,
            'tabs' => 'managerarea.cortex.pages.pages.tabs',
            'id' => "managerarea-cortex-pages-pages-{$page->getRouteKey()}-logs",
        ])->render('cortex/foundation::managerarea.pages.datatable-tab');
    }

    /**
     * Import pages.
     *
     * @param \Cortex\Pages\Models\Page                            $page
     * @param \Cortex\Foundation\DataTables\ImportRecordsDataTable $importRecordsDataTable
     *
     * @return \Illuminate\View\View
     */
    public function import(Page $page, ImportRecordsDataTable $importRecordsDataTable)
    {
        return $importRecordsDataTable->with([
            'resource' => $page,
            'tabs' => 'managerarea.cortex.pages.pages.tabs',
            'url' => route('managerarea.cortex.pages.pages.stash'),
            'id' => "managerarea-cortex-pages-pages-{$page->getRouteKey()}-import",
        ])->render('cortex/foundation::managerarea.pages.datatable-dropzone');
    }

    /**
     * Stash pages.
     *
     * @param \Cortex\Foundation\Http\Requests\ImportFormRequest $request
     * @param \Cortex\Foundation\Importers\DefaultImporter       $importer
     *
     * @return void
     */
    public function stash(ImportFormRequest $request, DefaultImporter $importer)
    {
        // Handle the import
        $importer->config['resource'] = $this->resource;
        $importer->handleImport();
    }

    /**
     * Hoard pages.
     *
     * @param \Cortex\Foundation\Http\Requests\ImportFormRequest $request
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function hoard(ImportFormRequest $request)
    {
        foreach ((array) $request->input('selected_ids') as $recordId) {
            $record = app('cortex.foundation.import_record')->find($recordId);

            try {
                $fillable = collect($record['data'])->intersectByKeys(array_flip(app('rinvex.pages.page')->getFillable()))->toArray();

                tap(app('rinvex.pages.page')->firstOrNew($fillable), function ($instance) use ($record) {
                    $instance->save() && $record->delete();
                });
            } catch (Exception $exception) {
                $record->notes = $exception->getMessage().(method_exists($exception, 'getMessageBag') ? "\n".json_encode($exception->getMessageBag())."\n\n" : '');
                $record->status = 'fail';
                $record->save();
            }
        }

        return intend([
            'back' => true,
            'with' => ['success' => trans('cortex/foundation::messages.import_complete')],
        ]);
    }

    /**
     * List page import logs.
     *
     * @param \Cortex\Foundation\DataTables\ImportLogsDataTable $importLogsDatatable
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function importLogs(ImportLogsDataTable $importLogsDatatable)
    {
        return $importLogsDatatable->with([
            'resource' => trans('cortex/pages::common.page'),
            'tabs' => 'managerarea.cortex.pages.pages.tabs',
            'id' => 'managerarea-cortex-pages-pages-import-logs',
        ])->render('cortex/foundation::managerarea.pages.datatable-tab');
    }

    /**
     * Create new page.
     *
     * @param \Illuminate\Http\Request  $request
     * @param \Cortex\Pages\Models\Page $page
     *
     * @return \Illuminate\View\View
     */
    public function create(Request $request, Page $page)
    {
        return $this->form($request, $page);
    }

    /**
     * Edit given page.
     *
     * @param \Illuminate\Http\Request  $request
     * @param \Cortex\Pages\Models\Page $page
     *
     * @return \Illuminate\View\View
     */
    public function edit(Request $request, Page $page)
    {
        return $this->form($request, $page);
    }

    /**
     * Show page create/edit form.
     *
     * @param \Illuminate\Http\Request  $request
     * @param \Cortex\Pages\Models\Page $page
     *
     * @return \Illuminate\View\View
     */
    protected function form(Request $request, Page $page)
    {
        if (! $page->exists && $request->has('replicate') && $replicated = $page->resolveRouteBinding($request->input('replicate'))) {
            $page = $replicated->replicate();
        }

        $tags = app('rinvex.tags.tag')->pluck('name', 'id');

        return view('cortex/pages::managerarea.pages.page', compact('page', 'tags'));
    }

    /**
     * Store new page.
     *
     * @param \Cortex\Pages\Http\Requests\Managerarea\PageFormRequest $request
     * @param \Cortex\Pages\Models\Page                               $page
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function store(PageFormRequest $request, Page $page)
    {
        return $this->process($request, $page);
    }

    /**
     * Update given page.
     *
     * @param \Cortex\Pages\Http\Requests\Managerarea\PageFormRequest $request
     * @param \Cortex\Pages\Models\Page                               $page
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function update(PageFormRequest $request, Page $page)
    {
        return $this->process($request, $page);
    }

    /**
     * Process stored/updated page.
     *
     * @param \Cortex\Foundation\Http\FormRequest $request
     * @param \Cortex\Pages\Models\Page               $page
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    protected function process(FormRequest $request, Page $page)
    {
        // Prepare required input fields
        $data = $request->validated();

        // Verify existing view
        if (! view()->exists($data['view'])) {
            return intend([
                'back' => true,
                'withInput' => $request->all(),
                'withErrors' => ['view' => trans('cortex/pages::messages.page.invalid_view')],
            ]);
        }

        // Save page
        $page->fill($data)->save();

        return intend([
            'url' => route('managerarea.cortex.pages.pages.index'),
            'with' => ['success' => trans('cortex/foundation::messages.resource_saved', ['resource' => trans('cortex/pages::common.page'), 'identifier' => $page->getRouteKey()])],
        ]);
    }

    /**
     * Destroy given page.
     *
     * @param \Cortex\Pages\Models\Page $page
     *
     * @throws \Exception
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function destroy(Page $page)
    {
        $page->delete();

        return intend([
            'url' => route('managerarea.cortex.pages.pages.index'),
            'with' => ['warning' => trans('cortex/foundation::messages.resource_deleted', ['resource' => trans('cortex/pages::common.page'), 'identifier' => $page->getRouteKey()])],
        ]);
    }
}
