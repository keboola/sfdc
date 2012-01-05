<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_ContactSnapshot extends App_Db_Table
{
	protected $_name = 'ContactSnapshot';
	protected $_isSnapshotTable = true;

	/**
	 * @param $idUser
	 * @param $data
	 */
	public function add($data)
	{
		$this->insertOrSet($data);
	}
}
