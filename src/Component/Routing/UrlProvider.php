<?php

namespace Pagekit\Component\Routing;

use Pagekit\Component\File\Exception\InvalidArgumentException;
use Pagekit\Component\File\ResourceLocator;
use Pagekit\Component\Routing\Event\GenerateRouteEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouteCollection;

class UrlProvider
{
    /**
     * @var RouteCollection
     */
    protected $routes;

    /**
     * @var RequestContext
     */
    protected $context;

    /**
     * @var ResourceLocator
     */
    protected $locator;

    /**
     * @var EventDispatcherInterface
     */
    protected $events;

    /**
     * @var UrlGenerator
     */
    protected $generator;

    /**
     * @var string
     */
    protected $base;

    /**
     * Constructor.
     *
     * @param Router                   $router
     * @param ResourceLocator          $locator
     * @param EventDispatcherInterface $events
     * @param UrlGenerator             $generator
     */
    public function __construct(Router $router, ResourceLocator $locator, EventDispatcherInterface $events, UrlGenerator $generator = null)
    {
        $this->routes    = $router->getRoutes();
        $this->context   = $router->getContext();
        $this->locator   = $locator;
        $this->events    = $events;
        $this->generator = $generator ?: new UrlGenerator($this->routes, $this->context);
    }

    /**
     * @return UrlGenerator
     */
    public function getGenerator()
    {
        return $this->generator;
    }

    /**
     * Get the base path for the current request.
     *
     * @param  mixed $referenceType
     * @return string
     */
    public function base($referenceType = UrlGenerator::ABSOLUTE_PATH)
    {
        $url = $this->context->getBasePath();

        if ($referenceType === UrlGenerator::ABSOLUTE_URL) {
            $url = $this->context->getSchemeAndHttpHost().$url;
        }

        return $url;
    }

    /**
     * Get the URL for the current request.
     *
     * @param  mixed $referenceType
     * @return string
     */
    public function current($referenceType = UrlGenerator::ABSOLUTE_PATH)
    {
        $url = $this->context->getBaseUrl();

        if ($referenceType === UrlGenerator::ABSOLUTE_URL) {
            $url = $this->context->getSchemeAndHttpHost().$url;
        }

        if ($qs = $this->context->getQueryString()) {
            $qs = '?'.$qs;
        }

        return $url.$this->context->getPathInfo().$qs;
    }

    /**
     * Get the URL for the previous request.
     *
     * @return string
     */
    public function previous()
    {
        if ($referer = $this->context->getReferer()) {
            return $this->to($referer);
        }

        return '';
    }

    /**
     * Get the URL to a path or locator resource.
     *
     * @param  string $path
     * @param  mixed  $parameters
     * @param  mixed  $referenceType
     * @return string
     */
    public function to($path, $parameters = array(), $referenceType = UrlGenerator::ABSOLUTE_PATH)
    {
        if (filter_var($path, FILTER_VALIDATE_URL) !== false) {

            try {

                $path = $this->locator->findResource($path);

            } catch (InvalidArgumentException $e) {
                return $path;
            }

        }

        if ($this->isAbsolutePath($path)) {
            $path = str_replace('\\', '/', $path);
            $path = strpos($path, $base = $this->context->getScriptPath()) === 0 ? substr($path, strlen($base)) : $path;
        }

        if ($query = http_build_query($parameters, '', '&')) {
            $query = '?'.$query;
        }

        return $this->generator->generateUrl($this->base($referenceType).'/'.trim($path, '/').$query, $referenceType);
    }

    /**
     * Get the URL to a route path or name route.
     *
     * @param  string $path
     * @param  mixed  $parameters
     * @param  mixed  $referenceType
     * @return string|false
     */
    public function route($path = '', $parameters = array(), $referenceType = UrlGenerator::ABSOLUTE_PATH)
    {
        if (filter_var($path, FILTER_VALIDATE_URL) !== false || $this->isAbsolutePath($path)) {
            return $path;
        }

        try {

            $event = $this->events->dispatch('route.generate', new GenerateRouteEvent($this->routes, $path, $parameters, $referenceType));

            if ($url = $event->getUrl()) {
                return $url;
            }

            return $this->generator->generate($event->getPath(), $event->getParameters(), $event->getReferenceType()) . $event->getFragment();

        } catch (RouteNotFoundException $e) {

            if (strpos($path, '@') === 0) {
                return false;
            } elseif ($path !== '') {
                $path = "/$path";
            }

            $url = $this->context->getBaseUrl().$path;
        }

        return $this->generator->generateUrl($url, $referenceType);
    }

    /**
     * Returns whether the file path is an absolute path.
     *
     * @param  string $file
     * @return bool
     */
    protected function isAbsolutePath($file)
    {
        return $file && ($file[0] == '/' || $file[0] == '\\' || (strlen($file) > 3 && ctype_alpha($file[0]) && $file[1] == ':' && ($file[2] == '\\' || $file[2] == '/')));
    }
}