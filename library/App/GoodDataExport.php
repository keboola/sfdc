<?php
/**
 * Class to send Facebook statistics to GoodData
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 29.6.11, 14:25
 *
 */

class App_GoodDataExport
{

	/**
	 * Id of demo project where we send all data
	 */
	const DEMO_PROJECT = 1;

	/**
	 * @var string
	 */
	private $_xmlPath;

	/**
	 * @var string Path to temp dir for csv files
	 */
	private $_tmpPath;

	/**
	 * @var \App_GoodData
	 */
	private $_gd;

	/**
	 * @var Zend_Config
	 */
	private $_config;

	/**
	 * @var int
	 */
	private $_idUser;

	/**
	 * @var Model_BiUser
	 */
	private $_user;

	/**
	 * @var string
	 */
	private $_idProject;


	/**
	 * @param $idProject
	 * @param $idUser
	 * @param $config
	 */
	public function __construct($idProject, $user, $config)
	{
		$this->_gd = new App_GoodData($config->gooddata->username, $config->gooddata->password, $idProject);

		$this->_idProject = $idProject;
		$this->_idUser = $user->id;
		$this->_user = $user;
		$this->_config = $config;

		$this->_xmlPath = realpath(APPLICATION_PATH . '/../gooddata/' . $user->strId);
		$this->_tmpPath = realpath(APPLICATION_PATH . '/../tmp');
	}

	/**
	 * @param $table
	 * @param bool $return
	 * @param bool $structure
	 * @param bool $all
	 * @return bool|string|void
	 */
	public function dumpTable($table, $return=false, $structure=false, $all=false)
	{
		$userStrId = $this->_user->strId;
		$tablesConfig = Zend_Registry::get('config')->sfUser->$userStrId->tables;

		$isSnapshotTable = false;
		$tableId = $table;
		if (strpos($table, "Snapshot")) {
			$isSnapshotTable = true;
			$tableId = str_replace("Snapshot", "", $table);
		}
		if (!isset($tablesConfig->$tableId)) {
			return;
		}
		$tableConfig = $tablesConfig->$tableId;

		$queryColumns = "";

		if (is_string($tableConfig->exportQueryColumns)) {
			$queryColumns = $tableConfig->exportQueryColumns;
		} elseif (count($tableConfig->exportQueryColumns->toArray())) {
			$queryColumns = join (", ", $tableConfig->exportQueryColumns->toArray());
		} else {
			throw new Exception("Export query columns configuration error for {$table}");
		}

		if ($isSnapshotTable) {
			$sql = "SELECT CONCAT(t.snapshotNumber, t.Id) AS Id, t.isDeleted, t.snapshotNumber, s.snapshotDate AS snapshotDate, t.Id as {$tableId}Id, {$queryColumns} FROM {$table} t LEFT JOIN bi_snapshot s ON t.snapshotNumber = s.snapshotNumber";
		} else {
			$sql = "SELECT t.Id, t.isDeleted, {$queryColumns} FROM {$table} t";
		}

		if ($structure) {
			$sql .= ' LIMIT 1';
		} elseif (!$all) {
			$sql .= ' WHERE t.lastModificationDate > \''.date('Y-m-d', strtotime('-4 days')).'\'';
		}

		$file = null;
		if (!$return)
			$file = $this->_tmpPath.'/'.$table.'.csv';
		return $this->dump($sql, $file);
	}

	/**
	 * @param $dataset
	 * @return void
	 */
	public function createDataset($dataset)
	{
		$this->dumpTable($dataset, false, true);
		$this->_gd->createDataset($this->_xmlPath . '/'.$dataset.'.xml', $this->_tmpPath.'/'.$dataset.'.csv');
	}

	/**
	 * @param $dataset
	 * @param bool $all
	 * @return void
	 */
	public function loadDataset($dataset, $all=true)
	{
		$this->dumpTable($dataset, false, false, $all);
		$this->_gd->loadData($this->_xmlPath . '/'.$dataset.'.xml', $this->_tmpPath.'/'.$dataset.'.csv', !$all);
	}

	/**
	 * Updates dataset in GoodData
	 * @param $dataset
	 * @return void
	 */
	public function updateStructure($dataset)
	{
		$this->dumpTable($dataset, false, true);
		$this->_gd->updateDataset($this->_xmlPath . '/'.$dataset.'.xml', $this->_tmpPath.'/'.$dataset.'.csv', $this->_idUser);
	}

	/**
	 * Create data sets in GoodData
	 * @return void
	 */
	public function setup()
	{
		$userStrId = $this->_user->strId;
		$config = Zend_Registry::get('config')->sfUser->$userStrId;

		if (isset($config->dateDimensions) && count($config->dateDimensions)) {
			foreach($config->dateDimensions as $dateDimension) {
				$this->_gd->createDate($dateDimension, FALSE);
			}
		}
		foreach($config->tables as $table => $tableConfig) {
//			$this->_gd->dropDataset($table);
			$this->createDataset($table);
			if ($tableConfig->snapshot) {
//				$this->_gd->dropDataset($table . "Snapshot");
				$this->createDataset($table . "Snapshot");
			}
		}
	}

	/**
	 * Loads data to all data sets in GoodData
	 * @param bool $all
	 * @return void
	 */
	public function loadData($all=false)
	{
		$userStrId = $this->_user->strId;
		$config = Zend_Registry::get('config')->sfUser->$userStrId;

		foreach($config->tables as $table => $tableConfig) {
			$this->loadDataset($table, $all);
			if ($tableConfig->snapshot) {
				$this->loadDataset($table . "Snapshot", $all);
			}
		}

		$this->_gd->updateReports();
	}


	public function idProject()
	{
		return $this->_idProject;
	}


	/**
	 * @param string $sql
	 * @param string $file Save output to file if set, return otherwise
	 * @return void
	 */
	public function dump($sql, $file='')
	{
		$command = 'mysql -u '.$this->_config->db->login.' -p'.$this->_config->db->password.' -h '.$this->_config->db->host
			.' '.$this->_user->dbName.' -B -e "'.$sql.'" | sed \'s/\"/\"\"/g\' | sed \'s/\t/","/g;s/^/"/;s/$/"/;s/\n//g\'';

		if ($file) {
			$command .= ' > '.$file;
		}

		$output = shell_exec($command);
		if ($file && $output) {
			App_Debug::send($output);
		}

		if(!$file)
			return $output;
	}

	/**
	 * @return string
	 */
	public function importDashboard()
	{
		echo "\n*** Import dashboard\n";
		return App_GoodDataService::importDashboard(1, $this->_idProject);
	}

	/**
	 * @param $email
	 * @return string
	 */
	public function inviteUser($email)
	{
		echo "\n*** Invite user: $email\n";
		return App_GoodDataService::inviteUser($email, $this->_idProject);
	}



}
