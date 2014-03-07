<?php

namespace Pagekit\Component\Database;

use Pagekit\Component\Database\Logging\DebugStack;
use Pagekit\Component\Database\ORM\EntityManager;
use Pagekit\Component\Database\ORM\Loader\AnnotationLoader;
use Pagekit\Component\Database\ORM\MetadataManager;
use Pagekit\Framework\Application;
use Pagekit\Framework\ServiceProviderInterface;

class DatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $default = array(
            'wrapperClass'        => 'Pagekit\Component\Database\ConnectionWrapper',
            'defaultTableOptions' => array()
        );

        $app['dbs'] = function($app) use ($default) {

            $dbs = array();

            foreach ($app['config']['database.connections'] as $name => $params) {

                $params = array_replace($default, $params);

                foreach (array('engine', 'charset', 'collate') as $option) {
                    if (isset($params[$option])) {
                        $params['defaultTableOptions'][$option] = $params[$option];
                    }
                }

                $events = $app['config']['database.default'] === $name ? $app['events'] : null;

                $dbs[$name] = new Connection($params, $events);
            }

            return $dbs;
        };

        $app['db'] = function ($app) {
            return $app['dbs'][$app['config']['database.default']];
        };

        $app['db.em'] = function($app) {
            return new EntityManager($app['db'], $app['db.metas']);
        };

        $app['db.metas'] = function($app) {

            $manager = new MetadataManager($app['db']);
            $manager->setLoader(new AnnotationLoader);
            $manager->setCache($app['caches']['phpfile']);

            return $manager;
        };

        $app['db.debug_stack'] = function($app) {
            return new DebugStack(null, $app['profiler.stopwatch']);
        };
    }

    public function boot(Application $app)
    {
    }
}