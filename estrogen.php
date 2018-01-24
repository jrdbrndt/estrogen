<?php

declare( strict_types = 1 );

spl_autoload_register( function( string $class ) : void {

	$p = __DIR__ . '/classes/' . str_replace( '\\', '/', strtolower( $class ) ) . '.php';
	if( file_exists( $p ) && is_file( $p ) && is_readable( $p ) ) require_once $p;

} );

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

		$timeStart = microtime( true );
		pcntl_sigwaitinfo( [ $this->signal ] );
		$timeEnd = microtime( true );

		var_dump( round( ($timeEnd - $timeStart) * 1000 ) . ' ms' );

		pcntl_sigprocmask( SIG_UNBLOCK, [ $this->signal] );

		$receipt = $this->driver->fetchResponse( $receipt );

		return $receipt;

	}

	public function sendResponse( Estrogen\Interfaces\Manifest $manifest, $response ) : void {

		$this->driver->sendResponse( $manifest, $response );

		posix_kill( $manifest->getPid(), $manifest->getSignal() );

	}

}

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
