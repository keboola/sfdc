<?
require_once 'Google/Api/Ads/AdWords/Lib/AdWordsUser.php';
require_once 'Google/Api/Ads/AdWords/Util/ReportUtils.php';

class App_AdWords
{

	/**
	 * @var \AdWordsUser
	 */
	private $_user;

	private $_idClient;


	/**
	 * @param $developerToken
	 * @param $oauthToken
	 * @param $oauthTokenSecret
	 * @param $oauthKey
	 * @param $oauthSecret
	 * @param $idClient
	 */
	public function __construct($developerToken, $oauthToken, $oauthTokenSecret, $oauthKey, $oauthSecret, $idClient)
	{
		$this->_idClient = $idClient;

		$this->_user = new AdWordsUser();
		$this->_user->SetOAuthInfo(array(
			'oauth_consumer_key'	=> $oauthKey,
			'oauth_consumer_secret'	=> $oauthSecret,
			'oauth_token'			=> $oauthToken,
			'oauth_token_secret'	=> $oauthTokenSecret
		));
		$this->_user->SetClientId($idClient);
		$this->_user->SetDeveloperToken($developerToken);
	}

	/**
	 * Returns accounts managed by current MCC
	 * @param int $tries
	 * @return array
	 * @throws Exception
	 */
	public function clients($tries=3)
	{
		try {
			$service =  $this->_user->GetService('ServicedAccountService');
			$selector = new ServicedAccountSelector();
			$selector->fields = array('CustomerId', 'Login', 'CompanyName');
			$result = $service->get($selector);

			if (isset($result->accounts) && is_array($result->accounts))
				return $result->accounts;
			else
				return array();
		} catch (Exception $e) {
			App_Debug::send($e);
			if ($tries > 0) {
				sleep(10);
				return $this->clients($tries-1);
			} else {
				throw new Exception($e->getMessage());
			}
		}
	}

	/**
	 * @param $since
	 * @param $until
	 * @param int $tries
	 * @return mixed
	 */
	public function campaigns($since, $until, $tries=3)
	{
		try {
			$this->_user->LoadService('CampaignService');

			$service = $this->_user->GetService('CampaignService');
			$selector = new Selector();
			$selector->fields = array('Id', 'Name', 'StartDate', 'EndDate');
			$selector->dateRange = new DateRange($since, $until);
			$result = $service->get($selector);

			if (isset($result->entries) && is_array($result->entries))
				return $result->entries;
			else
				return array();
		} catch (Exception $e) {
			App_Debug::send($e);
			if ($tries > 0) {
				sleep(10);
				return $this->campaigns($since, $until, $tries-1);
			} else {
				throw new Exception($e->getMessage());
			}
		}
	}


	/**
	 * @param $since
	 * @param $until
	 * @param int $tries
	 * @return array
	 */
	public function campaignReport($since, $until, $tries=3)
	{
		try {
			$this->_user->LoadService('ReportDefinitionService');

			$selector = new Selector();
			$selector->fields = array('Clicks', 'Cost', 'Date', 'Id', 'Impressions');
			$selector->dateRange = new DateRange($since, $until);

			$definition = new ReportDefinition();
			$definition->reportName = 'Cross-client campaign performance report #'. uniqid();
			$definition->dateRangeType = 'CUSTOM_DATE';
			$definition->reportType = 'CAMPAIGN_PERFORMANCE_REPORT';
			$definition->downloadFormat = 'XML';
			$definition->selector = $selector;

			$reportFile = ROOT_PATH.'/tmp/report-'.$this->_idClient.'.xml';
			ReportUtils::DownloadReport($definition, $reportFile, $this->user());

			if (file_exists($reportFile)) {
				$xml = simplexml_load_file($reportFile);
				$data = array();
				foreach($xml->table->row as $row) {
					$subData = array();
					foreach($row->attributes() as $k => $v) {
						$subData[$k] = (string)$v;
					}
					$data[$subData['campaignID']][$subData['day']] = $subData;
				}

				return $data;
			}
		} catch (Exception $e) {
			App_Debug::send($e);
			if ($tries > 0) {
				sleep(10);
				return $this->campaignReport($since, $until, $tries-1);
			} else {
				throw new Exception($e->getMessage());
			}
		}
	}


	/**
	 * @return AdWordsUser
	 */
	public function user()
	{
		return $this->_user;
	}

}