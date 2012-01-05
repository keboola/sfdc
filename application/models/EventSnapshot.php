<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_EventSnapshot extends App_Db_Table
{
	protected $_name = 'EventSnapshot';
	protected $_isSnapshotTable = true;

	/**
	 * @param $idUser
	 * @param $data
	 */
	public function add($data)
	{
		if ($data['AccountId'] == null) {
			$data['AccountId'] = '--empty--';
		}
		$this->insertOrSet($data);
	}
}
