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
		if ($data['Type'] == null) {
			$data['Type'] = '--empty--';
		}
		$data['isDeleted'] = 0;
		$account = $this->fetchRow(array('_idUser=?' => $data['_idUser'], 'Id=?' => $data['Id']));
		if (!$account) {
			$this->insert($data);
		} else {
			$account->setFromArray($data);
			$account->save();
		}
	}

}
