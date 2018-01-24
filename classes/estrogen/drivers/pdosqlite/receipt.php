<?php

declare( strict_types = 1 );

namespace Estrogen\Drivers\PDOSQLite;
use Estrogen;

class Receipt extends Estrogen\Main\Receipt {

	private $id, $time;

	public function __construct( Estrogen $estrogen, Estrogen\Interfaces\Driver $driver, int $id, int $pid, int $signal, $response = null, $time = null ) {

		$this->id = $id;
		$this->time = $time;

		parent::__construct( $estrogen, $driver, $pid, $signal, $response );

	}

	public function getId() : int {

		return $this->id;

	}

	public function getTime() : int {

		return $this->time;

	}

}
