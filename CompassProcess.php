<?php
namespace Glorpen\Assetic\CompassConnectorFilter;

use Assetic\Exception\FilterException;

use Assetic\Asset\BaseAsset;

use Assetic\Asset\FileAsset;

use Glorpen\Assetic\CompassConnectorFilter\Resolver\ResolverInterface;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\InvalidArgumentException;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\RuntimeException;

class CompassProcess {
	
	protected $initialInput = null, $output = null;
	protected $cwd, $env, $commandline;
	
	protected $apiMethods, $resolver, $plugins;
	private $touchedFiles;
	
	protected $errorOutput, $commandOutput, $apiRequests, $exitStatus;
	
	const MODE_VENDOR = 'vendor';
	const MODE_APP = 'app';
	const MODE_ABSOLUTE = 'absolute';
	
	const TYPE_GENERATED_IMAGE = 'generated_image';
	const TYPE_OUT_CSS = 'out_css';
	const TYPE_IMAGE = 'image';
	const TYPE_FONT = 'font';
	const TYPE_CSS = 'css';
	
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
				$f = $this->resolver->getOutFilePath($vpath, $type, $mode==self::MODE_VENDOR);
				break;
			default:
				$f = $this->resolver->getFilePath($vpath, $mode, $type);
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
		
		$isVendor = $mode == self::MODE_VENDOR;
		
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
		return preg_replace('#((^/)|(^[a-zA-Z0-9_]+:/))?/+#','$1/', $this->resolver->getUrl($path, $mode, $type));
	}
	
	/**
	 * @Api("api_version")
	 * @return number
	 */
	protected function getApiVersion(){
		return 3;
	}
	
	/**
	 * @Api("find_sprites_matching")
	 * @param unknown $path
	 * @param unknown $mode
	 */
	protected function findSpritesMatching($path, $mode){
		return $this->resolver->listVPaths($path, $mode);
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
	
	private function exportToLine($o){
		$ret = str_replace("\n","",var_export($o, true));
		$max = 200;
		if(strlen($ret)>$max){
			$ret = substr($ret,0,$max-3).'...';
		}
		
		return $ret;
	}
	
	protected function apiRequest(array $request){
		$methods = $this->getApiMethods();
		
		$requestedMethod = $request['method'];
		if(!array_key_exists($requestedMethod, $methods)){
			throw new InvalidArgumentException("Api method ".$requestedMethod.' was not found');
		}
		
		//output in/out api requests
		$this->apiRequests.="{$request['method']}: ".$this->exportToLine($request['args'])."\n";
		$ret = call_user_func_array(array($this, $methods[$requestedMethod]), $request['args']);
		$this->apiRequests.="response: ".$this->exportToLine($ret)."\n";
		
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
		
		$descriptors = array(
             array('pipe', 'r'), // stdin
             array('pipe', 'w'), // stdout
             array('pipe', 'w'), // stderr
         );
	
		$commandline = $this->commandline;
	
		@mkdir($this->cwd, 0755, true);
		$this->process = proc_open($commandline, $descriptors, $pipes, $this->cwd, $this->env, array());
	
		if (!is_resource($this->process)) {
			throw new RuntimeException('Unable to launch a new process.');
		}
	
		stream_set_blocking($pipes[0], true);
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);
		
		$readPipes = array($pipes[1], $pipes[2]);
		$buffor = array('','');
		
		while(True){
			$r = $readPipes;
			$w = null;
			$e = null;
			
			$n = @stream_select($r, $w, $e, 3);
			
			if (false === $n) break;
			if ($n === 0) {
				proc_terminate($this->process);
				throw new RuntimeException('The process timed out.');
			}
			
			foreach ($r as $pipe) {
				$type = array_search($pipe, $readPipes);
				$data = fgets($pipe);
				
				if (false === $data || feof($pipe)) {
					fclose($pipe);
					unset($readPipes[$type]);
					continue;
				}
				
				if($data[strlen($data)-1]!="\n"){
					$buffor[$type].=$data;
					continue;
				} else {
					$data = $buffor[$type].$data;
				}
				
				if($type === 1){
					$this->errorOutput.=$data;
				} else {
					if(preg_match('/^(\x1b\x5b[0-9]{1,2}m?)?({.*)$/S', $data, $matches)==1){
						$line = $matches[2];
						$ret = $this->apiRequest(json_decode($line, true));
						$written = fwrite($pipes[0], json_encode($ret)."\n");
					} else {
						$this->commandOutput.=$data;
					}
				}
			}
		}
		
		$info = proc_get_status($this->process);
		if (!$info['running']) {
			$exitcode = $info['exitcode'];
			
			if($exitcode != 0){
				throw new FilterException("Process exited with {$exitcode}\nOutput:\n{$this->commandOutput}\nError output:\n{$this->errorOutput}\nApi requests:\n{$this->apiRequests}");
			}
		} else {
			throw new RuntimeException("Process was still running");
		}
	}
}
