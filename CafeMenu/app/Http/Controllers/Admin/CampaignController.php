<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCampaignRequest;
use App\Http\Requests\Admin\UpdateCampaignRequest;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Campaign::class);

        $campaigns = Campaign::query()->withCount('discounts')->orderBy('name')->get();

        return view('admin.campaigns.index', ['campaigns' => $campaigns]);
    }

    public function create(): View
    {
        $this->authorize('create', Campaign::class);

        return view('admin.campaigns.create');
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        Campaign::create($request->validated());

        return redirect()->route('admin.campaigns.index')->with('status', __('admin.flash.campaign_created'));
    }

    public function edit(Campaign $campaign): View
    {
        $this->authorize('update', $campaign);

        return view('admin.campaigns.edit', ['campaign' => $campaign]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->update($request->validated());

        return redirect()->route('admin.campaigns.index')->with('status', __('admin.flash.campaign_updated'));
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);

        if ($campaign->discounts()->exists()) {
            return redirect()->route('admin.campaigns.index')
                ->with('error', __('admin.flash.campaign_delete_blocked'));
        }

        $campaign->delete();

        return redirect()->route('admin.campaigns.index')->with('status', __('admin.flash.campaign_deleted'));
    }
}
