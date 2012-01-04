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

	/**
	 * @param $idUser
	 * @param $data
	 */
	public function add($data)
	{
		if ($data['Type'] == null) {
			$data['Type'] = '--empty--';
		}
		$account = $this->fetchRow(array('_idUser=?' => $data['_idUser'], 'Id=?' => $data['Id'], 'snapshotNumber=?' => $data['snapshotNumber']));
		if (!$account) {
			$this->insert($data);
		} else {
			$account->setFromArray($data);
			$account->save();
		}
	}
}
