<?php

namespace App\Modules\Catalog\Application\Contracts;

use App\Modules\Catalog\Application\DTOs\Products\ProductSnapshot;
use Illuminate\Support\Collection;

/**
 * Public read contract that Catalog exposes to other modules.
 */
interface ProductCatalogInterface
{
    /**
     * Return the requested products only when every product exists and is active.
     * The returned collection is keyed by product ID.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, ProductSnapshot>
     *
     * @throws \App\Modules\Catalog\Domain\Exceptions\ProductUnavailableException
     */
    public function getActiveProductsByIds(array $productIds): Collection;
}
