<?php

declare( strict_types = 1 );

namespace Estrogen\Drivers\PDOSQLite;
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

	public function fetchResponse( Estrogen\Interfaces\Receipt $receipt, int $time = null ) : ?Estrogen\Interfaces\Receipt {

		$stmt = $this->pdo->prepare( 'SELECT "serverpid", "serversignal", "response" FROM "messages" WHERE "id" = :id AND "replied" = 1 LIMIT 1' );
		$stmt->bindValue( ':id', $receipt->getId() );
		$stmt->execute();

		$response = $stmt->fetch();

		$stmt = $this->pdo->prepare( 'DELETE FROM "messages" WHERE "id" = :id LIMIT 1' );
		$stmt->bindValue( ':id', $receipt->getId() );
		$stmt->execute();

		if( $response === false )
		return null;

		return new Receipt( $this->estrogen, $this, $receipt->getId(), $receipt->getPid(), $receipt->getSignal(), json_decode( $response->response ), $time );

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
