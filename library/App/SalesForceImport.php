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

	public function importUsers()
	{
		$response = $this->query("SELECT Id,Name FROM User");
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

	public function importOpportunities()
	{
		$response = $this->query("SELECT Id,AccountId,Amount,ExpectedRevenue,CloseDate,CreatedDate,IsWon,IsClosed,Name,StageName,OwnerId FROM Opportunity");
		$opportunity = new Model_Opportunity();
		if ($response['totalSize'] > 0) {
			foreach($response['records'] as $record) {
				$record['_idUser'] = $this->_user->id;
				unset($record['attributes']);
				$opportunity->add($record);
			}
		}
	}

	public function importAccounts()
	{
		$response = $this->query("SELECT Id,Name,Type FROM Account");
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

	private function query($query) {
		$url = "{$this->_user->instanceUrl}/services/data/v23.0/query?q=" . urlencode($query);

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth {$this->_user->accessToken}"));

		$json_response = curl_exec($curl);
		curl_close($curl);


		$response = json_decode($json_response, true);
		return $response;
	}



}