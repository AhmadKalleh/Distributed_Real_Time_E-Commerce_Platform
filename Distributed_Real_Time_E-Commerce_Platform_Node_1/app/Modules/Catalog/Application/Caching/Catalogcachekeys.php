<?php

namespace App\Modules\Catalog\Application\Caching;

/**
 * Single place for cache keys and TTLs of the Catalog module.
 */
final class CatalogCacheKeys
{
    public const PRODUCTS_GROUP = 'catalog:products';

    public const PRODUCT_TTL = 600;     // product details: 10 min
    public const LIST_TTL = 60;         // product lists: 60 s
    public const CATEGORIES_TTL = 3600; // categories: 1 h

    public const CATEGORIES = 'catalog:categories';

    public static function product(int $id): string
    {
        return "catalog:product:{$id}";
    }

    /** @param array<string, mixed> $query the full query string of the request */
    public static function productList(int $version, array $query): string
    {
        ksort($query);

        return "catalog:products:list:v{$version}:" . md5(json_encode($query));
    }
}
