# Nytris Cache

[![Build Status](https://github.com/nytris/cache/workflows/CI/badge.svg)](https://github.com/nytris/cache/actions?query=workflow%3ACI)

Implements a [ReactPHP][ReactPHP] cache using any PSR-6-compliant cache such as Symfony Cache.

## Usage
Install this package with Composer:

```shell
$ composer require nytris/cache
```

### When using Nytris platform (recommended)

Configure Nytris platform:

`nytris.config.php`

```php
<?php

declare(strict_types=1);

use Nytris\Boot\BootConfig;
use Nytris\Boot\PlatformConfig;
use Nytris\Cache\Adapter\ReactCacheAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$bootConfig = new BootConfig(new PlatformConfig(__DIR__ . '/var/cache/nytris/'));

$bootConfig->installPackage(new MyNytrisPackage(
    // Using Symfony Cache adapter as an example.
    cachePoolFactory: fn (string $cachePath) => new ReactCacheAdapter(
        new FilesystemAdapter(
            'my_cache_key',
            0,
            $cachePath
        )
    )
));

return $bootConfig;
```

### Caveats

- PSR-6 cache adapters may block, if so then the ReactPHP event loop will be blocked.
  It is the responsibility of the consuming application to not use PSR cache adapters that block.

[ReactPHP]: https://reactphp.org/
