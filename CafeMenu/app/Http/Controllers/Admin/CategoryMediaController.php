<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryMediaRequest;
use App\Models\Category;
use App\Models\Media;
use App\Services\Media\MediaUploader;
use Illuminate\Http\RedirectResponse;

class CategoryMediaController extends Controller
{
    public function __construct(private readonly MediaUploader $mediaUploader) {}

    public function store(StoreCategoryMediaRequest $request, Category $category): RedirectResponse
    {
        $this->mediaUploader->store($request->file('file'), $category);

        return redirect()->route('admin.categories.edit', $category)->with('status', __('admin.flash.image_uploaded'));
    }

    public function destroy(Category $category, Media $media): RedirectResponse
    {
        $this->authorize('update', $category);

        abort_unless($media->mediable_type === Category::class && $media->mediable_id === $category->id, 404);

        $this->mediaUploader->destroy($media);

        return redirect()->route('admin.categories.edit', $category)->with('status', __('admin.flash.image_removed'));
    }

    public function primary(Category $category, Media $media): RedirectResponse
    {
        $this->authorize('update', $category);

        abort_unless($media->mediable_type === Category::class && $media->mediable_id === $category->id, 404);

        $this->mediaUploader->makePrimary($media);

        return redirect()->route('admin.categories.edit', $category)->with('status', __('admin.flash.primary_image_updated'));
    }
}
