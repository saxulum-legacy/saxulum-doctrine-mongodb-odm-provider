<?php

namespace Saxulum\DoctrineMongoDbOdm\Provider;

use Doctrine\Common\Cache\ApcCache;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\CouchbaseCache;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\MemcacheCache;
use Doctrine\Common\Cache\MemcachedCache;
use Doctrine\Common\Cache\RedisCache;
use Doctrine\Common\Cache\XcacheCache;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Common\Persistence\Mapping\Driver\StaticPHPDriver;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ODM\MongoDB\Mapping\Driver\SimplifiedYamlDriver;
use Doctrine\ODM\MongoDB\Mapping\Driver\XmlDriver;
use Doctrine\ODM\MongoDB\Mapping\Driver\YamlDriver;
use Doctrine\ODM\MongoDB\Repository\DefaultRepositoryFactory;
use Doctrine\ODM\MongoDB\Types\Type;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class DoctrineMongoDbOdmProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     */
    public function register(Container $container)
    {
        foreach ($this->getMongodbOdmDefaults($container) as $key => $value) {
            if (!isset($container[$key])) {
                $container[$key] = $value;
            }
        }

        $container['mongodbodm.dm.default_options'] = array(
            'connection' => 'default',
            'database' => null,
            'mappings' => array(),
            'types' => array(),
        );

        $container['mongodbodm.dms.options.initializer'] = $container->protect(function () use ($container) {
            static $initialized = false;

            if ($initialized) {
                return;
            }

            $initialized = true;

            if (!isset($container['mongodbodm.dms.options'])) {
                $container['mongodbodm.dms.options'] = array('default' => isset($container['mongodbodm.dm.options']) ? $container['mongodbodm.dm.options'] : array());
            }

            $tmp = $container['mongodbodm.dms.options'];
            foreach ($tmp as $name => &$options) {
                $options = array_replace($container['mongodbodm.dm.default_options'], $options);

                if (!isset($container['mongodbodm.dms.default'])) {
                    $container['mongodbodm.dms.default'] = $name;
                }
            }
            $container['mongodbodm.dms.options'] = $tmp;
        });

        $container['mongodbodm.dm_name_from_param_key'] = $container->protect(function ($paramKey) use ($container) {
            $container['mongodbodm.dms.options.initializer']();

            if (isset($container[$paramKey])) {
                return $container[$paramKey];
            }

            return $container['mongodbodm.dms.default'];
        });

        $container['mongodbodm.dms'] = function () use ($container) {
            $container['mongodbodm.dms.options.initializer']();

            $dms = new Container();
            foreach ($container['mongodbodm.dms.options'] as $name => $options) {
                if ($container['mongodbodm.dms.default'] === $name) {
                    // we use shortcuts here in case the default has been overridden
                    $config = $container['mongodbodm.dm.config'];
                } else {
                    $config = $container['mongodbodm.dms.config'][$name];
                }

                if (isset($options['database'])) {
                    $config->setDefaultDB($options['database']);
                }

                $dms[$name] = function () use ($container, $options, $config) {
                    return DocumentManager::create(
                        $container['mongodbs'][$options['connection']],
                        $config,
                        $container['mongodbs.event_manager'][$options['connection']]
                    );
                };
            }

            return $dms;
        };

        $container['mongodbodm.dms.config'] = function () use ($container) {
            $container['mongodbodm.dms.options.initializer']();

            $configs = new Container();
            foreach ($container['mongodbodm.dms.options'] as $name => $options) {
                $config = new Configuration();

                $container['mongodbodm.cache.configurer']($name, $config, $options);

                $config->setProxyDir($container['mongodbodm.proxies_dir']);
                $config->setProxyNamespace($container['mongodbodm.proxies_namespace']);
                $config->setAutoGenerateProxyClasses($container['mongodbodm.auto_generate_proxies']);

                $config->setHydratorDir($container['mongodbodm.hydrator_dir']);
                $config->setHydratorNamespace($container['mongodbodm.hydrator_namespace']);
                $config->setAutoGenerateHydratorClasses($container['mongodbodm.auto_generate_hydrators']);

                $config->setClassMetadataFactoryName($container['mongodbodm.class_metadata_factory_name']);
                $config->setDefaultRepositoryClassName($container['mongodbodm.default_repository_class']);

                $config->setRepositoryFactory($container['mongodbodm.repository_factory']);

                $chain = $container['mongodbodm.mapping_driver_chain.locator']($name);
                foreach ((array) $options['mappings'] as $entity) {
                    if (!is_array($entity)) {
                        throw new \InvalidArgumentException(
                            "The 'mongodbodm.dm.options' option 'mappings' should be an array of arrays."
                        );
                    }

                    if (isset($entity['alias'])) {
                        $config->addDocumentNamespace($entity['alias'], $entity['namespace']);
                    }

                    switch ($entity['type']) {
                        case 'annotation':
                            $useSimpleAnnotationReader =
                                isset($entity['use_simple_annotation_reader'])
                                    ? $entity['use_simple_annotation_reader']
                                    : true;
                            $driver = $config->newDefaultAnnotationDriver((array) $entity['path'], $useSimpleAnnotationReader);
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        case 'yml':
                            $driver = new YamlDriver($entity['path']);
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        case 'simple_yml':
                            $driver = new SimplifiedYamlDriver(array($entity['path'] => $entity['namespace']));
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        case 'xml':
                            $driver = new XmlDriver($entity['path']);
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        case 'simple_xml':
                            $driver = new SimplifiedXmlDriver(array($entity['path'] => $entity['namespace']));
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        case 'php':
                            $driver = new StaticPHPDriver($entity['path']);
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        default:
                            throw new \InvalidArgumentException(sprintf('"%s" is not a recognized driver', $entity['type']));
                            break;
                    }
                }
                $config->setMetadataDriverImpl($chain);

                foreach ((array) $options['types'] as $typeName => $typeClass) {
                    if (Type::hasType($typeName)) {
                        Type::overrideType($typeName, $typeClass);
                    } else {
                        Type::addType($typeName, $typeClass);
                    }
                }

                $configs[$name] = $config;
            }

            return $configs;
        };

        $container['mongodbodm.cache.configurer'] = $container->protect(function ($name, Configuration $config, $options) use ($container) {
            $config->setMetadataCacheImpl($container['mongodbodm.cache.locator']($name, 'metadata', $options));
        });

        $container['mongodbodm.cache.locator'] = $container->protect(function ($name, $cacheName, $options) use ($container) {
            $cacheNameKey = $cacheName.'_cache';

            if (!isset($options[$cacheNameKey])) {
                $options[$cacheNameKey] = $container['mongodbodm.default_cache'];
            }

            if (isset($options[$cacheNameKey]) && !is_array($options[$cacheNameKey])) {
                $options[$cacheNameKey] = array(
                    'driver' => $options[$cacheNameKey],
                );
            }

            if (!isset($options[$cacheNameKey]['driver'])) {
                throw new \RuntimeException("No driver specified for '$cacheName'");
            }

            $driver = $options[$cacheNameKey]['driver'];

            $cacheInstanceKey = 'mongodbodm.cache.instances.'.$name.'.'.$cacheName;
            if (isset($container[$cacheInstanceKey])) {
                return $container[$cacheInstanceKey];
            }

            $cache = $container['mongodbodm.cache.factory']($driver, $options[$cacheNameKey]);

            if (isset($options['cache_namespace']) && $cache instanceof CacheProvider) {
                $cache->setNamespace($options['cache_namespace']);
            }

            return $container[$cacheInstanceKey] = $cache;
        });

        $container['mongodbodm.cache.factory.backing_memcache'] = $container->protect(function () {
            return new \Memcache();
        });

        $container['mongodbodm.cache.factory.memcache'] = $container->protect(function ($cacheOptions) use ($container) {
            if (empty($cacheOptions['host']) || empty($cacheOptions['port'])) {
                throw new \RuntimeException('Host and port options need to be specified for memcache cache');
            }

            /** @var \Memcache $memcache */
            $memcache = $container['mongodbodm.cache.factory.backing_memcache']();
            $memcache->connect($cacheOptions['host'], $cacheOptions['port']);

            $cache = new MemcacheCache();
            $cache->setMemcache($memcache);

            return $cache;
        });

        $container['mongodbodm.cache.factory.backing_memcached'] = $container->protect(function () {
            return new \Memcached();
        });

        $container['mongodbodm.cache.factory.memcached'] = $container->protect(function ($cacheOptions) use ($container) {
            if (empty($cacheOptions['host']) || empty($cacheOptions['port'])) {
                throw new \RuntimeException('Host and port options need to be specified for memcached cache');
            }

            /** @var \Memcached $memcached */
            $memcached = $container['mongodbodm.cache.factory.backing_memcached']();
            $memcached->addServer($cacheOptions['host'], $cacheOptions['port']);

            $cache = new MemcachedCache();
            $cache->setMemcached($memcached);

            return $cache;
        });

        $container['mongodbodm.cache.factory.backing_redis'] = $container->protect(function () {
            return new \Redis();
        });

        $container['mongodbodm.cache.factory.redis'] = $container->protect(function ($cacheOptions) use ($container) {
            if (empty($cacheOptions['host']) || empty($cacheOptions['port'])) {
                throw new \RuntimeException('Host and port options need to be specified for redis cache');
            }

            /** @var \Redis $redis */
            $redis = $container['mongodbodm.cache.factory.backing_redis']();
            $redis->connect($cacheOptions['host'], $cacheOptions['port']);

            if (isset($cacheOptions['password'])) {
                $redis->auth($cacheOptions['password']);
            }

            $cache = new RedisCache();
            $cache->setRedis($redis);

            return $cache;
        });

        $container['mongodbodm.cache.factory.array'] = $container->protect(function () {
            return new ArrayCache();
        });

        $container['mongodbodm.cache.factory.apc'] = $container->protect(function () {
            return new ApcCache();
        });

        $container['mongodbodm.cache.factory.xcache'] = $container->protect(function () {
            return new XcacheCache();
        });

        $container['mongodbodm.cache.factory.filesystem'] = $container->protect(function ($cacheOptions) {
            if (empty($cacheOptions['path'])) {
                throw new \RuntimeException('FilesystemCache path not defined');
            }

            $cacheOptions += array(
                'extension' => FilesystemCache::EXTENSION,
                'umask' => 0002,
            );

            return new FilesystemCache($cacheOptions['path'], $cacheOptions['extension'], $cacheOptions['umask']);
        });

        $container['mongodbodm.cache.factory.couchbase'] = $container->protect(function ($cacheOptions) {

            if (empty($cacheOptions['host'])) {
                $cacheOptions['host'] = '127.0.0.1';
            }

            if (empty($cacheOptions['bucket'])) {
                $cacheOptions['bucket'] = 'default';
            }

            $couchbase = new \Couchbase(
                $cacheOptions['host'],
                $cacheOptions['user'],
                $cacheOptions['password'],
                $cacheOptions['bucket']
            );

            $cache = new CouchbaseCache();
            $cache->setCouchbase($couchbase);

            return $cache;
        });

        $container['mongodbodm.cache.factory'] = $container->protect(function ($driver, $cacheOptions) use ($container) {
            switch ($driver) {
                case 'array':
                    return $container['mongodbodm.cache.factory.array']();
                case 'apc':
                    return $container['mongodbodm.cache.factory.apc']();
                case 'xcache':
                    return $container['mongodbodm.cache.factory.xcache']();
                case 'memcache':
                    return $container['mongodbodm.cache.factory.memcache']($cacheOptions);
                case 'memcached':
                    return $container['mongodbodm.cache.factory.memcached']($cacheOptions);
                case 'filesystem':
                    return $container['mongodbodm.cache.factory.filesystem']($cacheOptions);
                case 'redis':
                    return $container['mongodbodm.cache.factory.redis']($cacheOptions);
                case 'couchbase':
                    return $container['mongodbodm.cache.factory.couchbase']($cacheOptions);
                default:
                    throw new \RuntimeException("Unsupported cache type '$driver' specified");
            }
        });

        $container['mongodbodm.mapping_driver_chain.locator'] = $container->protect(function ($name = null) use ($container) {
            $container['mongodbodm.dms.options.initializer']();

            if (null === $name) {
                $name = $container['mongodbodm.dms.default'];
            }

            $cacheInstanceKey = 'mongodbodm.mapping_driver_chain.instances.'.$name;
            if (isset($container[$cacheInstanceKey])) {
                return $container[$cacheInstanceKey];
            }

            return $container[$cacheInstanceKey] = $container['mongodbodm.mapping_driver_chain.factory']($name);
        });

        $container['mongodbodm.mapping_driver_chain.factory'] = $container->protect(function ($name) use ($container) {
            return new MappingDriverChain();
        });

        $container['mongodbodm.add_mapping_driver'] = $container->protect(function (MappingDriver $mappingDriver, $namespace, $name = null) use ($container) {
            $container['mongodbodm.dms.options.initializer']();

            if (null === $name) {
                $name = $container['mongodbodm.dms.default'];
            }

            /** @var MappingDriverChain $driverChain */
            $driverChain = $container['mongodbodm.mapping_driver_chain.locator']($name);
            $driverChain->addDriver($mappingDriver, $namespace);
        });

        $container['mongodbodm.repository_factory'] = function ($container) {
            return new DefaultRepositoryFactory();
        };

        $container['mongodbodm.dm'] = function ($container) {
            $dms = $container['mongodbodm.dms'];

            return $dms[$container['mongodbodm.dms.default']];
        };

        $container['mongodbodm.dm.config'] = function ($container) {
            $configs = $container['mongodbodm.dms.config'];

            return $configs[$container['mongodbodm.dms.default']];
        };
    }

    /**
     * @param Container $container
     *
     * @return array
     */
    protected function getMongodbOdmDefaults(Container $container)
    {
        return array(
            'mongodbodm.proxies_dir' => __DIR__.'/../../cache/doctrine/proxies',
            'mongodbodm.proxies_namespace' => 'DoctrineProxy',
            'mongodbodm.auto_generate_proxies' => true,
            'mongodbodm.default_cache' => array(
                'driver' => 'array',
            ),
            'mongodbodm.hydrator_dir' => __DIR__.'/../../cache/doctrine/hydrator',
            'mongodbodm.hydrator_namespace' => 'DoctrineHydrator',
            'mongodbodm.auto_generate_hydrators' => true,
            'mongodbodm.class_metadata_factory_name' => 'Doctrine\ODM\MongoDB\Mapping\ClassMetadataFactory',
            'mongodbodm.default_repository_class' => 'Doctrine\ODM\MongoDB\DocumentRepository',
        );
    }
}
