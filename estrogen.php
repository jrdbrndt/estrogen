<?php

declare( strict_types = 1 );

namespace Estrogen\Interfaces {

	use Estrogen;

	interface Driver {

		public function __construct( Estrogen $estrogen, array $options = [] );
		public function sendRequest( int $clientpid, int $clientsignal, int $serverpid, int $serversignal, $request ) : Receipt;
		public function fetchResponse( Receipt $receipt ) : ?Receipt;
		public function fetchManifest( int $serverpid, int $serversignal ) : ?Manifest;
		public function sendResponse( Manifest $manifest, $response ) : void;

	}

	interface Receipt {

		public function getPid() : int;
		public function getSignal() : int;
		public function getResponse();

	}

	interface Manifest {

		public function getPid() : int;
		public function getSignal() : int;
		public function getRequest();
		public function sendResponse( $response );

	}

}

namespace Estrogen\Main {

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

}

namespace Estrogen\Drivers\PDOSQLite {

	use Estrogen, PDO;

	class Driver implements Estrogen\Interfaces\Driver {

		private $estrogen;
		private $pdo;

		public function __construct( Estrogen $estrogen, array $options = [] ) {

			$this->estrogen = $estrogen;

			$this->pdo = new PDO( 'sqlite:' . $options['file'] );

			$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			$this->pdo->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
			$this->pdo->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ );

			$this->pdo->exec( 'DROP TABLE IF EXISTS "messages"' );
			$this->pdo->exec( 'CREATE TABLE "messages" ( "id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "clientpid" INTEGER NOT NULL, "clientsignal" INTEGER NOT NULL, "serverpid" INTEGER NOT NULL, "serversignal" INTEGER NOT NULL, "request" TEXT NOT NULL, "response" TEXT DEFAULT NULL, "received" INTEGER NOT NULL DEFAULT 0, "replied" INTEGER NOT NULL DEFAULT 0 )' );

		}

		public function sendRequest( int $clientpid, int $clientsignal, int $serverpid, int $serversignal, $request ) : Estrogen\Interfaces\Receipt {

			$stmt = $this->pdo->prepare( 'INSERT INTO "messages" ( "clientpid", "clientsignal", "serverpid", "serversignal", "request" ) VALUES ( :cpid, :csig, :spid, :ssig, :req )' );
			$stmt->bindValue( ':cpid', $clientpid );
			$stmt->bindValue( ':csig', $clientsignal );
			$stmt->bindValue( ':spid', $serverpid );
			$stmt->bindValue( ':ssig', $serversignal );
			$stmt->bindValue( ':req', json_encode( $request ) );
			$stmt->execute();

			$id = (int) $this->pdo->lastInsertId();

			return new Receipt( $this->estrogen, $this, $id, $serverpid, $serversignal );

		}

		public function fetchResponse( Estrogen\Interfaces\Receipt $receipt ) : ?Estrogen\Interfaces\Receipt {

			$stmt = $this->pdo->prepare( 'SELECT "serverpid", "serversignal", "response" FROM "messages" WHERE "id" = :id AND "replied" = 1 LIMIT 1' );
			$stmt->bindValue( ':id', $receipt->getId() );
			$stmt->execute();

			$response = $stmt->fetch();

			$stmt = $this->pdo->prepare( 'DELETE FROM "messages" WHERE "id" = :id LIMIT 1' );
			$stmt->bindValue( ':id', $receipt->getId() );
			$stmt->execute();

			if( $response === false )
			return null;

			return new Receipt( $this->estrogen, $this, $receipt->getId(), $receipt->getPid(), $receipt->getSignal(), json_decode( $response->response ) );

		}

		public function fetchManifest( int $serverpid, int $serversignal ) : ?Estrogen\Interfaces\Manifest {

			$stmt = $this->pdo->prepare( 'SELECT "id", "clientpid", "clientsignal", "request" FROM "messages" WHERE "serverpid" = :pid AND "serversignal" = :signal AND "received" = 0 LIMIT 1' );
			$stmt->bindValue( ':pid', $serverpid );
			$stmt->bindValue( ':signal', $serversignal );
			$stmt->execute();

			$fetch = $stmt->fetch();

			if( $fetch === false )
			return null;

			$stmt = $this->pdo->prepare( 'UPDATE "messages" SET "received" = 1 WHERE "id" = :id LIMIT 1' );
			$stmt->bindValue( ':id', (int) $fetch->id );
			$stmt->execute();

			return new Manifest( $this->estrogen, $this, (int) $fetch->id, (int) $fetch->clientpid, (int) $fetch->clientsignal, json_decode( $fetch->request ) );

		}

