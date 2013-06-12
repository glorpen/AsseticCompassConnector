<?php
namespace Glorpen\Assetic\CompassConnectorFilter;

use Assetic\Asset\BaseAsset;

use Assetic\Asset\FileAsset;

use Glorpen\Assetic\CompassConnectorFilter\Resolver\ResolverInterface;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\InvalidArgumentException;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\RuntimeException;

class CompassProcess {
	
	protected $initialInput = null, $output;
	protected $cwd, $env, $commandline;
	
	protected $apiMethods, $resolver, $plugins;
	private $touchedFiles;
	
	public function __construct($cmd, ResolverInterface $resolver, $cwd, array $env = null, $input = null){
		$this->initialInput = $input;
		$this->cwd = $cwd;
		$this->env = $env;
		$this->commandline = $cmd;
		$this->resolver = $resolver;
	}
	
	static public function fromProcess(Process $p, ResolverInterface $resolver){
		return new static(
			$p->getCommandLine(),
			$resolver,
			$p->getWorkingDirectory(),
			$p->getEnv(),
			$p->getStdin()
		);
	}
	
	public function setPlugins(array $plugins){
		$this->plugins = $plugins;
	}
	
	public function getTouchedFiles(){
		return $this->touchedFiles;
	}
	
	/**
	 * @Api("get_configuration")
	 * @return array
	 */
	protected function getConfiguration(){
		return array(
			"environment" => ":development",
			"line_comments" => True,
			"output_style" => ":expanded", #nested, expanded, compact, compressed
				
			"generated_images_path" => "/",
			"css_path" => "/dev/null",
			"sass_path" => "/dev/null",
			"plugins" => $this->plugins
		);
	}
	
	/**
	 * @Api("get_file")
	 * @param string $vpath
	 * @param string $type
	 * @param string $mode
	 * @return array
	 */
	protected function getFile($vpath, $type, $mode){
		if($vpath == Filter::INITIAL_VFILE.'scss'){
			return array(
					"mtime" => time(),
					"data" => base64_encode($this->initialInput),
					"hash" => md5($vpath),
					"ext" => 'scss'
			);
		} elseif ($vpath == Filter::INITIAL_VFILE.'css'){
			return array(
					"mtime" => time(),
					"data" => base64_encode($this->output),
					"hash" => md5($vpath),
					"ext" => 'css'
			);
		}
		
		$vpath = ltrim($vpath,'/');
		switch($type){
			case 'generated_image':
			case 'out_css':
				$f = $this->resolver->getOutFilePath($vpath, $type, $mode=='vendor');
				break;
			default:
				$f = $this->resolver->getFilePath($vpath, $mode == 'vendor', $type);
		}
		return $this->getFileInfo($f);
	}
	
	/**
	 * @Api("put_file")
	 * @param string $vpath
	 * @param string $type
	 * @param string $data
	 * @throws \RuntimeException
	 * @return boolean
	 */
	protected function putFile($vpath, $type, $data, $mode){
		
		if($vpath == Filter::INITIAL_VFILE.'css'){
			$this->output = base64_decode($data);
			return true;
		}
		
		$isVendor = $mode == 'vendor';
		
		$vpath = ltrim($vpath,'/');
		switch($type){
			case 'generated_image':
			case 'out_css':
				$p = $this->resolver->getOutFilePath($vpath, $type, $isVendor);
				break;
			default:
				throw new \RuntimeException();
		}
		@mkdir(dirname($p), 0777, true);
		
		file_put_contents($p, base64_decode($data));
		return true;
	}
	
	/**
	 * @Api("get_url")
	 * @param string $path
	 * @param string $type
	 * @param string $mode
	 */
	protected function getUrl($path, $type, $mode){
		return preg_replace('#((^/)|(^[a-zA-Z0-9_]+:/))?/+#','$1/', $this->resolver->getUrl($path, $mode == 'vendor', $type));
	}
	
	/**
	 * @Api("api_version")
	 * @return number
	 */
	protected function getApiVersion(){
		return 2;
	}
	
	/**
	 * @Api("find_sprites_matching")
	 * @param unknown $path
	 * @param unknown $mode
	 */
	protected function findSpritesMatching($path, $mode){
		return $this->resolver->listVPaths($path, $mode=='vendor');
	}
	
	public function getOutput(){
		return $this->output;
	}
	
	protected function getFileInfo($asset){
		if(is_string($asset)){
			if(!file_exists($asset)) return;
			$asset = new FileAsset($asset);
			$asset->load();
		}
		
		if(!$asset) return null;
		
		if(!$asset instanceof BaseAsset){
			throw new \Exception('asset should be instance of BaseAsset');
		}
		
		$this->touchedFiles[$asset->getSourceRoot().DIRECTORY_SEPARATOR.$asset->getSourcePath()] = $asset;
		
		return array(
			"mtime" => $asset->getLastModified(),
			"data" => base64_encode($asset->getContent()),
			"hash" => md5($asset->getSourcePath()),
			"ext" => pathinfo($asset->getSourcePath(), PATHINFO_EXTENSION)
		);
	}
	
	protected function apiRequest(array $request){
		$methods = $this->getApiMethods();
		
		$requestedMethod = $request['method'];
		if(!array_key_exists($requestedMethod, $methods)){
			throw new InvalidArgumentException("Api method ".$requestedMethod.' was not found');
		}
		
		//var_dump($request);
		$ret = call_user_func_array(array($this, $methods[$requestedMethod]), $request['args']);
		//var_dump($ret);
		
		return $ret;
	}
	
	public function getApiMethods(){
		if($this->apiMethods === null){
			$methods = array();
			$r = new \ReflectionClass($this);
			foreach($r->getMethods() as $m){
				/* @var $m \ReflectionMethod */
				preg_match_all('/@Api\("([^\)]+)"\)/', $m->getDocComment(), $matches);
				foreach($matches[1] as $method){
					$methods[$method] = $m->getName();
				}
			}
			
			$this->apiMethods = $methods;
		}
		
		return $this->apiMethods;
	}
	
	public function run()
	{
		$this->touchedFiles = array();
		$this->starttime = microtime(true);
		
		$descriptors = array(
             array('pipe', 'r'), // stdin
             array('pipe', 'w'), // stdout
             //array('pipe', 'w'), // stderr
         );
	
		$commandline = $this->commandline;
	
		@mkdir($this->cwd, 0755, true);
		//var_dump($commandline);
		$this->process = proc_open($commandline, $descriptors, $this->pipes, $this->cwd, $this->env, array());
	
		if (!is_resource($this->process)) {
			throw new RuntimeException('Unable to launch a new process.');
		}
	
		foreach ($this->pipes as $pipe) {
			stream_set_blocking($pipe, true); //we want it to block
		}
		//stream_set_blocking($this->pipes[2], false);

		while(($line=fgets($this->pipes[1]))!==False){
			//echo fgets($this->pipes[2]);
			if(preg_match('/^(\x1b\x5b[0-9]{1,2}m?)?({.*)$/S', $line, $matches)==1){
				$line = $matches[2];
				$ret = $this->apiRequest(json_decode($line, true));
				fwrite($this->pipes[0], json_encode($ret)."\n");
			} else {
				//echo $line;
			}
		}
	}
}
