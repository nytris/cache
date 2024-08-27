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
use Exception;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * Class ReactCacheAdapter.
 *
 * A ReactPHP cache adapter backed by a PSR-6-cache.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ReactCacheAdapter implements ReactCacheAdapterInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $psrCachePool
    ) {
    }

    /**
     * @inheritDoc
     */
    public function clear(): PromiseInterface
    {
        $deferred = new Deferred();

        try {
            $deferred->resolve($this->psrCachePool->clear());
        } catch (Exception) {
            // When an error occurs, false should be returned.
            $deferred->resolve(false);
        }

        return $deferred->promise();
    }

    /**
     * @inheritDoc
     */
    public function delete($key): PromiseInterface
    {
        $deferred = new Deferred();

        try {
            $deferred->resolve($this->psrCachePool->deleteItem($this->sanitiseCacheKey($key)));
        } catch (Exception) {
            // When an error occurs, false should be returned.
            $deferred->resolve(false);
        }

        return $deferred->promise();
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(array $keys): PromiseInterface
    {
        $deferred = new Deferred();

        $sanitisedKeys = array_map($this->sanitiseCacheKey(...), $keys);

        try {
            $deferred->resolve($this->psrCachePool->deleteItems($sanitisedKeys));
        } catch (Exception) {
            // When an error occurs, false should be returned.
            $deferred->resolve(false);
        }

        return $deferred->promise();
    }

    /**
     * @inheritDoc
     */
    public function get($key, $default = null): PromiseInterface
    {
        $deferred = new Deferred();

        try {
            $item = $this->psrCachePool->getItem($this->sanitiseCacheKey($key));

            $deferred->resolve($item->isHit() ? $item->get() : $default);
        } catch (Exception) {
            // When an error occurs, the default should be returned.
            $deferred->resolve($default);
        }

        return $deferred->promise();
    }

    private function getEffectiveTtl(DateInterval|float|int|null $ttl): DateInterval|int|null
    {
        /**
         * For the ReactPHP interface, TTL may be a float, to specify sub-second precision.
         * However, the PSR spec only allows for integers, so we round floats to the nearest second.
         *
         * (Sub-second DateIntervals are possible, but not supported by Symfony Cache at the time of writing).
         */
        return is_float($ttl) ?
            (int) round($ttl) :
            $ttl;
    }

    /**
     * @inheritDoc
     *
     * @param string[] $keys
     * @param mixed $default
     * @return PromiseInterface<array<mixed>>
     */
    public function getMultiple(array $keys, $default = null): PromiseInterface
    {
        $deferred = new Deferred();

        $sanitisedKeys = [];
        $sanitisedKeyToOriginalMap = [];

        foreach ($keys as $key) {
            $sanitisedKey = $this->sanitiseCacheKey($key);
            $sanitisedKeys[] = $sanitisedKey;
            $sanitisedKeyToOriginalMap[$sanitisedKey] = $key;
        }

        try {
            $items = $this->psrCachePool->getItems($sanitisedKeys);
        } catch (Exception) {
            // On error, the exception should be swallowed and instead
            // all keys should be returned with the default as their value.
            $deferred->resolve(array_map(static fn () => $default, $keys));

            return $deferred->promise();
        }

        try {
            $values = [];

            foreach ($items as $sanitisedKey => $item) {
                try {
                    $value = $item->isHit() ? $item->get() : $default;
                } catch (Exception) {
                    // When an error occurs, the default should be used.
                    $value = $default;
                }

                $values[$sanitisedKeyToOriginalMap[$sanitisedKey]] = $value;
            }

            $deferred->resolve($values);
        } catch (Exception $exception) {
            $deferred->reject($exception);
        }

        return $deferred->promise();
    }

    /**
     * @inheritDoc
     */
    public function has($key): PromiseInterface
    {
        $deferred = new Deferred();

        try {
            $deferred->resolve($this->psrCachePool->hasItem($this->sanitiseCacheKey($key)));
        } catch (Exception) {
            // When an error occurs, false should be returned.
            $deferred->resolve(false);
        }

        return $deferred->promise();
    }

    /**
     * @inheritDoc
     */
    public function sanitiseCacheKey(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null): PromiseInterface
    {
        $deferred = new Deferred();

        try {
            $item = $this->psrCachePool->getItem($this->sanitiseCacheKey($key));

            $item->set($value);

            $item->expiresAfter($this->getEffectiveTtl($ttl));

            /*
             * Note that we cannot use `->saveDeferred(...)` as that gives no feedback
             * on when the save is actually performed, which we need to satisfy the interface.
             *
             * As documented in the README, it is up to the consuming application to ensure
             * that PSR adapters that block are not used.
             */
            $deferred->resolve($this->psrCachePool->save($item));
        } catch (Exception) {
            // When an error occurs, false should be returned.
            $deferred->resolve(false);
        }

        return $deferred->promise();
    }

    /**
     * @inheritDoc
     *
     * @param array<mixed> $values
     * @param float|null $ttl
     * @return PromiseInterface<bool>
     */
    public function setMultiple(array $values, $ttl = null): PromiseInterface
    {
        $deferred = new Deferred();

        $sanitisedKeys = [];
        $sanitisedKeyToOriginalMap = [];

        foreach (array_keys($values) as $key) {
            $sanitisedKey = $this->sanitiseCacheKey($key);
            $sanitisedKeys[] = $sanitisedKey;
            $sanitisedKeyToOriginalMap[$sanitisedKey] = $key;
        }

        try {
            /** @var CacheItemInterface[] $items */
            $items = $this->psrCachePool->getItems($sanitisedKeys);
        } catch (Exception) {
            // On error, the exception should be swallowed and false returned.
            $deferred->resolve(false);

            return $deferred->promise();
        }

        $success = true;

        foreach ($items as $sanitisedKey => $item) {
            try {
                $item->set($values[$sanitisedKeyToOriginalMap[$sanitisedKey]]);

                $item->expiresAfter($this->getEffectiveTtl($ttl));

                /*
                 * Note that we cannot use `->saveDeferred(...)` as that gives no feedback
                 * on when the save is actually performed, which we need to satisfy the interface.
                 *
                 * As documented in the README, it is up to the consuming application to ensure
                 * that PSR adapters that block are not used.
                 */
                if (!$this->psrCachePool->save($item)) {
                    $success = false;
                }
            } catch (Exception) {
                // When an error occurs, false should be returned.
                // Continue processing the remaining items.
                $success = false;
            }
        }

        $deferred->resolve($success);

        return $deferred->promise();
    }
}
