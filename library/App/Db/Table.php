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
	protected $_snapshotTableClass;
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
	public function prepareDeleteCheck($idUser)
	{
		$this->update(array("isDeletedCheck" => 1), array('_idUser=?' => $idUser));
	}

	/**
	 * set all rows as deleted that didnt pass the check
	 * @param $idUser
	 * @return void
	 */
	public function deleteCheck($idUser)
	{
		$this->update(array("isDeleted" => 1), array('_idUser=?' => $idUser, "isDeletedCheck=?" => 1, "Id!=?" => '--empty--'));
	}

	/**
	 *
	 * creates a snapshot in snapshot table
	 *
	 * @param $user
	 * @param $snapshotNumber
	 * @return void
	 */
	public function createSnapshot($user, $snapshotNumber)
	{
		$items = $this->fetchAll(array('_idUser=?' => $user));
		$snapshotTable = new $this->_snapshotTableClass;
		foreach ($items as $item) {
			$data = $item->toArray();
			unset($data['_id']);
			unset($data['isDeletedCheck']);
			$data['snapshotNumber'] = $snapshotNumber;
			$snapshotTable->add($data);
		}
	}

	public function insertOrSet($data)
	{
		if ($this->_isSnapshotTable) {
			$condition = array('_idUser=?' => $data['_idUser'], 'Id=?' => $data['Id'], 'snapshotNumber=?' => $data['snapshotNumber']);
		} else {
			$condition = array('_idUser=?' => $data['_idUser'], 'Id=?' => $data['Id']);
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

}