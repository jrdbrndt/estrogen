<?php

declare( strict_types = 1 );

error_reporting( E_ALL | E_STRICT );

class EstrogenDriver {

	private $estrogen;
	private $pdo;

	public function __construct( Estrogen $estrogen, string $database ) {

		$this->estrogen = $estrogen;

		$this->pdo = new PDO( 'sqlite:' . $database );

		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->pdo->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
		$this->pdo->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ );

		$this->pdo->exec( 'DROP TABLE IF EXISTS "messages"' );
		$this->pdo->exec( 'CREATE TABLE "messages" ( "id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "clientpid" INTEGER NOT NULL, "clientsignal" INTEGER NOT NULL, "serverpid" INTEGER NOT NULL, "serversignal" INTEGER NOT NULL, "request" TEXT NOT NULL, "response" TEXT DEFAULT NULL, "received" INTEGER NOT NULL DEFAULT 0, "replied" INTEGER NOT NULL DEFAULT 0 )' );

	}

	public function sendRequest( int $clientpid, int $clientsignal, int $serverpid, int $serversignal, $request ) : EstrogenReceipt {

		$stmt = $this->pdo->prepare( 'INSERT INTO "messages" ( "clientpid", "clientsignal", "serverpid", "serversignal", "request" ) VALUES ( :cpid, :csig, :spid, :ssig, :req )' );
		$stmt->bindValue( ':cpid', $clientpid );
		$stmt->bindValue( ':csig', $clientsignal );
		$stmt->bindValue( ':spid', $serverpid );
		$stmt->bindValue( ':ssig', $serversignal );
		$stmt->bindValue( ':req', json_encode( $request ) );
		$stmt->execute();

		$id = (int) $this->pdo->lastInsertId();

		return new EstrogenReceipt( $this->estrogen, $this, $id, $serverpid, $serversignal );

	}

	public function fetchResponse( EstrogenReceipt $receipt ) : ?EstrogenReceipt {

		$stmt = $this->pdo->prepare( 'SELECT "serverpid", "serversignal", "response" FROM "messages" WHERE "id" = :id AND "replied" = 1 LIMIT 1' );
		$stmt->bindValue( ':id', $receipt->getId() );
		$stmt->execute();

		$response = $stmt->fetch();

		$stmt = $this->pdo->prepare( 'DELETE FROM "messages" WHERE "id" = :id LIMIT 1' );
		$stmt->bindValue( ':id', $receipt->getId() );
		$stmt->execute();

		if( $response === false )
		return null;

		return new EstrogenReceipt( $this->estrogen, $this, $receipt->getId(), $receipt->getPid(), $receipt->getSignal(), json_decode( $response->response ) );

	}

	public function fetchManifest( int $serverpid, int $serversignal ) : ?EstrogenManifest {

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

		return new EstrogenManifest( $this->estrogen, $this, (int) $fetch->id, (int) $fetch->clientpid, (int) $fetch->clientsignal, json_decode( $fetch->request ) );

	}

	public function sendResponse( EstrogenManifest $manifest, $response ) : void {

		$stmt = $this->pdo->prepare( 'UPDATE "messages" SET "response" = :response, "replied" = 1 WHERE "id" = :id AND "replied" = 0 LIMIT 1' );
		$stmt->bindValue( ':response', json_encode( $response ) );
		$stmt->bindValue( ':id', $manifest->getId() );
		$stmt->execute();

	}

}

class EstrogenReceipt {

	private $estrogen, $driver;
	private $id, $pid, $signal, $response;

	public function __construct( Estrogen $estrogen, EstrogenDriver $driver, int $id, int $pid, int $signal, $response = null ) {

		$this->estrogen = $estrogen;
		$this->driver = $driver;

		$this->id = $id;
		$this->pid = $pid;
		$this->signal = $signal;
		$this->response = $response;

	}

	public function getId() : int {

		return $this->id;

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

class EstrogenManifest {

	private $estrogen, $driver;
	private $id, $pid, $signal, $request;

	public function __construct( Estrogen $estrogen, EstrogenDriver $driver, int $id, int $pid, int $signal, $request ) {

		$this->estrogen = $estrogen;
		$this->driver = $driver;

		$this->id = $id;
		$this->pid = $pid;
		$this->signal = $signal;
		$this->request = $request;

	}

	public function getId() : int {

		return $this->id;

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

class Estrogen {

	const VERSION = '0.3';

	private $driver, $signal;

	public function __construct( int $signal, string $database ) {

		pcntl_signal( $signal, SIG_IGN );

		$this->signal = $signal;
		$this->driver = new EstrogenDriver( $this, $database );

	}

	public function onRequest( callable $handler ) : void {

		pcntl_signal( $this->signal, function() use( $handler ) : void {

			while( ($manifest = $this->driver->fetchManifest( posix_getpid(), $this->signal )) !== null )
			$handler( $manifest );

		} );

	}

	public function sendRequest( int $pid, int $signal, $request ) {

		pcntl_sigprocmask( SIG_BLOCK, [ $this->signal ] );

		$receipt = $this->driver->sendRequest( posix_getpid(), $this->signal, $pid, $signal, $request );

		posix_kill( $pid, $signal );

		pcntl_sigwaitinfo( [ $this->signal ] );

		pcntl_sigprocmask( SIG_UNBLOCK, [ $this->signal] );

		$receipt = $this->driver->fetchResponse( $receipt );

		return $receipt;

	}

	public function sendResponse( EstrogenManifest $manifest, $response ) : void {

		$this->driver->sendResponse( $manifest, $response );

		posix_kill( $manifest->getPid(), $manifest->getSignal() );

	}

}

/* SERVER */

if( pcntl_fork() === 0 ) {

	pcntl_async_signals( true );
	file_put_contents( '/tmp/estrogen.pid', posix_getpid() );

	$e = new Estrogen( SIGUSR1, '/tmp/estrogen.db' );

	$e->onRequest( function( EstrogenManifest $manifest ) {

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

$e = new Estrogen( SIGUSR1, '/tmp/estrogen.db' );

$receipt = $e->sendRequest( $pid, SIGUSR1, 'status' );
var_dump( $receipt->getResponse() );

$receipt = $e->sendRequest( $pid, SIGUSR1, 'dfgdfgdfgsdgsgh' );
var_dump( $receipt->getResponse() );

$receipt = $e->sendRequest( $pid, SIGUSR1, 'shutdown' );
var_dump( $receipt->getResponse() );

echo 'Waiting one second for server to shutdown...', PHP_EOL;
sleep( 1 );

?>
