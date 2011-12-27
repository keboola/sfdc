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
	}

	public function importUsers($since)
	{
		$query = "SELECT Id,Name FROM User";
		if ($since) {
			$query .= " WHERE LastModifiedDate > {$since}";
		}
		$response = $this->query($query);
		$user = new Model_User();
		$user->add(array('_idUser' => $this->_user->id, 'Id' => '--empty--', 'Name' => '--empty--'));
		if ($response['totalSize'] > 0) {
			foreach($response['records'] as $record) {
				$record['_idUser'] = $this->_user->id;
				unset($record['attributes']);
				$user->add($record);
			}
		}
	}

	public function importOpportunities($since)
	{
		$query = "SELECT Id,AccountId,Amount,ExpectedRevenue,CloseDate,CreatedDate,IsWon,IsClosed,Name,StageName,OwnerId FROM Opportunity";
		if ($since) {
			$query .= " WHERE LastModifiedDate > {$since}";
		}
		$response = $this->query($query);
		$opportunity = new Model_Opportunity();
		if ($response['totalSize'] > 0) {
			foreach($response['records'] as $record) {
				$record['_idUser'] = $this->_user->id;
				unset($record['attributes']);
				$opportunity->add($record);
			}
		}
	}

	public function importOpportunityHistories($since)
	{
		$query = "SELECT Id,OpportunityId,Amount,ExpectedRevenue,StageName FROM OpportunityHistory";
		if ($since) {
			$query .= " WHERE SystemModstamp > {$since}";
		}
		$response = $this->query($query);
		$opportunity = new Model_OpportunityHistory();
		if ($response['totalSize'] > 0) {
			foreach($response['records'] as $record) {
				$record['_idUser'] = $this->_user->id;
				unset($record['attributes']);
				$opportunity->add($record);
			}
		}
	}

	public function importAccounts($since)
	{
		$query = "SELECT Id,Name,Type FROM Account";
		if ($since) {
			$query .= " WHERE LastModifiedDate > {$since}";
		}
		$response = $this->query($query);
		$account = new Model_Account();
		$account->add(array('_idUser' => $this->_user->id, 'Id' => '--empty--', 'Name' => '--empty--', 'Type' => null));
		if ($response['totalSize'] > 0) {
			foreach($response['records'] as $record) {
				$record['_idUser'] = $this->_user->id;
				unset($record['attributes']);
				$account->add($record);
			}
		}
	}

	public function importContacts($since)
	{
		$query = "SELECT Id,Name FROM Contact";
		if ($since) {
			$query .= " WHERE LastModifiedDate > {$since}";
		}

		$response = $this->query($query);
		$user = new Model_Contact();
		$user->add(array('_idUser' => $this->_user->id, 'Id' => '--empty--', 'Name' => '--empty--'));
		if ($response['totalSize'] > 0) {
			foreach($response['records'] as $record) {
				$record['_idUser'] = $this->_user->id;
				unset($record['attributes']);
				$user->add($record);
			}
		}

	}

	private function query($query, $queryUrl = '') {
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
		if ($response['done'] === false && $response['nextRecordsUrl'] != '') {
			$responseMore = $this->query($query, $response['nextRecordsUrl']);
			$response = array_merge_recursive($response, $responseMore);
		}
		return $response;
	}

}