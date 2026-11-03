<?php

namespace App\Modules\Catalog\Application\DTOs\Products;

use Illuminate\Http\UploadedFile;

final readonly class CreateProductDTO
{
    /** @param list<UploadedFile> $images */
    public function __construct(
        public int $categoryId,
        public string $name,
        public ?string $description,
        public string $price,
        public bool $isActive,
        public array $images = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            categoryId: (int) $data['category_id'],
            name: trim((string) $data['name']),
            description: isset($data['description']) ? (string) $data['description'] : null,
            price: (string) $data['price'],
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            images: $data['images'] ?? [],
        );
    }

    /** @return array<string, mixed> */
    public function toProductAttributes(string $slug): array
    {
        return [
            'category_id' => $this->categoryId,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'is_active' => $this->isActive,
            'slug' => $slug,
        ];
    }
}
