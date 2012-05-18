<?php
/**
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-11-07
 */

class App_Db_Table extends Zend_Db_Table
{
	/**
	 * @var string
	 */
	protected $_name;
	protected $_isSnapshotTable = false;
	protected $_rowClass = 'App_Db_Table_Row';


	/**
	 * @param array $config
	 * @param null $definition
	 * @param null $name
	 */
	public function __construct($config = array(), $definition = null, $name=null)
	{
		parent::__construct($config, $definition);

		$c = Zend_Registry::get('config');
		$this->_name = $c->db->prefix.(!empty($name) ? $name : $this->_name);
	}

	/**
	 * prepare all rows for delete check
	 * @param $idUser
	 * @return void
	 */
	public function prepareDeleteCheck()
	{
		if ($this->isSnapshotTable()) {
			return;
		}
		$this->update(array("isDeletedCheck" => 1), array('1=?' => 1));
	}

	/**
	 * set all rows as deleted that didnt pass the check
	 * @param $idUser
	 * @return void
	 */
	public function deleteCheck($ids=null)
	{
		if ($this->isSnapshotTable()) {
			return;
		}

		if ($ids===null)
		{
			$this->update(array("isDeleted" => 1), array("isDeletedCheck=?" => 1, "Id!=?" => '--empty--'));
		}
		else
		{
			$this->update(array("isDeleted" => 0, "isDeletedCheck" => 0), "isDeletedCheck = 1 AND Id IN (" . join(",", $ids) .")");
		}
	}

	public function insertOrSet($data)
	{
		if ($this->_isSnapshotTable) {
			$condition = array('Id=?' => $data['Id'], 'snapshotNumber=?' => $data['snapshotNumber']);
		} else {
			$condition = array('Id=?' => $data['Id']);
			$data['isDeletedCheck'] = 0;
			$data['isDeleted'] = 0;
		}
		$row = $this->fetchRow($condition);
		if (!$row) {
			$data['lastModificationDate'] = date("Y-m-d");
			$this->insert($data);
		} else {
			$row->setFromArray($data);
			if ($row->isChanged() && !$this->_isSnapshotTable) {
				$row->lastModificationDate = date("Y-m-d");
			}
			$row->save();
		}
	}

	public function isSnapshotTable()
	{
		return $this->_isSnapshotTable;
	}

}