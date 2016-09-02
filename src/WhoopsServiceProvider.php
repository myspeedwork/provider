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
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;
use Whoops\Util\Misc;

/**
 * Php error handling Service Provider.
 *
 * @author Sankar <sankar.suda@gmail.com>
 */
class WhoopsServiceProvider extends ServiceProvider
{
    public function register(Container $app)
    {
        $run     = new Run();
        $handler = new PrettyPageHandler();

        // Set the title of the error page:
        $handler->setPageTitle('Whoops! There was a problem.');

        $run->pushHandler($handler);

        if (Misc::isAjaxRequest() || $app['is_api_request']) {
            $run->pushHandler(new JsonResponseHandler());
        }

        // Register the handler with PHP, and you're set!
        $run->register();
    }
}
