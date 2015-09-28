<?php

namespace Speedwork\Provider\Session;

use Speedwork\Container\Container;
use Symfony\Component\HttpKernel\EventListener\TestSessionListener as BaseTestSessionListener;

/**
 * Simulates sessions for testing purpose.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TestSessionListener extends BaseTestSessionListener
{
    private $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    protected function getSession()
    {
        if (!isset($this->app['session'])) {
            return;
        }

        return $this->app['session'];
    }
}
