<?php

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Application\Contracts\ProductRepositoryInterface;
use App\Modules\Catalog\Application\DTOs\Products\StoredImageDTO;
use App\Modules\Catalog\Domain\Entities\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EloquentProductRepository implements ProductRepositoryInterface
{
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Product::query()
            ->select(['id', 'category_id', 'name', 'slug', 'description', 'price', 'is_active', 'created_at', 'updated_at'])
            ->with([
                'category:id,name,slug',
                'images:id,product_id,file_name,path,sort_order',
            ]);

        if (!($filters['include_inactive'] ?? false)) {
            $query->where('is_active', true);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (array_key_exists('min_price', $filters) && $filters['min_price'] !== null) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (array_key_exists('max_price', $filters) && $filters['max_price'] !== null) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (isset($filters['sort']) && in_array($filters['sort'], ['name', 'price', 'created_at'], true)) {
            $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
            $query->orderBy($filters['sort'], $direction)->orderBy('id', $direction);
        } else {
            $query->orderByDesc('id');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function findById(int $id): ?Product
    {
        return Product::query()
            ->with(['category:id,name,slug', 'images:id,product_id,file_name,path,sort_order'])
            ->find($id);
    }

    public function slugExists(string $slug): bool
    {
        return Product::query()->where('slug', $slug)->exists();
    }

    public function create(array $attributes, array $images = []): Product
    {
        return DB::transaction(function () use ($attributes, $images) {
            $product = Product::query()->create($attributes);


            $product->setRelation('images', $product->images()->createMany(
                array_map(
                    fn (StoredImageDTO $image, int $index) => [
                        'file_name'  => $image->fileName,
                        'path'       => $image->path,
                        'sort_order' => $index,
                    ],
                    $images,
                    array_keys($images),
                )
            ));

            return $product->load('category:id,name,slug'); 
        });
    }

}
