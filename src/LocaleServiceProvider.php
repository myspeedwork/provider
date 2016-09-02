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
use Speedwork\Provider\Locale\LocaleListener;

/**
 * Locale Provider.
 *
 * @author Sankar <sankar.suda@gmail.com>
 */
class LocaleServiceProvider extends ServiceProvider
{
    public function register(Container $app)
    {
        $app['locale.listener'] = function ($app) {
            return new LocaleListener($app, $app['locale'], $app['request_stack'], isset($app['request_context']) ? $app['request_context'] : null);
        };

        $app['locale'] = 'en';
    }
}
