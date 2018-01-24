<?php

declare( strict_types = 1 );

namespace Estrogen\Drivers\PDOSQLite;
use Estrogen;

class Manifest extends Estrogen\Main\Manifest {

	private $id;

	public function __construct( Estrogen $estrogen, Estrogen\Interfaces\Driver $driver, int $id, int $pid, int $signal, $request ) {

		$this->id = $id;

		parent::__construct( $estrogen, $driver, $pid, $signal, $request );

	}

	public function getId() : int {

		return $this->id;

	}

}
