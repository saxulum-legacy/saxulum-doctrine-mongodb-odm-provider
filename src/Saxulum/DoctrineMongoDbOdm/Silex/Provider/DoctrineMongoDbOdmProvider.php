<?php

namespace Saxulum\DoctrineMongoDbOdm\Silex\Provider;

use Saxulum\DoctrineMongoDbOdm\Provider\DoctrineMongoDbOdmProvider as BaseDoctrineMongoDbOdmProvider;
use Silex\Application;
use Silex\ServiceProviderInterface;

class DoctrineMongoDbOdmProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function register(Application $app)
    {
        $pimpleServiceProvider = new BaseDoctrineMongoDbOdmProvider;
        $pimpleServiceProvider->register($app);
    }
}
