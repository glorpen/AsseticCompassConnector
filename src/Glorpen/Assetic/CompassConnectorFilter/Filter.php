<?php
namespace Glorpen\Assetic\CompassConnectorFilter;

use Glorpen\Assetic\CompassConnectorFilter\Resolver\ResolverInterface;

use Assetic\Asset\AssetInterface;

use Assetic\Factory\AssetFactory;

use Assetic\Filter\DependencyExtractorInterface;

use Assetic\Filter\BaseProcessFilter;

class Filter extends BaseProcessFilter implements DependencyExtractorInterface {
	
	const INITIAL_VFILE = 'php::stdin.';
	
	protected $resolver, $compassPath, $rubyPath, $cacheDir;
	protected $plugins = array();
	
	public function __construct(ResolverInterface $resolver, $cacheDir = null, $compassPath = '/usr/bin/compass', $rubyPath = null) {
		$this->compassPath = $compassPath;
		$this->rubyPath = $rubyPath;
		$this->resolver = $resolver;
		$this->cacheDir = $cacheDir;
	
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
				'--trace',
				'-r', 'compass-connector',
		);
		
		foreach($this->plugins as $p){
			$compassProcessArgs[] = '-r';
			$compassProcessArgs[] = $p;
		}
		
		$compassProcessArgs[] = '@'.static::INITIAL_VFILE.'scss';
		if (null !== $this->rubyPath) {
			$compassProcessArgs = array_merge(explode(' ', $this->rubyPath), $compassProcessArgs);
		}
		$pb = $this->createProcessBuilder($compassProcessArgs);
		
		if($this->cacheDir){
			$pb->setWorkingDirectory($this->cacheDir);
		}
		
		$pb->setInput($asset->getContent());
		
		$compassProc = CompassProcess::fromProcess($pb->getProcess(), $this->resolver);
		$compassProc->run();
		
		$asset->setContent($compassProc->getOutput());
	}
	
	public function getChildren(AssetFactory $factory, $content, $loadPath = null){
		//TODO
		//var_dump("get children");
	}
	
	public function setPlugins(array $plugins){
		$this->plugins = $plugins;
	}
	
}