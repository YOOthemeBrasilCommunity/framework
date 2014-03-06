<?php

namespace Pagekit\Framework\Extension;

use Pagekit\Component\File\ResourceLocator;
use Pagekit\Component\Routing\Router;
use Pagekit\Framework\Application;
use Pagekit\Framework\ApplicationAware;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\Translator;

class Extension extends ApplicationAware
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var \ReflectionObject
     */
    protected $reflected;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $path
     * @param array  $config
     */
    public function __construct($name, $path, array $config = array())
    {
        $this->name   = $name;
        $this->path   = $path;
        $this->config = $config;
    }

    /**
     * Boots the extension.
     */
    public function boot(Application $app)
    {
        $this->registerControllers($app['router']);
        $this->registerLanguages($app['translator']);
        $this->registerResources($app['locator'], $app['events']);
    }

    /**
     * Returns the extensions's name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the extensions's absolute path.
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Returns the extension's config
     *
     * @param mixed $key
     * @param mixed $default
     * @return array
     */
    public function getConfig($key = null, $default = null)
    {
        if (null === $key) {
            return $this->config;
        }
        $array = $this->config;

        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {

            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Finds and registers controllers.
     *
     * Override this method if your extension controllers do not follow the conventions:
     *
     *  - The controller folder is defined in the extensions config
     *  - The naming convention is 'HelloController.php'
     *
     * @param Router $router
     */
    public function registerControllers(Router $router)
    {
        if ($config = $this->getConfig() and isset($config['controllers'])) {
            $controllers = (array) $config['controllers'];
            foreach ($controllers as $controller) {
                foreach (glob($this->getPath().'/'.ltrim($controller, '/')) as $file) {

                    $name = $this->getName();
                    $path = sprintf('%s/%s', $name, strtolower(basename($file, 'Controller.php')));

                    $router->addController($file, compact('name', 'path'));
                }
            }
        }
    }

    /**
     * Finds and registers languages.
     *
     * Override this method if your extension does not follow the conventions:
     *
     *  - Languages are in the 'languages' sub-directory
     *  - The naming convention '/locale/domain.format', example: /en_GB/hello.mo
     *
     * @param Translator $translator
     */
    public function registerLanguages(Translator $translator)
    {
        foreach (glob($this->getPath().'/languages/*/*') as $file) {
            if (preg_match('/languages\/(.+)\/(.+)\.(mo|po|php)$/', $file, $matches)) {

                list(, $locale, $domain, $format) = $matches;

                if ($format == 'php') {
                    $format = 'array';
                    $file = require($file);
                }

                $translator->addResource($format, $file, $locale, $domain);
                $translator->addResource($format, $file, substr($locale, 0, 2), $domain);
            }
        }
    }

    /**
     * Finds and adds extension's resources.
     *
     * @param ResourceLocator          $locator
     * @param EventDispatcherInterface $dispatcher
     */
    public function registerResources(ResourceLocator $locator, EventDispatcherInterface $dispatcher)
    {
        $root = $this->getPath();
        $addResources = function($config, $prefix = '') use ($root, $locator) {
            foreach ($config as $scheme => $resources) {

                if (strpos($scheme, '://') > 0 && $segments = explode('://', $scheme, 2)) {
                    list($scheme, $prefix)  = $segments;
                }

                $resources = (array) $resources;

                array_walk($resources, function(&$resource) use ($root) {
                    $resource = "$root/$resource";
                });

                $locator->addPath($scheme, $prefix, $resources);
            }
        };

        $addResources($this->getConfig('resources.export', array()), $this->getName());

        if ($config = $this->getConfig('resources.override')) {
            $dispatcher->on('init', function() use ($config, $addResources) {
                $addResources($config);
            });
        }
    }

    /**
     * Extension's enable hook
     */
    public function enable()
    {
    }

    /**
     * Extension's disable hook
     */
    public function disable()
    {
    }

    /**
     * Extension's uninstall hook
     */
    public function uninstall()
    {
    }
}
