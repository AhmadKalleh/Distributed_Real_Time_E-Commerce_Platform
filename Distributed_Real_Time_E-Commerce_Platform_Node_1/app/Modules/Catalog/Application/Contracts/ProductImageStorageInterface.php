<?php

namespace App\Modules\Catalog\Application\Contracts;

use Closure;
use Illuminate\Http\UploadedFile;

interface ProductImageStorageInterface
{
    public function url(string $path): string;

    public function storeAndPersist(array $files, Closure $persist): mixed;
}
