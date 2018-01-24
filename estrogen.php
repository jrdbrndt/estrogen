<?php

declare( strict_types = 1 );

error_reporting( E_ALL | E_STRICT );

class EstrogenReceipt {

	private $e;
	private $id, $pid, $signal, $response;

	public function __construct( Estrogen $e, int $id, int $pid, int $signal, $response ) {

		$this->e = $e;

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

	private $e;
	private $id, $pid, $signal, $request;

	public function __construct( Estrogen $e, int $id, int $pid, int $signal, $request ) {

		$this->e = $e;

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

		$this->e->sendResponse( $this, $response );

	}

}

class Estrogen {

	const VERSION = '0.2';

	private $pdo, $signal;

	public function __construct( int $signal, string $database ) {

		$this->signal = $signal;

		pcntl_signal( $this->signal, SIG_IGN );

		$this->pdo = new PDO( 'sqlite:' . $database );

		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->pdo->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
		$this->pdo->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ );

		$this->pdo->exec( 'DROP TABLE IF EXISTS "messages"' );

		$this->pdo->exec( 'CREATE TABLE "messages" ( "id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "from_pid" INTEGER NOT NULL, "from_signal" INTEGER NOT NULL, "to_pid" INTEGER NOT NULL, "request" TEXT NOT NULL, "response" TEXT DEFAULT NULL, "read" INTEGER NOT NULL DEFAULT 0 )' );

	}

	public function onRequest( callable $handler ) : void {

		pcntl_signal( $this->signal, function() use( $handler ) : void {

			$stmt = $this->pdo->prepare( 'SELECT "id", "from_pid", "from_signal", "request" FROM "messages" WHERE "to_pid" = :pid AND "read" = 0 ORDER BY "id" ASC LIMIT 1' );
			$stmt->bindValue( ':pid', posix_getpid() );
			$stmt->execute();

			$stmt2 = $this->pdo->prepare( 'UPDATE "messages" SET "read" = 1 WHERE "id" = :id LIMIT 1' );

			while( ($request = $stmt->fetch()) !== false ) {

				$stmt2->bindValue( ':id', (int) $request->id );
				$stmt2->execute();

				$manifest = new EstrogenManifest( $this, (int) $request->id, (int) $request->from_pid, (int) $request->from_signal, json_decode( $request->request ) );

				$handler( $manifest );

			}

		} );

	}

	public function sendRequest( int $pid, int $signal, $request ) {

		$stmt = $this->pdo->prepare( 'INSERT INTO "messages" ( "from_pid", "from_signal", "to_pid", "request" ) VALUES ( :fpid, :fsig, :tpid, :req )' );

		$stmt->bindValue( ':fpid', posix_getpid() );
		$stmt->bindValue( ':fsig', $this->signal );
		$stmt->bindValue( ':tpid', $pid );
		$stmt->bindValue( ':req', json_encode( $request ) );

		$stmt->execute();

		$id = (int) $this->pdo->lastInsertId();

		pcntl_sigprocmask( SIG_BLOCK, [ $this->signal ] );

		posix_kill( $pid, $signal );

		pcntl_sigwaitinfo( [ $this->signal ] );

		pcntl_sigprocmask( SIG_UNBLOCK, [ $this->signal] );

		$stmt = $this->pdo->prepare( 'SELECT "response" FROM "messages" WHERE "id" = :id LIMIT 1' );
		$stmt->bindValue( ':id', $id );
		$stmt->execute();

		$response = $stmt->fetch();
		$response = json_decode( $response->response );

		$stmt = $this->pdo->prepare( 'DELETE FROM "messages" WHERE "id" = :id LIMIT 1' );
		$stmt->bindValue( ':id', $id );
		$stmt->execute();

		$receipt = new EstrogenReceipt( $this, $id, $pid, $signal, $response );

		return $receipt;

	}

	public function sendResponse( EstrogenManifest $manifest, $response ) : void {

		$stmt = $this->pdo->prepare( 'UPDATE "messages" SET "response" = :response WHERE "id" = :id LIMIT 1' );

		$stmt->bindValue( ':response', json_encode( $response ) );
		$stmt->bindValue( ':id', $manifest->getId() );

		$stmt->execute();

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
