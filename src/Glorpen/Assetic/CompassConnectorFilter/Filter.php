<?php
namespace Glorpen\Assetic\CompassConnectorFilter;

use Glorpen\Assetic\CompassConnectorFilter\Resolver\ResolverInterface;

use Assetic\Asset\AssetInterface;

use Assetic\Factory\AssetFactory;

use Assetic\Filter\DependencyExtractorInterface;

use Assetic\Filter\BaseProcessFilter;

class Filter extends BaseProcessFilter implements DependencyExtractorInterface {
	
	const INITIAL_VFILE = 'php::stdin.';
	
	protected $resolver, $compassPath, $rubyPath;
	
	public function __construct(ResolverInterface $resolver, $compassPath = '/usr/bin/compass', $rubyPath = null) {
		$this->compassPath = $compassPath;
		$this->rubyPath = $rubyPath;
		$this->resolver = $resolver;
	
		if ('cli' !== php_sapi_name()) {
			$this->boring = true;
		}
	}
	
	public function filterLoad(AssetInterface $asset){
		
	}
	
	public function filterDump(AssetInterface $asset){
		$compassProcessArgs = array(
				$this->compassPath,
				'compile',
				'-r', 'compass-connector',
				'@'.static::INITIAL_VFILE.'scss',
		);
		if (null !== $this->rubyPath) {
			$compassProcessArgs = array_merge(explode(' ', $this->rubyPath), $compassProcessArgs);
		}
		
		$pb = $this->createProcessBuilder($compassProcessArgs);
		
		$pb->setInput($asset->getContent());
		
		$compassProc = CompassProcess::fromProcess($pb->getProcess(), $this->resolver);
		
		$compassProc->run();
		
		$asset->setContent($compassProc->getOutput());
	}
	
	public function getChildren(AssetFactory $factory, $content, $loadPath = null){
		
	}
	
}