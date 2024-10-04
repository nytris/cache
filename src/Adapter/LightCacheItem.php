<?php

/*
 * Nytris Cache
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/nytris/cache/
 *
 * Released under the MIT license.
 * https://github.com/nytris/cache/raw/main/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Nytris\Cache\Adapter;

use DateInterval;
use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use Psr\Cache\CacheItemInterface;
use function is_int;

/**
 * Class LightCacheItem.
 *
 * Based on Symfony Cache's CacheItem class.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class LightCacheItem implements CacheItemInterface
{
    private ?float $expiry = null;

    public function __construct(
        private readonly bool $isHit,
        private readonly string $key,
        private mixed $value
    ) {
    }

    /**
     * @inheritDoc
     */
    public function expiresAfter($time): CacheItemInterface
    {
        if (null === $time) {
            $this->expiry = null;
        } elseif ($time instanceof DateInterval) {
            $this->expiry = microtime(true) +
                (float) (DateTime::createFromFormat('U', '0')->add($time)->format('U.u'));
        } elseif (is_int($time)) {
            $this->expiry = $time + microtime(as_float: true);
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'Expiration date must be an integer, a DateInterval or null, "%s" given.',
                    get_debug_type($time)
                )
            );
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function expiresAt($expiration): CacheItemInterface
    {
        if ($expiration === null) {
            $this->expiry = null;
        } elseif ($expiration instanceof DateTimeInterface) {
            $this->expiry = (float) $expiration->format('U.u');
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'Expiration date must implement DateTimeInterface or be null, "%s" given.',
                    get_debug_type($expiration)
                )
            );
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get(): mixed
    {
        return $this->value;
    }

    /**
     * Fetches the expiry time set for this cache item, or null if none.
     */
    public function getExpiry(): ?float
    {
        return $this->expiry;
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @inheritDoc
     */
    public function isHit(): bool
    {
        return $this->isHit;
    }

    /**
     * @inheritDoc
     */
    public function set(mixed $value): CacheItemInterface
    {
        $this->value = $value;

        return $this;
    }
}
