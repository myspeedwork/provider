<?php

namespace Speedwork\Provider;

use Speedwork\Core\Container;
use Speedwork\Core\ServiceProvider;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Symfony Serializer component Provider.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Marijn Huizendveld <marijn@pink-tie.com>
 */
class SerializerServiceProvider implements ServiceProvider
{
    /**
     * {@inheritdoc}
     *
     * This method registers a serializer service. {@link http://api.symfony.com/master/Symfony/Component/Serializer/Serializer.html
     * The service is provided by the Symfony Serializer component}.
     */
    public function register(Container $app)
    {
        $app['serializer'] = function ($app) {
            return new Serializer($app['serializer.normalizers'], $app['serializer.encoders']);
        };

        $app['serializer.encoders'] = function () {
            return [new JsonEncoder(), new XmlEncoder()];
        };

        $app['serializer.normalizers'] = function () {
            return [new CustomNormalizer(), new GetSetMethodNormalizer()];
        };
    }
}
