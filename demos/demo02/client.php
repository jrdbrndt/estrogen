<?php

declare( strict_types = 1 );

error_reporting( E_ALL | E_STRICT );

require_once '../../classes/estrogen.php';

pcntl_async_signals( true );

$e = new Estrogen( SIGUSR1, 'PDOSQLite', [ 'file' => '/tmp/estrogen.demo02.db' ] );
$server = Estrogen::readServerFromFile( '/tmp/estrogen.demo02.server' );

$receipt = $e->sendRequest( $server, 'status' );
var_dump( $receipt->getTime() . ' ms', $receipt->getResponse() );

$receipt = $e->sendRequest( $server, 'dfgdfgdfgsdgsgh' );
var_dump( $receipt->getTime() . ' ms', $receipt->getResponse() );

$receipt = $e->sendRequest( $server, 'shutdown' );
var_dump( $receipt->getTime() . ' ms', $receipt->getResponse() );
