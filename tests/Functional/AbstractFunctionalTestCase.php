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

namespace Nytris\Cache\Tests\Functional;

use Nytris\Cache\Tests\AbstractTestCase;

/**
 * Class AbstractFunctionalTestCase.
 *
 * Base class for all functional test cases.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
abstract class AbstractFunctionalTestCase extends AbstractTestCase
{
    protected function rimrafDescendantsOf(string $path): void
    {
        foreach (glob($path . '/**') as $subPath) {
            if (is_file($subPath) || is_link($subPath)) {
                unlink($subPath);
            } else {
                $this->rimrafDescendantsOf($subPath);

                rmdir($subPath);
            }
        }
    }
}
