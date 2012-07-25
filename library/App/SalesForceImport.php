<?
require_once (ROOT_PATH . '/library/SalesForce/SforcePartnerClient.php');
class App_SalesForceImport
{
	private $_user;

	private $_sfConfig;

	private $_csvDelimiter = ",";

	private $_csvEnclosure = '"';

	public $tmpDir = "/tmp/";

	/**
	 *
	 * Storage API client
	 *
	 * @var App_StorageApi
	 */
	private $_sApi;

	/**
	 * @param $idUser
	 */
	public function __construct($user, $sfConfig)
	{
		$this->_user = $user;
		$this->_sfConfig = $sfConfig;
		$this->_registry = Zend_Registry::getInstance();
		$this->_snapshotNumber = time();
		$this->_sApi = new Keboola\StorageApi\Client($user->storageApiToken, $user->storageApiUrl);
	}


	/**
	 * imports all tables
	 * @return void
	 */
	public function importAll()
	{
		foreach($this->_sfConfig->objects as $objectConfig) {
			if (!$objectConfig->storageApiTable) {
				$matches = array();
				preg_match('/FROM (\w*)/', $objectConfig->query, $matches);
				$outputTable = $matches[1];
			} else {
				$outputTable = $objectConfig->storageApiTable;
			}

			$this->importQuery($objectConfig->query, $outputTable, $objectConfig->load);
		}
	}

	/**
	 *
	 * Drop all Storage API tables
	 *
	 * @return bool
	 */
	public function dropAll()
	{
		foreach($this->_sfConfig->objects as $objectConfig) {
			if (!$objectConfig->storageApiTable) {
				$matches = array();
				preg_match('/FROM (\w*)/', $objectConfig->query, $matches);
				$outputTable = $matches[1];
			} else {
				$outputTable = $objectConfig->storageApiTable;
			}
			$tableId = $this->_sApi->getTableId($outputTable, $this->_user->storageApiBucketId);
			if ($tableId) {
				$this->_sApi->dropTable($tableId);
			}
			// deleted items table
			if($deletedTableId =  $this->_sApi->getTableId($outputTable . "_deleted", $this->_user->storageApiBucketId)) {
				$this->_sApi->dropTable($deletedTableId);
			}
		}
		return true;
	}

	/**
	 *
	 * Writes result of a query into output table
	 *
	 * @param $query
	 * @param $fileName
	 * @param string $load Load Type (basic|increments|snapshots)
	 * @param bool $transactional All tables are transactional by default
	 * @throws Exception
	 */
	public function importQuery($query, $outputTable, $load="basic")
	{
		$increments = false;
		$deletedItems = false;
		$snapshots = false;
		switch ($load) {
			case "increments":
				$increments = true;
				$deletedItems = true;
				break;
			case "snapshots":
				$increments = true;
				$deletedItems = true;
				$snapshots = true;
				break;
		}

		// Check output file
		$fileName = $this->tmpDir . $outputTable . ".csv";
		$file = fopen($fileName, "w");
		if (!$file) {
			throw new Exception("Cannot open file '" . $fileName . "' for writing.");
		}

		// Check storage API table. If table does not exist, perform a full dump
		$tableId = $this->_sApi->getTableId($outputTable, $this->_user->storageApiBucketId);
		if (!$tableId && $increments) {
			$increments = false;
		}

		// Incremental queries require SOQL modification
		if ($increments) {
			if (strpos($query, "WHERE") !== false ) {
				$query .= " AND ";
			} else {
				$query .= " WHERE ";
			}
			$query .= " LastModifiedDate > " . date("Y-m-d", strtotime("-1 week")) ."T00:00:00Z";
		}

		// First batch
		$response = $this->_query($query);
		$headers = $this->_getHeaders($response);
		$this->_writeCsv($file, array($headers));

		$records = $this->_parseRecords($response);
		$this->_writeCsv($file, $records);

		// Query for more if all records could not be retreived at once
		while (isset($response['done']) && $response['done'] === false && $response['nextRecordsUrl'] != '') {
			$response = $this->_query($query, $response['nextRecordsUrl']);
			$records = $this->_parseRecords($response);
			$this->_writeCsv($file, $records);
		}

		// Close input file
		fclose($file);

		// If table in Storage API does not exist, create a new one
		if (!$tableId) {
			// Create oneliner with CSV header
			$definitionFilename = $this->tmpDir . $outputTable . ".header.csv";
			$definitionFile = fopen($definitionFilename, "w");
			$dataFile = fopen($fileName, "r");
			fputs($definitionFile, fgets($dataFile));
			fclose($definitionFile);
			fclose($dataFile);
			$tableId = $this->_sApi->createTable($this->_user->storageApiBucketId, $outputTable, $definitionFilename, ",", '"', "Id", $snapshots);
		}

		// Write data to table
		$this->_sApi->writeTable($tableId, $fileName, $this->_snapshotNumber, ",", '"', $increments);

		// Get deleted records in incremental mode
		if ($deletedItems) {
			$matches = array();
			preg_match('/FROM (\w*)/', $query, $matches);
			$this->importDeleted($matches[1], $outputTable);
		}
	}

