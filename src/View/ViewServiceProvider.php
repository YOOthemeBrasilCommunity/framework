<?php

namespace Pagekit\View;

use Pagekit\View\Event\ViewListener;
use Pagekit\View\Section\SectionManager;
use Pagekit\Application;
use Pagekit\ServiceProviderInterface;

class ViewServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['view'] = function($app) {

            $view = new View($app['view.sections']);
            $view->set('app', $app);

            return $view;
        };

        $app['view.sections'] = function() {
            return new SectionManager;
        };
    }

    public function boot(Application $app)
    {
        $app['events']->addSubscriber(new ViewListener($view = $app['view']));

        $app['view.sections']->prepend('head', function() use ($view) {

            $result = [];

            if ($title = $view->get('head.title')) {
                $result[] = sprintf('        <title>%s</title>', $title);
            }

            if ($links = $view->get('head.link', [])) {
                foreach($links as $rel => $attributes) {

                    if (!$attributes) {
                        continue;
                    }

                    if (!isset($attributes['rel'])) {
                        $attributes['rel'] = $rel;
                    }

                    $html = '<link';
                    foreach ($attributes as $name => $value) {
                        $html .= sprintf(' %s="%s"', $name, htmlspecialchars($value));
                    }
                    $result[] = $html.'>';
                }
            }

            return implode(PHP_EOL, $result);
        });
    }
}
