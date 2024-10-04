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

use APCUIterator;
use BadMethodCallException;
use InvalidArgumentException;
use LogicException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use const APC_ITER_KEY;

/**
 * Class LightApcuAdapter.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class LightApcuAdapter implements CacheItemPoolInterface
{
    public function __construct(
        private readonly string $namespace = '',
        private readonly int $defaultLifetime = 0
    ) {
        if (!self::isSupported()) {
            throw new LogicException('APCu is not enabled');
        }

        class_exists(LightCacheItem::class);
    }

    private function buildKey(string $key): string
    {
        return $this->namespace . '/' . $key;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        return apcu_delete(
            new APCUIterator(
                sprintf('/^%s/', preg_quote($this->namespace, '/')),
                APC_ITER_KEY
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function commit(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteItem($key): bool
    {
        return apcu_delete($this->buildKey($key));
    }

    /**
     * @inheritDoc
     */
    public function deleteItems(array $keys): bool
    {
        throw new BadMethodCallException(__METHOD__ . '() :: Not implemented');
    }

    /**
     * @inheritDoc
     */
    public function getItem($key): CacheItemInterface
    {
        $value = apcu_fetch($this->buildKey($key), success: $isHit);

        return new LightCacheItem(
            isHit: $isHit,
            key: $key,
            value: $isHit ? $value : null
        );
    }

    /**
     * @inheritDoc
     *
     * @return array<string, CacheItemInterface>
     */
    public function getItems(array $keys = array()): iterable
    {
        throw new BadMethodCallException(__METHOD__ . '() :: Not implemented');
    }

    /**
     * @inheritDoc
     */
    public function hasItem($key): bool
    {
        return apcu_exists($this->buildKey($key));
    }

    /**
     * Determines whether APCu is available so that this adapter can be used.
     */
    public static function isSupported(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }

    /**
     * @inheritDoc
     */
    public function save(CacheItemInterface $item): bool
    {
        if (!($item instanceof LightCacheItem)) {
            throw new InvalidArgumentException('$item must be a LightCacheItem');
        }

        return apcu_store(
            $this->buildKey($item->getKey()),
            $item->get(),
            $item->getExpiry() !== null ?
                (int) ($item->getExpiry() - microtime(as_float: true)) :
                $this->defaultLifetime
        );
    }

    /**
     * @inheritDoc
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }
}
