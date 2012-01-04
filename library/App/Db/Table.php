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
	 * set all rows as deleted
	 * @param $idUser
	 * @return void
	 */
	public function deleteAll($idUser)
	{
		$this->update(array("isDeleted" => 1), array('_idUser=?' => $idUser));
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
			$data['snapshotNumber'] = $snapshotNumber;
			$snapshotTable->add($data);
		}

	}

}