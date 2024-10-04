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

namespace Nytris\Cache\Tests\Unit\Adapter;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use Nytris\Cache\Adapter\LightCacheItem;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;

/**
 * Class LightCacheItemTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class LightCacheItemTest extends TestCase
{
    private LightCacheItem $cacheItem;

    protected function setUp(): void
    {
        $this->cacheItem = new LightCacheItem(isHit: true, key: 'test_key', value: 'test_value');
    }

    public function testImplementsCacheItemInterface(): void
    {
        static::assertInstanceOf(CacheItemInterface::class, $this->cacheItem);
    }

    public function testExpiresAfterSetsExpiryWhenIntervalIsPassed(): void
    {
        $interval = new DateInterval('PT2S'); // 2 seconds.
        $this->cacheItem->expiresAfter($interval);

        $expectedExpiry = microtime(true) + 2.0;
        $actualExpiry = $this->cacheItem->getExpiry();

        static::assertNotNull($actualExpiry);
        static::assertGreaterThanOrEqual($expectedExpiry - 0.01, $actualExpiry);
        static::assertLessThanOrEqual($expectedExpiry + 0.01, $actualExpiry);
    }

    public function testExpiresAfterSetsExpiryWhenIntegerIsPassed(): void
    {
        $this->cacheItem->expiresAfter(2); // 2 seconds.

        $expectedExpiry = microtime(true) + 2.0;
        $actualExpiry = $this->cacheItem->getExpiry();

        static::assertNotNull($actualExpiry);
        static::assertGreaterThanOrEqual($expectedExpiry - 0.01, $actualExpiry);
        static::assertLessThanOrEqual($expectedExpiry + 0.01, $actualExpiry);
    }

    public function testExpiresAfterSetsNullExpiryWhenNullIsPassed(): void
    {
        $this->cacheItem->expiresAfter(null);

        static::assertNull($this->cacheItem->getExpiry());
    }

    public function testExpiresAfterThrowsExceptionForInvalidArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expiration date must be an integer, a DateInterval or null, "string" given.');

        $this->cacheItem->expiresAfter('invalid');
    }

    public function testExpiresAtSetsExpiryWhenDateTimeImmutableIsPassed(): void
    {
        $expiryTime = new DateTimeImmutable('+2 seconds');
        $this->cacheItem->expiresAt($expiryTime);

        $expectedExpiry = (float) $expiryTime->format('U.u');
        $actualExpiry = $this->cacheItem->getExpiry();

        static::assertNotNull($actualExpiry);
        static::assertSame($expectedExpiry, $actualExpiry);
    }

    public function testExpiresAtSetsExpiryWhenDateTimeIsPassed(): void
    {
        $expiryTime = new DateTime('+2 seconds');
        $this->cacheItem->expiresAt($expiryTime);

        $expectedExpiry = (float) $expiryTime->format('U.u');
        $actualExpiry = $this->cacheItem->getExpiry();

        static::assertNotNull($actualExpiry);
        static::assertSame($expectedExpiry, $actualExpiry);
    }

    public function testExpiresAtSetsNullExpiryWhenNullIsPassed(): void
    {
        $this->cacheItem->expiresAt(null);

        static::assertNull($this->cacheItem->getExpiry());
    }

    public function testExpiresAtThrowsExceptionForInvalidArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expiration date must implement DateTimeInterface or be null, "string" given.');

        $this->cacheItem->expiresAt('invalid');
    }

    public function testGetReturnsTheValue(): void
    {
        static::assertSame('test_value', $this->cacheItem->get());
    }

    public function testGetExpiryReturnsCorrectExpiryTimeAfterExpiresAt(): void
    {
        $expiryTime = new DateTimeImmutable('+2 seconds');
        $this->cacheItem->expiresAt($expiryTime);

        $expectedExpiry = (float) $expiryTime->format('U.u');
        static::assertSame($expectedExpiry, $this->cacheItem->getExpiry());
    }

    public function testGetExpiryReturnsNullIfNoExpirySet(): void
    {
        static::assertNull($this->cacheItem->getExpiry());
    }

    public function testGetKeyReturnsTheKey(): void
    {
        static::assertSame('test_key', $this->cacheItem->getKey());
    }

    public function testIsHitReturnsTrueWhenHit(): void
    {
        static::assertTrue($this->cacheItem->isHit());
    }

    public function testSetUpdatesTheValue(): void
    {
        $this->cacheItem->set('new_value');

        static::assertSame('new_value', $this->cacheItem->get());
    }
}
