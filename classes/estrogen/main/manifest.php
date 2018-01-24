<?php

declare( strict_types = 1 );

namespace Estrogen\Main;
use Estrogen;

class Manifest implements Estrogen\Interfaces\Manifest {

	private $estrogen, $driver;
	private $pid, $signal, $request;

	public function __construct( Estrogen $estrogen, Estrogen\Interfaces\Driver $driver, int $pid, int $signal, $request ) {

		$this->estrogen = $estrogen;
		$this->driver = $driver;

		$this->pid = $pid;
		$this->signal = $signal;
		$this->request = $request;

	}

	public function getPid() : int {

		return $this->pid;

	}

	public function getSignal() : int {

		return $this->signal;

	}

	public function getRequest() {

		return $this->request;

	}

	public function sendResponse( $response ) {

		$this->estrogen->sendResponse( $this, $response );

	}

}
