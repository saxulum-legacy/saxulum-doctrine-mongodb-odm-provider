<?php

namespace Dflydev\Cilex\Provider\DoctrineOrm;

use Saxulum\DoctrineMongoDbOdm\Cilex\Provider\DoctrineMongoDbOdmProvider;
use Cilex\Application;

class DoctrineOrmServiceProviderTest extends \PHPUnit_Framework_TestCase
{
    protected function createMockDefaultAppAndDeps()
    {
        $app = new Application('test');

        $eventManager = $this->getMock('Doctrine\Common\EventManager');
        $connection = $this
            ->getMockBuilder('Doctrine\MongoDB\Connection')
            ->disableOriginalConstructor()
            ->getMock();

        $connection
            ->expects($this->any())
            ->method('getEventManager')
            ->will($this->returnValue($eventManager));

        $app['mongodbs'] = new \Pimple(array(
            'default' => $connection,
        ));

        $app['mongodbs.event_manager'] = new \Pimple(array(
            'default' => $eventManager,
        ));

        return array($app, $connection, $eventManager);;
    }

    protected function createMockDefaultApp()
    {
        list ($app, $connection, $eventManager) = $this->createMockDefaultAppAndDeps();

        return $app;
    }

    /**
     * Test registration
     */
    public function testRegister()
    {
        $app = $this->createMockDefaultApp();

        $app->register(new DoctrineMongoDbOdmProvider);

        $this->assertEquals($app['mongodbodm.dm'], $app['mongodbodm.dms']['default']);
        $this->assertInstanceOf('Doctrine\Common\Cache\ArrayCache', $app['mongodbodm.dm.config']->getMetadataCacheImpl());
        $this->assertInstanceOf('Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain', $app['mongodbodm.dm.config']->getMetadataDriverImpl());
    }
}