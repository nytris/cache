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

namespace Nytris\Cache\Tests\Functional\SymfonyCache;

use Nytris\Cache\Adapter\ReactCacheAdapter;
use Nytris\Cache\Tests\Functional\AbstractFunctionalTestCase;
use Nytris\Core\Package\PackageContextInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Tasque\Core\Scheduler\ContextSwitch\ManualStrategy;
use Tasque\EventLoop\ContextSwitch\FutureTickScheduler;
use Tasque\EventLoop\TasqueEventLoop;
use Tasque\EventLoop\TasqueEventLoopPackageInterface;
use Tasque\Tasque;
use Tasque\TasquePackageInterface;

/**
 * Class FilesystemAdapterTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class FilesystemAdapterTest extends AbstractFunctionalTestCase
{
    private FilesystemAdapter $psrAdapter;
    private ReactCacheAdapter $reactCacheAdapter;
    private string $varPath;

    public function setUp(): void
    {
        $this->varPath = dirname(__DIR__, 3) . '/var/test';
        @mkdir($this->varPath, recursive: true);

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

        $this->psrAdapter = new FilesystemAdapter(
            'my_namespace',
            0,
            $this->varPath
        );
        $this->reactCacheAdapter = new ReactCacheAdapter($this->psrAdapter);
    }

    public function tearDown(): void
    {
        TasqueEventLoop::uninstall();
        Tasque::uninstall();

        $this->rimrafDescendantsOf($this->varPath);
    }

    public function testClearEmptiesTheCache(): void
    {
        TasqueEventLoop::await(
            $this->reactCacheAdapter->setMultiple([
                'my_first_key' => 'my first value',
                'my_second_key' => 'my second value',
            ])
        );

        TasqueEventLoop::await($this->reactCacheAdapter->clear());

        static::assertFalse(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_first_key')
            )
        );
        static::assertFalse(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_second_key')
            )
        );
    }

    public function testDeleteRemovesAnItemFromTheCache(): void
    {
        TasqueEventLoop::await(
            $this->reactCacheAdapter->setMultiple([
                'my_first_key' => 'my first value',
                'my_second_key' => 'my second value',
            ])
        );

        TasqueEventLoop::await($this->reactCacheAdapter->delete('my_first_key'));

        static::assertFalse(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_first_key')
            )
        );
        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_second_key')
            )
        );
        static::assertSame(
            'my second value',
            TasqueEventLoop::await(
                $this->reactCacheAdapter->get('my_second_key')
            )
        );
    }

    public function testDeleteMultipleRemovesItemsFromTheCache(): void
    {
        TasqueEventLoop::await(
            $this->reactCacheAdapter->setMultiple([
                'my_first_key' => 'my first value',
                'my_second_key' => 'my second value',
                'my_third_key' => 'my third value',
            ])
        );

        TasqueEventLoop::await(
            $this->reactCacheAdapter->deleteMultiple(['my_first_key', 'my_third_key'])
        );

        static::assertFalse(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_first_key')
            )
        );
        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_second_key')
            )
        );
        static::assertSame(
            'my second value',
            TasqueEventLoop::await(
                $this->reactCacheAdapter->get('my_second_key')
            )
        );
        static::assertFalse(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_third_key')
            )
        );
    }

    public function testGetReturnsTheValueOfAStoredUnexpiredItem(): void
    {
        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->set('my_key', 'my value')
            )
        );
        static::assertSame(
            'my value',
            TasqueEventLoop::await(
                $this->reactCacheAdapter->get('my_key')
            )
        );
    }

    public function testGetReturnsTheGivenDefaultIfItemDoesntExist(): void
    {
        static::assertSame(
            'my default value',
            TasqueEventLoop::await(
                $this->reactCacheAdapter->get('my_key', 'my default value')
            )
        );
    }

    public function testGetReturnsNullRatherThanDefaultIfItemExistsWithValueNull(): void
    {
        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->set('my_key', null)
            )
        );

        static::assertNull(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->get('my_key', 'my default value')
            )
        );
    }

    public function testGetMultipleReturnsTheValuesOfStoredUnexpiredItems(): void
    {
        TasqueEventLoop::await(
            $this->reactCacheAdapter->setMultiple([
                'my_first_key' => 'my first value',
                'my_second_key' => 'my second value',
                'my_third_key' => 'my third value',
            ])
        );

        static::assertEquals(
            [
                'my_first_key' => 'my first value',
                'my_second_key' => 'my second value',
                'my_third_key' => 'my third value',
            ],
            TasqueEventLoop::await(
                $this->reactCacheAdapter->getMultiple([
                    'my_first_key',
                    'my_second_key',
                    'my_third_key',
                ])
            )
        );
    }

    public function testGetMultipleReturnsTheGivenDefaultForItemsThatDontExist(): void
    {
        TasqueEventLoop::await(
            $this->reactCacheAdapter->setMultiple([
                'my_first_key' => 'my first value',
                'my_second_key' => 'my second value',
            ])
        );

        static::assertEquals(
            [
                'my_first_key' => 'my first value',
                'my_second_key' => 'my second value',
                'my_non_existent_key' => 'my default value',
            ],
            TasqueEventLoop::await(
                $this->reactCacheAdapter->getMultiple(
                    [
                        'my_first_key',
                        'my_second_key',
                        'my_non_existent_key',
                    ],
                    'my default value'
                )
            )
        );
    }

    public function testGetMultipleReturnsNullRatherThanDefaultIfAnItemExistsWithValueNull(): void
    {
        TasqueEventLoop::await(
            $this->reactCacheAdapter->setMultiple([
                'my_first_key' => 'my first value',
                'my_second_key' => null,
            ])
        );

        static::assertEquals(
            [
                'my_first_key' => 'my first value',
                'my_second_key' => null,
            ],
            TasqueEventLoop::await(
                $this->reactCacheAdapter->getMultiple(
                    [
                        'my_first_key',
                        'my_second_key',
                    ],
                    'my default value'
                )
            )
        );
    }

    public function testHasReturnsTrueWhenItemExists(): void
    {
        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->set('my_key', 'my value')
            )
        );

        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_key')
            )
        );
    }

    public function testHasReturnsTrueWhenItemExistsWithValueNull(): void
    {
        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->set('my_key', null)
            )
        );

        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_key')
            )
        );
    }

    public function testHasReturnsFalseWhenItemDoesNotExist(): void
    {
        static::assertFalse(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_key')
            )
        );
    }

    public function testSetCorrectlySetsFloatTtlThatThenDoesNotExpire(): void
    {
        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->set('my_key', 'my value', 0.5)
            )
        );

        // Immediately recheck, before the second (rounded up) elapses.

        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_key')
            )
        );
    }

    public function testSetCorrectlySetsIntegerTtlThatThenDoesNotExpire(): void
    {
        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->set('my_key', 'my value', 1)
            )
        );

        // Immediately recheck, before the half a second elapses.

        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_key')
            )
        );
    }

    public function testSetCorrectlySetsTtlThatThenExpires(): void
    {
        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->set('my_key', 'my value', 0.5)
            )
        );

        sleep(2); // 2 times the TTL set above (rounded up) to allow for CI performance variance.

        static::assertFalse(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_key')
            )
        );
    }

    public function testSetMultipleCorrectlySetsFloatTtlThatThenDoesNotExpire(): void
    {
        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->setMultiple(['my_key' => 'my value'], 0.5)
            )
        );

        // Immediately recheck, before the second (rounded up) elapses.

        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_key')
            )
        );
    }

    public function testSetMultipleCorrectlySetsIntegerTtlThatThenDoesNotExpire(): void
    {
        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->setMultiple(['my_key' => 'my value'], 1)
            )
        );

        // Immediately recheck, before the half a second elapses.

        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_key')
            )
        );
    }

    public function testSetMultipleCorrectlySetsTtlThatThenExpires(): void
    {
        static::assertTrue(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->setMultiple(['my_key' => 'my value'], 0.5)
            )
        );

        sleep(2); // 2 times the TTL set above (rounded up) to allow for CI performance variance.

        static::assertFalse(
            TasqueEventLoop::await(
                $this->reactCacheAdapter->has('my_key')
            )
        );
    }
}
