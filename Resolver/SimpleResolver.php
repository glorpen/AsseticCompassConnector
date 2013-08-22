<?php
namespace Glorpen\Assetic\CompassConnectorFilter\Resolver;

use Glorpen\Assetic\CompassConnectorFilter\CompassProcess;

use Symfony\Component\Finder\Finder;

use Assetic\Asset\FileAsset;

use Assetic\Asset\GlobAsset;

/**
 * An example class implementing custom Resolver.
 * @author Arkadiusz Dzięgiel
 *
 */
class SimpleResolver implements ResolverInterface {

	protected $appPrefix = '/';
	protected $vendorPrefix = '/vendor';
	protected $generatedPrefix = '/generated';
	
	protected $vendorFontsDir = 'fonts';
	protected $vendorImagesDir = 'images';
	protected $generatedDir = 'generated-images';
	
	protected $assetsDir = 'assets';
	protected $vendorDir = 'vendor';
	
	protected $sourceDir, $outputDir;
	
	public function __construct($sourceDir, $outputDir){
		$this->sourceDir = $sourceDir;
		$this->outputDir = realpath($outputDir);
	}
	
	public function setAppPrefix($prefix){
		$this->appPrefix = $prefix;
	}
	public function setVendorPrefix($prefix){
		$this->vendorPrefix = $prefix;
	}
	public function setVendorDir($dir){
		$this->vendorDir = $dir;
	}
	
	public function listVPaths($vpath, $isVendor){
		if($isVendor){
			$parts = array($this->sourceDir, $this->vendorDir, $this->vendorImagesDir);
		} else {
			$parts = array($this->sourceDir, $this->assetsDir);
		}
		
		list($pre, $post) = explode('*', $vpath, 2);
		
		$prefixPath = implode(DIRECTORY_SEPARATOR, $parts);
		$parts[] = $pre;
		$searchPath = implode(DIRECTORY_SEPARATOR, $parts);
		
		$finder = Finder::create()->in($searchPath)->files();
		
		foreach($finder as $f){
			/* @var $f \SplFileInfo */
			$ret[] = ($isVendor?'':'@').substr($f->getPathname(),strlen($prefixPath)+1);
		}
		return $ret;
	}
	
	public function getUrl($vpath, $mode, $type){
		$isVendor = $mode == CompassProcess::MODE_VENDOR;
		if($type == "generated_image"){
			$path = $this->generatedPrefix."/";
		} else {
			if($isVendor){
				$path = $this->vendorPrefix."/".$this->{"vendor".ucfirst($type)."sDir"}."/";
			} else {
				$path = "/";
			}
		}
		
		return $this->appPrefix."{$path}{$vpath}";
	}
	
	public function getFilePath($vpath, $mode, $type){
		//handles absolute urls in same way as app urls
		$isVendor = $mode == CompassProcess::MODE_VENDOR;
		if($isVendor){
			$parts = array($this->vendorDir, $this->{"vendor".ucfirst($type)."sDir"});
		} else {
			$parts = $type == 'scss'?array():array($this->assetsDir);
		}
		$parts[] = $vpath;
		array_unshift($parts, $this->sourceDir);
		
		return implode(DIRECTORY_SEPARATOR, $parts);
	}
	
	public function getOutFilePath($vpath, $type, $isVendor){
		$parts = array($this->outputDir);
		if($type == 'generated_image'){
			$parts[] = $this->generatedDir;
		}
		$parts[] = $vpath;
		return implode(DIRECTORY_SEPARATOR, $parts);
	}
}
