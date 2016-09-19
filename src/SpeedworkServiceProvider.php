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

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class SpeedworkServiceProvider extends ServiceProvider
{
    public function register(Container $app)
    {
        $app->set('resolver', new Resolver());
        $app->get('resolver')->setContainer($app);
        $app->get('resolver')->setSystem($app['config']->get('app.apps'));

        $app['acl'] = function ($app) {
            $acl = new Acl();
            $acl->setContainer($app);

            return $acl;
        };

        if ($app->isConsole()) {
            return true;
        }

        if (!$app->get('is_api_request')) {
            $app->register(new SessionServiceProvider(), $app['config']->get('session'));
            $this->registerNonApi($app);
        }

        $app['template'] = function ($app) {
            $template = new Template();
            $template->setContainer($app);
            $template->setDefaults();

            return $template;
        };

        $app['theme'] = function ($app) {
            return $app['template'];
        };

        $app_name = $app['config']->get('app.app_name', 'app');
        $app->get('resolver')->loadAppController($app_name);
    }

    protected function registerNonApi(Container $app)
    {
        $app['resolver']->helper('router')->index();

        $data = $_REQUEST;

        $task   = $data['_task'] ? trim($data['_task']) : trim($data['task']);
        $type   = $data['_type'] ? strtolower(trim($data['_layout'])) : strtolower(trim($data['type']));
        $format = $data['_format'] ? strtolower(trim($data['_format'])) : strtolower(trim($data['format']));
        $layout = $data['_layout'] ? strtolower(trim($data['_layout'])) : strtolower(trim($data['layout']));

        $option = $data['option'] ? strtolower(trim($data['option'])) : trim($data['method']);

        list($option, $view) = explode('.', $option);

        $view          = $view ?: trim($data['view']);
        $app['option'] = strtolower($option);
        $app['view']   = strtolower($view);
        $app['route']  = trim($option.'.'.$view);

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
}
