<?php

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploader
{
    public function __construct(
        private readonly ImageProcessor $imageProcessor,
    ) {}

    public function store(UploadedFile $file, Model $mediable): Media
    {
        $extension = $file->extension() ?: 'bin';
        $filename = Str::uuid()->toString().'.'.$extension;
        $mediableType = Str::snake(class_basename($mediable));
        $directory = "media/{$mediableType}/{$mediable->id}";

        $path = $file->storeAs($directory, $filename, 'public');
        $absolutePath = Storage::disk('public')->path($path);

        [$width, $height] = $this->imageProcessor->optimize($absolutePath, $file->getMimeType())
            ?? $this->fallbackDimensions($absolutePath);

        return $mediable->media()->create([
            'disk' => 'public',
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => filesize($absolutePath),
            'width' => $width,
            'height' => $height,
            'is_primary' => ! $mediable->media()->exists(),
            'sort_order' => ($mediable->media()->max('sort_order') ?? -1) + 1,
        ]);
    }

    public function destroy(Media $media): void
    {
        DB::transaction(function () use ($media) {
            $wasPrimary = $media->is_primary;
            $mediable = $media->mediable;

            $media->delete();

            if ($wasPrimary) {
                $mediable->media()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
            }
        });
    }

    public function makePrimary(Media $media): void
    {
        DB::transaction(function () use ($media) {
            $media->mediable->media()->whereKeyNot($media->id)->update(['is_primary' => false]);
            $media->update(['is_primary' => true]);
        });
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function fallbackDimensions(string $absolutePath): array
    {
        $size = @getimagesize($absolutePath);

        return $size ? [$size[0], $size[1]] : [null, null];
    }
}
