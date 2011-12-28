<?php
/**
 * Class to send Facebook statistics to GoodData
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 29.6.11, 14:25
 *
 */

class App_GoodDataExport
{

	/**
	 * Id of demo project where we send all data
	 */
	const DEMO_PROJECT = 1;

	/**
	 * @var string
	 */
	private $_xmlPath;

	/**
	 * @var string Path to temp dir for csv files
	 */
	private $_tmpPath;

	/**
	 * @var \App_GoodData
	 */
	private $_gd;

	/**
	 * @var Zend_Config
	 */
	private $_config;

	/**
	 * @var int
	 */
	private $_idUser;

	/**
	 * @var string
	 */
	private $_idProject;


	/**
	 * @param $idProject
	 * @param $idUser
	 * @param $config
	 */
	public function __construct($idProject, $idUser, $config)
	{
		$this->_gd = new App_GoodData($config->gooddata->username, $config->gooddata->password, $idProject);

		$this->_idProject = $idProject;
		$this->_idUser = $idUser;
		$this->_config = $config;

		$this->_xmlPath = realpath(APPLICATION_PATH . '/../gooddata');
		$this->_tmpPath = realpath(APPLICATION_PATH . '/../tmp');
	}

	/**
	 * @param $table
	 * @param bool $return
	 * @param bool $structure
	 * @param bool $all
	 * @return bool|string|void
	 */
	public function dumpTable($table, $return=false, $structure=false, $all=false)
	{
		$isDemo = $this->_idUser == self::DEMO_PROJECT;
		$prefix = $this->_config->db->prefix;

		switch($table) {
			case 'Opportunity':
				$sql = 'SELECT t.Id, t.AccountId, t.Amount, t.ExpectedRevenue, t.CloseDate, t.CreatedDate, t.IsWon, t.IsClosed, t.Name, t.StageName, t.OwnerId '
					. 'FROM '.$prefix.'Opportunity t '
					. 'WHERE t._idUser = '.$this->_idUser;
				break;
			case 'OpportunityHistory':
				$sql = 'SELECT t.Id, t.OpportunityId, t.Amount, t.ExpectedRevenue, t.StageName '
					. 'FROM '.$prefix.'OpportunityHistory t '
					. 'WHERE t._idUser = '.$this->_idUser;
				break;
			case 'Account':
				$sql = 'SELECT t.Id, t.Name, t.Type '
					. 'FROM '.$prefix.'Account t '
					//. 'LEFT JOIN '.$prefix.'accounts a ON (t.idAccount = a.id)'
					. 'WHERE t._idUser = '.$this->_idUser;
				break;
			case 'User':
				$sql = 'SELECT t.Id, t.Name '
					. 'FROM '.$prefix.'User t '
					//. 'LEFT JOIN '.$prefix.'accounts a ON (t.idAccount = a.id)'
					. 'WHERE t._idUser = '.$this->_idUser;
				break;
			case 'Contact':
				$sql = 'SELECT t.Id, t.Name '
					. 'FROM '.$prefix.'Contact t '
					//. 'LEFT JOIN '.$prefix.'accounts a ON (t.idAccount = a.id)'
					. 'WHERE t._idUser = '.$this->_idUser;
				break;
			case 'Task':
				$sql = 'SELECT t.Id, t.AccountId, t.OwnerId, t.ActivityDate, t.Priority, t.Status, t.Subject, t.IsClosed '
					. 'FROM '.$prefix.'Task t '
					//. 'LEFT JOIN '.$prefix.'accounts a ON (t.idAccount = a.id)'
					. 'WHERE t._idUser = '.$this->_idUser;
				break;
			case 'Event':
				$sql = 'SELECT t.Id, t.AccountId, t.OwnerId, t.ActivityDate, t.Subject '
					. 'FROM '.$prefix.'Event t '
					//. 'LEFT JOIN '.$prefix.'accounts a ON (t.idAccount = a.id)'
					. 'WHERE t._idUser = '.$this->_idUser;
				break;
			case 'Campaign':
				$sql = 'SELECT t.Id, t.OwnerId, t.Name, t.ExpectedRevenue, t.BudgetedCost, t.ActualCost, t.StartDate, t.Type, t.Status '
					. 'FROM '.$prefix.'Campaign t '
					//. 'LEFT JOIN '.$prefix.'accounts a ON (t.idAccount = a.id)'
					. 'WHERE t._idUser = '.$this->_idUser;
				break;
			default:
				return false;
		}

		if ($structure) {
			$sql .= ' LIMIT 1';
		} elseif (!$all) {
			// TODO WTF is this?
			$sql .= ' AND t.timestamp > \''.date('Y-m-d H:i:s', strtotime('-4 days')).'\'';
		}

		$file = null;
		if (!$return)
			$file = $this->_tmpPath.'/'.$table.'.csv';
		return $this->dump($sql, $file);
	}

