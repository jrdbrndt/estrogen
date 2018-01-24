<?php

declare( strict_types = 1 );

error_reporting( E_ALL | E_STRICT );

require_once '../../classes/estrogen.php';

/* SERVER */

if( pcntl_fork() === 0 ) {

	pcntl_async_signals( true );
	file_put_contents( '/tmp/estrogen.pid', posix_getpid() );

	$e = new Estrogen( SIGUSR1, 'PDOSQLite', [ 'file' => '/tmp/estrogen.db' ] );
	$e->writeServerToFile( '/tmp/estrogen.server' );

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
$server = Estrogen::readServerFromFile( '/tmp/estrogen.server' );

$receipt = $e->sendRequest( $server, 'status' );
var_dump( $receipt->getTime() . ' ms', $receipt->getResponse() );

$receipt = $e->sendRequest( $server, 'dfgdfgdfgsdgsgh' );
var_dump( $receipt->getTime() . ' ms', $receipt->getResponse() );

$receipt = $e->sendRequest( $server, 'shutdown' );
var_dump( $receipt->getTime() . ' ms', $receipt->getResponse() );

echo 'Waiting one second for server to shutdown...', PHP_EOL;
sleep( 1 );
