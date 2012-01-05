<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_Task extends App_Db_Table
{
	protected $_name = 'Task';
	protected $_snapshotTableClass = 'Model_TaskSnapshot';

	/**
	 * @param $idUser
	 * @param $data
	 */
	public function add($data)
	{
		if ($data['AccountId'] == null) {
			$data['AccountId'] = '--empty--';
		}
		if ($data['Subject'] == null) {
			$data['Subject'] = '--empty--';
		}
		if ($data['ActivityDate'] == null) {
			$data['ActivityDate'] = '1900-01-01';
		}
		$this->insertOrSet($data);
	}
}
