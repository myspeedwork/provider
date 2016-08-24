<?php

/**
 * This file is part of the Speedwork framework.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */
namespace Speedwork\Provider;

use Speedwork\Container\Container;
use Speedwork\Container\ServiceProvider;
use Speedwork\Core\Acl;
use Speedwork\Core\Registry;
use Speedwork\Core\Resolver;
use Speedwork\View\Template;
use Speedwork\View\ViewServiceProvider;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class SpeedworkServiceProvider extends ServiceProvider
{
    public function register(Container $di)
    {
        $this->registerConfig($di);

        $di->register(new \Speedwork\Filesystem\FilesystemServiceProvider());
        $di->register(new \Speedwork\Provider\SessionServiceProvider(), $di['config']->get('session'));
        $di->register(new \Speedwork\Database\DatabaseServiceProvider());

        $di->set('resolver', new Resolver());
        $di->get('resolver')->setContainer($di);
        $di->get('resolver')->setSystem($di['config']->get('app.core_apps'));

        $di['acl'] = function ($di) {
            $acl = new Acl();
            $acl->setContainer($di);

            return $acl;
        };

        $this->registerNonApi($di);

        $di['template'] = function ($di) {
            $template = new Template();
            $template->setContainer($di);
            $template->beforeRender();

            return $template;
        };

        //load resolver specific controller
        $di->get('resolver')->loadAppController(_APP_NAME);
    }

    protected function registerConfig(Container $di)
    {
        $di->register(new \Speedwork\Config\ConfigServiceProvider(), [
            'config.paths' => [
                APP.'system'.DS.'config'.DS,
            ],
        ]);

        if (file_exists(APP.'.env')) {
            $di['config.loader']->load(APP.'.env');
        }

        $di['config.loader']->load([
            APP.'system'.DS.'config'.DS,
        ], true);

        require SYS.'system'.DS.'config'.DS.'constants.php';
    }

    protected function registerNonApi(Container $di)
    {
        $is_api_request = $di->get('is_api_request');

        if (!$is_api_request) {
            $di->get('resolver')->helper('router')->index();
        }

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

        $option = explode('.', $option);
        $view   = $option[1] ?: trim($data['view']);
        $option = $option[0];

        $di['option'] = strtolower($option);
        $di['view']   = strtolower($view);

        $this->registerView($di);

        /*======================================================
        // DEFAULT VARABLES
        /=======================================================*/
        $variables = [
            'themeimages'    => _TMP_IMG,
            'themecss'       => _TMP_CSS,
            'themebase'      => _TMP_URL,
            'baseurl'        => _URL,
            'siteurl'        => _URL,
            'static'         => _STATIC,
            'imageurl'       => _UPLOAD,
            'images'         => _UPLOAD,
            'sitename'       => _SITENAME,
            'public'         => _PUBLIC,
            'is_api_request' => $is_api_request,
            'option'         => $option,
            'view'           => $view,
            'task'           => $task,
            'format'         => $format,
            'type'           => $type,
            'tpl'            => $tpl,
        ];

        if (!$is_api_request) {
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

            $di['engine']->assign('flash', $di['session']->getFlashBag()->get('flash'));
        }

        $this->set($di, $variables);

        $di['engine']->assign('config', $di['config']->all());
    }

    protected function set(Container $di, $values = [])
    {
        foreach ($values as $key => $value) {
            $di[$key] = $value;
            $di['engine']->assign($key, $value);
            $di['config']->set($key, $value);
            Registry::set($key, $value);
        }
    }

    protected function registerView(Container $di)
    {
        $di['smarty'] = function () use ($di) {
            return $di->get('resolver')->helper('smarty')->init();
        };

        $di['twig'] = function () use ($di) {
            return $di->get('resolver')->helper('twig')->init();
        };

        $di['mustache'] = function () use ($di) {
            return $di->get('resolver')->helper('mustache')->init();
        };

        $di['plates'] = function () use ($di) {
            return $di->get('resolver')->helper('plates')->init();
        };

        $di->register(new ViewServiceProvider());

        require SYS.'system'.DS.'config'.DS.'theme.php';
    }
}
