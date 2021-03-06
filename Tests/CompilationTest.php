<?php
namespace Glorpen\Assetic\CompassConnectorFilter\Tests;

use Assetic\Exception\FilterException;

use Assetic\Asset\FileAsset;

use Glorpen\Assetic\CompassConnectorFilter\Resolver\SimpleResolver;

use Glorpen\Assetic\CompassConnectorFilter\Filter;

use Assetic\Asset\StringAsset;

use Assetic\Asset\AssetCollection;

class CompilationTest extends \PHPUnit_Framework_TestCase {
	protected function getAssetCollection($filename, array $plugins = array()){

		$resolver = new SimpleResolver(
				implode(DIRECTORY_SEPARATOR,array(__DIR__,'Resources')),
				implode(DIRECTORY_SEPARATOR,array(__DIR__,'Resources','out'))
		);
		$resolver->setAppPrefix('http://example.com/');
		
		$f = new Filter($resolver, __DIR__.DIRECTORY_SEPARATOR.'cache', '/home/arkus/.gem/ruby/1.9.1/bin/compass');
		$f->setPlugins($plugins);
		
		$css = new AssetCollection(array(
				new FileAsset(implode(DIRECTORY_SEPARATOR, array(__DIR__,'Resources','scss',$filename))),
		), array(
				$f
		));
		return $css;
	}
	
	public function testSimpleImport(){
		$css = $this->getAssetCollection('test_simple_imports.scss');
		$this->assertContains('color: red', $css->dump());
	}
	
	public function testSimple(){
		$css = $this->getAssetCollection('test_simple.scss');
		$this->assertContains('color: red', $css->dump());
	}
	
	public function testFonts(){
		$css = $this->getAssetCollection('test_fonts.scss');
		$out = $css->dump();
		
		$this->assertContains('http://example.com/vendor/fonts/this.eot', $out);
		$this->assertContains('http://example.com/fonts/this.eot', $out);
		$this->assertContains("'http://example.com/this-abs.eot'", $out);
		
		$this->assertContains("app-inline-font: url('data:font/truetype;base64", $out);
		$this->assertContains("vendor-inline-font: url('data:font/truetype;base64", $out);
	}
	
	public function testImages(){
		$css = $this->getAssetCollection('test_images.scss');
		$out = $css->dump();
		
		$this->assertContains("'http://example.com/vendor/images/vendor_1x1.png?1370450255'", $out);
		$this->assertContains("'http://example.com/images/image.png?1370450255'", $out);
		$this->assertContains('width-app: 10px;', $out);
		$this->assertContains('width-vendor: 10px;', $out);
		$this->assertContains("image-inline: url('data:image/png;base64,", $out);
		$this->assertContains("vendor-generated-image-busted: url('http://example.com/generated/1x1.png?1370450255'", $out);
		$this->assertContains("vendor-generated-image: url('http://example.com/generated/1x1.png'", $out);
		$this->assertContains("generated-image-busted: url('http://example.com/generated/1x1.png?1370450255'", $out);
		$this->assertContains("generated-image: url('http://example.com/generated/1x1.png'", $out);
		$this->assertContains("abs-image-url: url('http://example.com/images/image.png?", $out);
		$this->assertContains("width-abs: 10px;", $out);
	}
	
	public function testSprites(){
		$css = $this->getAssetCollection('test_sprites.scss');
		$out = $css->dump();
		
		$this->assertContains('http://example.com/generated/sprites/something-sb55c0df6d7.png', $out);
		$this->assertContains('http://example.com/generated/vendor-something-s51c948a8c3.png', $out);
	}
	
	public function testPluginsRequiring(){
		$css = $this->getAssetCollection('test_zurb.scss', array('zurb-foundation'));
		$this->assertContains('body', $css->dump(), 'Plugin without version');
		
		$css = $this->getAssetCollection('test_zurb.scss', array('zurb-foundation'=>'>0'));
		$this->assertContains('body', $css->dump(), 'Plugin with version');
		
		try{
			$css = $this->getAssetCollection('test_zurb.scss', array('zurb-foundation'=>'>999'));
			$css->dump();
			$this->fail('Plugin with non existing version');
		} catch (FilterException $e){}
	}
}