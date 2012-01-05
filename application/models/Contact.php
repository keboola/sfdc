<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_Contact extends App_Db_Table
{
	protected $_name = 'Contact';
	protected $_snapshotTableClass = 'Model_ContactSnapshot';

	/**
	 * @param $idUser
	 * @param $data
	 */
	public function add($data)
	{
		$this->insertOrSet($data);
	}

	public function insertEmptyRow($userId)
	{
		$this->add(array('_idUser' => $userId, 'Id' => '--empty--', 'Name' => '--empty--'));
	}

}
