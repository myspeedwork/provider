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

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use Speedwork\Container\Container;
use Speedwork\Container\ServiceProvider;

/**
 * Logger service provider.
 *
 * @author Sankar <sankar.suda@gmail.com>
 */
class MonologServiceProvider extends ServiceProvider
{
    public function register(Container $app)
    {
        $app['logger'] = function () use ($app) {
            return $app['monolog'];
        };

        $app['monolog'] = function ($app) {
            $logger = new Logger($app['monolog.name']);

            $rotate = $app['config']->get('app.log.rotate', 'single');
            $logger->pushHandler($app['monolog.handler.'.$rotate]);

            return $logger;
        };

        $app['monolog.formatter'] = function () {
            return new LineFormatter();
        };

        $app['monolog.handler.single'] = function ($app) {
            $handler = new StreamHandler($app['monolog.logfile'], $app['monolog.level']);
            $handler->setFormatter($app['monolog.formatter']);

            return $handler;
        };

        $app['monolog.handler.daily'] = function ($app) {
            $maxFiles = $app['config']->get('app.log.max_files', 5);
            $handler  = new RotatingFileHandler($app['monolog.logfile'], $maxFiles, $app['monolog.level']);
            $handler->setFormatter($app['monolog.formatter']);

            return $handler;
        };

        $app['monolog.handler.error'] = function ($app) {
            $handler = new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, $app['monolog.level']);
            $handler->setFormatter($app['monolog.formatter']);

            return $handler;
        };

        $app['monolog.handler.syslog'] = function ($app) {
            $handler = new SyslogHandler($app['monolog.name'], LOG_USER, $app['monolog.level']);
            $handler->setFormatter($app['monolog.formatter']);

            return $handler;
        };

        $level                = $app['config']->get('app.log.level', 'debug');
        $app['monolog.level'] = $this->parseLevel($level);

        $path = $app['config']->get('paths.logs');

        $app['monolog.logfile'] = $path.$this->getSettings('app.log.logfile');
        $app['monolog.name']    = $this->getSettings('monolog.name', 'app.name');
    }

    public function parseLevel($name)
    {
        // level is already translated to logger constant, return as-is
        if (is_int($name)) {
            return $name;
        }

        $levels = Logger::getLevels();
        $upper  = strtoupper($name);

        if (!isset($levels[$upper])) {
            throw new \InvalidArgumentException("Provided logging level '$name' does not exist. Must be a valid monolog logging level.");
        }

        return $levels[$upper];
    }
}
