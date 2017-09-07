<?php

declare(strict_types=1);

namespace Cortex\Pages\Http\Controllers\Adminarea;

use Illuminate\Http\Request;
use Rinvex\Pages\Contracts\PageContract;
use Cortex\Foundation\DataTables\LogsDataTable;
use Cortex\Pages\DataTables\Adminarea\PagesDataTable;
use Cortex\Pages\Http\Requests\Adminarea\PageFormRequest;
use Cortex\Foundation\Http\Controllers\AuthorizedController;

class PagesController extends AuthorizedController
{
    /**
     * {@inheritdoc}
     */
    protected $resource = 'pages';

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return app(PagesDataTable::class)->with([
            'id' => 'cortex-pages',
            'phrase' => trans('cortex/pages::common.pages'),
        ])->render('cortex/foundation::adminarea.pages.datatable');
    }

    /**
     * Display a listing of the resource logs.
     *
     * @return \Illuminate\Http\Response
     */
    public function logs(PageContract $page)
    {
        return app(LogsDataTable::class)->with([
            'type' => 'pages',
            'resource' => $page,
            'id' => 'cortex-pages-logs',
            'phrase' => trans('cortex/pages::common.pages'),
        ])->render('cortex/foundation::adminarea.pages.datatable-logs');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Cortex\Pages\Http\Requests\Adminarea\PageFormRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(PageFormRequest $request)
    {
        return $this->process($request, app('rinvex.pages.page'));
    }

    /**
     * Update the given resource in storage.
     *
     * @param \Cortex\Pages\Http\Requests\Adminarea\PageFormRequest $request
     * @param \Rinvex\Pages\Contracts\PageContract                  $page
     *
     * @return \Illuminate\Http\Response
     */
    public function update(PageFormRequest $request, PageContract $page)
    {
        return $this->process($request, $page);
    }

    /**
     * Delete the given resource from storage.
     *
     * @param \Rinvex\Pages\Contracts\PageContract $page
     *
     * @return \Illuminate\Http\Response
     */
    public function delete(PageContract $page)
    {
        $page->delete();

        return intend([
            'url' => route('adminarea.pages.index'),
            'with' => ['warning' => trans('cortex/pages::messages.page.deleted', ['slug' => $page->slug])],
        ]);
    }

    /**
     * Show the form for create/update of the given resource.
     *
     * @param \Rinvex\Pages\Contracts\PageContract $page
     *
     * @return \Illuminate\Http\Response
     */
    public function form(PageContract $page)
    {
        return view('cortex/pages::adminarea.forms.page', compact('page'));
    }

    /**
     * Process the form for store/update of the given resource.
     *
     * @param \Illuminate\Http\Request             $request
     * @param \Rinvex\Pages\Contracts\PageContract $page
     *
     * @return \Illuminate\Http\Response
     */
    protected function process(Request $request, PageContract $page)
    {
        // Prepare required input fields
        $data = $request->all();

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
            'url' => route('adminarea.pages.index'),
            'with' => ['success' => trans('cortex/pages::messages.page.saved', ['slug' => $page->slug])],
        ]);
    }
}
