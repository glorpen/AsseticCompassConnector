<?php
namespace Glorpen\Assetic\CompassConnectorFilter\Tests;

use Glorpen\Assetic\CompassConnectorFilter\CompassProcess;

class FilterTest extends \PHPUnit_Framework_TestCase {

	public function testUrlNormalization(){
		//$resolver = $this->
		$resolver = $this->getMock('Glorpen\Assetic\CompassConnectorFilter\Resolver\ResolverInterface');
		$resolver
		->expects($this->any())
		->method('getUrl')
		->will($this->returnArgument(0))
		;
		
		$method = new \ReflectionMethod('Glorpen\Assetic\CompassConnectorFilter\CompassProcess', 'getUrl');
		$method->setAccessible(TRUE);
		
		$f = new CompassProcess('', $resolver, null);
		
		//(?<!^[a-z0-9]+:)
		$this->assertEquals('/some/url/', $method->invokeArgs($f, array('/some////url///','type','mode')));
		$this->assertEquals('//some/url/', $method->invokeArgs($f, array('//some////url///','type','mode')));
		$this->assertEquals('thing://some/url/', $method->invokeArgs($f, array('thing://some////url///','type','mode')));
	}
	
}
