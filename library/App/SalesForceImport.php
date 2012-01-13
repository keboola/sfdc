<?
class App_SalesForceImport
{
	private $_user;

	/**
	 * @param $idUser
	 */
	public function __construct($user)
	{
		$this->_user = $user;
		$this->_registry = Zend_Registry::getInstance();
		$snapshotTable = new Model_BiSnapshot();
		$this->_snapshotNumber = $snapshotTable->getSnapshotNumber();
	}


	/**
	 * imports all tables
	 * @return void
	 */
	public function importAll()
	{
		$registry = Zend_Registry::getInstance();
		$userStrId = $this->_user->strId;
		foreach($registry->config->sfUser->$userStrId->tables as $table => $tableConfig) {
			$this->import($table);
		}
	}

	/**
	 * imports one table
	 * @param $tableName
	 * @return bool
	 */
	public function import($tableName) {
		$registry = Zend_Registry::getInstance();
		$userStrId = $this->_user->strId;
		if(isset($registry->config->sfUser->$userStrId->tables->$tableName)) {
			$tableConfig = $registry->config->sfUser->$userStrId->tables->$tableName;

			$table = $tableName;
			$query = '';
			if (isset($tableConfig->importQuery)) {
				$query = $tableConfig->importQuery;
			}
			if (isset($tableConfig->importQueryColumns) && count($tableConfig->importQueryColumns->toArray())) {
				$query = "SELECT " . join(",", $tableConfig->importQueryColumns->toArray()) . " FROM {$table}";
			}
			if (!$query) {
				throw new Exception("Table {$table} not configured.");
			}
			$response = $this->_query($query);

			$tableClass = "Model_" . $table;
			$dbTable = new Model_DataTable(null, null, $table);

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

			if ($response['totalSize'] > 0) {
				foreach($response['records'] as $record) {
					unset($record['attributes']);
					$record = $this->transformValues($record, $tableConfig);
					$dbTable->insertOrSet($record);
				}
			}

			$dbTable->deleteCheck();

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
		// Empty values
		if (isset($tableConfig->emptyColumn)) {
			foreach($tableConfig->emptyColumn as $emptyColumnName => $emptyColumnValue) {
				if (!isset($record[$emptyColumnName]) || $record[$emptyColumnName] == null) {
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
	 * @throws Exception
	 * @param $query
	 * @param string $queryUrl
	 * @return array|mixed
	 */
	private function _query($query, $queryUrl = '') {
		if (!$queryUrl) {
			$url = "{$this->_user->instanceUrl}/services/data/v23.0/query?q=" . urlencode($query);
		} else {
			$url = $this->_user->instanceUrl.$queryUrl;
		}

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth {$this->_user->accessToken}"));

		$json_response = curl_exec($curl);
		curl_close($curl);

		$response = json_decode($json_response, true);
		// Query more
		if (isset($response['done']) && $response['done'] === false && $response['nextRecordsUrl'] != '') {
			$responseMore = $this->_query($query, $response['nextRecordsUrl']);
			$response = array_merge_recursive($response, $responseMore);
		}
		if (isset($response[0]['errorCode'])) {
			throw new Exception($response[0]['errorCode'] . ': '. $response[0]['message']);
		}
		return $response;
	}

}