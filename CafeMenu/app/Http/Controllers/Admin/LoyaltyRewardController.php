<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLoyaltyRewardRequest;
use App\Http\Requests\Admin\UpdateLoyaltyRewardRequest;
use App\Models\LoyaltyReward;
use App\Models\Product;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoyaltyRewardController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', LoyaltyReward::class);

        $loyaltyRewards = LoyaltyReward::query()
            ->with('freeProduct')
            ->orderBy('name')
            ->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.loyalty-rewards.index', ['loyaltyRewards' => $loyaltyRewards]);
    }

    public function create(): View
    {
        $this->authorize('create', LoyaltyReward::class);

        return view('admin.loyalty-rewards.create', $this->formOptions());
    }

    public function store(StoreLoyaltyRewardRequest $request): RedirectResponse
    {
        LoyaltyReward::create($request->validated());

        return redirect()->route('admin.loyalty-rewards.index')->with('status', __('admin.flash.loyalty_reward_created'));
    }

    public function edit(LoyaltyReward $loyaltyReward): View
    {
        $this->authorize('update', $loyaltyReward);

        return view('admin.loyalty-rewards.edit', ['loyaltyReward' => $loyaltyReward, ...$this->formOptions()]);
    }

    public function update(UpdateLoyaltyRewardRequest $request, LoyaltyReward $loyaltyReward): RedirectResponse
    {
        $loyaltyReward->update($request->validated());

        return redirect()->route('admin.loyalty-rewards.index')->with('status', __('admin.flash.loyalty_reward_updated'));
    }

    public function destroy(LoyaltyReward $loyaltyReward): RedirectResponse
    {
        $this->authorize('delete', $loyaltyReward);

        if ($loyaltyReward->redemptions()->exists()) {
            return redirect()->route('admin.loyalty-rewards.index')
                ->with('error', __('admin.flash.loyalty_reward_delete_blocked'));
        }

        $loyaltyReward->delete();

        return redirect()->route('admin.loyalty-rewards.index')->with('status', __('admin.flash.loyalty_reward_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'products' => Product::orderBy('name')->get(),
        ];
    }
}
