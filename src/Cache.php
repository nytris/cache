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

namespace Nytris\Cache;

use Nytris\Cache\Adapter\ReactCacheAdapter;
use Nytris\Cache\Adapter\ReactCacheAdapterInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class Cache.
 *
 * Defines the public facade API for the library.
 *
 * TODO: Get rid in favour of just instantiating ReactCacheAdapter directly?
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class Cache implements CacheInterface
{
    /**
     * @inheritDoc
     */
    public function createReactCacheFromPsr(CacheItemPoolInterface $psrCachePool): ReactCacheAdapterInterface
    {
        return new ReactCacheAdapter($psrCachePool);
    }
}
