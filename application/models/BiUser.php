<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-14
 *
 */

class Model_BiUser extends Zend_Db_Table
{
	protected $_name = 'bi_user';
	protected $_rowClass = 'Model_Row_BiUser';

	public function init() {
		$this->_setAdapter(Zend_Registry::get("db"));
	}

}
