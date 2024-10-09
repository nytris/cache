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

namespace Nytris\Cache\Tests\Functional\Adapter;

use BadMethodCallException;
use DateTimeImmutable;
use Nytris\Cache\Adapter\LightApcuAdapter;
use Nytris\Cache\Adapter\LightCacheItem;
use Nytris\Cache\Tests\Functional\AbstractFunctionalTestCase;

/**
 * Class LightApcuAdapterTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class LightApcuAdapterTest extends AbstractFunctionalTestCase
{
    private LightApcuAdapter $adapter;

    protected function setUp(): void
    {
        if (!LightApcuAdapter::isSupported()) {
            $this->markTestSkipped('APCu is not enabled.');
        }

        apcu_clear_cache();

        $this->adapter = new LightApcuAdapter(namespace: 'test_namespace', defaultLifetime: 3600);
    }

    public function testClearRemovesAllItemsInNamespace(): void
    {
        apcu_store('test_namespace/item1', 'value1');
        apcu_store('test_namespace/item2', 'value2');
        apcu_store('other_namespace/item3', 'value3');

        // Clear the namespace "test_namespace".
        static::assertTrue($this->adapter->clear());

        static::assertFalse(apcu_exists('test_namespace/item1'));
        static::assertFalse(apcu_exists('test_namespace/item2'));
        static::assertTrue(
            apcu_exists('other_namespace/item3'),
            'Item from another namespace should remain'
        );
    }

    public function testDeleteItemRemovesSpecificItem(): void
    {
        apcu_store('test_namespace/item1', 'value1');

        static::assertTrue(apcu_exists('test_namespace/item1'));
        static::assertTrue($this->adapter->deleteItem('item1'));
        static::assertFalse(apcu_exists('test_namespace/item1'));
    }

    public function testDeleteItemsThrowsBadMethodCallException(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('::deleteItems() :: Not implemented');

        $this->adapter->deleteItems(['item1', 'item2']);
    }

    public function testGetItemRetrievesStoredItem(): void
    {
        apcu_store('test_namespace/item1', 'value1');

        $item = $this->adapter->getItem('item1');

        static::assertInstanceOf(LightCacheItem::class, $item);
        static::assertTrue($item->isHit());
        static::assertSame('value1', $item->get());
    }

    public function testGetItemReturnsMissWhenItemNotInCache(): void
    {
        $item = $this->adapter->getItem('nonexistent_item');

        static::assertInstanceOf(LightCacheItem::class, $item);
        static::assertFalse($item->isHit());
        static::assertNull($item->get());
    }

    public function testGetItemsThrowsBadMethodCallException(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('::getItems() :: Not implemented');

        $this->adapter->getItems(['item1', 'item2']);
    }

    public function testHasItemReturnsTrueWhenItemExists(): void
    {
        apcu_store('test_namespace/item1', 'value1');

        static::assertTrue($this->adapter->hasItem('item1'));
    }

    public function testHasItemReturnsFalseWhenItemDoesNotExist(): void
    {
        static::assertFalse($this->adapter->hasItem('nonexistent_item'));
    }

    public function testIsSupportedWhenApcuIsAvailable(): void
    {
        static::assertTrue(LightApcuAdapter::isSupported());
    }

    public function testSaveStoresCacheItem(): void
    {
        $cacheItem = new LightCacheItem(isHit: true, key: 'item1', value: 'value1');

        static::assertTrue($this->adapter->save($cacheItem));
        static::assertSame('value1', apcu_fetch('test_namespace/item1'));
    }

    public function testSaveStoresItemWithExpiresAt(): void
    {
        $expiryTime = new DateTimeImmutable('+2 seconds');
        $cacheItem = new LightCacheItem(isHit: true, key: 'item1', value: 'value1');
        $cacheItem->expiresAt($expiryTime);

        static::assertTrue($this->adapter->save($cacheItem));
        static::assertTrue($this->adapter->hasItem('item1'), 'Item should exist before expiring');
        sleep(3); // Allow item to expire.
        static::assertFalse($this->adapter->hasItem('item1'), 'Item should no longer exist');
    }

    public function testSaveStoresItemWithExpiresAfter(): void
    {
        $cacheItem = new LightCacheItem(isHit: true, key: 'item1', value: 'value1');
        $cacheItem->expiresAfter(2); // 2 seconds

        static::assertTrue($this->adapter->save($cacheItem));
        static::assertTrue($this->adapter->hasItem('item1'), 'Item should exist before expiring');
        sleep(3); // Allow item to expire.
        static::assertFalse($this->adapter->hasItem('item1'), 'Item should no longer exist');
    }

    public function testSaveStoresItemWithDefaultLifetime(): void
    {
        // Default lifetime for this adapter is 3600 seconds (1 hour).
        $cacheItem = new LightCacheItem(isHit: true, key: 'item1', value: 'value1');

        static::assertTrue($this->adapter->save($cacheItem)); // Save item without specifying TTL.
        static::assertTrue($this->adapter->hasItem('item1'), 'Item should exist before expiring');
        // Sleep for a brief period (should still be present, since 3600 seconds has not passed).
        sleep(2);
        static::assertTrue($this->adapter->hasItem('item1'), 'Item should still be present');
    }

    public function testSaveStoresItemWithCustomDefaultLifetime(): void
    {
        // Create a new adapter with a short default lifetime (2 seconds).
        $this->adapter = new LightApcuAdapter(namespace: 'test_namespace', defaultLifetime: 2);
        $cacheItem = new LightCacheItem(isHit: true, key: 'item1', value: 'value1');

        static::assertTrue($this->adapter->save($cacheItem)); // Save item without specifying TTL.
        static::assertTrue($this->adapter->hasItem('item1'), 'Item should exist before expiring');
        sleep(3); // Allow item to expire.
        static::assertFalse($this->adapter->hasItem('item1'), 'Item should no longer exist');
    }

    public function testSaveItemWithNoExpirationKeepsItemInCache(): void
    {
        // Use a custom adapter with no default lifetime (infinite lifetime).
        $this->adapter = new LightApcuAdapter(namespace: 'test_namespace', defaultLifetime: 0);
        $cacheItem = new LightCacheItem(isHit: true, key: 'item1', value: 'value1');

        // Save the item without setting TTL, meaning it should persist indefinitely.
        static::assertTrue($this->adapter->save($cacheItem));
        static::assertTrue($this->adapter->hasItem('item1'), 'Item should exist before expiring');
        // Sleep for a while (the item should still be present because it has no TTL).
        sleep(3);
        static::assertTrue($this->adapter->hasItem('item1'), 'Item should still be present');
    }

    public function testSaveDeferredIsAliasOfSave(): void
    {
        $cacheItem = new LightCacheItem(isHit: true, key: 'item1', value: 'value1');

        static::assertTrue($this->adapter->saveDeferred($cacheItem));
        static::assertSame('value1', apcu_fetch('test_namespace/item1'));
    }
}
