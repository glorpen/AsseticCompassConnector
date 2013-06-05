<?php
namespace Glorpen\Assetic\CompassConnectorFilter\Resolver;

interface ResolverInterface {
	public function listVPaths($path, $isVendor);
	public function getUrl($path, $isVendor, $type);
	public function getFilePath($path, $isVendor, $type);
	public function getOutFilePath($path, $type, $isVendor);
}
