<?php

class Database {

	private $dbHost = 'sql302.epizy.com';
	private $dbUser = 'epiz_34156578';
	private $dbPass = 'TRsNEqA9Fq2xtey';
	private $dbName = 'epiz_34156578_pro';

	protected $statement;
	protected $error;

	protected function connect() {

		try {
			
			$dsn = 'mysql:host=' . $this->dbHost . ';dbname=' . $this->dbName;
			$pdo = new PDO($dsn, $this->dbUser, $this->dbPass);
			$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
			return $pdo;
			
		} catch(PDOException $e) {

			print "Error!: " . $e->getMessage() . "<br/>";
			die();

		}

	}

	protected function query($sql) {

		$this->statement = $this->connect()->query($sql);

	}

	protected function prepare($sql) {

		$this->statement = $this->connect()->prepare($sql);
		
	}
	

}