	/**
	 * @param $dataset
	 * @return void
	 */
	public function createDataset($dataset)
	{
		$this->dumpTable($dataset, false, true);
		$this->_gd->createDataset($this->_xmlPath . '/'.$dataset.'.xml', $this->_tmpPath.'/'.$dataset.'.csv');
	}

	/**
	 * @param $dataset
	 * @param bool $all
	 * @return void
	 */
	public function loadDataset($dataset, $all=true)
	{
		$this->dumpTable($dataset, false, false, $all);
		$this->_gd->loadData($this->_xmlPath . '/'.$dataset.'.xml', $this->_tmpPath.'/'.$dataset.'.csv', !$all);
	}

	/**
	 * Updates dataset in GoodData
	 * @param $dataset
	 * @return void
	 */
	public function updateStructure($dataset)
	{
		$this->dumpTable($dataset, false, true);
		$this->_gd->updateDataset($this->_xmlPath . '/'.$dataset.'.xml', $this->_tmpPath.'/'.$dataset.'.csv', $this->_idUser);
	}

	/**
	 * Create data sets in GoodData
	 * @return void
	 */
	public function setup()
	{
		$this->_gd->createDate('SF_OpportunityCloseDate', FALSE);
		$this->_gd->createDate('SF_OpportunityCreatedDate', FALSE);
		$this->_gd->createDate('SF_TaskActivityDate', FALSE);
		$this->_gd->createDate('SF_EventActivityDate', FALSE);
		$this->_gd->createDate('SF_CampaignStartDate', FALSE);
		$this->createDataset('User');
		$this->createDataset('Account');
		$this->createDataset('Opportunity');
		$this->createDataset('OpportunityHistory');
		$this->createDataset('Contact');
		$this->createDataset('Task');
		$this->createDataset('Event');
		$this->createDataset('Campaign');

	}

	/**
	 * Loads data to all data sets in GoodData
	 * @param bool $all
	 * @return void
	 */
	public function loadData($all=false)
	{
		$this->loadDataset('User', TRUE);
		$this->loadDataset('Account', TRUE);
		$this->loadDataset('Opportunity', TRUE);
		$this->loadDataset('OpportunityHistory', TRUE);
		$this->loadDataset('Contact', TRUE);
		$this->loadDataset('Task', TRUE);
		$this->loadDataset('Event', TRUE);
		$this->loadDataset('Campaign', TRUE);
		$this->_gd->updateReports();
	}


	public function idProject()
	{
		return $this->_idProject;
	}


	/**
	 * @param string $sql
	 * @param string $file Save output to file if set, return otherwise
	 * @return void
	 */
	public function dump($sql, $file='')
	{
		$command = 'mysql -u '.$this->_config->db->login.' -p'.$this->_config->db->password.' -h '.$this->_config->db->host
			.' '.$this->_config->db->db.' -B -e "'.$sql.'" | sed \'s/\t/","/g;s/^/"/;s/$/"/;s/\n//g\'';

		if ($file) {
			$command .= ' > '.$file;
		}

		$output = shell_exec($command);
		if ($file && $output) {
			App_Debug::send($output);
		}

		if(!$file)
			return $output;
	}

	/**
	 * @return string
	 */
	public function importDashboard()
	{
		echo "\n*** Import dashboard\n";
		return App_GoodDataService::importDashboard(1, $this->_idProject);
	}

	/**
	 * @param $email
	 * @return string
	 */
	public function inviteUser($email)
	{
		echo "\n*** Invite user: $email\n";
		return App_GoodDataService::inviteUser($email, $this->_idProject);
	}



}
