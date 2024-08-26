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

use Mockery\MockInterface;
use Nytris\Cache\Adapter\ReactCacheAdapter;
use Nytris\Cache\Tests\AbstractTestCase;
use Nytris\Core\Package\PackageContextInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Tasque\Core\Scheduler\ContextSwitch\ManualStrategy;
use Tasque\EventLoop\ContextSwitch\FutureTickScheduler;
use Tasque\EventLoop\TasqueEventLoop;
use Tasque\EventLoop\TasqueEventLoopPackageInterface;
use Tasque\Tasque;
use Tasque\TasquePackageInterface;

/**
 * Class ReactCacheAdapterTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ReactCacheAdapterTest extends AbstractTestCase
{
    private MockInterface&CacheItemPoolInterface $psrAdapter;
    private ReactCacheAdapter $reactCacheAdapter;

    public function setUp(): void
    {
        $this->psrAdapter = mock(CacheItemPoolInterface::class);

        Tasque::install(
            mock(PackageContextInterface::class),
            mock(TasquePackageInterface::class, [
                'getSchedulerStrategy' => new ManualStrategy(),
                'isPreemptive' => false,
            ])
        );
        TasqueEventLoop::install(
            mock(PackageContextInterface::class),
            mock(TasqueEventLoopPackageInterface::class, [
                'getContextSwitchInterval' => TasqueEventLoopPackageInterface::DEFAULT_CONTEXT_SWITCH_INTERVAL,
                // Switch every tick to make tests deterministic.
                'getContextSwitchScheduler' => new FutureTickScheduler(),
                'getEventLoop' => null,
            ])
        );

        $this->reactCacheAdapter = new ReactCacheAdapter($this->psrAdapter);
    }

    public function tearDown(): void
    {
        TasqueEventLoop::uninstall();
        Tasque::uninstall();
    }

    public function testClearReturnsPromiseResolvingTrueOnSuccess(): void
    {
        $this->psrAdapter->allows()
            ->clear()
            ->andReturnTrue();

        static::assertTrue(TasqueEventLoop::await($this->reactCacheAdapter->clear()));
    }

    public function testClearReturnsPromiseResolvingFalseOnFailure(): void
    {
        $this->psrAdapter->allows()
            ->clear()
            ->andReturnFalse();

        static::assertFalse(TasqueEventLoop::await($this->reactCacheAdapter->clear()));
    }

    public function testClearReturnsPromiseResolvingFalseOnException(): void
    {
        $this->psrAdapter->allows()
            ->clear()
            ->andThrow(new RuntimeException('Bang!'));

        static::assertFalse(TasqueEventLoop::await($this->reactCacheAdapter->clear()));
    }

    public function testDeleteReturnsPromiseResolvingTrueOnSuccess(): void
    {
        $this->psrAdapter->allows()
            ->deleteItem('my_key')
            ->andReturnTrue();

        static::assertTrue(TasqueEventLoop::await($this->reactCacheAdapter->delete('my_key')));
    }

    public function testDeleteReturnsPromiseResolvingFalseOnFailure(): void
    {
        $this->psrAdapter->allows()
            ->deleteItem('my_key')
            ->andReturnFalse();

        static::assertFalse(TasqueEventLoop::await($this->reactCacheAdapter->delete('my_key')));
    }

    public function testDeleteReturnsPromiseResolvingFalseOnException(): void
    {
        $this->psrAdapter->allows()
            ->deleteItem('my_key')
            ->andThrow(new RuntimeException('Bang!'));

        static::assertFalse(TasqueEventLoop::await($this->reactCacheAdapter->delete('my_key')));
    }

    public function testDeleteMultipleReturnsPromiseResolvingTrueOnSuccess(): void
    {
        $this->psrAdapter->allows()
            ->deleteItems(['key1', 'key2'])
            ->andReturnTrue();

        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->deleteMultiple(['key1', 'key2'])
            )
        );
    }

    public function testDeleteMultipleReturnsPromiseResolvingFalseOnFailure(): void
    {
        $this->psrAdapter->allows()
            ->deleteItems(['key1', 'key2'])
            ->andReturnFalse();

        static::assertFalse(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->deleteMultiple(['key1', 'key2'])
            )
        );
    }

    public function testDeleteMultipleReturnsPromiseResolvingFalseOnException(): void
    {
        $this->psrAdapter->allows()
            ->deleteItems(['key1', 'key2'])
            ->andThrow(new RuntimeException('Bang!'));

        static::assertFalse(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->deleteMultiple(['key1', 'key2'])
            )
        );
    }

    public function testGetReturnsPromiseResolvingToItemValueOnHit(): void
    {
        $item = mock(CacheItemInterface::class, [
            'get' => 'my value',
            'isHit' => true,
        ]);
        $this->psrAdapter->allows()
            ->getItem('my_key')
            ->andReturn($item);

        static::assertSame(
            'my value',
            TasqueEventLoop::await(
                $this->reactCacheAdapter->get('my_key')
            )
        );
    }

    public function testGetReturnsPromiseResolvingToGivenDefaultValueOnMiss(): void
    {
        $item = mock(CacheItemInterface::class, [
            'isHit' => false,
        ]);
        $this->psrAdapter->allows()
            ->getItem('my_key')
            ->andReturn($item);

        static::assertSame(
            'my default value',
            TasqueEventLoop::await(
                $this->reactCacheAdapter->get('my_key', 'my default value')
            )
        );
    }

    public function testGetReturnsPromiseResolvingToGivenDefaultValueOnException(): void
    {
        $this->psrAdapter->allows()
            ->getItem('my_key')
            ->andThrow(new RuntimeException('Bang!'));

        static::assertSame(
            'my default value',
            TasqueEventLoop::await(
                $this->reactCacheAdapter->get('my_key', 'my default value')
            )
        );
    }

    public function testGetMultipleReturnsPromiseResolvingToItemValuesOnHit(): void
    {
        $item1 = mock(CacheItemInterface::class, [
            'get' => 'my first value',
            'isHit' => true,
        ]);
        $item2 = mock(CacheItemInterface::class, [
            'get' => 'my second value',
            'isHit' => true,
        ]);
        $this->psrAdapter->allows()
            ->getItems(['my_first_key', 'my_second_key'])
            ->andReturn([
                'my_first_key' => $item1,
                'my_second_key' => $item2,
            ]);

        static::assertEquals(
            [
                'my_first_key' => 'my first value',
                'my_second_key' => 'my second value',
            ],
            TasqueEventLoop::await(
                $this->reactCacheAdapter->getMultiple(['my_first_key', 'my_second_key'])
            )
        );
    }

    public function testGetMultipleReturnsPromiseResolvingToGivenDefaultValueOnMissPerItem(): void
    {
        $item1 = mock(CacheItemInterface::class, [
            'isHit' => false,
        ]);
        $item2 = mock(CacheItemInterface::class, [
            'get' => 'my second value',
            'isHit' => true,
        ]);
        $this->psrAdapter->allows()
            ->getItems(['my_first_key', 'my_second_key'])
            ->andReturn([
                'my_first_key' => $item1,
                'my_second_key' => $item2,
            ]);

        static::assertEquals(
            [
                'my_first_key' => 'my default value', // Note that default value is used.
                'my_second_key' => 'my second value',
            ],
            TasqueEventLoop::await(
                $this->reactCacheAdapter->getMultiple(['my_first_key', 'my_second_key'], 'my default value')
            )
        );
    }

    public function testGetMultipleReturnsPromiseResolvingToGivenDefaultValueOnExceptionPerItem(): void
    {
        $item1 = mock(CacheItemInterface::class, [
            'isHit' => true,
        ]);
        $item1->allows()
            ->get()
            ->andThrow(new RuntimeException('Bang!'));
        $item2 = mock(CacheItemInterface::class, [
            'get' => 'my second value',
            'isHit' => true,
        ]);
        $this->psrAdapter->allows()
            ->getItems(['my_first_key', 'my_second_key'])
            ->andReturn([
                'my_first_key' => $item1,
                'my_second_key' => $item2,
            ]);

        static::assertEquals(
            [
                'my_first_key' => 'my default value', // Note that default value is used.
                'my_second_key' => 'my second value',
            ],
            TasqueEventLoop::await(
                $this->reactCacheAdapter->getMultiple(['my_first_key', 'my_second_key'], 'my default value')
            )
        );
    }

    public function testHasReturnsPromiseResolvingTrueOnSuccess(): void
    {
        $this->psrAdapter->allows()
            ->hasItem('my_key')
            ->andReturnTrue();

        static::assertTrue(TasqueEventLoop::await($this->reactCacheAdapter->has('my_key')));
    }

    public function testHasReturnsPromiseResolvingFalseOnFailure(): void
    {
        $this->psrAdapter->allows()
            ->hasItem('my_key')
            ->andReturnFalse();

        static::assertFalse(TasqueEventLoop::await($this->reactCacheAdapter->has('my_key')));
    }

    public function testHasReturnsPromiseResolvingFalseOnException(): void
    {
        $this->psrAdapter->allows()
            ->hasItem('my_key')
            ->andThrow(new RuntimeException('Bang!'));

        static::assertFalse(TasqueEventLoop::await($this->reactCacheAdapter->has('my_key')));
    }
}
