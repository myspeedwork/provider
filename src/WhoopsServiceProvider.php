<?php

namespace Speedwork\Provider;

use Speedwork\Container\Container;
use Speedwork\Container\ServiceProvider;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;
use Whoops\Util\Misc;

/**
 * Symfony Translation component Provider.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
