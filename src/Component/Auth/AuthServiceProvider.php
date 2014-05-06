<?php

namespace Pagekit\Component\Auth;

use Pagekit\Component\Auth\Encoder\NativePasswordEncoder;
use Pagekit\Component\Auth\Event\LoginEvent;
use Pagekit\Component\Auth\Event\LogoutEvent;
use Pagekit\Framework\Application;
use Pagekit\Framework\ServiceProviderInterface;
use RandomLib\Factory;
use SecurityLib\Strength;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AuthServiceProvider implements ServiceProviderInterface, EventSubscriberInterface
{
    protected $app;

    public function register(Application $app)
    {
        $this->app = $app;

        $app['auth'] = function($app) {
            return new Auth($app['events'], $app['session']);
        };

        $app['auth.password'] = function() {
            return new NativePasswordEncoder;
        };

        $app['auth.random'] = function() {
            $factory = new Factory;
            return $factory->getMediumStrengthGenerator();
            //return $factory->getGenerator(new Strength(Strength::HIGH));
        };
    }

    public function boot(Application $app)
    {
        $app['events']->addSubscriber($this);
    }

    /**
     * Redirects a user after successful login.
     *
     * @param LoginEvent $event
     */
    public function onLogin(LoginEvent $event)
    {
        $event->setResponse(new RedirectResponse($this->app['request']->get(Auth::REDIRECT_PARAM)));
    }

    /**
     * Redirects a user after successful logout.
     *
     * @param LogoutEvent $event
     */
    public function onLogout(LogoutEvent $event)
    {
        $event->setResponse(new RedirectResponse($this->app['request']->get(Auth::REDIRECT_PARAM)));
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            AuthEvents::LOGIN  => array('onLogin', -32),
            AuthEvents::LOGOUT => array('onLogout', -32)
        );
    }
}
