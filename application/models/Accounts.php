<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_Accounts extends App_Db_Table
{
	protected $_name = 'accounts';

	/**
	 * @param $idUser
	 * @param $data
	 */
	public function add($idUser, $data)
	{
		$a = $this->fetchRow(array('idUser=?' => $idUser, 'idAdWords=?' => $data->customerId));
		if (!$a) {
			$this->insert(array(
				'idUser'	=> $idUser,
				'idAdWords'	=> $data->customerId,
				'email'		=> $data->login,
				'name'		=> $data->companyName
			));
		}
	}
}
