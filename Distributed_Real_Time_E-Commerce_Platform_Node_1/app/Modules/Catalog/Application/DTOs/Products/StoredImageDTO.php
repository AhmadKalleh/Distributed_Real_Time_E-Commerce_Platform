<?php

namespace App\Modules\Catalog\Application\DTOs\Products;

final readonly class StoredImageDTO
{
    public function __construct(
        public string $path,
        public string $fileName,
    ) {}
}
