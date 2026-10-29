<?php

namespace App\Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Http\Requests\ListProductsRequest;
use App\Modules\Products\Http\Requests\StoreProductRequest;
use App\Modules\Products\Http\Resources\ProductResource;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductController extends Controller
{
    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $products = Product::query()
            ->active()
            ->with(['category', 'images'])
            ->when($filters['category'] ?? null, function ($query, $category) {
                $query->whereHas('category', fn ($q) => ctype_digit($category)
                    ? $q->whereKey((int) $category)
                    : $q->where('slug', $category));
            })
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when(isset($filters['min_price']), fn ($q) => $q->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($q) => $q->where('price', '<=', $filters['max_price']))
            ->tap(fn ($q) => match ($filters['sort'] ?? 'newest') {
                'price_asc' => $q->orderBy('price')->orderBy('id'),
                'price_desc' => $q->orderByDesc('price')->orderBy('id'),
                'name' => $q->orderBy('name')->orderBy('id'),
                default => $q->orderByDesc('id'),
            })
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        abort_unless($product->is_active, 404);

        return new ProductResource($product->load(['category', 'images']));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->safe()->except('images');
        $storedPaths = [];

        try {
            $product = DB::transaction(function () use ($data, $request, &$storedPaths) {
                $product = Product::create($data + [
                    'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
                ]);

                foreach ($request->file('images') as $index => $file) {
                    // Never trust the client file name: store under a random name.
                    $fileName = Str::uuid().'.'.$file->extension();
                    $path = $file->storeAs("products/{$product->id}", $fileName, ProductImage::DISK);
                    $storedPaths[] = $path;

                    $product->images()->create([
                        'file_name' => $fileName,
                        'path' => $path,
                        'sort_order' => $index,
                    ]);
                }

                return $product;
            });
        } catch (Throwable $e) {
            Storage::disk(ProductImage::DISK)->delete($storedPaths);

            throw $e;
        }

        return (new ProductResource($product->load(['category', 'images'])))
            ->response()
            ->setStatusCode(201)
            ->header('Location', url("/api/v1/products/{$product->id}"));
    }
}
