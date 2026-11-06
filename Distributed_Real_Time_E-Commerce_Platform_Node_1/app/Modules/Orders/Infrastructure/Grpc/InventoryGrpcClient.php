<?php

namespace App\Modules\Orders\Infrastructure\Grpc;

use App\Modules\Orders\Domain\Exceptions\InventoryServiceUnavailableException;
use App\Modules\Orders\Infrastructure\Grpc\Generated\InventoryServiceClient;
use App\Modules\Orders\Infrastructure\Grpc\Generated\ProductQuantityRequest;
use App\Modules\Orders\Infrastructure\Grpc\Generated\ReleaseStockRequest;
use App\Modules\Orders\Infrastructure\Grpc\Generated\ReserveStockRequest;
use App\Modules\Orders\Infrastructure\Grpc\Generated\StockItem;
use App\Modules\Orders\Domain\Exceptions\InsufficientStockException;
use App\Shared\Contracts\InventoryGatewayInterface;
use Throwable;

/** The only place in Orders that knows about gRPC. */
final class InventoryGrpcClient implements InventoryGatewayInterface
{
    private InventoryServiceClient $client;
    private int $timeoutMicroseconds;

    public function __construct()
    {
        if (! extension_loaded('grpc')) {
            throw new InventoryServiceUnavailableException(
                'The PHP gRPC extension is not installed or enabled.'
            );
        }

        $target = (string) config(
            'services.inventory.grpc_target',
            env('INVENTORY_GRPC_TARGET', '127.0.0.1:50051')
        );
        $timeoutMilliseconds = (int) config(
            'services.inventory.timeout_ms',
            env('INVENTORY_GRPC_TIMEOUT_MS', 3000)
        );

        $this->timeoutMicroseconds = max(100, $timeoutMilliseconds) * 1000;
        // 1) Use insecure credentials for local development and testing.
        // 2) Use secure credentials in production (TLS/SSL).
        $this->client = new InventoryServiceClient(
            $target,
            ['credentials' => \Grpc\ChannelCredentials::createInsecure()]
        );
    }

    /** Returns null when the product ID is not registered in Inventory. */
    public function getAvailableQuantity(int $productId): ?int
    {
        $request = new ProductQuantityRequest();
        $request->setProductId($productId);

        $response = $this->waitFor(fn () => $this->client->GetProductQuantity(
            $request,
            [],
            ['timeout' => $this->timeoutMicroseconds]
        ));

        return $response->getFound() ? (int) $response->getAvailableQuantity() : null;
    }

    public function initializeProductStock(int $productId, int $quantity): void
    {
        $request = new StockItem();
        $request->setProductId($productId);
        $request->setQuantity($quantity);

        $response = $this->waitFor(fn () => $this->client->InitializeProductStock(
            $request,
            [],
            ['timeout' => $this->timeoutMicroseconds]
        ));

        if (! $response->getSuccess()) {
            throw new InventoryServiceUnavailableException(
                'Product was created, but its initial inventory could not be initialized: '.$response->getMessage()
            );
        }
    }

    /** @param array<int|string, int> $quantities keyed by product ID */
    public function reserveStock(string $reservationReference, array $quantities): void
    {
        $items = [];
        foreach ($quantities as $productId => $quantity) {
            $item = new StockItem();
            $item->setProductId((int) $productId);
            $item->setQuantity((int) $quantity);
            $items[] = $item;
        }

        $request = new ReserveStockRequest();
        $request->setReservationReference($reservationReference);
        $request->setItems($items);

        $response = $this->waitFor(fn () => $this->client->ReserveStock(
            $request,
            [],
            ['timeout' => $this->timeoutMicroseconds]
        ));

        if (! $response->getSuccess()) {
            throw new InsufficientStockException(
                $response->getMessage() ?: 'The requested quantities are not available.'
            );
        }
    }

    public function releaseStock(string $reservationReference): void
    {
        $request = new ReleaseStockRequest();
        $request->setReservationReference($reservationReference);

        $response = $this->waitFor(fn () => $this->client->ReleaseStock(
            $request,
            [],
            ['timeout' => $this->timeoutMicroseconds]
        ));

        if (! $response->getSuccess()) {
            throw new InventoryServiceUnavailableException(
                'Inventory could not release reservation '.$reservationReference.': '.$response->getMessage()
            );
        }
    }

    private function waitFor(callable $startCall): object
    {
        try {
            [$response, $status] = $startCall()->wait();
        } catch (Throwable $exception) {
            report($exception);
            throw new InventoryServiceUnavailableException();
        }

        // gRPC status 0 is OK. DeadlineExceeded/Unavailable are returned as HTTP 503.
        if (! isset($status->code) || (int) $status->code !== 0) {
            throw new InventoryServiceUnavailableException(
                'The inventory service did not respond successfully before the configured timeout.'
            );
        }

        return $response;
    }
}
