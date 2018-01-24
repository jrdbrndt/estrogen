<?php

declare( strict_types = 1 );

namespace Estrogen\Drivers\PDOSQLite;
use Estrogen;

class Receipt extends Estrogen\Main\Receipt {

	private $id;

	public function __construct( Estrogen $estrogen, Estrogen\Interfaces\Driver $driver, int $id, int $pid, int $signal, $response = null ) {

		$this->id = $id;

		parent::__construct( $estrogen, $driver, $pid, $signal, $response );

	}

	public function getId() : int {

		return $this->id;

	}

}