	/**
	 *
	 * Imports deleted items into Storage API with _deleted suffix
	 *
	 * @param $object
	 * @param $outputTable
	 * @throws Exception¨
	 */
	public function importDeleted($object, $outputTable)
	{
		$fileName = $this->tmpDir . $outputTable . "_deleted.csv";
		$file = fopen($fileName, "w");
		if (!$file) {
			throw new Exception("Cannot open file '" . $fileName . "' for writing.");
		}

		$deletedArray = array(array("Id", "deletedDate"));

		// Get deleted records
		$deleted = $this->_getDeletedRecords($object);

		if ($deleted && count($deleted)) {
			foreach($deleted as $deletedItem) {
				$deletedArray[] = array($deletedItem->id, $deletedItem->deletedDate);
			}
		}

		$this->_writeCsv($file, $deletedArray);
		fclose($file);

		$tableId = $this->_sApi->getTableId($outputTable . "_deleted", $this->_user->storageApiBucketId);

		// If table in Storage API does not exist, create a new one
		if (!$tableId) {
			// Create oneliner with CSV header
			$definitionFilename = $this->tmpDir . $outputTable . "_deleted.header.csv";
			$definitionFile = fopen($definitionFilename, "w");
			$dataFile = fopen($fileName, "r");
			fputs($definitionFile, fgets($dataFile));
			fclose($definitionFile);
			fclose($dataFile);
			$tableId = $this->_sApi->createTable($this->_user->storageApiBucketId, $outputTable . "_deleted", $definitionFilename, ",", '"', "Id");
		}

		$this->_sApi->writeTable($tableId, $fileName, $this->_snapshotNumber, ",", '"', true);
	}

	/**
	 *
	 * Writes to CSV
	 *
	 * @param $file
	 * @param $data array of records
	 */
	private function _writeCsv($file, $data) {
		foreach ($data as $line) {
			fputcsv($file, $line, $this->_csvDelimiter, $this->_csvEnclosure);
		}
	}

	/**
	 *
	 * Returns column names
	 *
	 * @param $response
	 * @return array
	 */
	private function _getHeaders($response) {
		$firstRecord = $this->_parseRecord($response["records"][0]);
		return array_keys($firstRecord);
	}

	/**
	 *
	 * Flattens the result to a single line for each record (used when joining objects)
	 *
	 * @param $response
	 * @return array
	 */
	private function _parseRecords($response, $incremental=false) {
		$records = array();
		foreach($response['records'] as $record) {
			$record = $this->_parseRecord($record, $incremental);
			$records[] = $record;
		}
		return $records;
	}

