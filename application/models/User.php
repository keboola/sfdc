<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_User extends App_Db_Table
{
	protected $_name = 'User';
	protected $_snapshotTableClass = 'Model_UserSnapshot';

	/**
	 * @param $idUser
	 * @param $data
	 */
	public function add($data)
	{
		$this->insertOrSet($data);
	}

	public function insertEmptyRow()
	{
		$this->add(array('Id' => '--empty--', 'Name' => '--empty--'));
	}

}
