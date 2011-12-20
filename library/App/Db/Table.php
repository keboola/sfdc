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

}