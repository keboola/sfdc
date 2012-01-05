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
		$snapshot = $this->fetchRow(array('snapshotNumber=?' => $todayNumber));
		if (!$snapshot) {
			$this->insert(array('snapshotNumber' => $todayNumber, 'snapshotDate' => $todayDate));
		}
		return $todayNumber;
	}

}
