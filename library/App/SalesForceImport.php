<?
require_once (ROOT_PATH . '/library/SalesForce/SforcePartnerClient.php');
class App_SalesForceImport
{
	private $_sfConfig;
	private $_soqlConfig;
	private $_configName;

	public $tmpDir = "/tmp/";

	public $storageApiBucket = "in.c-SFDC";
	public $instanceUrl;
	public $accessToken;
	public $username;
	public $passSecret;
	public $userId;

	private $importTtl = 5;
	private $importPause = 60;

	/**
	 *
	 * Storage API client
	 *
	 * @var \Keboola\StorageApi\Client
	 */
	public $sApi;

	/**
	 * @param $idUser
	 */
	public function __construct($sfConfig, $soqlConfig=array(), $configName)
	{
		$this->_sfConfig = $sfConfig;
		$this->_soqlConfig = $soqlConfig;
		$this->_registry = Zend_Registry::getInstance();
		$this->_configName = $configName;
		$this->_snapshotNumber = floor (time()  / 86400);
	}


	/**
	 * imports all queries or selected
	 *
	 * @return void
	 */
	public function import($queryNumber=false)
	{
		$this->log("Starting extraction");

		$i = 0;
		foreach($this->_soqlConfig as $objectConfig) {
			$i++;
			if ($queryNumber && $queryNumber != $i) {
				continue;
			}

			$logDataQuery = array(
				"query" => $objectConfig->query
			);
			$this->log("Processing query {$queryNumber}", $logDataQuery, \Keboola\StorageApi\Event::TYPE_INFO);

			if (!$objectConfig->storageApiTable) {
				$matches = array();
				preg_match('/FROM (\w*)/', $objectConfig->query, $matches);
				$outputTable = $matches[1];
			} else {
				$outputTable = $objectConfig->storageApiTable;
			}

			// Catch Import errors
			$tableImported = false;
			$iteration = 0;
			while (!$tableImported && $iteration <= $this->importTtl) {
				try {
					$iteration++;
					$this->importQuery($objectConfig->query, $outputTable, $objectConfig->load);
					$tableImported = true;
				} catch (\Keboola\Exception $e) {
					if ($e->getStringCode() == "QUERY_TIMEOUT" && $iteration <= $this->importTtl) {
						sleep($this->importPause);
					} else {
						$message = "Import failed for table '{$outputTable}': " . $e->getMessage();
						$newE = new \Keboola\Exception($message, $e->getCode(), $e, "IMPORT");
						$newE->setContextParams(array_merge($e->getContextParams(), array("query" => $objectConfig->query)));
						throw $newE;
					}
				} catch (\Exception $e) {
					$message = "Import failed for table '{$outputTable}': " . $e->getMessage();
					$newE = new \Keboola\Exception($message, $e->getCode(), $e, "IMPORT");
					$newE->setContextParams(array("query" => $objectConfig->query));
					throw $newE;
				}
			}
			$this->log("Query {$queryNumber} extracted", $logDataQuery, \Keboola\StorageApi\Event::TYPE_SUCCESS);
		}
		$this->log("Extraction finished", array(), \Keboola\StorageApi\Event::TYPE_SUCCESS);
	}

