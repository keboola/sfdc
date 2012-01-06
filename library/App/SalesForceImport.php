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

	public function importAll()
	{
		$this->importContacts();
		$this->importUsers();
		$this->importOpportunities();
		$this->importOpportunityHistory();
		$this->importAccounts();
		$this->importTasks();
		$this->importEvents();
		$this->importCampaigns();
	}

	public function importUsers()
	{
		$query = "SELECT Id,Name FROM User";
		$this->_processImport($query, "Model_User", true);
	}

	public function importCampaigns()
	{
		$query = "SELECT Id,OwnerId,Name,ExpectedRevenue,BudgetedCost,ActualCost,StartDate,Type,Status FROM Campaign";
		$this->_processImport($query, "Model_Campaign");
	}

	public function importAccounts()
	{
//		$query = "SELECT Id,Name,Type FROM Account";
		$query = "SELECT Id,Name FROM Account";
		$this->_processImport($query, "Model_Account", true);
	}

	public function importContacts()
	{
		$query = "SELECT Id,Name FROM Contact";
		$this->_processImport($query, "Model_Contact", true);
	}

	public function importEvents()
	{
		$query = "SELECT AccountId,ActivityDate,Id,OwnerId,Subject FROM Event";
		$this->_processImport($query, "Model_Event");
	}

	public function importTasks()
	{
		$query = "SELECT AccountId,ActivityDate,Id,IsClosed,OwnerId,Priority,Status,Subject FROM Task";
		$this->_processImport($query, "Model_Task");
	}

	public function importOpportunities()
	{
//		$query = "SELECT Id,AccountId,Amount,ExpectedRevenue,CloseDate,CreatedDate,IsWon,IsClosed,Name,StageName,OwnerId FROM Opportunity";
		$query = "SELECT Id,AccountId,Amount,CloseDate,CreatedDate,IsWon,IsClosed,Name,StageName,ForecastCategory,Probability,OwnerId FROM Opportunity";
		$this->_processImport($query, "Model_Opportunity");
	}

	public function importOpportunityHistory()
	{
		$query = "SELECT Amount,CloseDate,CreatedDate,ForecastCategory,Id,OpportunityId,Probability,StageName,SystemModstamp FROM OpportunityHistory";
		$this->_processImport($query, "Model_OpportunityHistory", false, false);
	}


	private function _processImport($query, $tableClass, $emptyRow=false, $createSnapshot=true)
	{
		$response = $this->_query($query);
		$dbTable = new $tableClass;
		$dbTable->prepareDeleteCheck($this->_user->id);
		if ($emptyRow) {
			$dbTable->insertEmptyRow($this->_user->id);
		}

		if ($response['totalSize'] > 0) {
			foreach($response['records'] as $record) {
				$record['_idUser'] = $this->_user->id;
				unset($record['attributes']);
				$dbTable->add($record);
			}
		}

		$dbTable->deleteCheck($this->_user->id);

		if ($createSnapshot) {
			$dbTable->createSnapshot($this->_user->id, $this->_snapshotNumber);
		}
	}

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