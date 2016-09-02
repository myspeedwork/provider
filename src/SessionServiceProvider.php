<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Provider;

use Speedwork\Container\Container;
use Speedwork\Container\ServiceProvider;
use Speedwork\Provider\Session\SessionListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * Symfony HttpFoundation component Provider for sessions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SessionServiceProvider extends ServiceProvider
{
    public function register(Container $app)
    {
        $app['session'] = function ($app) {
            return new Session($app['session.storage']);
        };

        $app['session.storage'] = function ($app) {
            return $app['session.storage.native'];
        };

        $app['session.storage.handler'] = function ($app) {
            return new NativeFileSessionHandler($app['session.storage.save_path']);
        };

        $app['session.storage.native'] = function ($app) {
            return new NativeSessionStorage(
                $app['session.storage.options'],
                $app['session.storage.handler']
            );
        };

        $app['session.listener'] = function ($app) {
            return new SessionListener($app, $app['session.attribute_bag'], $app['session.flash_bag']);
        };

        $app['session.storage.options']   = [];
        $app['session.default_locale']    = 'en';
        $app['session.storage.save_path'] = null;
        $app['session.attribute_bag']     = null;
        $app['session.flash_bag']         = null;
    }

    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addSubscriber($app['session.listener']);
    }
}