	/**
	 *
	 * Query for SOQL, handles paging
	 *
	 * @throws Exception
	 * @param $query
	 * @param string $queryUrl
	 * @return mixed
	 */
	private function _query($query, $queryUrl='') {

		NDebugger::timer("query");

		if (!$queryUrl) {
			$url = "{$this->_user->sfdcInstanceUrl}/services/data/v24.0/query?q=" . urlencode($query);
		} else {
			$url = $this->_user->sfdcInstanceUrl.$queryUrl;
		}

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth {$this->_user->sfdcAccessToken}"));

		$json_response = curl_exec($curl);
		curl_close($curl);

		var_dump($url);
		var_dump(strlen($json_response));
		$duration = NDebugger::timer("query");
		var_dump($duration);

		$log = Zend_Registry::get("log");
		$log->log("SalesForce query finished.", Zend_Log::INFO, array(
			"query" => $query,
			"duration" => $duration,
			"responseLength" => strlen($json_response),
			"client" => $this->_user->name
		));

		$response = json_decode($json_response, true);

		if (isset($response[0]['errorCode'])) {
			throw new Exception($response[0]['errorCode'] . ': '. $response[0]['message']);
		}
		return $response;
	}

	/**
	 *
	 * Flattens one record to a single line - useful when joining tables
	 *
	 * @param $record
	 * @return array
	 */
	private function _parseRecord($record, $incremental=false, $recursion=false) {
		unset($record['attributes']);
		$result = array();
		foreach($record as $key => $data) {
			if ($key == "attributes") {
				continue;
			}

			if (is_array($data)) {
				$data = $this->_parseRecord($data, $incremental, true);
				foreach ($data as $innerKey => $innerData) {
					$innerKey = $key . '_' . $innerKey;
					$result[$innerKey] = $innerData;
				}
			} else {
				// Date transformation: "2011-11-15T20:49:19.000Z" to "2011-11-15 20:49:19"
				if (strlen($data) == 24 && preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}.[0-9]{3}Z$/", $data)) {
					$data = str_replace("T", " ", substr($data, 0, -5));
				}
				// Date transformation: "2011-11-15T20:49:19.000+0000" to "2011-11-15 20:49:19"
				if (strlen($data) == 28 && preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}.[0-9]{3}\+0000$/", $data)) {
					$data = str_replace("T", " ", substr($data, 0, -9));
				}

				$result[$key] = $data;
			}
		}
		return $result;
	}

	/**
	 *
	 * Simple request to API, does not handle paging
	 *
	 * @throws Exception
	 * @param $url
	 * @return mixed
	 */
	private function _request($url) {

		$curl = curl_init($this->_user->sfdcInstanceUrl . $url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth {$this->_user->sfdcAccessToken}"));

		$json_response = curl_exec($curl);
		curl_close($curl);

		$response = json_decode($json_response, true);
		// Query more
		// if (isset($response['done']) && $response['done'] === false && $response['nextRecordsUrl'] != '') {
		//	$responseMore = $this->_query($response['nextRecordsUrl']);
		//	$response = array_merge_recursive($response, $responseMore);
		// }
		if (isset($response[0]['errorCode'])) {
			throw new Exception($response[0]['errorCode'] . ': '. $response[0]['message']);
		}
		return $response;

	}

	/**
	 *
	 * Returns deleted records in last 30 days
	 *
	 * @param $entity
	 * @param Zend_Db_Table_Abstract $dbTable
	 * @return array
	 */
	private function _getDeletedRecords($entity) {
		$config = Zend_Registry::get("config");
		$sfc = new SforcePartnerClient();
		$sfc->createConnection(ROOT_PATH . "/library/SalesForce/partner.wsdl.xml");
		$db = Zend_Registry::get("db");
		$passSecret = $db->fetchOne("SELECT AES_DECRYPT(?, ?)", array($this->_user->sfdcPassSecret, $config->app->salt));
		$sfc->login($this->_user->sfdcUsername, $passSecret);
		$records = $sfc->getDeleted($entity, date("Y-m-d", strtotime("-29 day")) . "T00:00:00Z", date("Y-m-d", strtotime("+1 day")) . "T00:00:00Z");
		return $records->deletedRecords;
	}

	/**
	 *
	 * Describe one SF object
	 *
	 * @param $object
	 * @return string
	 */
	public function describe($object) {
		return $this->_request("/services/data/v23.0/sobjects/{$object}/describe");
	}

	/**
	 * List all SF Objects
	 *
	 * @return string
	 */
	public function listObjects() {
		return $this->_request("/services/data/v23.0/sobjects/");
	}

	/**
	 * Run a SOQL query
	 *
	 * @return string
	 */
	public function runQuery($query) {
		return $this->_query($query);

	}
}