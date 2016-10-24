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

use Speedwork\Container\BootableInterface;
use Speedwork\Container\Container;
use Speedwork\Container\ServiceProvider;
use Speedwork\Core\Acl;
use Speedwork\Core\Resolver;
use Speedwork\Core\Router;
use Speedwork\View\Template;
use Speedwork\View\ViewServiceProvider;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class SpeedworkServiceProvider extends ServiceProvider implements BootableInterface
{
    public function register(Container $app)
    {
        $app['resolver'] = function ($app) {
            $resolver = new Resolver();
            $resolver->setContainer($app);

            return $resolver;
        };

        $app['acl'] = function ($app) {
            $acl = new Acl();
            $acl->setContainer($app);

            return $acl;
        };

        $app['template'] = function ($app) {
            $template = new Template();
            $template->setContainer($app);
            $template->setDefaults();

            return $template;
        };

        $app['theme'] = function ($app) {
            return $app['template'];
        };

        if ($app->isConsole()) {
            return true;
        }

        if (!$app->get('is_api_request')) {
            $app->register(new SessionServiceProvider());
        }
    }

    public function boot(Container $app)
    {
        if ($app->isConsole() || $app->isApi()) {
            return true;
        }

        $router = $app['resolver']->helper('router');
        Router::addRewrite($router);
        $values = Router::route();

        $app['request']->addInput($values);

        $this->registerNonApi($app);
    }

    protected function registerNonApi(Container $app)
    {
        $data = $app['request']->input();

        $_task  = $data['_task'] ? trim($data['_task']) : trim($data['task']);
        $type   = $data['_type'] ? strtolower(trim($data['_layout'])) : strtolower(trim($data['type']));
        $format = $data['_format'] ? strtolower(trim($data['_format'])) : strtolower(trim($data['format']));
        $layout = $data['_layout'] ? strtolower(trim($data['_layout'])) : strtolower(trim($data['layout']));
        $option = $data['option'] ? strtolower(trim($data['option'])) : trim($data['method']);

        list($option, $view, $task) = explode('.', $option);

        $task          = $task ?: $_task;
        $view          = $view ?: trim($data['view']);
        $app['option'] = strtolower($option);
        $app['view']   = strtolower($view);
        $app['route']  = trim($option.'.'.$view);
        $app['rule']   = trim($option.'.'.$view.'.'.$task);

        $this->registerView($app);
        $is_logged_in = $app['acl']->isUserLoggedIn();

        $variables = [
            'option'            => $option,
            'view'              => $view,
            'task'              => $task,
            'format'            => $format,
            'type'              => $type,
            'layout'            => $layout,
            'sitename'          => _SITENAME,
            'is_user_logged_in' => $is_logged_in,
        ];

        $this->set($app, $variables);
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

        $app['view.engine']->assign('flash', $app['session']->getFlashBag()->get('flash'));

        $locations = $app['config']->get('location');
        $app['view.engine']->assign('location', $locations);
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

        if (!empty($themes[$deviceType])) {
            $deviceType = 'computer';
        }
        $theme = $themes[$deviceType];

        list($option, $view) = explode('.', $app['route']);

        $matches = [
            $option.':'.$view,
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

        list($theme, $layout, $id) = explode('.', $template);

        defined('_THEME') or define('_THEME', $app['location.themes'].$theme.'/');
        defined('THEME') or define('THEME', $app['path.themes'].$theme.DS);

        $app['config']->set('view.theme.name', $theme);
        $app['config']->set('view.theme.id', $id);
        $app['config']->set('view.theme.layout', $layout);

        $app['config']->set('path.theme', THEME);
        $app['path.theme'] = THEME;

        $app['config']->set('location.theme', _THEME);
        $app['location.theme'] = _THEME;
        $app['view.engine']->assign('theme', _THEME);
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
