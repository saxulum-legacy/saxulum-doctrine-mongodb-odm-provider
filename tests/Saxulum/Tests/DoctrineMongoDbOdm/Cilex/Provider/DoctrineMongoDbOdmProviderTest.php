<?php

namespace Saxulum\Tests\DoctrineMongoDbOdm\Cilex\Provider;

use Doctrine\ODM\MongoDB\DocumentRepository;
use Saxulum\DoctrineMongoDb\Cilex\Provider\DoctrineMongoDbProvider;
use Saxulum\DoctrineMongoDbOdm\Cilex\Provider\DoctrineMongoDbOdmProvider;
use Saxulum\Tests\DoctrineMongoDbOdm\Document\Page;
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

    public function testAnnotationMapping()
    {
        if (!extension_loaded('mongo')) {
            $this->markTestSkipped('mongo is not available');
        }

        $proxyPath = __DIR__ . '/../../../../../../cache/doctrine/proxies';
        $hydratorPath = __DIR__ . '/../../../../../../cache/doctrine/hydrator';

        @mkdir($proxyPath, 0777, true);
        @mkdir($hydratorPath, 0777, true);

        $app = new Application('test');

        $app->register(new DoctrineMongoDbProvider, array(
            'mongodb.options' => array(
                'server' => 'mongodb://localhost:27017'
            )
        ));

        $app->register(new DoctrineMongoDbOdmProvider, array(
            "mongodbodm.proxies_dir" => $proxyPath,
            "mongodbodm.hydrator_dir" => $hydratorPath,
            "mongodbodm.dm.options" => array(
                "database" => "test",
                "mappings" => array(
                    array(
                        "type" => "annotation",
                        "namespace" => "Saxulum\\Tests\\DoctrineMongoDbOdm\\Document",
                        "path" => __DIR__."../../Document",
                        "use_simple_annotation_reader" => false
                    )
                ),
            ),
        ));

        $title = 'title';
        $body = 'body';

        $page = new Page();
        $page->setTitle($title);
        $page->setBody($body);

        $app['mongodbodm.dm']->persist($page);
        $app['mongodbodm.dm']->flush();

        $repository = $app['mongodbodm.dm']
            ->getRepository("Saxulum\\Tests\\DoctrineMongoDbOdm\\Document\\Page")
        ;
        /** @var DocumentRepository $repository */

        $pageFromDb = $repository->findOneBy(array(), array('id' => 'DESC'));
        /** @var Page $pageFromDb */

        $this->assertEquals($title, $pageFromDb->getTitle());
        $this->assertEquals($body, $pageFromDb->getBody());
    }
}
