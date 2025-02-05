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

### LightApcuAdapter

This is a thin, lightweight, minimal wrapper around APCu that implements the PSR-6 interface.

Usage:

```php
<?php

declare(strict_types=1);

use Nytris\Cache\Adapter\LightApcuAdapter;

$adapter = new LightApcuAdapter(
    namespace: 'my_namespace', // Defaults to the empty string.
    defaultLifetime: 3600      // Defaults to 0 / no expiry.
);

$cacheItem = $adapter->getItem('my_key');
$cacheItem->set('my_value');
$cacheItem->expiresAfter(2); // Set to expire 2 seconds from now.

$adapter->save($cacheItem);
```

### Caveats

- PSR-6 cache adapters may block, if so then the ReactPHP event loop will be blocked.
  It is the responsibility of the consuming application to not use PSR cache adapters that block.

[ReactPHP]: https://reactphp.org/
