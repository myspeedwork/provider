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
use Speedwork\Core\Acl;
use Speedwork\Core\Resolver;
use Speedwork\Database\DatabaseServiceProvider;
use Speedwork\Filesystem\FilesystemServiceProvider;
use Speedwork\View\Template;
use Speedwork\View\ViewServiceProvider;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class SpeedworkServiceProvider extends ServiceProvider
{
    public function register(Container $app)
    {
        $this->loadConfiguration($app);
        $this->setTimeZone($app['config']->get('app.timezone'));
        $this->setUpUrl($app);
        $this->setUpConstants($app);
        $this->setUpPaths($app);
        $this->setUpLocations($app);

        $app->register(new FilesystemServiceProvider());
        $app->register(new DatabaseServiceProvider());

        $app->set('resolver', new Resolver());
        $app->get('resolver')->setContainer($app);
        $app->get('resolver')->setSystem($app['config']->get('app.apps'));

        $app['acl'] = function ($app) {
            $acl = new Acl();
            $acl->setContainer($app);

            return $acl;
        };

        if (!$app->get('is_api_request') && !$app->isConsole()) {
            $app->register(new SessionServiceProvider(), $app['config']->get('session'));
            $this->registerNonApi($app);
        }

        if ($app->isConsole()) {
            return true;
        }

        $app['template'] = function ($app) {
            $template = new Template();
            $template->setContainer($app);
            $template->beforeRender();

            return $template;
        };

        $app['theme'] = function ($app) {
            return $app['template'];
        };

        $app_name = $app['config']->get('app.app_name', 'app');
        $app->get('resolver')->loadAppController($app_name);
    }

    protected function loadConfiguration(Container $app)
    {
        $app->register(new ConfigServiceProvider(), [
            'config.paths' => [
                APP.'config'.DS,
            ],
        ]);

        $loadedFromCache = false;
        // First we will see if we have a cache configuration file. If we do, we'll load
        // the configuration items from that file so that it is very quick. Otherwise
        // we will need to spin through every configuration file and load them all.
        if (file_exists($cached = APP.'storage/cache/config.php')) {
            $items = include $cached;

            $loadedFromCache = true;
        }

        if ($loadedFromCache) {
            $app['config']->set($items);

            return true;
        }

        if (file_exists(APP.'.env')) {
            $app['config.loader']->load(APP.'.env');
        }

        $app['config.loader']->load([APP.'config'.DS], true);
    }

    protected function registerNonApi(Container $app)
    {
        $app['resolver']->helper('router')->index();

        $data = $_REQUEST;

        $option = $data['option'] ? strtolower(trim($data['option'])) : trim($data['method']);
        $task   = $data['_task'] ? trim($data['_task']) : trim($data['task']);
        $type   = $data['_type'] ? strtolower(trim($data['_layout'])) : strtolower(trim($data['type']));
        $format = $data['_format'] ? strtolower(trim($data['_format'])) : strtolower(trim($data['format']));
        $layout = $data['_layout'] ? strtolower(trim($data['_layout'])) : strtolower(trim($data['layout']));

        list($option, $view) = explode('.', $option);

        $view          = $view ?: trim($data['view']);
        $app['option'] = strtolower($option);
        $app['view']   = strtolower($view);

        $this->registerView($app);

        //Generate a key for every session
        $token = $app['session']->get('token');
        if (!$token) {
            $token = md5(uniqid());
            $app['session']->set('token', $token);
        }

        $is_logged_in = $app['acl']->isUserLoggedIn();
        $app['view.engine']->assign('flash', $app['session']->getFlashBag()->get('flash'));

        $variables = [
            'is_api_request'    => false,
            'option'            => $option,
            'view'              => $view,
            'task'              => $task,
            'format'            => $format,
            'type'              => $type,
            'layout'            => $layout,
            'sitename'          => _SITENAME,
            'token'             => $token,
            'is_user_logged_in' => $is_logged_in,
        ];

        $locations = $app['config']->get('location');

        $this->set($app, $variables);
        $this->set($app, $locations);
    }

    protected function set(Container $app, $values = [])
    {
        foreach ($values as $key => $value) {
            $app[$key] = $value;
            $app['view.engine']->assign($key, $value);
        }
    }

    protected function registerView(Container $app)
    {
        $app->register(new ViewServiceProvider());

        $this->setUpTheme($app);
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

    protected function setUpTheme(Container $app)
    {
        $device = $app['config']->get('app.device');

        if (empty($device)) {
            $device = $app['resolver']->helper('device')->get();
            $app['config']->set('app.device', $device);
        }

        $deviceType = $device['type'];
        $themes     = $app['config']->get('view.themes');

        $user   = $app['user'];
        $themes = $this->userLevelTheme($user, $themes);

        if (!isset($themes[$deviceType])) {
            $deviceType = 'computer';
        }
        $theme  = $themes[$deviceType];
        $option = $app['option'];
        $view   = $app['view'];

        $matches = [
            $option.':'.$view,
            $option.':'.$view.':*',
            $option.':*',
            'default',
        ];

        $template = null;
        foreach ($matches as $match) {
            if ($theme[$match]) {
                $template = $theme[$match];
                break;
            }
        }

        if (empty($template)) {
            $theme = $themes['default'];
            if (is_array($theme)) {
                foreach ($matches as $match) {
                    if ($theme[$match]) {
                        $template = $theme[$match];
                        break;
                    }
                }
            } else {
                $template = $theme;
            }
        }

        list($theme, $theme_id, $theme_view) = explode(':', $template);

        defined('_THEMEVIEW') or define('_THEMEVIEW', $theme_view);
        defined('_THEME') or define('_THEME', _THEMES.$theme.'/');
        defined('THEME') or define('THEME', THEMES.$theme.DS);

        $app['config']->set('view.theme.name', $theme);
        $app['config']->set('view.theme.id', $theme_id);
        $app['config']->set('view.theme.view', $theme_view);

        $app['config']->set('path.themebase', THEME);
        $app['path.themesbase'] = THEME;

        $app['config']->set('location.themebase', _THEME);
        $app['location.themesbase'] = _THEME;
        $app['themebase']           = _THEME;
    }

    protected function userLevelTheme($user, $themes = [])
    {
        // User level theme support
        $meta = $user['meta'];

        if ($meta && !is_array($meta)) {
            $meta = json_decode($meta, true);
            if (is_array($meta) && is_array($meta['theme'])) {
                $themes = array_replace_recursive($themes, $meta['theme']);
            }
        }

        return $themes;
    }

    protected function setUpPaths(Container $app)
    {
        $paths = [
            'themesbase' => THEMES,
            'config'     => APP.'config/',
            'static'     => STATICD,
            'images'     => UPLOAD,
            'upload'     => UPLOAD,
            'public'     => PUBLICD,
            'media'      => MEDIA,
            'base'       => APP,
            'storage'    => STORAGE,
            'tmp'        => TMP,
            'cache'      => CACHE,
            'logs'       => LOGS,
            'log'        => LOGS,
            'lang'       => STORAGE.'/lang/',
        ];

        $app['config']->set('path', $paths);

        foreach ($paths as $key => $value) {
            $app['path.'.$key] = $value;
        }

        return $paths;
    }

    protected function setUpLocations(Container $app)
    {
        $locations = [
            'baseurl'  => _URL,
            'siteurl'  => _URL,
            'url'      => _URL,
            'static'   => _STATIC,
            'imageurl' => _UPLOAD,
            'images'   => _UPLOAD,
            'public'   => _PUBLIC,
        ];

        $app['config']->set('location', $locations);

        foreach ($locations as $key => $value) {
            $app['location.'.$key] = $value;
        }

        return $locations;
    }

    protected function setUpUrl(Container $app)
    {
        $ssl = false;

        if (env('HTTPS') == 'on' || env('HTTPS') == '1' || env('SERVER_PORT') == 443) {
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
        $sys_url = $app['config']->get('app.system_url', _URL.'speedwork/');

        define('_SYS', SYS.'system'.DS);
        define('_SYSURL', $sys_url.'system/');
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
        define('_SYSTEM', _URL.'system/');
        defined('_THEMES') or define('_THEMES', _PUBLIC.'themes/');

        define('SYSTEM', APP.'system'.DS);
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
