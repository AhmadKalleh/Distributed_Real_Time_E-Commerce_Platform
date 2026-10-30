<?php

namespace App\Modules\Orders\Enums;

enum OrderStatus: string
{
    case Pending = 'Pending';
    case Confirmed = 'Confirmed';
    case Shipped = 'Shipped';
    case Delivered = 'Delivered';
    case Cancelled = 'Cancelled';

    /**
     * The order lifecycle: Pending -> Confirmed -> Shipped -> Delivered,
     * with cancellation allowed until the order ships.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
