<?php
// Minimal generated-style gRPC client stub for inventory.proto.
// Keep this file in sync with the RPC names in the shared proto contract.

namespace App\Modules\Orders\Grpc\Generated;

final class InventoryServiceClient extends \Grpc\BaseStub
{
    public function GetProductQuantity($argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(
            '/inventory.v1.InventoryService/GetProductQuantity',
            $argument,
            [ProductQuantityResponse::class, 'decode'],
            $metadata,
            $options
        );
    }

    public function ReserveStock($argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(
            '/inventory.v1.InventoryService/ReserveStock',
            $argument,
            [StockOperationResponse::class, 'decode'],
            $metadata,
            $options
        );
    }

    public function ReleaseStock($argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(
            '/inventory.v1.InventoryService/ReleaseStock',
            $argument,
            [StockOperationResponse::class, 'decode'],
            $metadata,
            $options
        );
    }
}
