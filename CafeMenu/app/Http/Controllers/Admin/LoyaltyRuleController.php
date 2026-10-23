<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLoyaltyRuleRequest;
use App\Http\Requests\Admin\UpdateLoyaltyRuleRequest;
use App\Models\LoyaltyRule;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoyaltyRuleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', LoyaltyRule::class);

        $loyaltyRules = LoyaltyRule::query()
            ->orderBy('name')
            ->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.loyalty-rules.index', ['loyaltyRules' => $loyaltyRules]);
    }

    public function create(): View
    {
        $this->authorize('create', LoyaltyRule::class);

        return view('admin.loyalty-rules.create');
    }

    public function store(StoreLoyaltyRuleRequest $request): RedirectResponse
    {
        LoyaltyRule::create($request->validated());

        return redirect()->route('admin.loyalty-rules.index')->with('status', __('admin.flash.loyalty_rule_created'));
    }

    public function edit(LoyaltyRule $loyaltyRule): View
    {
        $this->authorize('update', $loyaltyRule);

        return view('admin.loyalty-rules.edit', ['loyaltyRule' => $loyaltyRule]);
    }

    public function update(UpdateLoyaltyRuleRequest $request, LoyaltyRule $loyaltyRule): RedirectResponse
    {
        $loyaltyRule->update($request->validated());

        return redirect()->route('admin.loyalty-rules.index')->with('status', __('admin.flash.loyalty_rule_updated'));
    }

    public function destroy(LoyaltyRule $loyaltyRule): RedirectResponse
    {
        $this->authorize('delete', $loyaltyRule);

        if ($loyaltyRule->ledgerEntries()->exists()) {
            return redirect()->route('admin.loyalty-rules.index')
                ->with('error', __('admin.flash.loyalty_rule_delete_blocked'));
        }

        $loyaltyRule->delete();

        return redirect()->route('admin.loyalty-rules.index')->with('status', __('admin.flash.loyalty_rule_deleted'));
    }
}
