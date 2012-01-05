<?php
/**
 * společná abstrakce pro všechny rows
 *
 * @author Ondřej Hlaváček <ondrej.hlavacek@keboola.com>
 */

class App_Db_Table_Row extends Zend_Db_Table_Row
{

	/**
	 * zda se změnila data
	 *
	 * @var boolean
	 */
	var $_changed = false;

	/**
	 * vrací, zda byla data změněna
	 * @return boolean
	 */
	public function isChanged()
	{
		return $this->_changed;
	}

	/**
	 * univerzální setter, který sleduje změny
	 *
	 * @param string $columnName název proměnné
	 * @param mixed $value mixed obsah proměnné
	 */
	public function __set($columnName, $value) {

		if ($value != $this->$columnName && $columnName != 'isDeletedCheck' && $columnName != 'lastModificationDate') {
			$this->_changed = true;
		}
		parent::__set($columnName, $value);
	}


	public function  isFieldChanged($fieldName) {
		return array_key_exists($fieldName, $this->_modifiedFields);
	}

}

