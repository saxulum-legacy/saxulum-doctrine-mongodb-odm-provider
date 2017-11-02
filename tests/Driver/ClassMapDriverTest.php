<?php

namespace Saxulum\Tests\DoctrineMongoDbOdm\Driver;

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Saxulum\DoctrineMongoDbOdm\Driver\ClassMapDriver;
use Saxulum\Tests\DoctrineMongoDbOdm\Document\Page;
use Saxulum\Tests\DoctrineMongoDbOdm\Mapping\PageMapping;

class ClassMapDriverTest extends TestCase
{
    public function testDriverLoadsMappingClassAndEnrichesClassMetadataObjectWithMapping()
    {
        /** @var ClassMetadata|\PHPUnit_Framework_MockObject_MockObject $classMetadata */
        $classMetadata = $this->getMockBuilder(ClassMetadata::class)
            ->disableOriginalConstructor()
            ->setMethods(['setCollection', 'mapField'])
            ->getMock();

        $classMetadata
            ->expects(self::once())
            ->method('setCollection')
            ->with('page');

        $classMetadata
            ->expects(self::at(1))
            ->method('mapField')
            ->with([
                'fieldName' => 'id',
                'type' => 'string',
                'id' => true,
            ]);

        $classMetadata
            ->expects(self::at(2))
            ->method('mapField')
            ->with([
                'fieldName' => 'title',
                'type' => 'string',
            ]);

        $classMetadata
            ->expects(self::at(3))
            ->method('mapField')
            ->with([
                'fieldName' => 'body',
                'type' => 'string',
            ]);

        $driver = new ClassMapDriver($this->getClassMap());

        $driver->loadMetadataForClass(Page::class, $classMetadata);
    }

    public function testGetAllClassNamesReturnsTheClassMap()
    {
        $classMap = $this->getClassMap();

        $driver = new ClassMapDriver($classMap);

        self::assertEquals(array_keys($classMap), $driver->getAllClassNames());
    }

    private function getClassMap()
    {
        return [Page::class => PageMapping::class];
    }
}
