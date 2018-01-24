<?php

declare( strict_types = 1 );

error_reporting( E_ALL | E_STRICT );

require_once '../../classes/estrogen.php';

pcntl_async_signals( true );

$e = new Estrogen( SIGUSR1, 'PDOSQLite', [ 'file' => '/tmp/estrogen.demo02.db' ] );
$e->writeServerToFile( '/tmp/estrogen.demo02.server' );

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

while( true )
sleep( PHP_INT_MAX );
