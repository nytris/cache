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

use Nytris\Cache\Adapter\ReactCacheAdapterInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Interface CacheInterface.
 *
 * Defines the public facade API for the library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface CacheInterface
{
    /**
     * Creates a ReactPHP cache using the provided PSR-6-compliant cache.
     */
    public function createReactCacheFromPsr(CacheItemPoolInterface $psrCachePool): ReactCacheAdapterInterface;
}
