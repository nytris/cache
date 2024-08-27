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

use React\Cache\CacheInterface;

/**
 * Interface ReactCacheAdapterInterface.
 *
 * A ReactPHP cache adapter backed by a PSR-6-cache.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ReactCacheAdapterInterface extends CacheInterface
{
    /**
     * Ensures that the key does not contain any reserved characters.
     */
    public function sanitiseCacheKey(string $key): string;
}
