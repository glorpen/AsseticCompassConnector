===============================
Glorpen AsseticCompassConnector
===============================

The better Compass integration for your PHP project.

For forking and other funnies:

- https://bitbucket.org/glorpen/asseticcompassconnector
- https://github.com/glorpen/AsseticCompassConnector

What problems is it solving?
============================

This Assetic filter:

- provides a way to closely integrate Compass with any framework - you just have to write simple *Resolver* for your project 
- adds bundle/plugins/package namespace for compass files - so you can do cross-plugin imports or use assets from other packages

  - ... and it could enable distributing bundles with Compass assets

- your *Resolver* provides required files - so it can support any project structure
- assets recompiling/updating when any of its dependencies are modified - be it another import, inlined font file or just ``width: image-width(@SomeBundle:public/myimage.png);``

Available Resolvers
===================

- Symfony2 - https://bitbucket.org/glorpen/glorpencompassconnectorbundle


How to install
==============

- first, you need to install ruby connector gem:

.. sourcecode:: bash

   gem install compass-connector

- add requirements to composer.json:

.. sourcecode:: json

   {
       "require": {
           "glorpen/assetic-compass-connector": "*"
       }
   }
   

Virtual Paths
=============

There are four kinds of "paths":

- app: looks like ``@MyBundle:public/images/asset.png``
- absolute path: starts with single ``/``, should only be used in resolving on-disk file and url prefixing, it is always a public file
- vendor: a relative path, should be used only by compass plugins (eg. zurb-foundation, blueprint)
- absolute path: starts with ``//``, ``http://`` etc. and will NOT be changed by connector

Some examples:

.. sourcecode:: css

   @import "@SomeBundle:scss/settings"; /* will resolve to src/SomeBundle/Resources/scss/_settings.scss */
   @import "foundation"; /* will include foundation scss from your compass instalation */
   
   width: image-size("@SomeBundle:public/images/my.png"); /* will output image size of SomeBundle/Resources/public/images/my.png */
   background-image: image-url("@SomeBundle:public/images/my.png"); /* will generate url with prefixes given by Symfony2 config */
   @import "@SomeBundle:sprites/*.png"; /* will import sprites located in src/SomeBundle/Resources/sprites/ */


Usage
=====

This filter's Assetic name is ``compass_connector``.

You can compile assets as in any Assetic filter:

.. sourcecode:: php

   <?php
      use Glorpen\Assetic\CompassConnectorFilter\Filter;
      use Glorpen\Assetic\CompassConnectorFilter\Resolver\SimpleResolver;
      use Assetic\Asset\AssetCollection;
      use Assetic\Asset\FileAsset;
      
      $resolver = new SimpleResolver(
            "ResourcesDir/", "outputDir/"
      );
      
      $f = new Filter($resolver, 'path/to/cache', 'path/to/compass/bin');
      $f->setPlugins(array("zurb-foundation")); //or array("zurb-foundation"=>">4")
      $f->addImport('/path/to/some/scss/dir'); //and then @import "scss_in_some_dir";
      
      $css = new AssetCollection(array(
            new FileAsset('path/to/file.scss'),
      ), array( $f ));
