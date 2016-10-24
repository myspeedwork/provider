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

use Speedwork\Config\ConfigServiceProvider;
use Speedwork\Container\Container;
use Speedwork\Container\ServiceProvider;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class BootstrapServiceProvider extends ServiceProvider
{
    public function register(Container $app)
    {
        $this->loadConfiguration($app);
        $this->setTimeZone($app['config']->get('app.timezone'));
        $this->setUpUrl($app);
        $this->setUpConstants($app);
        $this->setUpPaths($app);
        $this->setUpLocations($app);
    }

    protected function loadConfiguration(Container $app)
    {
        $app->register(new ConfigServiceProvider(), [
            'config.paths' => [$app->getPath('config')],
        ]);

        // First we will see if we have a cache configuration file. If we do, we'll load
        // the configuration items from that file so that it is very quick. Otherwise
        // we will need to spin through every configuration file and load them all.
        if (file_exists($cached = $app->getPath('cache').'config.php')) {
            $items = include $cached;

            $app['config']->set($items);

            return true;
        }

        if (file_exists($app->getPath('env'))) {
            $app['config.loader']->load($app->getPath('env'));
        }

        $app['config.loader']->load([$app->getPath('config')], true);
    }

    protected function setTimeZone($timezone)
    {
        if ($timezone) {
            if (strpos($timezone, '.') !== false) {
                list($hours, $minutes) = explode('.', $timezone);

                $offset   = $hours.'.'.round(($minutes / 60) * 100);
                $timezone = timezone_name_from_abbr('', $offset * 3600, 0);
            }
            if ($timezone) {
                date_default_timezone_set($timezone);
            }
        }
    }

    protected function setUpPaths(Container $app)
    {
        $paths = $app->getPath();

        $app['config']->set($paths);

        return $paths;
    }

    protected function setUpLocations(Container $app)
    {
        $url    = $app['config']->get('app.url');
        $public = $app['config']->get('app.public');

        if ($public !== false) {
            $public = $url;
        } else {
            $public = $url.'public/';
        }

        $locations = [
            'baseurl' => $url,
            'siteurl' => $url,
            'url'     => $url,
            'public'  => $public,
            'static'  => $public.'static/',
            'assets'  => $public.'assets/',
            'cache'   => $public.'cache/',
            'images'  => $public.'uploads/',
            'upload'  => $public.'uploads/',
            'media'   => $public.'uploads/media/',
            'users'   => $public.'uploads/users/',
            'themes'  => $public.'themes/',
            'email'   => $public.'email/',
        ];

        foreach ($locations as $key => $value) {
            $app['location.'.$key] = $value;
        }

        $app['config']->set(['location' => $locations]);

        return $locations;
    }

    protected function setUpUrl(Container $app)
    {
        if (env('HTTPS') == 'on'
            || env('HTTPS') == '1'
            || env('SERVER_PORT') == 443
            || env('HTTP_X_FORWARDED_PORT') == 443) {
            $app['config']->set('app.ssl', true);
        }

        $ssl = $app['config']->get('app.ssl');
        $url = $app['config']->get('app.url');

        if (empty($url) && !defined('_URL')) {
            if ($app->isConsole()) {
                $url = $app['config']->get('app.cliurl');
            } else {
                $url = 'http://'.env('HTTP_HOST');
                $url = rtrim($url, '/').'/';
            }
        }

        $url = rtrim($url, '/').'/';

        if ($ssl) {
            $url = str_replace('http://', 'https://', $url);
        }

        $app['config']->set('app.url', $url);
        defined('_URL') or define('_URL', $url);
    }

    protected function setUpConstants(Container $app)
    {
        define('_SITENAME', $app['config']->get('app.name'));
        define('_ADMIN_MAIL', $app['config']->get('app.email'));

        /*========================================================
                        SOME GLOBAL DEFINATIONS
        /*********************************************************/
        $name = strtolower(rtrim($app->getNameSpace(), '\\'));

        define('SYSTEM', APP.$name.DS);
        defined('PUBLICD') or define('PUBLICD', APP.'public'.DS);
        defined('UPLOAD') or define('UPLOAD', PUBLICD.'uploads'.DS);
        defined('IMAGES') or define('IMAGES', UPLOAD);
        defined('MEDIA') or define('MEDIA', UPLOAD.'media'.DS);
        defined('STATICD') or define('STATICD', PUBLICD.'static/');
        defined('STORAGE') or define('STORAGE', APP.'storage'.DS);
        defined('TMP') or define('TMP', STORAGE.'tmp'.DS);
        defined('CACHE') or define('CACHE', STORAGE.'cache'.DS);
        defined('LOGS') or define('LOGS', STORAGE.'logs'.DS);
        defined('THEMES') or define('THEMES', PUBLICD.'themes'.DS);

        /*========================================================
                        COOKIE SETTINGS
        /*********************************************************/
        define('COOKIE_SUFX', md5($app['config']->get('session.options.cookie_domain')));
        define('COOKIE_PATH', preg_replace('|https?://[^/]+|i', '', _URL));
        define('COOKIE_NAME', 'NAME_'.COOKIE_SUFX);
        define('COOKIE_KEY', 'KEY_'.COOKIE_SUFX);
        define('COOKIE_UID', 'UID_'.COOKIE_SUFX);
        define('COOKIE_TIME', 864000);        //    10 days : 60(sec)*60(min)*24(hrs)*10(days)
    }
}
