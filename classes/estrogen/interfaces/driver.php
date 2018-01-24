<?php

declare( strict_types = 1 );

namespace Estrogen\Interfaces;
use Estrogen;

interface Driver {

	public function __construct( Estrogen $estrogen, array $options = [] );
	public function sendRequest( int $clientpid, int $clientsignal, int $serverpid, int $serversignal, $request ) : Receipt;
	public function fetchResponse( Receipt $receipt ) : ?Receipt;
	public function fetchManifest( int $serverpid, int $serversignal ) : ?Manifest;
	public function sendResponse( Manifest $manifest, $response ) : void;

}
