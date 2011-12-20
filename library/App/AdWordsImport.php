<?
class App_AdWordsImport
{
	private $_idUser;

	private $_developerToken;

	private $_oauthToken;

	private $_oauthTokenSecret;

	private $_oauthKey;

	private $_oauthSecret;


	/**
	 * @param $idUser
	 * @param $oauthToken
	 * @param $oauthTokenSecret
	 * @param $developerToken
	 * @param $oauthKey
	 * @param $oauthSecret
	 */
	public function __construct($idUser, $oauthToken, $oauthTokenSecret, $developerToken, $oauthKey, $oauthSecret)
	{
		$this->_idUser = $idUser;
		$this->_oauthToken = $oauthToken;
		$this->_oauthTokenSecret = $oauthTokenSecret;
		$this->_developerToken = $developerToken;
		$this->_oauthKey = $oauthKey;
		$this->_oauthSecret = $oauthSecret;
	}

	/**
	 * Get all clients managed by current MCC and save them to db
	 * @param $idManager
	 */
	public function importClients($idManager)
	{
		$_a = new Model_Accounts();

		$aw = $this->adWords($idManager);

		$clients = $aw->clients();
		foreach($clients as $client) if (!$client->canManageClients) {
			$_a->add($this->_idUser, $client);
		}
	}


	/**
	 * @param $since
	 * @param $until
	 * @return bool
	 */
	public function importCampaigns($since, $until)
	{
		$_a = new Model_Accounts();
		$_c = new Model_Campaigns();
		$_cs = new Model_CampaignStats();

		foreach($_a->fetchAll(array('idUser=?' => $this->_idUser)) as $a) {
			$aw = $this->adWords($a->idAdWords);

			$campaigns = $aw->campaigns($since, $until);
			foreach ($campaigns as $campaignAW) {
				$_c->add($a->id, $campaignAW);
			}

			$report = $aw->campaignReport($since, $until);
			foreach ($report as $id => $days) {
				$c = $_c->fetchRow(array('idAccount=?' => $a->id, 'idAdWords=?' => $id));
				foreach ($days as $date => $stats) {
					$_cs->add($a->id, $c->id, $date, $stats);
				}
			}
		}

		return TRUE;
	}


	/**
	 * @param $idClient
	 * @param int $tries
	 * @return App_AdWords
	 * @throws Exception
	 */
	public function adWords($idClient, $tries=3)
	{
		try {
			return new App_AdWords($this->_developerToken, $this->_oauthToken, $this->_oauthTokenSecret, $this->_oauthKey, $this->_oauthSecret, $idClient);
		} catch (Exception $e) {
			App_Debug::send($e);
			if ($tries > 0) {
				sleep(10);
				return $this->adWords($idClient, $tries-1);
			} else {
				throw new Exception($e->getMessage());
			}
		}
	}




}