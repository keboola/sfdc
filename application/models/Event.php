<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_Event extends App_Db_Table
{
	protected $_name = 'Event';
	protected $_snapshotTableClass = 'Model_EventSnapshot';

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
