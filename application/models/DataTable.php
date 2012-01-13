<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_DataTable extends App_Db_Table
{

	protected $_isSnapshotTable = false;

	public function __construct($config = array(), $definition = null, $name=null)
	{
		parent::__construct($config, $definition, $name);

		$this->_name = $name;
	}

	/**
	 *
	 * creates a snapshot in snapshot table
	 *
	 * @param $user
	 * @param $snapshotNumber
	 * @return void
	 */
	public function createSnapshot($snapshotNumber)
	{
		$items = $this->fetchAll();
		$snapshotTable = new Model_DataTableSnapshot(null, null, $this->_name);
		foreach ($items as $item) {
			$data = $item->toArray();
			unset($data['_id']);
			unset($data['isDeletedCheck']);
			$data['snapshotNumber'] = $snapshotNumber;
			$snapshotTable->insertOrSet($data);
		}
	}



}
