<?php

namespace Pagekit\Component\Routing;

use Pagekit\Component\Routing\Controller\ControllerCollectionInterface;
use Pagekit\Component\Routing\Event\RouteCollectionEvent;
use Pagekit\Component\Routing\Generator\UrlGenerator;
use Pagekit\Component\Routing\Generator\UrlGeneratorDumper;
use Pagekit\Component\Routing\Generator\UrlGeneratorInterface;
use Pagekit\Component\Routing\RequestContext as ExtendedRequestContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherDumper;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class Router implements RouterInterface, UrlGeneratorInterface
{
    /**
     * @var EventDispatcherInterface
     */
    protected $events;

    /**
     * @var HttpKernelInterface
     */
    protected $kernel;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var RequestContext
     */
    protected $context;

    /**
     * @var UrlMatcher
     */
    protected $matcher;

    /**
     * @var UrlGenerator
     */
    protected $generator;

    /**
     * @var RouteCollection
     */
    protected $routes;

    /**
     * @var ControllerCollectionInterface
     */
    protected $controllers;

    /**
     * @var array
     */
    protected $aliases;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array
     */
    protected $cache;

    /**
     * @var ParamsResolverInterface[]
     */
    protected $resolver = [];

    /**
     * Constructor.
     *
     * @param HttpKernelInterface           $kernel
     * @param ControllerCollectionInterface $controllers
     * @param array                         $options
     */
    public function __construct(EventDispatcherInterface $events, HttpKernelInterface $kernel, ControllerCollectionInterface $controllers, array $options = [])
    {
        $this->events      = $events;
        $this->kernel      = $kernel;
        $this->controllers = $controllers;
        $this->aliases     = [];
        $this->context     = new ExtendedRequestContext;

        $this->options = array_replace([
            'cache'     => null,
            'matcher'   => 'Symfony\Component\Routing\Matcher\UrlMatcher',
            'generator' => 'Pagekit\Component\Routing\Generator\UrlGenerator'
        ], $options);
    }

    /**
     * Get the current request.
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function setContext(RequestContext $context)
    {
        $this->context = $context;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param array $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    /**
     * Gets a route by name.
     *
     * @param string $name The route name
     *
     * @return Route|null A Route instance or null when not found
     */
    public function getRoute($name)
    {
        return $this->getRouteCollection()->get($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteCollection()
    {
        if (!$this->routes) {

            $this->routes = $this->controllers->flush();

            $this->events->dispatch('route.collection', new RouteCollectionEvent($this, $this->routes));

            foreach ($this->aliases as $source => $alias) {

                $name   = $source;
                $params = ['_resolver' => $alias[1]];

                $this->parseParams($name, $params);

                if ($route = $this->routes->get($name)) {
                    $this->routes->add($source, new Route($alias[0], array_merge($route->getDefaults(), $params, ['_variables' => $route->compile()->getPathVariables()])));
                }
            }
        }

        return $this->routes;
    }

    /**
     * Gets the URL matcher instance.
     *
     * @return UrlMatcher
     */
    public function getMatcher()
    {
        if (!$this->matcher) {
            if ($cache = $this->getCache('%s/%s.matcher.cache')) {

                $class = sprintf('UrlMatcher%s', $cache['key']);

                if (!$cache['fresh']) {
                    $options = ['class' => $class, 'base_class' => $this->options['matcher']];
                    $this->writeCache($cache['file'], (new PhpMatcherDumper($this->getRouteCollection()))->dump($options));
                }

                require_once $cache['file'];

                $this->matcher = new $class($this->context);

            } else {

                $class = $this->options['matcher'];

                $this->matcher = new $class($this->getRouteCollection(), $this->context);
            }
        }

        return $this->matcher;
    }

    /**
     * Gets the UrlGenerator instance associated with this Router.
     *
     * @return UrlGenerator
     */
    public function getGenerator()
    {
        if (!$this->generator) {
            if ($cache = $this->getCache('%s/%s.generator.cache')) {

                $class = sprintf('UrlGenerator%s', $cache['key']);

                if (!$cache['fresh']) {
                    $options = ['class' => $class, 'base_class' => $this->options['generator']];
                    $this->writeCache($cache['file'], (new UrlGeneratorDumper($this->getRouteCollection()))->dump($options));
                }

                require_once $cache['file'];

                $this->generator = new $class($this->context);

            } else {

                $class = $this->options['generator'];

                $this->generator = new $class($this->getRouteCollection(), $this->context);
            }
        }

        return $this->generator;
    }

    /**
     * Gets an alias.
     *
     * @param  string $name
     * @return array
     */
    public function getAlias($name)
    {
        return isset($this->aliases[$name]) || isset($this->aliases[$name = strtok($name, '?')]) ? $this->aliases[$name] : false;
    }

    /**
     * Adds an alias.
     *
     * @param string $path
     * @param string $name
     * @param string $resolver
     */
    public function addAlias($path, $name, $resolver = null)
    {
        $path = preg_replace('/^[^\/]/', '/$0', $path);

        $this->aliases[$name] = [$path, $resolver];
    }

    /**
     * Aborts the current request by sending a proper HTTP error.
     *
     * @param  int    $code
     * @param  string $message
     * @param  array  $headers
     * @throws HttpException
     * @throws NotFoundHttpException
     */
    public function abort($code, $message = '', array $headers = [])
    {
        if ($code == 404) {
            throw new NotFoundHttpException($message);
        } else {
            throw new HttpException($code, $message, null, $headers);
        }
    }

    /**
     * Terminates a request/response cycle.
     *
     * @param Request  $request
     * @param Response $response
     */
    public function terminate(Request $request, Response $response)
    {
        $this->kernel->terminate($request, $response);
    }

    /**
     * Handles a Request to convert it to a Response.
     *
     * @param  Request $request
     * @param  int     $type
     * @param  bool    $catch
     * @return Response
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $this->request = $request;
        $this->context->fromRequest($request);

        return $this->kernel->handle($request, $type, $catch);
    }

    /**
     * Handles a Subrequest to call an action internally.
     *
     * @param  string $name
     * @param  array  $parameters
     * @throws \RuntimeException
     * @return Response
     */
    public function call($name, $parameters = [])
    {
        if (empty($this->request)) {
            throw new \RuntimeException('No Request set.');
        }

        return $this->kernel->handle(Request::create($this->generate($name, $parameters, true)), HttpKernelInterface::SUB_REQUEST);
    }

    /**
     * {@inheritdoc}
     */
    public function match($pathinfo)
    {
        $params = $this->getMatcher()->match($pathinfo);

        if ($resolver = $this->getResolver($params)) {
            $params = $resolver->match($params);
        }

        if (false !== $pos = strpos($params['_route'], '?')) {
            $params['_route'] = substr($params['_route'], 0, $pos);
        }

        return $params;
    }

    /**
     * {@inheritdoc}
     */
    public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH)
    {
        if ($fragment = strstr($name, '#')) {
            $name = strstr($name, '#', true);
        }

        $this->parseParams($name, $parameters);

        $generator = $this->getGenerator();
        if ($referenceType !== self::LINK_URL
            && ($props = $generator->getRouteProperties($generator->generate($name, $parameters, 'link')) or $props = $generator->getRouteProperties($name))
            && $resolver = $this->getResolver($props[1])
        ) {
            $parameters = $resolver->generate($parameters);
        }

        return $generator->generate($name, $parameters, $referenceType).$fragment;
    }

    /**
     * Gets cache info.
     *
     * @param  string $file
     * @return array|null
     */
    protected function getCache($file)
    {
        if (!$this->options['cache']) {
            return null;
        }

        if (!$this->cache) {

            $modified  = 0;
            $resources = $this->controllers->getResources();

            foreach ($this->aliases as $name => $alias) {
                $resources['aliases'][] = $name.$alias[0];
            }

            foreach ($resources['controllers'] as $controller) {
                if (file_exists($controller) && ($time = filemtime($controller)) > $modified) {
                    $modified = $time;
                }
            }

            $resources['options'] = $this->options;

            $this->cache = ['key' => sha1(json_encode($resources)), 'modified' => $modified];
        }

        $file  = sprintf($file, $this->options['cache'], $this->cache['key']);
        $fresh = file_exists($file) && (!$this->cache['modified'] || filemtime($file) >= $this->cache['modified']);

        return array_merge(compact('fresh', 'file'), $this->cache);
    }

    /**
     * Writes cache file.
     *
     * @param  string $file
     * @param  string $content
     * @throws \RuntimeException
     */
    protected function writeCache($file, $content)
    {
        if (!file_put_contents($file, $content)) {
            throw new \RuntimeException("Failed to write cache file ($file).");
        }
    }

    /**
     * Separates internal links into $name and $parameters.
     *
     * @param string $name
     * @param array  $parameters
     */
    protected function parseParams(&$name, array &$parameters)
    {
        if ($query = substr(strstr($name, '?'), 1)) {
            parse_str($query, $params);
            $name       = strstr($name, '?', true);
            $parameters = array_replace($parameters, $params);
        }
    }

    /**
     * Gets resolver instance from parameters.
     *
     * @param  array $parameters
     * @return ParamsResolverInterface|null
     */
    protected function getResolver(array $parameters = [])
    {
        $resolver = isset($parameters['_resolver']) ? $parameters['_resolver'] : false;

        if (!isset($this->resolver[$resolver])) {

            if (!is_subclass_of($resolver, 'Pagekit\Component\Routing\ParamsResolverInterface')) {
                return null;
            }

            $this->resolver[$resolver] = new $resolver;
        }

        return $this->resolver[$resolver];
    }
}
