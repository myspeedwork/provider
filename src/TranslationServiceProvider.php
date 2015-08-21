<?php

namespace Speedwork\Core\Provider;

use Speedwork\Core\Container;
use Speedwork\Core\ServiceProvider;
use Symfony\Component\HttpKernel\EventListener\TranslatorListener;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Translator;

/**
 * Symfony Translation component Provider.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TranslationServiceProvider implements ServiceProvider
{
    public function register(Container $app)
    {
        $app['translator'] = function ($app) {
            if (!isset($app['locale'])) {
                throw new \LogicException('You must define \'locale\' parameter or register the LocaleServiceProvider to use the TranslationServiceProvider');
            }

            $translator = new Translator($app['locale'], $app['translator.message_selector'], $app['translator.cache_dir'], $app['debug']);
            $translator->setFallbackLocales($app['locale_fallbacks']);
            $translator->addLoader('array', new ArrayLoader());
            $translator->addLoader('xliff', new XliffFileLoader());

            // Register default resources
            foreach ($app['translator.resources'] as $resource) {
                $translator->addResource($resource[0], $resource[1], $resource[2], $resource[3]);
            }

            foreach ($app['translator.domains'] as $domain => $data) {
                foreach ($data as $locale => $messages) {
                    $translator->addResource('array', $messages, $locale, $domain);
                }
            }

            return $translator;
        };

        $app['translator.listener'] = function ($app) {
            return new TranslatorListener($app['translator'], $app['request_stack']);
        };

        $app['translator.message_selector'] = function () {
            return new MessageSelector();
        };

        $app['translator.resources'] = $app->protect(function ($app) {
            return [];
        });

        $app['translator.domains']   = [];
        $app['locale_fallbacks']     = ['en'];
        $app['translator.cache_dir'] = null;
    }
}
