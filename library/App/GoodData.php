<?php
/**
 * GoodData API class
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 29.6.11, 13:51
 * 
 */
 
class App_GoodData
{
	/**
	 * Path to GD CLI
	 */
	const CLI_PATH = '/opt/ebs-disk/GD/cli/bin/gdi.sh';


	/**
	 * @var GD username
	 */
	private $_username;

	/**
	 * @var GD password
	 */
	private $_password;

	/**
	 * @var id of GD project
	 */
	private $_idProject;

	
	/**
	 * @param $username
	 * @param $password
	 * @param $idProject
	 */
	public function __construct($username, $password, $idProject)
	{
		$this->_username = $username;
		$this->_password = $password;
		$this->_idProject = $idProject;
	}

	/**
	 * Common wrapper for GD CLI commands
	 * @param array $args
	 * @param bool $reportErrors
	 * @return void
	 */
	public function call($args, $reportErrors=true)
	{
		$command = self::CLI_PATH.' -u '.$this->_username.' -p '.$this->_password.' -e \'OpenProject(id="'.$this->_idProject.'");';
		$command .= $args;

		$output = shell_exec($command.'\'');

		if (strpos($output, '503 Service Unavailable')
			|| strpos($output, 'Error invoking GoodData WebDav API')
			|| strpos($output, '404 Not Found')
			|| strpos($output, '500 Internal Server Error')) {

			$log = Zend_Registry::get('log');
			$log->log('GoodData Service Unavailable', Zend_Log::NOTICE, array(
				'pid' => $this->_idProject
			));
			sleep(60);
			$this->call($args, $reportErrors);
		} else {
			if ($reportErrors && strpos($output, 'ERROR')) {
				$debugFile = ROOT_PATH . '/tmp/debug-' . date('Ymd-His').'.log';
				system('mv debug.log ' . $debugFile);
				$log = Zend_Registry::get('log');
				$log->log('GoodData export error', Zend_Log::ERR, array(
					'pid'		=> $this->_idProject,
					'error'		=> $output,
					'debugFile' => $debugFile
				));
			}

			echo $output;
			system('rm ./*.log*');
		}
	}

	/**
	 * Set of commands which create a date
	 * @param $name
	 * @param $includeTime
	 * @return void
	 */
	public function createDate($name, $includeTime=FALSE)
	{
		echo "\n".'*** Create date: '.$name."\n";
		$maqlFile = ROOT_PATH.'/tmp/temp.maql';

		$command = 'UseDateDimension(name="'.$name.'", includeTime="'.($includeTime ? 'true' : 'false').'");';
		$command .= 'GenerateMaql(maqlFile="'.$maqlFile.'");';
		$command .= 'ExecuteMaql(maqlFile="'.$maqlFile.'");';
		$command .= 'TransferData();';

		$this->call($command);
		system('rm -rf '.$maqlFile);
	}

	/**
	 * Update reports command
	 */
	public function updateReports()
	{
		echo "\n".'*** Updating Reports'."\n";
		$maqlFile = ROOT_PATH.'/tmp/temp.maql';

		$command = 'GetReports(fileName="'.$maqlFile.'");';
		$command .= 'ExecuteReports(fileName="'.$maqlFile.'");';

		$this->call($command, false);
		system('rm -rf '.$maqlFile);
	}

	/**
	 * Set of commands which create a dataset
	 * @param $xml
	 * @param $csv
	 * @return void
	 */
	public function createDataset($xml, $csv)
	{
		echo "\n".'*** Create dataset: '.basename($xml)."\n";
		$maqlFile = ROOT_PATH.'/tmp/temp.maql';

		$command = 'UseCsv(csvDataFile="' . $csv . '", hasHeader="true", configFile="' . $xml . '");';
		$command .= 'GenerateMaql(maqlFile="'.$maqlFile.'");';
		$command .= 'ExecuteMaql(maqlFile="'.$maqlFile.'");';

		$this->call($command);
		system('rm -rf '.$maqlFile);
	}

	/**
	 * Set of commands which create a dataset
	 * @param $xml
	 * @param $csv
	 * @param $idUser
	 */
	public function updateDataset($xml, $csv, $idUser)
	{
		echo "\n".'*** Update dataset: '.basename($xml)."\n";
		$maqlFile = ROOT_PATH.'/tmp/update-'.$idUser.'-'.basename($xml).'-'.date('Ymd-His').'.maql';

		$command = 'UseCsv(csvDataFile="' . $csv . '", hasHeader="true", configFile="' . $xml . '");';
		$command .= 'GenerateUpdateMaql(maqlFile="'.$maqlFile.'");';
		$command .= 'ExecuteMaql(maqlFile="'.$maqlFile.'");';

		$this->call($command);
	}

	/**
	 * Set of commands which loads data to data set
	 * @param $xml
	 * @param $csv
	 * @param bool $incremental
	 * @return void
	 */
	public function loadData($xml, $csv, $incremental=false)
	{
		echo "\n".'*** Load data: '.basename($csv)."\n";
		$command = 'UseCsv(csvDataFile="' . $csv . '", hasHeader="true", configFile="' . $xml . '");';
		$command .= 'TransferData(incremental="'.($incremental ? 'true' : 'false').'", waitForFinish="true");';

		$this->call($command);
	}

	/**
	 * @static
	 * @param $string
	 * @param bool $stripQuotes
	 * @param bool $shorten
	 * @return mixed|string
	 */
	public static function escapeString($string, $stripQuotes=FALSE, $shorten=TRUE)
	{
		if($stripQuotes) {
			$result = str_replace('"', '', $string);
		} else {
			$result = str_replace('"', '""', (string)$string);
		}
		if ($shorten)
			$result = substr(trim($result), 0, 255);

		// remove trailing quotation mark if there is only one in the end of string
		if(substr($result, strlen($result)-1) == '"' && substr($result, strlen($result)-2) != '""') {
			$result = substr($result, 0, strlen($result)-1);
		}
		return $result;
	}

}
