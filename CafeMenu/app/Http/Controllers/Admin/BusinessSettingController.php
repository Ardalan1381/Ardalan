<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBusinessLogoRequest;
use App\Http\Requests\Admin\UpdateBusinessSettingRequest;
use App\Models\BusinessSetting;
use App\Services\AuditLogger;
use App\Services\Media\MediaUploader;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BusinessSettingController extends Controller
{
    public function __construct(
        private readonly MediaUploader $mediaUploader,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function edit(PaymentGatewayManager $gatewayManager): View
    {
        $this->authorize('settings.manage');

        $settings = BusinessSetting::current();
        $settings->load('media');

        return view('admin.settings.business', [
            'settings' => $settings,
            'configuredGateways' => $gatewayManager->configured(),
        ]);
    }

    public function update(UpdateBusinessSettingRequest $request): RedirectResponse
    {
        $settings = BusinessSetting::current();

        $data = $request->validated();
        $gatewayCredentials = $data['gateway_credentials'] ?? [];
        unset($data['gateway_credentials']);

        $settings->update($data);
        $changes = $settings->getChanges();

        // Credential values themselves are never logged — only that a
        // settings update happened — since audit log entries are visible
        // to anyone with audit.view, which is a broader permission than
        // settings.manage. mergeGatewayCredentials() does its own save()
        // after this, so $changes is captured first rather than re-read
        // afterward (which would only reflect that second save).
        $settings->mergeGatewayCredentials($gatewayCredentials);

        $this->auditLogger->log('settings.business_updated', $settings, new: $changes);

        return redirect()->route('admin.settings.business.edit')->with('status', __('admin.flash.business_settings_updated'));
    }

    public function storeLogo(StoreBusinessLogoRequest $request): RedirectResponse
    {
        $settings = BusinessSetting::current();

        // Logo is a single image, not a gallery: replace whatever is there.
        $settings->media->each(fn ($media) => $this->mediaUploader->destroy($media));

        $this->mediaUploader->store($request->file('file'), $settings);

        return redirect()->route('admin.settings.business.edit')->with('status', __('admin.flash.logo_updated'));
    }

    public function destroyLogo(): RedirectResponse
    {
        $this->authorize('settings.manage');

        BusinessSetting::current()->media->each(fn ($media) => $this->mediaUploader->destroy($media));

        return redirect()->route('admin.settings.business.edit')->with('status', __('admin.flash.logo_removed'));
    }
}
