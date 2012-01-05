<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_Account extends App_Db_Table
{
	protected $_name = 'Account';
	protected $_snapshotTableClass = 'Model_AccountSnapshot';

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

	public function insertEmptyRow($userId)
	{
		$this->add(array('_idUser' => $userId, 'Id' => '--empty--', 'Name' => '--empty--', 'Type' => null));
	}

}
