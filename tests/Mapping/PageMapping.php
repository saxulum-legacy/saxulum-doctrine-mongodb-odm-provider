<?php

namespace Saxulum\Tests\DoctrineMongoDbOdm\Mapping;

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Saxulum\DoctrineMongoDbOdm\Driver\OdmMappingInterface;

class PageMapping implements OdmMappingInterface
{
    /**
     * @param ClassMetadata $metadata
     */
    public function configureMapping(ClassMetadata $metadata)
    {
        $metadata->setCollection('page');

        $metadata->mapField([
            'fieldName' => 'id',
            'type' => 'string',
            'id' => true,
        ]);

        $metadata->mapField([
            'fieldName' => 'title',
            'type' => 'string',
        ]);

        $metadata->mapField([
            'fieldName' => 'body',
            'type' => 'string',
        ]);
    }
}
