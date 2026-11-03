<?php

namespace App\Modules\Catalog\Domain\Services;

use Illuminate\Support\Str;

final class ProductSlugGenerator
{
    public function fromName(string $name): string
    {
        $slug = Str::slug($name);

        return $slug !== '' ? $slug : 'product-' . Str::uuid()->toString();
    }
}
