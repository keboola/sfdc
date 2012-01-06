<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_AccountSnapshot extends App_Db_Table
{
	protected $_name = 'AccountSnapshot';
	protected $_isSnapshotTable = true;

	/**
	 * @param $idUser
	 * @param $data
	 */
	public function add($data)
	{
		if (!isset($data['Type']) || $data['Type'] == null) {
			$data['Type'] = '--empty--';
		}
		$this->insertOrSet($data);
	}
}