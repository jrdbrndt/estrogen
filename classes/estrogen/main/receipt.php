<?php

declare( strict_types = 1 );

namespace Estrogen\Main;
use Estrogen;

class Receipt implements Estrogen\Interfaces\Receipt {

	private $estrogen, $driver;
	private $pid, $signal, $response;

	public function __construct( Estrogen $estrogen, Estrogen\Interfaces\Driver $driver, int $pid, int $signal, $response = null ) {

		$this->estrogen = $estrogen;
		$this->driver = $driver;

		$this->pid = $pid;
		$this->signal = $signal;
		$this->response = $response;

	}

	public function getPid() : int {

		return $this->pid;

	}

	public function getSignal() : int {

		return $this->signal;

	}

	public function getResponse() {

		return $this->response;

	}

}
