<?php

declare( strict_types = 1 );

namespace Estrogen\Interfaces;
use Estrogen;

interface Manifest {

	public function getPid() : int;
	public function getSignal() : int;
	public function getRequest();
	public function sendResponse( $response );

}
