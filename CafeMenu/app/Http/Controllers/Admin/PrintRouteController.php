<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePrintRouteRequest;
use App\Http\Requests\Admin\UpdatePrintRouteRequest;
use App\Models\Category;
use App\Models\Printer;
use App\Models\PrintRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PrintRouteController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PrintRoute::class);

        $printRoutes = PrintRoute::query()->with(['printer', 'category'])->orderBy('document_type')->get();

        return view('admin.print-routes.index', ['printRoutes' => $printRoutes]);
    }

    public function create(): View
    {
        $this->authorize('create', PrintRoute::class);

        return view('admin.print-routes.create', $this->formOptions());
    }

    public function store(StorePrintRouteRequest $request): RedirectResponse
    {
        PrintRoute::create($request->validated());

        return redirect()->route('admin.print-routes.index')->with('status', __('admin.flash.print_route_created'));
    }

    public function edit(PrintRoute $printRoute): View
    {
        $this->authorize('update', $printRoute);

        return view('admin.print-routes.edit', ['printRoute' => $printRoute, ...$this->formOptions()]);
    }

    public function update(UpdatePrintRouteRequest $request, PrintRoute $printRoute): RedirectResponse
    {
        $printRoute->update($request->validated());

        return redirect()->route('admin.print-routes.index')->with('status', __('admin.flash.print_route_updated'));
    }

    public function destroy(PrintRoute $printRoute): RedirectResponse
    {
        $this->authorize('delete', $printRoute);

        $printRoute->delete();

        return redirect()->route('admin.print-routes.index')->with('status', __('admin.flash.print_route_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'printers' => Printer::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ];
    }
}
