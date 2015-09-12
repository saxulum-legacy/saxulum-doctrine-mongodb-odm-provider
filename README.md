saxulum-doctrine-mongodb-odm-provider
=====================================

**works with plain silex-php**

[![Build Status](https://api.travis-ci.org/saxulum/saxulum-doctrine-mongodb-odm-provider.png?branch=master)](https://travis-ci.org/saxulum/saxulum-doctrine-mongodb-odm-provider)
[![Total Downloads](https://poser.pugx.org/saxulum/saxulum-doctrine-mongodb-odm-provider/downloads.png)](https://packagist.org/packages/saxulum/saxulum-doctrine-mongodb-odm-provider)
[![Latest Stable Version](https://poser.pugx.org/saxulum/saxulum-doctrine-mongodb-odm-provider/v/stable.png)](https://packagist.org/packages/saxulum/saxulum-doctrine-mongodb-odm-provider)

Provides Doctrine MongoDB ODM Document Managers as services to Pimple applications.

Features
--------

 * Default Document Manager can be bound to any database connection
 * Multiple Document Managers can be defined
 * Mechanism for allowing Service Providers to register their own
   mappings


Requirements
------------

 * PHP 5.3+
 * Doctrine MongoDB ODM ~1.0


Installation
------------

Through [Composer](http://getcomposer.org) as [saxulum/saxulum-doctrine-mongodb-odm-provider][4].


Usage
-----

To get up and running, register `DoctrineMongoDbOdmProvider` and
manually specify the directory that will contain the proxies along
with at least one mapping.

In each of these examples an Document Manager that is bound to the
default database connection will be provided. It will be accessible
via **mongodbodm.dm**.

```php
<?php

// Default document manager.
$em = $app['mongodbodm.dm'];
```

### Pimple

```php
<?php

use Pimple\Container;
use Saxulum\DoctrineMongoDb\Provider\DoctrineMongoDbProvider;
use Saxulum\DoctrineMongoDbOdm\Provider\DoctrineMongoDbOdmProvider;

$app = new Container;

$app->register(new DoctrineMongoDbProvider, array(
    "mongodb.options" => array(
        "server" => "mongodb://localhost:27017",
        "options" => array(
            "username" => "root",
            "password" => "root",
            "db" => "admin"
        ),
    ),
));

$app->register(new DoctrineMongoDbOdmProvider, array(
    "mongodbodm.proxies_dir" => "/path/to/proxies",
    "mongodbodm.hydrator_dir" => "/path/to/hydrator",
    "mongodbodm.dm.options" => array(
        "database" => "test",
        "mappings" => array(
            // Using actual filesystem paths
            array(
                "type" => "annotation",
                "namespace" => "Foo\Entities",
                "path" => __DIR__."/src/Foo/Entities",
            ),
            array(
                "type" => "xml",
                "namespace" => "Bat\Entities",
                "path" => __DIR__."/src/Bat/Resources/mappings",
            ),
        ),
    ),
));
```


Configuration
-------------

### Parameters

 * **mongodbodm.dm.options**:
   Array of Document Manager options.

   These options are available:
   * **connection** (Default: default):
     String defining which database connection to use. Used when using
     named databases via **mongodbs**.
   * **database**
     The database which should be uses
   * **mappings**:
     Array of mapping definitions.

     Each mapping definition should be an array with the following
     options:
     * **type**: Mapping driver type, one of `annotation`, `xml`, `yml`, `simple_xml`, `simple_yml` or `php`.
     * **namespace**: Namespace in which the entities reside.

     Additionally, each mapping definition should contain one of the
     following options:
     * **path**: Path to where the mapping files are located. This should
       be an actual filesystem path. For the php driver it can be an array
       of paths
     * **resources_namespace**: A namespaceish path to where the mapping
       files are located. Example: `Path\To\Foo\Resources\mappings`

     Each mapping definition can have the following optional options:
     * **alias** (Default: null): Set the alias for the document namespace.

     Each **annotation** mapping may also specify the following options:
     * **use_simple_annotation_reader** (Default: true):
       If `true`, only simple notations like `@Document` will work.
       If `false`, more advanced notations and aliasing via `use` will
       work. (Example: `use Doctrine\ODM\MongoDB\Mapping AS ODM`, `@ODM\Document`)
       Note that if set to `false`, the `AnnotationRegistry` will probably
       need to be configured correctly so that it can load your Annotations
       classes. See this FAQ:
       [Why aren't my Annotations classes being found?](#why-arent-my-annotations-classes-being-found)
   * **metadata_cache** (Default: setting specified by mongodbodm.default_cache):
     String or array describing metadata cache implementation.
   * **types**
     An array of custom types in the format of 'typeName' => 'Namespace\To\Type\Class'
 * **mongodbodm.dms.options**:
   Array of Document Manager configuration sets indexed by each Document Manager's
   name. Each value should look like **mongodbodm.dm.options**.

   Example configuration:

   ```php
   <?php
   $app['mongodbodm.dms.default'] = 'sqlite';
   $app['mongodbodm.dms.options'] = array(
        'mongo1' => array(
            'server' => 'mongodb://localhost:27017',
            'options' => array(
                'username' => 'root',
                'password' => 'root',
                'db' => 'admin'
            )
        ),
        'mongo2' => array(
            'server' => 'mongodb://localhost:27018',
            'options' => array(
                'username' => 'root',
                'password' => 'root',
                'db' => 'admin'
            )
        )
   );
   ```

   Example usage:

   ```php
   <?php
   $emMysql = $app['mongodbodm.dms']['mongo1'];
   $emSqlite = $app['mongodbodm.dms']['mongo2'];
   ```
 * **mongodbodm.dms.default** (Default: first Document Manager processed):
   String defining the name of the default Document Manager.
 * **mongodbodm.proxies_dir**:
   String defining path to where Doctrine generated proxies should be located.
 * **mongodbodm.proxies_namespace** (Default: DoctrineProxy):
   String defining namespace in which Doctrine generated proxies should reside.
 * **mongodbodm.auto_generate_proxies**:
   Boolean defining whether or not proxies should be generated automatically.
 * **mongodbodm.hydrator_dir**:
   String defining path to where Doctrine generated hydrator should be located.
 * **mongodbodm.hydrator_namespace** (Default: DoctrineHydrator):
   String defining namespace in which Doctrine generated hydrator should reside.
 * **mongodbodm.default_cache**:
   String or array describing default cache implementation.
 * **mongodbodm.add_mapping_driver**:
   Function providing the ability to add a mapping driver to an Document Manager.

   These params are available:
    * **$mappingDriver**:
      Mapping driver to be added,
      instance `Doctrine\Common\Persistence\Mapping\Driver\MappingDriver`.
    * **$namespace**:
      Namespace to be mapped by `$mappingDriver`, string.
    * **$name**:
      Name of Document Manager to add mapping to, string, default `null`.
 * **mongodbodm.dm_name_from_param**:
   Function providing the ability to retrieve an document manager's name from
   a param.

   This is useful for being able to optionally allow users to specify which
   document manager should be configured for a 3rd party service provider
   but fallback to the default document manager if not explitely specified.

   For example:

   ```php
   <?php
   $emName = $app['mongodbodm.dm_name_from_param']('3rdparty.provider.dm');
   $em = $app['mongodbodm.dms'][$emName];
   ```

   This code should be able to be used inside of a 3rd party service provider
   safely, whether the user has defined `3rdparty.provider.dm` or not.

### Services

 * **mongodbodm.dm**:
   Document Manager, instance `Doctrine\ODM\MongoDB\DocumentManager`.
 * **mongodbodm.dms**:
   Document Managers, array of `Doctrine\ODM\MongoDB\DocumentManager` indexed by name.


Frequently Asked Questions
--------------------------

### Why aren't my Annotations classes being found?

When **use_simple_annotation_reader** is set to `False` for an document,
the `AnnotationRegistry` needs to have the project's autoloader added
to it.

Example:

```php
<?php
$loader = require __DIR__ . '/../vendor/autoload.php';

\Doctrine\Common\Annotations\AnnotationRegistry::registerLoader(array($loader, 'loadClass'));
```

License
-------

MIT, see LICENSE.


Community
---------

If you have questions or want to help out, join us in the
[#silex-php][#silex-php] channels on irc.freenode.net.


Not Invented Here
-----------------

This project is based heavily on the work done by [@dflydev][1]
on the [dflydev/dflydev-doctrine-orm-service-provider][2] project.

Copyright
---------
* Dominik Zogg <dominik.zogg@gmail.com>
* Beau Simensen <beau@dflydev.com> ([Doctrine ORM Service Provider][2])


[1]: https://github.com/dflydev
[2]: https://github.com/dflydev/dflydev-doctrine-orm-service-provider
[3]: https://github.com/dflydev/dflydev-psr0-resource-locator-service-provider
[4]: https://packagist.org/packages/saxulum/saxulum-doctrine-mongodb-odm-provider

[#silex-php]: irc://irc.freenode.net/#silex-php
