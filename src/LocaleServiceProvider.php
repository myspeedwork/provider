<?php

namespace Speedwork\Core\Provider;

use Speedwork\Core\Container;
use Speedwork\Core\Provider\Locale\LocaleListener;
use Speedwork\Core\ServiceProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Locale Provider.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class LocaleServiceProvider implements ServiceProvider
{
    public function register(Container $app)
    {
        $app['locale.listener'] = function ($app) {
            return new LocaleListener($app, $app['locale'], $app['request_stack'], isset($app['request_context']) ? $app['request_context'] : null);
        };

        $app['locale'] = 'en';
    }

    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addSubscriber($app['locale.listener']);
    }
}
