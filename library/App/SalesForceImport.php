<?
require_once (ROOT_PATH . '/library/SalesForce/SforcePartnerClient.php');
class App_SalesForceImport
{
	private $_user;

	private $_sfConfig;

	/**
	 * @param $idUser
	 */
	public function __construct($user, $sfConfig)
	{
		$this->_user = $user;
		$this->_sfConfig = $sfConfig;
		$this->_registry = Zend_Registry::getInstance();
		$snapshotTable = new Model_BiSnapshot();
		$this->_snapshotNumber = $snapshotTable->getSnapshotNumber();
	}


	/**
	 * imports all tables
	 * @return void
	 */
	public function importAll($incremental=false)
	{
		foreach($this->_sfConfig->tables as $table => $tableConfig) {
			$this->import($table, null, $incremental);
		}
	}

	/**
	 * imports one table
	 * @param $tableName
	 * @return bool
	 */
	public function import($tableName, $attribute=null, $incremental=false) {
		if(isset($this->_sfConfig->tables->$tableName)) {
			$tableConfig = $this->_sfConfig->tables->$tableName;

			$table = $tableName;
			$sfTable = $tableName;

			// Custom Table Name in SalesForce
			if ($tableConfig->sfTableName) {
				$sfTable  = $tableConfig->sfTableName;
			}

			$query = '';
			if (isset($tableConfig->importQuery)) {
				$query = $tableConfig->importQuery;
			}
			if (isset($tableConfig->importQueryColumns) && count($tableConfig->importQueryColumns->toArray())) {
				$query = "SELECT " . join(",", $tableConfig->importQueryColumns->toArray()) . " FROM {$sfTable}";
			}
			if (!$query) {
				throw new Exception("Table {$table} not configured.");
			}

			$dbTable = new Model_DataTable(null, null, $table);

			if ($attribute) {
				// Request only one attribute

				$query = "SELECT Id, {$attribute} FROM {$sfTable}";
				$response = $this->_query($query);
				$this->_parseAttributeResponse($response, $dbTable, $tableConfig, $attribute, $tableName);
				// Query more
				while (isset($response['done']) && $response['done'] === false && $response['nextRecordsUrl'] != '') {
					$response = $this->_query($query, $response['nextRecordsUrl']);
					$this->_parseAttributeResponse($response, $dbTable, $tableConfig, $attribute, $tableName);
				}

			} else {

				if ($incremental) {

					$query .= " WHERE LastModifiedDate > " . date("Y-m-d", strtotime("-1 week")) ."T00:00:00Z";
					$response = $this->_query($query);
					$this->_parseResponse($response, $dbTable, $tableConfig);
					// Query more
					while (isset($response['done']) && $response['done'] === false && $response['nextRecordsUrl'] != '') {
						$response = $this->_query($query, $response['nextRecordsUrl']);
						$this->_parseResponse($response, $dbTable, $tableConfig);
					}

					// get deleted records
					$deleted = $this->_getDeletedRecords("Contact", $dbTable);
					if (count($deleted)) {
						$dbTable->getAdapter()->query("
							UPDATE {$table}
							SET
								lastModificationDate = '" . date("Y-m-d") . "',
								isDeleted = 1
							WHERE Id IN ('" . join("','", $deleted) . "')
						");
					}

				} else {

					$dbTable->prepareDeleteCheck();

					// Empty record
					if ($tableConfig->emptyRecord) {
						$data = array();

						foreach($tableConfig->emptyRecord as $column => $value) {
							$data[$column] = $value;
						}
						$data = $this->transformValues($data, $tableConfig);
						$dbTable->insertOrSet($data);
					}

					$response = $this->_query($query);
					$this->_parseResponse($response, $dbTable, $tableConfig);

					// Query more
					while (isset($response['done']) && $response['done'] === false && $response['nextRecordsUrl'] != '') {
						$response = $this->_query($query, $response['nextRecordsUrl']);
						$this->_parseResponse($response, $dbTable, $tableConfig);
					}

					$dbTable->deleteCheck();
				}
			}

			if ($tableConfig->snapshot) {
				$dbTable->createSnapshot($this->_snapshotNumber);
			}
		}
	}

	/**
	 * sets empty values, transforms desired columns
	 * @param $record
	 * @param $tableConfig
	 * @return array
	 */
	public function transformValues($record, $tableConfig) {
		// Column aliases
		if (isset($tableConfig->importQueryColumnAlias)) {
			foreach($tableConfig->importQueryColumnAlias as $source => $destination) {
				$record[$destination] = $record[$source];
				unset($record[$source]);
			}
		}

		// Empty values
		if (isset($tableConfig->emptyColumn)) {
			foreach($tableConfig->emptyColumn as $emptyColumnName => $emptyColumnValue) {
				if (!isset($record[$emptyColumnName]) || $record[$emptyColumnName] === null) {
					$record[$emptyColumnName] = $emptyColumnValue;
				}
			}
		}

		// Value transformation
		if (isset($tableConfig->columnTransformation)) {
			foreach($tableConfig->columnTransformation as $columnName => $columnTransformation) {
				if ($columnTransformation == 'timeToDate') {
					$dateParts = explode("T", $record[$columnName]);
					$record[$columnName] = $dateParts[0];
				}
			}
		}
		return $record;
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
	 * Parse response data and insert into DB
	 *
	 * @param $response
	 * @param $dbTable
	 * @param $tableConfig
	 */
	private function _parseResponse($response, $dbTable, $tableConfig)
	{
		if ($response['totalSize'] > 0) {
			$ids = array();
			$idsStrings = array();
			$storedIds = array();
			$recordsHash = array();

			$durations = array(
				"transformValues" => 0,
				"getDbRecords" => 0,
				"compareRecords" => 0,
				"updateRecords" =>0,
				"insertRecords" => 0,
				"count" => $response['totalSize']
			);

			NDebugger::timer("transformValues");
			// Transofm values
			foreach($response['records'] as $key => $record) {
				$record = $this->_parseRecord($record);
				$recordsHash[$record["Id"]] = $this->transformValues($record, $tableConfig);
				$ids[] = $record["Id"];
				$idsStrings[] = "'{$record["Id"]}'";
			}

			$durations["transformValues"] = NDebugger::timer("transformValues");

			NDebugger::timer("getDbRecords");
			// get all records, if snapshot table, then in
			$query = "Id IN (" . join(",", $idsStrings) . ")";
			if ($dbTable->isSnapshotTable()) {
				$query .= " AND snapshotNumber='{$dbTable->getSnapshotNumber()}'";
			}
			$storedRecords = $dbTable->fetchAll($query);
			$durations["getDbRecords"] = NDebugger::timer("getRecords");

			$dbTable->deleteCheck($idsStrings);

			// Check for updates
			foreach ($storedRecords as $storedRecord) {
				$storedIds[] = $storedRecord["Id"];
				$storedRecordArray = $storedRecord->toArray();
				$recordModified = false;
				NDebugger::timer("compareRecords");
				foreach(array_keys($recordsHash[$storedRecord["Id"]]) as $recordKey) {
					if ($recordKey != "Id") {
						if ($storedRecordArray[$recordKey] != $recordsHash[$storedRecord["Id"]][$recordKey]) {
							$recordModified = true;
							continue;
						}
					}
				}
				$durations["compareRecords"] += NDebugger::timer("compareRecords");

				// Modifications found
				if ($recordModified) {
					// print "{$storedRecordArray["_id"]} / {$storedRecordArray["Id"]} modified\n";
					// var_dump($storedRecordArray);
					// var_dump($recordsHash[$storedRecord["Id"]]);
					NDebugger::timer("updateRecords");
					$row = $dbTable->fetchRow("_id = '{$storedRecordArray["_id"]}'");
					$row->setFromArray($recordsHash[$storedRecord["Id"]]);
					if ($row->isChanged() && !$dbTable->isSnapshotTable()) {
						$row->lastModificationDate = date("Y-m-d");
					}
					if (!$dbTable->isSnapshotTable()) {
						$row->isDeletedCheck = 0;
						$row->isDeleted = 0;
					}

					$row->save();
					$durations["updateRecords"] += NDebugger::timer("updateRecords");
				}
			}

			NDebugger::timer("insertRecords");
			// Insert new records
			foreach(array_diff($ids, $storedIds) as $missingId) {
				$data = $recordsHash[$missingId];
				$data['lastModificationDate'] = date("Y-m-d");
				if (!$dbTable->isSnapshotTable()) {
					$data['isDeletedCheck'] = 0;
					$data['isDeleted'] = 0;
				}
				$dbTable->insert($data);
			}
			$durations["insertRecords"] += NDebugger::timer("insertRecords");

			var_dump($durations);
		}
	}

	/**
	 * Parse response data and insert into DB, just one selected attribute
	 *
	 * @param $response
	 * @param $dbTable
	 * @param $tableConfig
	 */
	private function _parseAttributeResponse($response, $dbTable, $tableConfig, $attribute, $tableName)
	{
		if ($response['totalSize'] > 0) {
			$recordsHash = array();

			$durations = array(
				"transformValues" => 0,
				"updateRecords" =>0,
				"count" => $response['totalSize']
			);

			if ($tableConfig->importQueryColumnAlias->$attribute) {
				$attribute = $tableConfig->importQueryColumnAlias->$attribute;
			}

			// Transofm values
			foreach($response['records'] as $key => $record) {
				NDebugger::timer("transformValues");
				$record = $this->_parseRecord($record);
				$transformedRecord  = $this->transformValues($record, $tableConfig);
				$recordsHash[$record["Id"]] = $transformedRecord;
				$durations["transformValues"] += NDebugger::timer("transformValues");

				NDebugger::timer("updateRecords");
				// Direct update query to DB
				$dbTable->getAdapter()->query("UPDATE {$tableName} SET {$attribute} = ? WHERE Id = '{$record["Id"]}'", array($transformedRecord[$attribute]));
				$durations["updateRecords"] += NDebugger::timer("updateRecords");

			}
			var_dump($durations);
		}
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
	private function _getDeletedRecords($entity, Zend_Db_Table_Abstract $dbTable) {
		$config = Zend_Registry::get("config");
		$sfc = new SforcePartnerClient();
		$sfc->createConnection("library/SalesForce/partner.wsdl.xml");
		$passSecret = $dbTable->getAdapter()->fetchOne("SELECT AES_DECRYPT(?, ?)", array($this->_user->passSecret, $config->app->salt));
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