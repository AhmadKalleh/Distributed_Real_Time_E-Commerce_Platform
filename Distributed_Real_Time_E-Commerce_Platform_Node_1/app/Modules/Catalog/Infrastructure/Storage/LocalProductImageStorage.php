<?php

namespace App\Modules\Catalog\Infrastructure\Storage;

use App\Modules\Catalog\Application\Contracts\ProductImageStorageInterface;
use App\Modules\Catalog\Application\DTOs\Products\StoredImageDTO;
use App\Modules\Catalog\Domain\Exceptions\ProductImageStorageException;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class LocalProductImageStorage implements ProductImageStorageInterface
{
    private const DISK = 'public';
    private const DIRECTORY = 'products';

    private function store(UploadedFile $file): string
    {
        try {
            $extension = strtolower($file->extension() ?: 'bin');
            $fileName = Str::uuid()->toString() . '.' . $extension;
            $path = $file->storeAs(self::DIRECTORY, $fileName, self::DISK);

            if (!is_string($path) || $path === '') {
                throw new ProductImageStorageException();
            }

            return $path;
        } catch (ProductImageStorageException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw new ProductImageStorageException();
        }
    }

    private function delete(string $path): void
    {
        try {
            if ($path !== '' && str_starts_with($path, self::DIRECTORY . '/')) {
                Storage::disk(self::DISK)->delete($path);
            }
        } catch (Throwable $exception) {
            report($exception);
            // Deletion failure is logged; it must not hide the original business result.
        }
    }

    public function storeAndPersist(array $files, Closure $persist): mixed
    {
        $stored = [];

        try {
            foreach ($files as $file) {
                $path = $this->store($file);
                $stored[] = new StoredImageDTO($path, basename($path));
            }

            return $persist($stored);
        } catch (Throwable $exception) {
            foreach ($stored as $image) {
                $this->delete($image->path);
            }
            throw $exception;
        }
    }

    public function url(string $path): string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(self::DISK);
        return $disk->url($path);
    }
}
