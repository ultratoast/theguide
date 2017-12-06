<?php

class Database {
	require '../settings.php';
	private $db = $cconfig['db'];
    private $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],$db['user'], $db['pass']);
	public function createTables() {
		//create module table
		$query = $this->pdo->prepare('CREATE TABLE (IF NOT EXISTS) modules (mid SMALLINT(5) unsigned NOT NULL AUTO_INCREMENT, uuid VARCHAR(50) NOT NULL UNIQUE KEY, title VARCHAR(50) NOT NULL, summary TEXT CHARACTER SET latin1 COLLATE latin1_general_cs, creator VARCHAR(50) NOT NULL,date_created DATE, active BOOLEAN, date_activated DATE, score SMALLINT(5) signed, location VARCHAR(50), PRIMARY KEY (mid)) ENGINE=InnoDB'); 
		try {
			$this->db->beginTransaction();
			$query->execute();
			$this->db->commit();
		} catch (PDOException $error) {
			$this->db->rollback();
			error_log("Error! ".$error->getMessage());
		}
		//create module steps table
		$query = $this->pdo->prepare('CREATE TABLE (IF NOT EXISTS) module_steps (msid SMALLINT(5) unsigned NOT NULL AUTO_INCREMENT, mid SMALLINT(5) unsigned, order SMALLINT(5) unsigned, participants SMALLINT(5) signed, score SMALLINT(5) signed, summary TEXT CHARACTER SET latin1 COLLATE latin1_general_cs, PRIMARY KEY (sid), FOREIGN KEY fk_module(mid) REFERENCES modules(mid) ON UPDATE CASCADE ON DELETE CASCADE) ENGINE=InnoDB');
		try {
			$this->db->beginTransaction();
			$query->execute();
			$this->db->commit();
		} catch (PDOException $error) {
			$this->db->rollback();
			error_log("Error! ".$error->getMessage());
		}
		//create module types table
		$query = $this->db->prepare('CREATE TABLE (IF NOT EXISTS) module_types (mtid SMALLINT(5) unsigned NOT NULL AUTO_INCREMENT, title VARCHAR(50) NOT NULL, PRIMARY KEY (mtid) ENGINE=InnoDB');
		try {
			$this->db->beginTransaction();
			$query->execute();
			$this->db->commit();
		} catch (PDOException $error) {
			$this->db->rollback();
			error_log("Error! ".$error->getMessage());
		}
		//module to types table
		$query = $this->db->prepare('CREATE TABLE (IF NOT EXISTS modules_to_types (mttid SMALLINT(5) unsigned NOT NULL AUTO_INCREMENT, mid SMALLINT(5) unsigned, mtid SMALLINT(5) unsigned, PRIMARY KEY (mttid), FOREIGN KEY fk_module(mid) REFERENCES modules(mid) ON UPDATE CASCADE ON DELETE CASCADE, FOREIGN KEY fk_module_type(mtid) REFERENCES module_types(mtid) ON UPDATE CASCADE ON DELETE CASCADE) ENGINE=InnoDB');
		try {
			$this->db->beginTransaction();
			$query->execute();
			$this->db->commit();
		} catch (PDOException $error) {
			$this->db->rollback();
			error_log("Error! ".$error->getMessage());
		}
		//create project table
		$query = $this->pfo->prepare('CREATE TABLE (IF NOT EXISTS) projects (pid SMALLINT(5) unsigned NOT NULL AUTO_INCREMENT, uuid VARCHAR(50) NOT NULL UNIQUE KEY, title VARCHAR(50) NOT NULL, creator VARCHAR(50) NOT NULL, summary TEXT CHARACTER SET latin1 COLLATE latin1_general_cs, score SMALLINT(5) signed, location VARCHAR(50), date_started DATE, date_ended DATE, active BOOLEAN, date_activated DATE, personnel SMALLINT(5) unsigned NOt NULL, shelter_provided BOOLEAN DEFAULT FALSE, beds SMALLINT(5), food_provided BOOLEAN DEFAULT FALSE, PRIMARY KEY (pid)) ENGINE=InnoDB');
		try {
			$this->db->beginTransaction();
			$query->execute();
			$this->db->commit();
		} catch (PDOException $error) {
			$this->db->rollback();
			error_log("Error! ".$error->getMessage());
		}
		//create projects to modules table
		//amsid = active module step id
		$query = $this->pdo->prepare('CREATE TABLE (IF NOT EXISTS) projects_to_modules (ptmid SMALLINT(5) unsigned NOT NULL AUTO_INCREMENT, amsid SMALLINT(5) unsigned, mid SMALLINT(5) unsigned, pid SMALLINT(5) unsigned, PRIMARY KEY (ptmid), FOREIGN KEY fk_module(mid), REFERENCES modules(mid) ON UPDATE CASCADE ON DELETE CASCADE, FOREIGN KEY fk_project(pid), REFERENCES projects(pid) ON UPDATE CASCADE ON DELETE CASCADE ) ENGINE=InnoDB');
		try {
			$this->db->beginTransaction();
			$query->execute();
			$this->db->commit();
		} catch (PDOException $error) {
			$this->db->rollback();
			error_log("Error! ".$error->getMessage());
		}
		//create tags table
		$query = $this->pdo->prepare('CREATE TABLE (IF NOT EXISTS) tags (tid SMALLINT(5) unsigned NOT NULL AUTO_INCREMENT, uuid VARCHAR(50) NOT NULL UNIQUE KEY, active BOOLEAN, date_activated BOOLEAN, title VARCHAR(50) NOT NULL, summary TEXT CHARACTER SET latin1 COLLATE latin1_general_cs, score SMALLINT(5) signed, author VARCHAR (50), date_created DATE, date_modified DATE, PRIMARY KEY (tid)) ENGINE=InnoDB');
		try {
			$this->db->beginTransaction();
			$query->execute();
			$this->db->commit();
		} catch (PDOException $error) {
			$this->db->rollback();
			error_log("Error! ".$error->getMessage());
		}
		//create modules to tags table, you're welcome Kali
		$query = $this->pdo->prepare('CREATE TABLE (IF NOT EXISTS) modules_to_tags (mtgid SMALLINT(5) unsigned NOT NULL AUTO_INCREMENT, mid SMALLINT(5) unsigned, tid SMALLINT(5) unsigned,PRIMARY KEY (mtgid), FOREIGN KEY fk_module(mid) REFERENCES modules(mid) ON UPDATE CASCADE ON DELETE CASCADE, FOREIGN KEY fk_tag(tid) REFERENCES tags(tid) ON UPDATE CASCADE ON DELETE CASCADE) ENGINE=InnoDB');
		try {
			$this->db->beginTransaction();
			$query->execute();
			$this->db->commit();
		} catch (PDOException $error) {
			$this->db->rollback();
			error_log("Error! ".$error->getMessage());
		}
		//create comments table
		$query = $this->pdo->prepare('CREATE TABLE (IF NOT EXISTS) comments (cid SMALLINT(5) unsigned NOT NULL AUTO_INCREMENT, date_created timestamp, comment TEXT CHARACTER SET latin1 COLLATE latin1_general_cs, score SMALLINT(5) signed, mid SMALLINT(5) unsigned, msid SMALLINT(5) unsigned, pid SMALLINT(5) unsigned, tid SMALLINT(5) unsigned, PRIMARY KEY (cid), FOREIGN KEY fk_modules(mid) REFERENCES modules(mid) ON UPDATE CASCADE ON DELETE CASCADE, FOREIGN KEY fk_projects(pid) REFERENCES projects(pid) ON UPDATE CASCADE ON DELETE CASCADE FOREIGN KEY fk_module_stepss(msid) REFERENCES module_steps(msid) ON UPDATE CASCADE ON DELETE CASCADE FOREIGN KEY fk_tags(tid) REFERENCES tags(tid) ON UPDATE CASCADE ON DELETE CASCADE) ENGINE=InnoDB');
		try {
			$this->db->beginTransaction();
			$query->execute();
			$this->db->commit();
		} catch (PDOException $error) {
			$this->db->rollback();
			error_log("Error! ".$error->getMessage());
		}
		//create images tables
		$query = $this->db->prepare('CREATE TABLE (IF NOT EXISTS) images (iid SMALLINT(5) unsigned NOT NULL AUTO_INCREMENT, filename VARCHAR(50), mid SMALLINT(5) unsigned, msid SMALLINT(5) unsigned, pid SMALLINT(5) unsigned, tid SMALLINT(5) unsigned, PRIMARY KEY (iid), FOREIGN KEY fk_modules(mid) REFERENCES modules(mid) ON UPDATE CASCADE ON DELETE CASCADE, FOREIGN KEY fk_projects(pid) REFERENCES projects(pid) ON UPDATE CASCADE ON DELETE CASCADE FOREIGN KEY fk_module_stepss(msid) REFERENCES module_steps(msid) ON UPDATE CASCADE ON DELETE CASCADE FOREIGN KEY fk_tags(tid) REFERENCES tags(tid) ON UPDATE CASCADE ON DELETE CASCADE) ENGINE=InnoDB');
		try {
			$this->db->beginTransaction();
			$query->execute();
			$this->db->commit();
		} catch (PDOException $error) {
			$this->db->rollback();
			error_log("Error! ".$error->getMessage());
		}
	}
}