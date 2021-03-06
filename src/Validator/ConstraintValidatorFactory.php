<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Provider\Validator;

use Speedwork\Container\Container;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactory as BaseConstraintValidatorFactory;

/**
 * Uses a service container to create constraint validators with dependencies.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 * @author Alex Kalyvitis <alex.kalyvitis@gmail.com>
 */
class ConstraintValidatorFactory extends BaseConstraintValidatorFactory
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var array
     */
    protected $serviceNames;

    /**
     * Constructor.
     *
     * @param Container $container    DI container
     * @param array     $serviceNames Validator service names
     */
    public function __construct(Container $container, array $serviceNames = [], $accessor = null)
    {
        parent::__construct($accessor);

        $this->container    = $container;
        $this->serviceNames = $serviceNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getInstance(Constraint $constraint)
    {
        $name = $constraint->validatedBy();

        if (isset($this->serviceNames[$name])) {
            return $this->container[$this->serviceNames[$name]];
        }

        return parent::getInstance($constraint);
    }
}
