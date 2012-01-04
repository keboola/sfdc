<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_TaskSnapshot extends App_Db_Table
{
	protected $_name = 'TaskSnapshot';

	/**
	 * @param $idUser
	 * @param $data
	 */
	public function add($data)
	{
		if ($data['AccountId'] == null) {
			$data['AccountId'] = '--empty--';
		}
		if ($data['ActivityDate'] == null) {
			$data['ActivityDate'] = '1900-01-01';
		}

		$user = $this->fetchRow(array('_idUser=?' => $data['_idUser'], 'Id=?' => $data['Id'], 'snapshotNumber=?' => $data['snapshotNumber']));
		if (!$user) {
			$this->insert($data);
		} else {
			$user->setFromArray($data);
			$user->save();
		}
	}
}
