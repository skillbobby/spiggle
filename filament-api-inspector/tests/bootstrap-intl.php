<?php

declare(strict_types=1);

namespace Illuminate\Support;

/**
 * The sandbox used for development does not ship ext-intl. Filament only needs
 * NumberFormatter for pagination labels, so the Symfony ICU polyfill provides
 * the class while this shim satisfies Laravel's extension check.
 *
 * In real installations ext-intl is present and this delegates to the actual
 * extension. This file is autoload-dev only.
 */
function extension_loaded(string $extension): bool
{
    if ($extension === 'intl' && ! \extension_loaded('intl')) {
        return class_exists(\NumberFormatter::class);
    }

    return \extension_loaded($extension);
}
