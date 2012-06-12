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
			// Fill older missing snapshots
			$lastSnapshot = $this->getAdapter()->fetchOne("SELECT MAX(snapshotDate) FROM {$this->_name}");
			if ($lastSnapshot) {
				// Insert empty snapshot into db
				$dateTo = date("Y-m-d", strtotime("yesterday"));
				$currentDate = $lastSnapshot;
				while($currentDate < $dateTo) {
					$currentDate = date("Y-m-d", strtotime("+1 day", strtotime($currentDate)));
					$this->getAdapter()->query("INSERT INTO {$this->_name} SET snapshotNumber = CAST(UNIX_TIMESTAMP('{$currentDate}')/86400 AS UNSIGNED), snapshotDate = '{$currentDate}'");
				}
			}
			$this->getAdapter()->query("INSERT INTO {$this->_name} SET snapshotNumber = CAST(UNIX_TIMESTAMP(DATE(NOW()))/86400 AS UNSIGNED), snapshotDate = '{$todayDate}'");
			$snapshot = $this->fetchRow(array('snapshotDate=?' => $todayNumber));
		}
		return $snapshot->snapshotNumber;
	}

}
