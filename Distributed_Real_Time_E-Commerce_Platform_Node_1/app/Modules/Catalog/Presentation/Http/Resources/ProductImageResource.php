<?php

namespace App\Modules\Catalog\Presentation\Http\Resources;

use App\Modules\Catalog\Application\Contracts\ProductImageStorageInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => app(ProductImageStorageInterface::class)->url($this->path),
            'sort_order' => $this->sort_order,
        ];
    }
}