		public function sendResponse( Estrogen\Interfaces\Manifest $manifest, $response ) : void {

			$stmt = $this->pdo->prepare( 'UPDATE "messages" SET "response" = :response, "replied" = 1 WHERE "id" = :id AND "replied" = 0 LIMIT 1' );
			$stmt->bindValue( ':response', json_encode( $response ) );
			$stmt->bindValue( ':id', $manifest->getId() );
			$stmt->execute();

		}

	}

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

}

namespace {

	class Estrogen {

		const VERSION = '0.3';

		private $driver, $signal;

		public function __construct( int $signal, string $driver, array $options = [] ) {

			pcntl_signal( $signal, SIG_IGN );

			$driver = 'Estrogen\\Drivers\\' . $driver . '\\Driver';

			$this->signal = $signal;
			$this->driver = new $driver( $this, $options );

		}

		public function onRequest( callable $handler ) : void {

			pcntl_signal( $this->signal, function() use( $handler ) : void {

				while( ($manifest = $this->driver->fetchManifest( posix_getpid(), $this->signal )) !== null )
				$handler( $manifest );

			} );

		}

		public function sendRequest( int $pid, int $signal, $request ) : Estrogen\Interfaces\Receipt {

			pcntl_sigprocmask( SIG_BLOCK, [ $this->signal ] );

			$receipt = $this->driver->sendRequest( posix_getpid(), $this->signal, $pid, $signal, $request );

			posix_kill( $pid, $signal );

			pcntl_sigwaitinfo( [ $this->signal ] );

			pcntl_sigprocmask( SIG_UNBLOCK, [ $this->signal] );

			$receipt = $this->driver->fetchResponse( $receipt );

			return $receipt;

		}

		public function sendResponse( Estrogen\Interfaces\Manifest $manifest, $response ) : void {

			$this->driver->sendResponse( $manifest, $response );

			posix_kill( $manifest->getPid(), $manifest->getSignal() );

		}

	}

}

namespace {

	error_reporting( E_ALL | E_STRICT );

	/* SERVER */

	if( pcntl_fork() === 0 ) {

		pcntl_async_signals( true );
		file_put_contents( '/tmp/estrogen.pid', posix_getpid() );

		$e = new Estrogen( SIGUSR1, 'PDOSQLite', [ 'file' => '/tmp/estrogen.db' ] );

		$e->onRequest( function( $manifest ) {

			$request = $manifest->getRequest();

			if( $request === 'status' ) {

				$manifest->sendResponse( 'you are my friend' );

			}

			elseif( $request === 'shutdown' ) {

				$manifest->sendResponse( 'ok i will shutdown' );
				exit;

			}

			else {

				$manifest->sendResponse( 'i do not understand' );

			}

		} );

		while( true ) sleep( 10 );

	}

	echo 'Waiting one second before starting client...', PHP_EOL;
	sleep( 1 );

	/* CLIENT */

	pcntl_async_signals( true );
	$pid = (int) file_get_contents( '/tmp/estrogen.pid' );

	$e = new Estrogen( SIGUSR1, 'PDOSQLite', [ 'file' => '/tmp/estrogen.db' ] );

	$receipt = $e->sendRequest( $pid, SIGUSR1, 'status' );
	var_dump( $receipt->getResponse() );

	$receipt = $e->sendRequest( $pid, SIGUSR1, 'dfgdfgdfgsdgsgh' );
	var_dump( $receipt->getResponse() );

	$receipt = $e->sendRequest( $pid, SIGUSR1, 'shutdown' );
	var_dump( $receipt->getResponse() );

	echo 'Waiting one second for server to shutdown...', PHP_EOL;
	sleep( 1 );

}
