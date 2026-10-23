<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductMediaRequest;
use App\Models\Media;
use App\Models\Product;
use App\Services\Media\MediaUploader;
use Illuminate\Http\RedirectResponse;

class ProductMediaController extends Controller
{
    public function __construct(private readonly MediaUploader $mediaUploader) {}

    public function store(StoreProductMediaRequest $request, Product $product): RedirectResponse
    {
        $this->mediaUploader->store($request->file('file'), $product);

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.flash.image_uploaded'));
    }

    public function destroy(Product $product, Media $media): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_unless($media->mediable_type === Product::class && $media->mediable_id === $product->id, 404);

        $this->mediaUploader->destroy($media);

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.flash.image_removed'));
    }

    public function primary(Product $product, Media $media): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_unless($media->mediable_type === Product::class && $media->mediable_id === $product->id, 404);

        $this->mediaUploader->makePrimary($media);

        return redirect()->route('admin.products.edit', $product)->with('status', __('admin.flash.primary_image_updated'));
    }
}
