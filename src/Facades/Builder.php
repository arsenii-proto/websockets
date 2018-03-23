<?php

namespace Arsenii\WebSockets\Facades;


use Illuminate\Support\Facades\Facade;

class Builder extends Facade {

    /**
     * @method static string version()
     * @method static string basePath()
     * @method static string environment()
     * @method static bool isDownForMaintenance()
     * @method static void registerConfiguredProviders()
     * @method static \Illuminate\Support\ServiceProvider register(\Illuminate\Support\ServiceProvider|string $provider, array $options = [], bool $force = false)
     * @method static void registerDeferredProvider(string $provider, string $service = null)
     * @method static void boot()
     * @method static void booting(mixed $callback)
     * @method static void booted(mixed $callback)
     * @method static string getCachedServicesPath()
     *
     * @see \Arsenii\WebSockets\Services\BuilderService
     */

    protected static function getFacadeAccessor()
    {
        return 'ws.builder';
    }

}