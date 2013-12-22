<?php

namespace Saxulum\DoctrineMongoDbOdm\Cilex\Provider;

use Saxulum\DoctrineMongoDbOdm\Provider\DoctrineMongoDbOdmProvider as BaseDoctrineMongoDbOdmProvider;
use Cilex\Application;
use Cilex\ServiceProviderInterface;

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
