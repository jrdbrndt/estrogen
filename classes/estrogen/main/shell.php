<?php

declare( strict_types = 1 );

namespace Estrogen\Main;
use Estrogen;

class Shell implements Estrogen\Interfaces\Shell {

	private $pid, $signal;

	public function __construct( int $pid, int $signal ) {

		$this->pid = $pid;
		$this->signal = $signal;

	}

	public function getPid() : int {

		return $this->pid;

	}

	public function getSignal() : int {

		return $this->signal;

	}

}
