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

	/**
	 * @param $idUser
	 * @param $data
	 */
	public function add($data)
	{
		$user = $this->fetchRow(array('_idUser=?' => $data['_idUser'], 'Id=?' => $data['Id']));
		if (!$user) {
			$this->insert($data);
		} else {
			$user->setFromArray($data);
			$user->save();
		}
	}
}
