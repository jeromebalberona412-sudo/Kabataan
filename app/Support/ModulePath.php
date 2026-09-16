<?php

namespace App\Support;

/**
 * Resolve module Routes/routes and Views/views on case-sensitive filesystems.
 */
final class ModulePath
{
    public static function routes(string $providerDirectory, string $filename): ?string
    {
        $moduleRoot = dirname($providerDirectory);

        foreach (['Routes', 'routes'] as $directory) {
            $path = $moduleRoot.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.$filename;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function views(string $providerDirectory): string
    {
        $moduleRoot = dirname($providerDirectory);

        foreach (['Views', 'views'] as $directory) {
            $path = $moduleRoot.DIRECTORY_SEPARATOR.$directory;

            if (is_dir($path)) {
                return $path;
            }
        }

        return $moduleRoot.DIRECTORY_SEPARATOR.'Views';
    }
}
