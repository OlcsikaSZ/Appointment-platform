<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ImageOptimizationService
{
    private const MAX_SOURCE_PIXELS = 40_000_000;

    /**
     * A feltöltött képből metaadat nélküli, méretezett WebP főképet és thumbnailt készít.
     *
     * @return array{url:string, thumbnail_url:string, width:int, height:int, bytes:int}
     */
    public function optimize(
        UploadedFile $file,
        string $collection,
        int $maxWidth,
        int $maxHeight,
        int $thumbnailWidth,
        int $thumbnailHeight,
    ): array {
        $this->assertCollection($collection);

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw new HttpException(500, 'A szerveren nincs engedélyezve a WebP-képfeldolgozás (PHP GD).');
        }

        $sourcePath = $file->getRealPath();
        $info = $sourcePath ? @getimagesize($sourcePath) : false;
        $sourceWidth = (int) ($info[0] ?? 0);
        $sourceHeight = (int) ($info[1] ?? 0);

        if (! $info || $sourceWidth < 1 || $sourceHeight < 1) {
            throw new HttpException(422, 'A feltöltött fájl nem feldolgozható kép.');
        }

        if ($sourceWidth * $sourceHeight > self::MAX_SOURCE_PIXELS) {
            throw new HttpException(422, 'A kép pixelszáma túl nagy. Legfeljebb 40 megapixeles kép tölthető fel.');
        }

        $contents = @file_get_contents($sourcePath);
        $source = $contents === false ? false : @imagecreatefromstring($contents);
        if ($source === false) {
            throw new HttpException(422, 'A kép sérült vagy a kódolása nem támogatott.');
        }

        $source = $this->applyExifOrientation($source, $sourcePath, (string) ($info['mime'] ?? ''));
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        [$targetWidth, $targetHeight] = $this->fitDimensions($sourceWidth, $sourceHeight, $maxWidth, $maxHeight);
        $mainImage = $this->resampleFit($source, $targetWidth, $targetHeight);
        $thumbnail = $this->resampleCover($source, $thumbnailWidth, $thumbnailHeight);

        $directory = storage_path('app/public/'.$collection);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            imagedestroy($source);
            imagedestroy($mainImage);
            imagedestroy($thumbnail);
            throw new HttpException(500, 'Nem sikerült létrehozni a képek mappáját.');
        }

        $baseName = Str::uuid()->toString();
        $mainName = $baseName.'.webp';
        $thumbnailName = $baseName.'-thumb.webp';
        $mainPath = $directory.DIRECTORY_SEPARATOR.$mainName;
        $thumbnailPath = $directory.DIRECTORY_SEPARATOR.$thumbnailName;

        $mainWritten = imagewebp($mainImage, $mainPath, 82);
        $thumbnailWritten = imagewebp($thumbnail, $thumbnailPath, 78);

        imagedestroy($source);
        imagedestroy($mainImage);
        imagedestroy($thumbnail);

        if (! $mainWritten || ! $thumbnailWritten) {
            @unlink($mainPath);
            @unlink($thumbnailPath);
            throw new HttpException(500, 'A WebP-képfájl létrehozása nem sikerült.');
        }

        return [
            'url' => './uploads/'.$collection.'/'.$mainName,
            'thumbnail_url' => './uploads/'.$collection.'/'.$thumbnailName,
            'width' => $targetWidth,
            'height' => $targetHeight,
            'bytes' => (int) (filesize($mainPath) ?: 0),
        ];
    }

    /** @param array<int, string|null> $urls */
    public function delete(array $urls, string $collection): void
    {
        $this->assertCollection($collection);

        foreach ($urls as $url) {
            $relative = ltrim((string) $url, './');
            if (! str_starts_with($relative, 'uploads/'.$collection.'/')) {
                continue;
            }

            $filename = basename($relative);
            $paths = [
                storage_path('app/public/'.$collection.'/'.$filename),
                dirname(base_path()).DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.$collection.DIRECTORY_SEPARATOR.$filename,
            ];

            foreach ($paths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /**
     * @param array<int, string|null> $referencedUrls
     * @return array{scanned:int, deleted:int, kept:int}
     */
    public function cleanupUnused(string $collection, array $referencedUrls, int $graceSeconds = 86400): array
    {
        $this->assertCollection($collection);
        $directories = array_unique([
            storage_path('app/public/'.$collection),
            dirname(base_path()).DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.$collection,
        ]);
        $referenced = array_fill_keys(array_filter(array_map(
            fn ($url) => basename(ltrim((string) $url, './')),
            $referencedUrls,
        )), true);
        $result = ['scanned' => 0, 'deleted' => 0, 'kept' => 0];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (glob($directory.DIRECTORY_SEPARATOR.'*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $path) {
                $result['scanned']++;
                $name = basename($path);
                $tooNew = (time() - (int) filemtime($path)) < max(0, $graceSeconds);
                if (isset($referenced[$name]) || $tooNew) {
                    $result['kept']++;
                    continue;
                }

                if (@unlink($path)) {
                    $result['deleted']++;
                } else {
                    $result['kept']++;
                }
            }
        }

        return $result;
    }

    private function assertCollection(string $collection): void
    {
        if (! in_array($collection, ['services', 'businesses'], true)) {
            throw new \InvalidArgumentException('Nem támogatott képgyűjtemény.');
        }
    }

    /** @return array{0:int,1:int} */
    private function fitDimensions(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $ratio = min(1, $maxWidth / $width, $maxHeight / $height);

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    private function resampleFit(\GdImage $source, int $width, int $height): \GdImage
    {
        $target = imagecreatetruecolor($width, $height);
        $this->enableAlpha($target);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        return $target;
    }

    private function resampleCover(\GdImage $source, int $width, int $height): \GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = max($width / $sourceWidth, $height / $sourceHeight);
        $cropWidth = max(1, (int) round($width / $scale));
        $cropHeight = max(1, (int) round($height / $scale));
        $sourceX = max(0, (int) floor(($sourceWidth - $cropWidth) / 2));
        $sourceY = max(0, (int) floor(($sourceHeight - $cropHeight) / 2));

        $target = imagecreatetruecolor($width, $height);
        $this->enableAlpha($target);
        imagecopyresampled($target, $source, 0, 0, $sourceX, $sourceY, $width, $height, $cropWidth, $cropHeight);

        return $target;
    }

    private function enableAlpha(\GdImage $image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
    }

    private function applyExifOrientation(\GdImage $image, string $path, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $transformed = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if ($transformed !== $image && $transformed instanceof \GdImage) {
            imagedestroy($image);

            return $transformed;
        }

        return $image;
    }
}
