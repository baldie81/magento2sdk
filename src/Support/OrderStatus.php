<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Support;

use Baldie81\Magento2SDK\Enum\OrderState;

/**
 * An order status code.
 *
 * Unlike the system {@see OrderState} (a fixed, closed set), Magento order
 * *statuses* are admin-configurable — every store can define its own — so this
 * is a value object rather than an enum. The well-known defaults are exposed as
 * constants, and a process-wide registry (seeded with those defaults) lets
 * client code teach the SDK about custom statuses via {@see register()}:
 *
 *   OrderStatus::register('awaiting_pickup', 'Awaiting Pickup');
 *
 *   $status = $magento->orders()->getStatus(7);
 *   $status->code();     // 'processing'
 *   $status->label();    // 'Processing'
 *   $status->state();    // OrderState::Processing (null for custom statuses)
 *   $status->isKnown();  // true
 */
final class OrderStatus
{
    public const PENDING         = 'pending';
    public const PENDING_PAYMENT = 'pending_payment';
    public const PROCESSING      = 'processing';
    public const COMPLETE        = 'complete';
    public const CLOSED          = 'closed';
    public const CANCELED        = 'canceled';
    public const HOLDED          = 'holded';
    public const PAYMENT_REVIEW  = 'payment_review';
    public const FRAUD           = 'fraud';

    /**
     * Magento's out-of-the-box statuses (code => label). {@see reset()} restores
     * the registry to exactly this set.
     */
    private const DEFAULTS = [
        self::PENDING         => 'Pending',
        self::PENDING_PAYMENT => 'Pending Payment',
        self::PROCESSING      => 'Processing',
        self::COMPLETE        => 'Complete',
        self::CLOSED          => 'Closed',
        self::CANCELED        => 'Canceled',
        self::HOLDED          => 'On Hold',
        self::PAYMENT_REVIEW  => 'Payment Review',
        self::FRAUD           => 'Suspected Fraud',
    ];

    /** @var array<string,string> code => label */
    private static array $registry = self::DEFAULTS;

    private function __construct(
        public readonly string $code,
    ) {}

    public static function of(string $code): self
    {
        return new self($code);
    }

    /**
     * Register a custom status so the SDK recognises it. A null label defaults
     * to a humanised form of the code. Re-registering a code updates its label.
     */
    public static function register(string $code, ?string $label = null): void
    {
        self::$registry[$code] = $label ?? self::humanize($code);
    }

    /**
     * Register many at once: either a code => label map or a plain list of codes.
     *
     * @param array<string,?string>|list<string> $statuses
     */
    public static function registerMany(array $statuses): void
    {
        foreach ($statuses as $key => $value) {
            is_int($key) ? self::register((string) $value) : self::register($key, $value);
        }
    }

    /**
     * Forget a status (no-op if it isn't registered). Useful for removing a
     * default you don't use.
     */
    public static function unregister(string $code): void
    {
        unset(self::$registry[$code]);
    }

    /**
     * Every known status, code => label (defaults plus anything registered).
     *
     * @return array<string,string>
     */
    public static function known(): array
    {
        return self::$registry;
    }

    /**
     * Restore the registry to Magento's defaults, dropping any registrations.
     * Mainly for test isolation, since the registry is process-wide state.
     */
    public static function reset(): void
    {
        self::$registry = self::DEFAULTS;
    }

    public function code(): string
    {
        return $this->code;
    }

    /**
     * Whether this code is in the registry (a default or a registered custom).
     */
    public function isKnown(): bool
    {
        return isset(self::$registry[$this->code]);
    }

    /**
     * The registered label, or a humanised fallback for an unknown code.
     */
    public function label(): string
    {
        return self::$registry[$this->code] ?? self::humanize($this->code);
    }

    /**
     * The matching system {@see OrderState} when the status code coincides with
     * one (the case for the default statuses), otherwise null.
     */
    public function state(): ?OrderState
    {
        return OrderState::tryFrom($this->code);
    }

    public function equals(self|string $other): bool
    {
        return $this->code === ($other instanceof self ? $other->code : $other);
    }

    public function __toString(): string
    {
        return $this->code;
    }

    private static function humanize(string $code): string
    {
        return ucwords(str_replace('_', ' ', $code));
    }
}
