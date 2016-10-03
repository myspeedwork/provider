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
            if (is_numeric($timezone)) {
                $offset   = explode('.', $timezone);
                $offset   = $offset[0].'.'.round(($offset[1] / 60) * 100);
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
        $locations = [
            'baseurl' => _URL,
            'siteurl' => _URL,
            'url'     => _URL,
            'public'  => _URL.'public/',
            'static'  => _URL.'public/static/',
            'assets'  => _URL.'public/assets/',
            'cache'   => _URL.'public/cache/',
            'images'  => _URL.'public/uploads/',
            'upload'  => _URL.'public/uploads/',
            'media'   => _URL.'public/uploads/media/',
            'users'   => _URL.'public/uploads/users/',
            'themes'  => _URL.'public/themes/',
            'email'   => _URL.'public/email/',
        ];

        foreach ($locations as $key => $value) {
            $app['location.'.$key] = $value;
        }

        $app['config']->set(['location' => $locations]);

        return $locations;
    }

    protected function setUpUrl(Container $app)
    {
        $ssl = false;

        if (env('HTTPS') == 'on'
            || env('HTTPS') == '1'
            || env('SERVER_PORT') == 443
            || env('HTTP_X_FORWARDED_PORT') == 443) {
            $ssl = true;
        }

        $app['config']->set('app.ssl', $ssl);

        $base = '/';
        $url  = $app['config']->get('app.url');
        if (empty($url) && !defined('_URL')) {
            if (substr(PHP_SAPI, 0, 3) == 'cli') {
                $url = $app['config']->get('app.cliurl');
            } else {
                $url  = 'http://'.env('HTTP_HOST');
                $url  = rtrim($url, '/').'/';
                $base = str_replace(
                    str_replace('\\', '/', env('DOCUMENT_ROOT')), '',
                    str_replace('\\', '/', APP)
                );
                $url .= $base;
            }
        }

        $url = rtrim($url, '/').'/';

        if ($ssl) {
            $url = str_replace('http://', 'https://', $url);
        }

        defined('_URL') or define('_URL', $url);
    }

    protected function setUpConstants(Container $app)
    {
        define('_SITENAME', $app['config']->get('app.name'));
        define('_ADMIN_MAIL', $app['config']->get('app.email'));

        /*========================================================
                        SOME GLOBAL DEFINATIONS
        /*********************************************************/
        defined('_PUBLIC') or define('_PUBLIC', _URL.'public/');
        defined('_STATIC') or define('_STATIC', _PUBLIC.'static/');
        defined('_UPLOAD') or define('_UPLOAD', _PUBLIC.'uploads/');
        defined('_IMAGES') or define('_IMAGES', _UPLOAD);
        defined('_MEDIA') or define('_MEDIA', _UPLOAD.'/media/');
        defined('_THEMES') or define('_THEMES', _PUBLIC.'themes/');

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
        define('COOKIE_SUFX', md5(config('session.cookie_domain')));
        define('COOKIE_PATH', preg_replace('|https?://[^/]+|i', '', _URL));
        define('COOKIE_NAME', 'NAME_'.COOKIE_SUFX);
        define('COOKIE_KEY', 'KEY_'.COOKIE_SUFX);
        define('COOKIE_UID', 'UID_'.COOKIE_SUFX);
        define('COOKIE_TIME', 864000);        //    10 days : 60(sec)*60(min)*24(hrs)*10(days)
    }
}
