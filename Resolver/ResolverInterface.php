<?php
namespace Glorpen\Assetic\CompassConnectorFilter\Resolver;

interface ResolverInterface {
	public function listVPaths($path, $mode);
	public function getUrl($path, $mode, $type);
	public function getFilePath($path, $mode, $type);
	public function getOutFilePath($path, $mode, $type);
}
