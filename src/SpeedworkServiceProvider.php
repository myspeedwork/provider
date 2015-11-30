<?php

/**
 * This file is part of the Speedwork framework.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Provider;

use Cake\Core\Configure\Engine\PhpConfig;
use Speedwork\Config\Configure;
use Speedwork\Config\Engine\DbConfig;
use Speedwork\Container\Container;
use Speedwork\Container\ServiceProvider;
use Speedwork\Core\Acl;
use Speedwork\Core\Registry;
use Speedwork\Core\Resolver;
use Speedwork\Database\Database;
use Speedwork\View\Template;
use Speedwork\View\ViewServiceProvider;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class SpeedworkServiceProvider extends ServiceProvider
{
    public function register(Container $di)
    {
        $di->set('resolver', new Resolver());
        $di->get('resolver')->setContainer($di);

        $is_api_request = $di->get('is_api_request');

        Configure::config('system', new PhpConfig(SYS.'system'.DS.'config'.DS));
        Configure::config('default', new PhpConfig(APP.'system'.DS.'config'.DS));
        Configure::config('initial', new PhpConfig(APP));

        Configure::load('config', 'system');
        Configure::load('config', 'initial');

        $di['database'] = function ($di) {
            $database = new Database();

            $datasource = Configure::read('database.config');
            $datasource = ($datasource) ? $datasource : 'default';
            $config     = Configure::read('database.'.$datasource);

            $connection = $database->connect($config);
            if (!$connection) {
                if (php_sapi_name() == 'cli' || $is_api_request) {
                    echo json_encode([
                        'status'  => 'ERROR',
                        'message' => 'database was gone away',
                        'error'   => $database->lastError(),
                    ]);
                    die;
                } else {
                    $path = SYS.'public'.DS.'templates'.DS.'system'.DS.'databasegone.tpl';
                    echo file_get_contents($path);
                    die('<!-- Database was gone away... -->');
                }
            }

            $database->setContainer($di);

            register_shutdown_function(function () use ($database) {
                $database->disConnect();
            });

            return $database;
        };

        //load white label helper
        if (!$is_api_request && Configure::read('white_label')) {
            $di->get('resolver')->helper('whitelabel')->run();
        }

        //load app configuration
        Configure::load('config');
        if (!$is_api_request) {
            Configure::config('db', new DbConfig($di['database']));
            Configure::load('database', 'db');
        }

        require _SYS_DIR.'system'.DS.'config'.DS.'core.php';

        $di['session.storage.options'] = Configure::read('session');

        $di['acl'] = function ($di) {
            $acl = new Acl();
            $acl->setContainer($di);

            return $acl;
        };

        $di->get('resolver')->setSystem(Configure::read('system_core_apps'));

        if (!$is_api_request) {
            //load shortUrl helper
            $di->get('resolver')->helper('router')->index();

            $option = trim($_REQUEST['option']);
            $task   = trim($_REQUEST['task']);
            $format = strtolower(trim($_REQUEST['_format']));
            $type   = strtolower(trim($_REQUEST['_type']));
            $tpl    = strtolower(trim($_REQUEST['_tpl']));

            $type   = ($type) ? $type : strtolower(trim($_REQUEST['type']));
            $format = ($format) ? $format : strtolower(trim($_REQUEST['format']));
            $tpl    = ($tpl) ? $tpl : strtolower(trim($_REQUEST['tpl']));

            $option = explode('.', $option);
            $view   = $option[1];
            $option = $option[0];

            if (empty($view)) {
                $view = trim($_REQUEST['view']);
            }

            Registry::set('option', $option);
            Registry::set('view', $view);

            $di['smarty'] = function () use ($di) {
                return $di->get('resolver')->helper('smarty')->init();
            };

            $di->register(new ViewServiceProvider());

            require _SYS_DIR.'system'.DS.'config'.DS.'theme.php';

            $di['is_user_logged_in'] = $di['acl']->isUserLoggedIn() ? true : false;

            $token = $di['session']->get('token');
            //Generate a key for every session
            if (!$di['is_ajax_request'] && !$token) {
                $token = md5(uniqid());
                $di['session']->set('token', $token);
            }

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
                'is_cli_request' => IS_CLI_REQUEST,
                'ip'             => ip(),
                'option'         => $option,
                'view'           => $view,
                'task'           => $task,
                'format'         => $format,
                'type'           => $type,
                'tpl'            => $tpl,
            ];

            $variables['is_user_logged_in'] = $di->get('is_user_logged_in');
            $variables['token']             = $token;

            foreach ($variables as $key => $value) {
                $di->set($key, $value);
                $di['engine']->assign($key, $value);
                Configure::write($key, $value);
                Registry::set($key, $value);
            }

            $di['engine']->assign('config', Configure::read());
        }

        $di['template'] = function ($di) {
            $template = new Template();
            $template->setContainer($di);
            $template->beforeRender();

            return $template;
        };

        //load resolver specific controller
        $di->get('resolver')->loadAppController(_APP_NAME);
    }
}
