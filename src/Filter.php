<?php
namespace Glorpen\Assetic\CompassConnectorFilter;

use Assetic\Asset\FileAsset;

use Glorpen\Assetic\CompassConnectorFilter\Resolver\ResolverInterface;

use Assetic\Asset\AssetInterface;

use Assetic\Factory\AssetFactory;

use Assetic\Filter\DependencyExtractorInterface;

use Assetic\Filter\BaseProcessFilter;

class Filter extends BaseProcessFilter implements DependencyExtractorInterface {
	
	const INITIAL_VFILE = 'php::stdin.';
	
	protected $resolver, $compassPath, $rubyPath, $cacheDir;
	protected $plugins = array();
	
	private $children = null;
	
	public function __construct(ResolverInterface $resolver, $cacheDir = '/tmp/compass-connector', $compassPath = '/usr/bin/compass', $rubyPath = null) {
		$this->compassPath = $compassPath;
		$this->rubyPath = $rubyPath;
		$this->resolver = $resolver;
		$this->cacheDir = $cacheDir;
	
		if ('cli' !== php_sapi_name()) {
			$this->boring = true;
		}
		
		$this->cacheChildrenFile = $this->cacheDir.DIRECTORY_SEPARATOR.'children.cache.php';
	}
	
	public function filterDump(AssetInterface $asset){
		
	}
	
	public function filterLoad(AssetInterface $asset){
		$compassProcessArgs = array(
				$this->compassPath,
				'compile',
				'--trace',
		);
		
		foreach($this->plugins as $p){
			$compassProcessArgs[] = '-r';
			$compassProcessArgs[] = $p;
		}
		
		$compassProcessArgs[] = '-r';
		$compassProcessArgs[] = 'compass-connector';
		
		$compassProcessArgs[] = '@'.static::INITIAL_VFILE.'scss';
		if (null !== $this->rubyPath) {
			$compassProcessArgs = array_merge(explode(' ', $this->rubyPath), $compassProcessArgs);
		}
		$pb = $this->createProcessBuilder($compassProcessArgs);
		$pb->setWorkingDirectory($this->cacheDir);
		
		$pb->setInput($asset->getContent());
		
		$compassProc = CompassProcess::fromProcess($pb->getProcess(), $this->resolver);
		$compassProc->run();
		
		$this->children = $compassProc->getTouchedFiles();
		@mkdir($this->cacheDir, 0755, true);
		file_put_contents($this->cacheChildrenFile.'.'.md5($asset->getContent()), '<'.'?php return '.var_export(array_keys($this->children), true).';');
		
		$asset->setContent($compassProc->getOutput());
	}
	
	private function loadCachedChildren($hash){
		$this->children = array();
		$cache = $this->cacheChildrenFile.'.'.$hash;
		
		if(file_exists($cache))
		foreach(include($cache) as $f){
			if(file_exists($f)){
				$this->children[] = new FileAsset($f);
			}
		}
	}
	
	public function getChildren(AssetFactory $factory, $content, $loadPath = null){
		if($this->children === null){
			$this->loadCachedChildren(md5($content));
		}
		return $this->children;
	}
	
	public function setPlugins(array $plugins){
		$this->plugins = $plugins;
	}
	
}