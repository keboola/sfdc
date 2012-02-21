<?php
/**
 * Script access lock
 * Inspired by:
 *  - http://www.mysqlperformanceblog.com/2009/10/14/watch-out-for-your-cron-jobs/
 *  - http://www.phpdeveloper.org.uk/mysql-named-locks/
 *
 * User: Martin Halamíček
 * Date: 27.12.11
 * Time: 9:45
 */

class App_Lock
{

	/**
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_db;
	protected $_lockNamePrefix;
	protected $_lockName;


	/**
	 * @param Zend_Db_Adapter_Abstract $db
	 * @param string $lockName Lock name is server wide - should be prefixed by db name
	 */
	public function __construct($db, $lockName)
	{
		$this->_db = $db;
		$this->_lockName = $lockName;
		$this->_lockNamePrefix = $this->_dbName();
	}

	/**
	 * @param $name
	 * @param int $timeout
	 * @return bool
	 */
	public function lock($timeout = 0)
	{
		return (bool) $this->_db->fetchOne("SELECT GET_LOCK(?, ?)", array(
			$this->_prefixedLockName(),
			$timeout,
		));
	}

	public function unlock()
	{
		$this->_db->query("DO RELEASE_LOCK(?)", array($this->_prefixedLockName()));
	}

	protected function _prefixedLockName()
	{
		return $this->_lockNamePrefix . '.' . $this->_lockName;
	}

	protected function _dbName()
	{
		return $this->_db->fetchOne("SELECT DATABASE()");
	}

}