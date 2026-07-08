<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

use App\Models\Document;

class GenerateThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Document $document
    ) {}

    /**
     * Generate a thumbnail for the image document.
     *
     * Generate a thumbnail for the document if it is an image.
     * - read the original image file,
     * - resize it to a 200x200 pixel square with center cropping,
     * - and save the thumbnail as a JPEG file with 80% quality.
     *
     * @return void
     * @throws \Exception If an error occurs during thumbnail generation.
     */
    public function handle(): void
    {
        if (!$this->document->isImage()) return;

        $file_path = storage_path('/app/public/' . $this->document->file_path . '/' . $this->document->file_name);
        if (!file_exists($file_path)) return;

        try {
            $driver = extension_loaded('gd') ? new Driver() : null;
            if (!$driver) {
                Log::warning('GD driver not available for thumbnail generation.');
                return;
            }

            $img_manager = new ImageManager($driver);

            $image = $img_manager->read($file_path);
            $image->cover(200, 200, 'center'); // reading the image, resize (covering 200 x 200 px), and center

            $thumbnail_path ='documents/thumbnails/' . $this->document->uuid . '.jpg';
            $thumbnail_full_path = storage_path('/app/public/' . $thumbnail_path);

            $thumbnail_dir = dirname($thumbnail_full_path);
            if (!is_dir($thumbnail_dir)) { // ensuring the thumbnail directory exists
                mkdir($thumbnail_dir, 0755, true);
            }

            $image->save($thumbnail_full_path, 80, 'jpg'); // saving thumbnail as JPG with 80% quality: not terrible not great :)

            $this->document->update([
                'thumbnail_path' => $thumbnail_path
            ]);

            Log::info('Thumbnail generated for document ID: ' . $this->document->id);

        } catch (\Exception $e) {
            Log::error('Thumbnail generation error for document ID: ' . $this->document->id . ': ' . $e->getMessage());
        }
    }
}
