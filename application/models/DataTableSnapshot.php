<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_DataTableSnapshot extends App_Db_Table
{

	protected $_isSnapshotTable = true;

	public function __construct($config = array(), $definition = null, $name=null)
	{
		parent::__construct($config, $definition, $name);
		$this->_name = $name . "Snapshot";
	}
}
