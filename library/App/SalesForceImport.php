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
	 * @param $idUser
	 */
	public function __construct($user, $sfConfig)
	{
		$this->_user = $user;
		$this->_sfConfig = $sfConfig;
		$this->_registry = Zend_Registry::getInstance();
		$this->_snapshotNumber = time();
	}


	/**
	 * imports all tables
	 * @return void
	 */
	public function importAll()
	{
		foreach($this->_sfConfig->objects as $objectConfig) {
			$this->importQuery($objectConfig->query, $objectConfig->storageApiTable, $objectConfig->incremental);
		}
	}

	/**
	 *
	 * imports
	 *
	 * @param $query
	 * @param $fileName
	 * @param bool $incremental
	 * @throws Exception
	 */
	public function importQuery($query, $outputTable, $incremental=false) {

		$fileName = $this->tmpDir . $outputTable . ".csv";
		$file = fopen($fileName, "w");
		if (!$file) {
			throw new Exception("Cannot open file '" . $fileName . "' for writing.");
		}

		if ($incremental) {
			if (strpos($query, "WHERE") !== false ) {
				$query .= " AND ";
			} else {
				$query .= " WHERE ";
			}
			$query .= " LastModifiedDate > " . date("Y-m-d", strtotime("-1 week")) ."T00:00:00Z";
		}

		$response = $this->_query($query);
		$headers = $this->_getHeaders($response);
		$this->_writeCsv($file, array($headers));

		$records = $this->_parseRecords($response);
		$this->_writeCsv($file, $records);

		// Query for more
		while (isset($response['done']) && $response['done'] === false && $response['nextRecordsUrl'] != '') {
			$response = $this->_query($query, $response['nextRecordsUrl']);
			$records = $this->_parseRecords($response);
			$this->_writeCsv($file, $records);
		}

		fclose($file);

		//get deleted records
		if ($incremental) {

			$fileName = $this->tmpDir . $outputTable . ".deleted.csv";
			$file = fopen($fileName, "w");
			if (!$file) {
				throw new Exception("Cannot open file '" . $fileName . "' for writing.");
			}

			$deletedArray = array(array("Id", "isDeleted"));

			$matches = array();
			preg_match('/FROM (\w*)/', $query, $matches);

			// get deleted records
			$deleted = $this->_getDeletedRecords($matches[1]);

			if (count($deleted)) {
				foreach($deleted as $deletedItem) {
					$deletedArray[] = array($deletedItem, 1);
				}
			}

			$this->_writeCsv($file, $deletedArray);
			fclose($file);
		}
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
	private function _parseRecords($response) {
		$records = array();
		foreach($response['records'] as $record) {
			$record = $this->_parseRecord($record);
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
			$url = "{$this->_user->instanceUrl}/services/data/v24.0/query?q=" . urlencode($query);
		} else {
			$url = $this->_user->instanceUrl.$queryUrl;
		}

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth {$this->_user->accessToken}"));

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
	private function _parseRecord($record) {
		unset($record['attributes']);
		$result = array();
		foreach($record as $key => $data) {
			if ($key == "attributes") {
				continue;
			}

			if (is_array($data)) {
				$data = $this->_parseRecord($data);
				foreach ($data as $innerKey => $innerData) {
					$innerKey = $key . '.' . $innerKey;
					$result[$innerKey] = $innerData;
				}
			} else {
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

		$curl = curl_init($this->_user->instanceUrl . $url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth {$this->_user->accessToken}"));

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
		$passSecret = $db->fetchOne("SELECT AES_DECRYPT(?, ?)", array($this->_user->passSecret, $config->app->salt));
		$sfc->login($this->_user->username, $passSecret);
		$records = $sfc->getDeleted($entity, date("Y-m-d", strtotime("-29 day")) . "T00:00:00Z", date("Y-m-d", strtotime("+1 day")) . "T00:00:00Z");
		$ids = array();
		if (isset($records->deletedRecords)) {
			foreach($records->deletedRecords as $deletedRecord) {
				$ids[] = $deletedRecord->id;
			}
		}
		return $ids;
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