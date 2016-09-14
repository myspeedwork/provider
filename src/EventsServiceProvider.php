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
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Event Service Provider.
 *
 * @author Sankar <sankar.suda@gmail.com>
 */
class EventsServiceProvider extends ServiceProvider
{
    public function register(Container $app)
    {
        $app['events'] = function () {
            return new EventDispatcher();
        };
    }
}
