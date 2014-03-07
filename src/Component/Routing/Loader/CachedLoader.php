<?php

namespace Pagekit\Component\Routing\Loader;

use Pagekit\Component\Cache\CacheInterface;

class CachedLoader
{
    /**
     * Route loader instance.
     *
     * @var RouteLoader
     */
    protected $loader;

    /**
     * Cached routes.
     *
     * @var array
     */
    protected $routes = array();

    /**
     * Cache instance.
     *
     * @var CacheInterface
     */
    protected $cache;

    /**
     * Cache key.
     *
     * @var string
     */
    protected $cacheKey = 'Routes';

    /**
     * Cache dirty.
     *
     * @var bool
     */
    protected $cacheDirty = false;

    /**
     * Check controller modified time.
     *
     * @var bool
     */
    protected $check;

    /**
     * Constructor.
     *
     * @param RouteLoader    $loader
     * @param CacheInterface $cache
     * @param bool           $check
     */
    public function __construct(RouteLoader $loader, CacheInterface $cache, $check = false)
    {
        $this->loader = $loader;
        $this->cache  = $cache;
        $this->check  = $check;

        if ($routes = $this->cache->fetch($this->cacheKey)) {
            $this->routes = $routes;
        }
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        if ($this->cacheDirty) {
            $this->cache->save($this->cacheKey, $this->routes);
        }
    }

    /**
     * Loads routes by parsing controller method names.
     *
     * @param  string $controller
     * @param  array  $options
     * @return array
     */
    public function load($controller, array $options = array())
    {
        if ('.php' != substr($controller, -4)) {
            $reflection = new \ReflectionClass($controller);
            $controller = $reflection->getFileName();
        }

        if ($this->check || !isset($this->routes[$controller])) {
            $time = file_exists($controller) ? filemtime($controller) : 0;
        }

        if ($this->check && isset($this->routes[$controller]) && $this->routes[$controller]['time'] != $time) {
            unset($this->routes[$controller]);
        }

        $routes = isset($this->routes[$controller]) ? $this->routes[$controller]['routes'] : $this->loader->load($controller, $options);

        if (isset($time) && $time) {
            $this->routes[$controller] = compact('routes', 'time');
            $this->cacheDirty = true;
        }

        return $routes;
    }
}