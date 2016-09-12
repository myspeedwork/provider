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
use Speedwork\Core\Acl;
use Speedwork\Core\Resolver;
use Speedwork\View\Template;
use Speedwork\View\ViewServiceProvider;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class SpeedworkServiceProvider extends ServiceProvider
{
    public function register(Container $di)
    {
        $this->registerConfig($di);
        $this->setTimeZone($di['config']->get('app.timezone'));

        $di['events'] = function () {
            return new EventDispatcher();
        };

        $di->register(new \Speedwork\Filesystem\FilesystemServiceProvider());
        $di->register(new \Speedwork\Provider\SessionServiceProvider(), $di['config']->get('session'));
        $di->register(new \Speedwork\Database\DatabaseServiceProvider());

        $di->set('resolver', new Resolver());
        $di->get('resolver')->setContainer($di);
        $di->get('resolver')->setSystem($di['config']->get('app.apps'));

        $di['acl'] = function ($di) {
            $acl = new Acl();
            $acl->setContainer($di);

            return $acl;
        };

        if (!$di->get('is_api_request')) {
            $this->registerNonApi($di);
        }

        $this->setUpPaths($di);

        $di['template'] = function ($di) {
            $template = new Template();
            $template->setContainer($di);
            $template->beforeRender();

            return $template;
        };

        $di['theme'] = function ($di) {
            return $di['template'];
        };

        $app_name = $di['config']->get('app.app_name', 'app');
        $di->get('resolver')->loadAppController($app_name);
    }

    protected function registerConfig(Container $di)
    {
        $di->register(
            new \Speedwork\Config\ConfigServiceProvider(), [
            'config.paths' => [
                APP.'config'.DS,
            ],
            ]
        );

        if (file_exists(APP.'.env')) {
            $di['config.loader']->load(APP.'.env');
        }

        $di['config.loader']->load(
            [
            APP.'config'.DS,
            ], true
        );

        include SYS.'config'.DS.'constants.php';
    }

    protected function registerNonApi(Container $di)
    {
        $di->get('resolver')->helper('router')->index();

        $data = $_REQUEST;

        $option = strtolower(trim($data['option']));
        $task   = trim($data['_task']);
        $format = strtolower(trim($data['_format']));
        $type   = strtolower(trim($data['_type']));
        $tpl    = strtolower(trim($data['_tpl']));

        $option = $option ?: trim($data['method']);
        $task   = $task ?: trim($data['task']);
        $type   = $type ?: strtolower(trim($data['type']));
        $format = $format ?: strtolower(trim($data['format']));
        $tpl    = $tpl ?: strtolower(trim($data['tpl']));

        list($option, $view) = explode('.', $option);

        $view         = $view ?: trim($data['view']);
        $di['option'] = strtolower($option);
        $di['view']   = strtolower($view);

        $this->registerView($di);

        /*======================================================
        // DEFAULT VARABLES
        /=======================================================*/

        $variables = [
            'is_api_request' => false,
            'option'         => $option,
            'view'           => $view,
            'task'           => $task,
            'format'         => $format,
            'type'           => $type,
            'tpl'            => $tpl,
        ];

        //Generate a key for every session
        $token = $di['session']->get('token');
        if (!$token) {
            $token = md5(uniqid());
            $di['session']->set('token', $token);
        }

        $logged_in = $di['acl']->isUserLoggedIn();

        $di['is_user_logged_in'] = $logged_in;

        $variables['is_user_logged_in'] = $logged_in;
        $variables['token']             = $token;

        $di['view.engine']->assign('flash', $di['session']->getFlashBag()->get('flash'));

        $this->set($di, $variables);

        $locations = [
            'themeimages' => _THEME.'/images/',
            'themecss'    => _THEME.'/css/',
            'themebase'   => _THEME,
            'baseurl'     => _URL,
            'siteurl'     => _URL,
            'static'      => _STATIC,
            'imageurl'    => _UPLOAD,
            'images'      => _UPLOAD,
            'sitename'    => _SITENAME,
            'public'      => _PUBLIC,
        ];

        $di['config']->set('locations', $locations);

        $this->set($di, $locations);
    }

    protected function set(Container $di, $values = [])
    {
        foreach ($values as $key => $value) {
            $di[$key] = $value;
            $di['view.engine']->assign($key, $value);
        }
    }

    protected function registerView(Container $di)
    {
        $di->register(new ViewServiceProvider());

        $this->setUpTheme($di);
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

        $_device = $device['type'];
        $themes  = $app['config']->get('view.themes');
        if (!isset($themes[$_device])) {
            $_device = 'computer';
        }

        $option = $app['option'];
        $view   = $app['view'];

        // User level theme support
        $user = $app['user'];
        $meta = $user['meta'];

        if ($meta && !is_array($meta)) {
            $meta = json_decode($meta, true);

            if (is_array($meta) && is_array($meta['theme'])) {
                $themes = array_replace_recursive($themes, $meta['theme']);
            }
        }

        $theme   = $themes[$_device];
        $matches = [
            $option.':'.$view,
            $option.':'.$view.':*',
            $option.':*',
            'default',
        ];

        foreach ($matches as $match) {
            if ($theme[$match]) {
                $tmplate = $theme[$match];
                break;
            }
        }

        if (empty($tmplate)) {
            $theme = $themes['default'];
            if (is_array($theme)) {
                foreach ($matches as $match) {
                    if ($theme[$match]) {
                        $tmplate = $theme[$match];
                        break;
                    }
                }
            } else {
                $tmplate = $theme;
            }
        }

        $tmplate    = explode(':', $tmplate);
        $theme      = $tmplate[0];
        $theme_id   = $tmplate[1];
        $theme_view = $tmplate[2];

        config('view.theme.name', $theme);
        config('view.theme.id', $theme_id);
        config('view.theme.view', $theme_view);

        defined('_THEMEVIEW') or define('_THEMEVIEW', $theme_view);
        defined('_THEME') or define('_THEME', _THEMES.$theme.'/');
        defined('THEME') or define('THEME', THEMES.$theme.DS);
    }

    protected function setUpPaths(Container $di)
    {
        $paths = [
            'themesbase' => THEMES,
            'themebase'  => THEME,
            'static'     => STATICD,
            'images'     => UPLOAD,
            'upload'     => UPLOAD,
            'public'     => PUBLICD,
            'media'      => MEDIA,
            'base'       => ABSPATH,
            'storage'    => STORAGE,
            'tmp'        => TMP,
            'cache'      => CACHE,
            'logs'       => LOGS,
            'log'        => LOGS,
        ];

        $di['config']->set('paths', $paths);
    }
}
