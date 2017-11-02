<?php

namespace Saxulum\DoctrineMongoDbOdm\Driver;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata as OdmClassMetadata;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\ODM\MongoDB\Mapping\MappingException;

class ClassMapDriver implements MappingDriver
{
    /**
     * @var array|string[]
     */
    private $classMap;

    /**
     * @param array|string[] $classMap
     */
    public function __construct(array $classMap)
    {
        $this->classMap = $classMap;
    }

    /**
     * Loads the metadata for the specified class into the provided container.
     *
     * @param string        $className
     * @param ClassMetadata $metadata
     * @throws MappingException
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata)
    {
        if (false === $metadata instanceof OdmClassMetadata) {
            throw new MappingException(
                sprintf('Metadata is of class "%s" instead of "%s"', get_class($metadata), OdmClassMetadata::class)
            );
        }

        if (false === isset($this->classMap[$className])) {
            throw new MappingException(
                sprintf('No configured mapping for document "%s"', $className)
            );
        }

        $mappingClassName = $this->classMap[$className];

        if (false === ($mapping = new $mappingClassName()) instanceof OdmMappingInterface) {
            throw new MappingException('Class %s does not implement the OdmMappingInterface');
        }

        /* @var OdmMappingInterface $mapping */
        /* @var OdmClassMetadata $metadata */

        $mapping->configureMapping($metadata);
    }

    /**
     * Gets the names of all mapped classes known to this driver.
     *
     * @return array the names of all mapped classes known to this driver
     */
    public function getAllClassNames()
    {
        return array_keys($this->classMap);
    }

    /**
     * Returns whether the class with the specified name should have its metadata loaded.
     * This is only the case if it is either mapped as an Entity or a MappedSuperclass.
     *
     * @param string $className
     *
     * @return bool
     */
    public function isTransient($className)
    {
        if (isset($this->classMap[$className])) {
            return false;
        }

        return true;
    }
}
