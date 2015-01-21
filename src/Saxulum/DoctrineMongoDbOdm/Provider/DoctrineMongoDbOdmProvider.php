<?php

namespace Saxulum\DoctrineMongoDbOdm\Provider;

use Doctrine\Common\Cache\ApcCache;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\CacheProvider;
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
use Doctrine\ODM\MongoDB\Types\Type;
use Silex\Application;
use Silex\ServiceProviderInterface;


class DoctrineMongoDbOdmProvider implements ServiceProviderInterface
{

    public function boot(Application $app) {}

    /**
     * @param Container $container
     */
    public function register(Application $app)
    {
        foreach ($this->getMongodbOdmDefaults($app) as $key => $value) {
            if (!isset($app[ $key ])) {
                $app[ $key ] = $value;
            }
        }

        $app[ 'mongodbodm.dm.default_options' ] = array(
            'connection' => 'default',
            'database'   => null,
            'mappings'   => array(),
            'types'      => array()
        );

        $app[ 'mongodbodm.dms.options.initializer' ] = $app->protect(function () use ($app) {
            static $initialized = false;

            if ($initialized) {
                return;
            }

            $initialized = true;

            if (!isset($app[ 'mongodbodm.dms.options' ])) {
                $app[ 'mongodbodm.dms.options' ] = array('default' => isset($app[ 'mongodbodm.dm.options' ]) ? $app[ 'mongodbodm.dm.options' ] : array());
            }

            $tmp = $app[ 'mongodbodm.dms.options' ];
            foreach ($tmp as $name => &$options) {
                $options = array_replace($app[ 'mongodbodm.dm.default_options' ], $options);

                if (!isset($app[ 'mongodbodm.dms.default' ])) {
                    $app[ 'mongodbodm.dms.default' ] = $name;
                }
            }
            $app[ 'mongodbodm.dms.options' ] = $tmp;
        });

        $app[ 'mongodbodm.dm_name_from_param_key' ] = $app->protect(function ($paramKey) use ($app) {
            $app[ 'mongodbodm.dms.options.initializer' ]();

            if (isset($app[ $paramKey ])) {
                return $app[ $paramKey ];
            }

            return $app[ 'mongodbodm.dms.default' ];
        });

        $app[ 'mongodbodm.dms' ] = function () use ($app) {
            $app[ 'mongodbodm.dms.options.initializer' ]();

            $dms = new Application();
            foreach ($app[ 'mongodbodm.dms.options' ] as $name => $options) {
                if ($app[ 'mongodbodm.dms.default' ] === $name) {
                    // we use shortcuts here in case the default has been overridden
                    $config = $app[ 'mongodbodm.dm.config' ];
                } else {
                    $config = $app[ 'mongodbodm.dms.config' ][ $name ];
                }

                if (isset($options[ 'database' ])) {
                    $config->setDefaultDB($options[ 'database' ]);
                }

                $dms[ $name ] = function () use ($app, $options, $config) {
                    return DocumentManager::create(
                        $app[ 'mongodbs' ][ $options[ 'connection' ] ],
                        $config,
                        $app[ 'mongodbs.event_manager' ][ $options[ 'connection' ] ]
                    );
                };
            }

            return $dms;
        };

        $app[ 'mongodbodm.dms.config' ] = function () use ($app) {
            $app[ 'mongodbodm.dms.options.initializer' ]();

            $configs = new Application();
            foreach ($app[ 'mongodbodm.dms.options' ] as $name => $options) {
                $config = new Configuration();

                $app[ 'mongodbodm.cache.configurer' ]($name, $config, $options);

                $config->setProxyDir($app[ 'mongodbodm.proxies_dir' ]);
                $config->setProxyNamespace($app[ 'mongodbodm.proxies_namespace' ]);
                $config->setAutoGenerateProxyClasses($app[ 'mongodbodm.auto_generate_proxies' ]);
                $config->setHydratorDir($app[ 'mongodbodm.hydrator_dir' ]);
                $config->setHydratorNamespace($app[ 'mongodbodm.hydrator_namespace' ]);
                $config->setAutoGenerateHydratorClasses($app[ 'mongodbodm.auto_generate_hydrators' ]);

                $chain = $app[ 'mongodbodm.mapping_driver_chain.locator' ]($name);
                foreach ((array)$options[ 'mappings' ] as $entity) {
                    if (!is_array($entity)) {
                        throw new \InvalidArgumentException(
                            "The 'mongodbodm.dm.options' option 'mappings' should be an array of arrays."
                        );
                    }

                    if (isset($entity[ 'alias' ])) {
                        $config->addDocumentNamespace($entity[ 'alias' ], $entity[ 'namespace' ]);
                    }

                    switch ($entity[ 'type' ]) {
                        case 'annotation':
                            $useSimpleAnnotationReader =
                                isset($entity[ 'use_simple_annotation_reader' ])
                                    ? $entity[ 'use_simple_annotation_reader' ]
                                    : true;
                            $driver                    = $config->newDefaultAnnotationDriver((array)$entity[ 'path' ],
                                                                                             $useSimpleAnnotationReader);
                            $chain->addDriver($driver, $entity[ 'namespace' ]);
                            break;
                        case 'yml':
                            $driver = new YamlDriver($entity[ 'path' ]);
                            $chain->addDriver($driver, $entity[ 'namespace' ]);
                            break;
                        //                        case 'simple_yml':
                        //                            $driver = new SimplifiedYamlDriver(array($entity['path'] => $entity['namespace']));
                        //                            $chain->addDriver($driver, $entity['namespace']);
                        //                            break;
                        case 'xml':
                            $driver = new XmlDriver($entity[ 'path' ]);
                            $chain->addDriver($driver, $entity[ 'namespace' ]);
                            break;
                        //                        case 'simple_xml':
                        //                            $driver = new SimplifiedXmlDriver(array($entity['path'] => $entity['namespace']));
                        //                            $chain->addDriver($driver, $entity['namespace']);
                        //                            break;
                        case 'php':
                            $driver = new StaticPHPDriver($entity[ 'path' ]);
                            $chain->addDriver($driver, $entity[ 'namespace' ]);
                            break;
                        default:
                            throw new \InvalidArgumentException(sprintf('"%s" is not a recognized driver',
                                                                        $entity[ 'type' ]));
                            break;
                    }
                }
                $config->setMetadataDriverImpl($chain);

                foreach ((array)$options[ 'types' ] as $typeName => $typeClass) {
                    if (Type::hasType($typeName)) {
                        Type::overrideType($typeName, $typeClass);
                    } else {
                        Type::addType($typeName, $typeClass);
                    }
                }

                $configs[ $name ] = $config;
            }

            return $configs;
        };

        $app[ 'mongodbodm.cache.configurer' ] = $app->protect(function ($name, Configuration $config, $options) use ($app) {
            $config->setMetadataCacheImpl($app[ 'mongodbodm.cache.locator' ]($name, 'metadata', $options));
        });

        $app[ 'mongodbodm.cache.locator' ] = $app->protect(function ($name, $cacheName, $options) use ($app) {
            $cacheNameKey = $cacheName . '_cache';

            if (!isset($options[ $cacheNameKey ])) {
                $options[ $cacheNameKey ] = $app[ 'mongodbodm.default_cache' ];
            }

            if (isset($options[ $cacheNameKey ]) && !is_array($options[ $cacheNameKey ])) {
                $options[ $cacheNameKey ] = array(
                    'driver' => $options[ $cacheNameKey ],
                );
            }

            if (!isset($options[ $cacheNameKey ][ 'driver' ])) {
                throw new \RuntimeException("No driver specified for '$cacheName'");
            }

            $driver = $options[ $cacheNameKey ][ 'driver' ];

            $cacheInstanceKey = 'mongodbodm.cache.instances.' . $name . '.' . $cacheName;
            if (isset($app[ $cacheInstanceKey ])) {
                return $app[ $cacheInstanceKey ];
            }

            $cache = $app[ 'mongodbodm.cache.factory' ]($driver, $options[ $cacheNameKey ]);

            if (isset($options[ 'cache_namespace' ]) && $cache instanceof CacheProvider) {
                $cache->setNamespace($options[ 'cache_namespace' ]);
            }

            return $app[ $cacheInstanceKey ] = $cache;
        });

        $app[ 'mongodbodm.cache.factory.backing_memcache' ] = $app->protect(function () {
            return new \Memcache();
        });

        $app[ 'mongodbodm.cache.factory.memcache' ] = $app->protect(function ($cacheOptions) use ($app) {
            if (empty($cacheOptions[ 'host' ]) || empty($cacheOptions[ 'port' ])) {
                throw new \RuntimeException('Host and port options need to be specified for memcache cache');
            }

            /** @var \Memcache $memcache */
            $memcache = $app[ 'mongodbodm.cache.factory.backing_memcache' ]();
            $memcache->connect($cacheOptions[ 'host' ], $cacheOptions[ 'port' ]);

            $cache = new MemcacheCache();
            $cache->setMemcache($memcache);

            return $cache;
        });

        $app[ 'mongodbodm.cache.factory.backing_memcached' ] = $app->protect(function () {
            return new \Memcached();
        });

        $app[ 'mongodbodm.cache.factory.memcached' ] = $app->protect(function ($cacheOptions) use ($app) {
            if (empty($cacheOptions[ 'host' ]) || empty($cacheOptions[ 'port' ])) {
                throw new \RuntimeException('Host and port options need to be specified for memcached cache');
            }

            /** @var \Memcached $memcached */
            $memcached = $app[ 'mongodbodm.cache.factory.backing_memcached' ]();
            $memcached->addServer($cacheOptions[ 'host' ], $cacheOptions[ 'port' ]);

            $cache = new MemcachedCache();
            $cache->setMemcached($memcached);

            return $cache;
        });

        $app[ 'mongodbodm.cache.factory.backing_redis' ] = $app->protect(function () {
            return new \Redis();
        });

        $app[ 'mongodbodm.cache.factory.redis' ] = $app->protect(function ($cacheOptions) use ($app) {
            if (empty($cacheOptions[ 'host' ]) || empty($cacheOptions[ 'port' ])) {
                throw new \RuntimeException('Host and port options need to be specified for redis cache');
            }

            $redis = $app[ 'mongodbodm.cache.factory.backing_redis' ]();
            $redis->connect($cacheOptions[ 'host' ], $cacheOptions[ 'port' ]);

            /** @var \Redis $redis */
            $cache = new RedisCache();
            $cache->setRedis($redis);

            return $cache;
        });

        $app[ 'mongodbodm.cache.factory.array' ] = $app->protect(function () {
            return new ArrayCache();
        });

        $app[ 'mongodbodm.cache.factory.apc' ] = $app->protect(function () {
            return new ApcCache();
        });

        $app[ 'mongodbodm.cache.factory.xcache' ] = $app->protect(function () {
            return new XcacheCache();
        });

        $app[ 'mongodbodm.cache.factory.filesystem' ] = $app->protect(function ($cacheOptions) {
            if (empty($cacheOptions[ 'path' ])) {
                throw new \RuntimeException('FilesystemCache path not defined');
            }

            return new FilesystemCache($cacheOptions[ 'path' ]);
        });

        $app[ 'mongodbodm.cache.factory' ] = $app->protect(function ($driver, $cacheOptions) use ($app) {
            switch ($driver) {
                case 'array':
                    return $app[ 'mongodbodm.cache.factory.array' ]();
                case 'apc':
                    return $app[ 'mongodbodm.cache.factory.apc' ]();
                case 'xcache':
                    return $app[ 'mongodbodm.cache.factory.xcache' ]();
                case 'memcache':
                    return $app[ 'mongodbodm.cache.factory.memcache' ]($cacheOptions);
                case 'memcached':
                    return $app[ 'mongodbodm.cache.factory.memcached' ]($cacheOptions);
                case 'filesystem':
                    return $app[ 'mongodbodm.cache.factory.filesystem' ]($cacheOptions);
                case 'redis':
                    return $app[ 'mongodbodm.cache.factory.redis' ]($cacheOptions);
                default:
                    throw new \RuntimeException("Unsupported cache type '$driver' specified");
            }
        });

        $app[ 'mongodbodm.mapping_driver_chain.locator' ] = $app->protect(function ($name = null) use ($app) {
            $app[ 'mongodbodm.dms.options.initializer' ]();

            if (null === $name) {
                $name = $app[ 'mongodbodm.dms.default' ];
            }

            $cacheInstanceKey = 'mongodbodm.mapping_driver_chain.instances.' . $name;
            if (isset($app[ $cacheInstanceKey ])) {
                return $app[ $cacheInstanceKey ];
            }

            return $app[ $cacheInstanceKey ] = $app[ 'mongodbodm.mapping_driver_chain.factory' ]($name);
        });

        $app[ 'mongodbodm.mapping_driver_chain.factory' ] = $app->protect(function ($name) use ($app) {
            return new MappingDriverChain();
        });

        $app[ 'mongodbodm.add_mapping_driver' ] = $app->protect(function (MappingDriver $mappingDriver, $namespace, $name = null) use ($app) {
            $app[ 'mongodbodm.dms.options.initializer' ]();

            if (null === $name) {
                $name = $app[ 'mongodbodm.dms.default' ];
            }

            /** @var MappingDriverChain $driverChain */
            $driverChain = $app[ 'mongodbodm.mapping_driver_chain.locator' ]($name);
            $driverChain->addDriver($mappingDriver, $namespace);
        });

        $app[ 'mongodbodm.dm' ] = function ($app) {
            $dms = $app[ 'mongodbodm.dms' ];

            return $dms[ $app[ 'mongodbodm.dms.default' ] ];
        };

        $app[ 'mongodbodm.dm.config' ] = function ($app) {
            $configs = $app[ 'mongodbodm.dms.config' ];

            return $configs[ $app[ 'mongodbodm.dms.default' ] ];
        };
    }

    /**
     * @param  Application $app
     *
     * @return array
     */
    protected function getMongodbOdmDefaults(Application $app)
    {
        return array(
            'mongodbodm.proxies_dir'             => __DIR__ . '/../../../../../../../../cache/doctrine/proxies',
            'mongodbodm.proxies_namespace'       => 'DoctrineProxy',
            'mongodbodm.auto_generate_proxies'   => true,
            'mongodbodm.hydrator_dir'            => __DIR__ . '/../../../../../../../../cache/doctrine/hydrator',
            'mongodbodm.hydrator_namespace'      => 'DoctrineHydrator',
            'mongodbodm.auto_generate_hydrators' => true,
            'mongodbodm.default_cache'           => 'array',
        );
    }
}
