<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTableSectionRequest;
use App\Http\Requests\Admin\UpdateTableSectionRequest;
use App\Models\TableSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TableSectionController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', TableSection::class);

        $tableSections = TableSection::query()->withCount('tables')->orderBy('sort_order')->get();

        return view('admin.table-sections.index', ['tableSections' => $tableSections]);
    }

    public function create(): View
    {
        $this->authorize('create', TableSection::class);

        return view('admin.table-sections.create');
    }

    public function store(StoreTableSectionRequest $request): RedirectResponse
    {
        TableSection::create($request->validated());

        return redirect()->route('admin.table-sections.index')->with('status', __('admin.flash.section_created'));
    }

    public function edit(TableSection $tableSection): View
    {
        $this->authorize('update', $tableSection);

        return view('admin.table-sections.edit', ['tableSection' => $tableSection]);
    }

    public function update(UpdateTableSectionRequest $request, TableSection $tableSection): RedirectResponse
    {
        $tableSection->update($request->validated());

        return redirect()->route('admin.table-sections.index')->with('status', __('admin.flash.section_updated'));
    }

    public function destroy(TableSection $tableSection): RedirectResponse
    {
        $this->authorize('delete', $tableSection);

        if ($tableSection->tables()->exists()) {
            return redirect()->route('admin.table-sections.index')
                ->with('error', __('admin.flash.section_delete_blocked'));
        }

        $tableSection->delete();

        return redirect()->route('admin.table-sections.index')->with('status', __('admin.flash.section_deleted'));
    }
}
