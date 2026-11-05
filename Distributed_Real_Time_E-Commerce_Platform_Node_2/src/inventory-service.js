function createInventoryHandlers(repository) {
  return {
    getProductQuantity: asyncUnary(async (request) => {
      const productId = Number(request.product_id);
      if (!Number.isSafeInteger(productId) || productId <= 0) {
        return { found: false, available_quantity: 0 };
      }
      return repository.getProductQuantity(productId);
    }),

    initializeProductStock: asyncUnary(async (request) => {
      const productId = Number(request.product_id);
      const quantity = Number(request.quantity);
      if (!Number.isSafeInteger(productId) || productId <= 0 ||
          !Number.isInteger(quantity) || quantity < 0 || quantity > 2147483647) {
        return { success: false, message: 'A valid product ID and non-negative quantity are required.' };
      }
      return repository.initializeProductStock(productId, quantity);
    }),

    reserveStock: asyncUnary(async (request) => {
      return repository.reserveStock(request.reservation_reference, request.items || []);
    }),

    releaseStock: asyncUnary(async (request) => {
      return repository.releaseStock(request.reservation_reference);
    }),
  };
}

function asyncUnary(handler) {
  return (call, callback) => {
    Promise.resolve()
      .then(() => handler(call.request))
      .then((response) => callback(null, response))
      .catch((error) => {
        // Infrastructure errors are gRPC failures. Business rejections are normal
        // responses with success=false, so the Laravel client can return HTTP 409.
        callback({
          code: 13, // INTERNAL
          details: 'Inventory service failed while processing the request.',
        });
        process.stderr.write(`[inventory-grpc] ${error.stack || error}\n`);
      });
  };
}

module.exports = { createInventoryHandlers };
