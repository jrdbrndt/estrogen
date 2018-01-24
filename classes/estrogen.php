<?php

declare( strict_types = 1 );

spl_autoload_register( function( string $class ) : void {

	$p = __DIR__ . '/' . str_replace( '\\', '/', strtolower( $class ) ) . '.php';
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

	public function sendRequest( Estrogen\Interfaces\Shell $server, $request, int $wait = 0 ) : Estrogen\Interfaces\Receipt {

		pcntl_sigprocmask( SIG_BLOCK, [ $this->signal ] );

		$receipt = $this->driver->sendRequest( posix_getpid(), $this->signal, $server->getPid(), $server->getSignal(), $request );

		posix_kill( $server->getPid(), $server->getSignal() );

		if( $wait > 0 ) {

			$timeStart = microtime( true );
			$sig = pcntl_sigtimedwait( [ $this->signal ], $siginfo, 0, $wait * 1000000 );
			$timeEnd = microtime( true );
var_dump( $sig );
		}

		else {

			$timeStart = microtime( true );
			$sig = pcntl_sigwaitinfo( [ $this->signal ] );
			$timeEnd = microtime( true );

		}

		pcntl_sigprocmask( SIG_UNBLOCK, [ $this->signal] );

		$receipt = $this->driver->fetchResponse( $receipt, (int) round( ($timeEnd - $timeStart) * 1000 ) );

		return $receipt;

	}

	public function sendResponse( Estrogen\Interfaces\Manifest $manifest, $response ) : void {

		$this->driver->sendResponse( $manifest, $response );

		posix_kill( $manifest->getPid(), $manifest->getSignal() );

	}

	public function writeServerToFile( string $file ) : void {

		file_put_contents( $file, json_encode( [ posix_getpid(), $this->signal ] ) );

	}

	public static function readServerFromFile( string $file ) : Estrogen\Interfaces\Shell {

		list( $pid, $signal ) = json_decode( file_get_contents( $file ) );
		return new Estrogen\Main\Shell( $pid, $signal );

	}

}
