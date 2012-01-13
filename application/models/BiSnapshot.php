<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-14
 *
 */

class Model_BiSnapshot extends Zend_Db_Table
{
	protected $_name = 'bi_snapshot';

	/**
	 * return the string id for todays snapshot
	 * @return string
	 */
	public function getSnapshotNumber() {
		$todayNumber = date("Ymd");
		$todayDate = date("Y-m-d");
		$snapshot = $this->fetchRow(array('snapshotDate=?' => $todayNumber));
		if (!$snapshot) {
			$this->getAdapter()->query("INSERT INTO {$this->_name} SET snapshotNumber = CAST(UNIX_TIMESTAMP(DATE(NOW()))/86400 AS UNSIGNED), snapshotDate = '{$todayDate}'");
			$snapshot = $this->fetchRow(array('snapshotDate=?' => $todayNumber));
		}
		return $snapshot->snapshotNumber;
	}

}
