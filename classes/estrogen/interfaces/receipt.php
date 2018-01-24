<?php

declare( strict_types = 1 );

namespace Estrogen\Interfaces;
use Estrogen;

interface Receipt {

	public function getPid() : int;
	public function getSignal() : int;
	public function getResponse();

}