	/**
	 *
	 * Drop all Storage API tables
	 *
	 * @return bool
	 */
	public function dropAll()
	{
		foreach($this->_soqlConfig as $objectConfig) {
			if (!$objectConfig->storageApiTable) {
				$matches = array();
				preg_match('/FROM (\w*)/', $objectConfig->query, $matches);
				$outputTable = $matches[1];
			} else {
				$outputTable = $objectConfig->storageApiTable;
			}
			$tableId = $this->sApi->getTableId($outputTable, $this->storageApiBucket);
			if ($tableId) {
				$this->sApi->dropTable($tableId);
			}
			// deleted items table
			if($deletedTableId =  $this->sApi->getTableId($outputTable . "_deleted", $this->storageApiBucket)) {
				$this->sApi->dropTable($deletedTableId);
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
		$incrementalLoad = false;
		switch ($load) {
			case "increments":
				$incrementalLoad = true;
				$increments = true;
				$deletedItems = true;
				break;
			case "snapshots":
				$incrementalLoad = true;
				$increments = false;
				$deletedItems = false;
				$snapshots = true;
				break;
			case "snapshotIncrements":
				$incrementalLoad = true;
				$increments = true;
				$deletedItems = true;
				$snapshots = true;
				break;
		}


		// Check storage API table. If table does not exist, perform a full dump
		$tableId = $this->sApi->getTableId($outputTable, $this->storageApiBucket);
		if ($tableId === false) {
			$increments = false;
			$incrementalLoad = false;
		}

		// Incremental queries require SOQL modification
		if ($increments) {
			if (strpos($query, "WHERE") !== false ) {
				$query .= " AND ";
			} else {
				$query .= " WHERE ";
			}
			// OpportunityFieldHistory and *History do not have SystemModstamp, only CreatedDate
			// OpportunityHistory does have SystemModstamp
			if (
				strpos($query, "OpportunityFieldHistory") !== false
					|| strpos($query, "History") !== false && strpos($query, "FieldHistory") === false && strpos($query, "History") > 0) {
				$query .= "CreatedDate > " . date("Y-m-d", strtotime("-1 week")) ."T00:00:00Z";
			} else {
				$query .= "SystemModstamp > " . date("Y-m-d", strtotime("-1 week")) ."T00:00:00Z";
			}
		}

		// First batch
		$response = $this->_query($query);
		$fileName = $this->tmpDir . $outputTable . ".csv";
		$file = new \Keboola\Csv\CsvFile($fileName);

		if (count($response["records"]) > 0) {
			// Check output file

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

			if (count($records)) {

				$fileNameGz = $fileName . ".gz";
				exec("gzip $fileName");

				// If table in Storage API does not exist, create a new one
				if (!$tableId) {
					$this->sApi->createTableAsync(
							$this->storageApiBucket,
							$outputTable,
							new \Keboola\Csv\CsvFile($fileNameGz, ",", '"'),
							array("primaryKey" => "Id", "transactional" => $snapshots));

				} else {
					// Write data to table
					$this->sApi->writeTableAsync(
							$tableId,
							new \Keboola\Csv\CsvFile($fileNameGz, ",", '"'),
							array("transaction" => $this->_snapshotNumber, "incremental" => $incrementalLoad)
					);
				}
			}
		} else {
			// If table in Storage API does not exist, create a new one, with make-up columns
			if (!$tableId) {
				$matches = array();
				preg_match("/^SELECT (.*?) FROM/", $query, $matches);
				$columns = explode(",", $matches[1]);
				$headers = array();
				foreach($columns as $columnName) {
					$columnName = trim($columnName);
					// Replace dots
					$columnName = str_replace(".", "_", $columnName);
					// Remove function
					if (strpos($columnName, "(")) {
						$columnName = substr($columnName, strpos($columnName, "("), -1);
					}
					$headers[] = $columnName;
				}
				$file->writeRow($headers);
				$this->sApi->createTableAsync(
						$this->storageApiBucket,
						$outputTable,
						$file,
						array("primaryKey" => "Id", "transactional" => $snapshots));

			}
		}


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
	 * @throws Exception
	 */
	public function importDeleted($object, $outputTable)
	{
		$fileName = $this->tmpDir . $outputTable . "_deleted.csv";
		$file = new \Keboola\Csv\CsvFile($fileName);

		$deletedArray = array(array("Id", "deletedDate"));
		$deletedHeader = $deletedArray;

		// Get deleted records
		$deleted = $this->_getDeletedRecords($object);

		if ($deleted && count($deleted)) {
			foreach($deleted as $deletedItem) {
				$deletedArray[] = array($deletedItem->id, $deletedItem->deletedDate);
			}
			$this->_writeCsv($file, $deletedArray);
		}

		$tableId = $this->sApi->getTableId($outputTable . "_deleted", $this->storageApiBucket);

		// If table in Storage API does not exist, create a new one
		if (!$tableId) {
			$fileHeaderName = $this->tmpDir . $outputTable . "_deleted" . "_header.csv";
			$fileHeader = new \Keboola\Csv\CsvFile($fileHeaderName);
			$this->_writeCsv($fileHeader, $deletedHeader);
			// Create oneliner with CSV header
			$tableId = $this->sApi->createTableAsync(
					$this->storageApiBucket,
					$outputTable . "_deleted",
					new Keboola\Csv\CsvFile($fileHeaderName, ",", '"'),
					array("primaryKey" => "Id")
			);
		}
		if (count($deletedArray) > 1) {
			$fileNameGz = $fileName . ".gz";
			exec("gzip $fileName");
			$this->sApi->writeTableAsync(
					$tableId,
					new \Keboola\Csv\CsvFile($fileNameGz, ",", '"'),
					array("transaction" => $this->_snapshotNumber, "incremental" => true)
			);
		}
	}

	/**
	 *
	 * Writes to CSV
	 *
	 * @param $file \Keboola|Csv
	 * @param $data array of records
	 */
	private function _writeCsv(\Keboola\Csv\CsvFile $file, $data) {
		foreach ($data as $line) {
			$file->writeRow($line);
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
	private function _query($query, $queryUrl='')
	{

		$log = Zend_Registry::get("log");
		$logData = array(
			"query" => $query,
			"queryUrl" => $queryUrl,
			"client" => $this->userId,
			"token" => $this->sApi->getLogData()
		);
		$log->log("SalesForce query starting.", Zend_Log::INFO, $logData);

		NDebugger::timer("query");

		if (!$queryUrl) {
			$url = "{$this->instanceUrl}/services/data/v24.0/query?q=" . urlencode($query);
		} else {
			$url = $this->instanceUrl . $queryUrl;
		}

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth {$this->accessToken}"));

		$json_response = curl_exec($curl);
		curl_close($curl);

		$duration = NDebugger::timer("query");

		$logData["duration"] = $duration;
		$logData["responseLength"] = strlen($json_response);
		$log->log("SalesForce query finished.", Zend_Log::INFO, $logData);

		$response = json_decode($json_response, true);

		if (isset($response[0]['errorCode'])) {
			throw new \Keboola\Exception($response[0]['errorCode'] . ': '. $response[0]['message'], null, null, $response[0]['errorCode']);
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

		$curl = curl_init($this->instanceUrl . $url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth {$this->accessToken}"));

		$json_response = curl_exec($curl);
		curl_close($curl);

		$response = json_decode($json_response, true);
		// Query more
		// if (isset($response['done']) && $response['done'] === false && $response['nextRecordsUrl'] != '') {
		//	$responseMore = $this->_query($response['nextRecordsUrl']);
		//	$response = array_merge_recursive($response, $responseMore);
		// }
		if (isset($response[0]['errorCode'])) {
			throw new \Keboola\Exception($response[0]['errorCode'] . ': '. $response[0]['message'], null, null, $response[0]['errorCode']);
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
		$sfc = new SforcePartnerClient();
		$sfc->createConnection(ROOT_PATH . "/library/SalesForce/partner.wsdl.xml");
		$sfc->login($this->username, $this->passSecret);
		$records = $sfc->getDeleted($entity, date("Y-m-d", strtotime("-29 day")) . "T00:00:00Z", date("Y-m-d", strtotime("+1 day")) . "T00:00:00Z");
		if (isset($records->deletedRecords)) {
			return $records->deletedRecords;
		}

		return null;
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

	/**
	 *
	 * Returns a new access token
	 *
	 * @throws Exception
	 *
	 */
	public function revalidateAccessToken($accessToken, $clientId, $clientSecret, $refreshToken, $loginUri) {

		$url = $loginUri . "/services/oauth2/token";

		$params = "grant_type=refresh_token&client_id=" . $clientId . "&client_secret=" . $clientSecret . "&refresh_token=" . $refreshToken;

		$curl = curl_init($url);

		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth {$accessToken}"));

		$json_response = curl_exec($curl);

		curl_close($curl);

		$response = json_decode($json_response, true);
		if (isset($response['error'])) {
			$tokenInfo = $this->sApi->getLogData();
			throw new \Keboola\Exception("Refreshing OAuth access token in account '{$this->_configName}' failed: " . $response['error'] . ": " . $response['error_description']);
		}

		$this->accessToken = $response['access_token'];
		$this->instanceUrl = $response['instance_url'];

		return $response;
	}

	/**
	 * @param $message
	 * @param array $data
	 * @param string $level
	 */
	public function log($message, $data=array(), $level=\Keboola\StorageApi\Event::TYPE_INFO)
	{
		$event = new \Keboola\StorageApi\Event();
		$event->setComponent("ex-sfdc");
		$event->setRunId($this->sApi->getRunId());
		$event->setMessage($message);
		$event->setParams($data);
		$event->setConfigurationId($this->_configName);
		$event->setType($level);
		$this->sApi->createEvent($event);
	}
}