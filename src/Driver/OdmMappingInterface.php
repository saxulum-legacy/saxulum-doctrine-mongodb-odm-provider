<?php

namespace Saxulum\DoctrineMongoDbOdm\Driver;

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;

interface OdmMappingInterface
{

    /**
     * @param ClassMetadata $metadata
     * @return void
     */
    public function configureMapping(ClassMetadata $metadata);
}
