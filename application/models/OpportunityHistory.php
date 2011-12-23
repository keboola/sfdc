<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_OpportunityHistory extends App_Db_Table
{
	protected $_name = 'OpportunityHistory';

	/**
	 * @param $idUser
	 * @param $data
	 */
	public function add($data)
	{
		if ($data['Amount'] == null) {
			$data['Amount'] = 0;
		}
		if ($data['ExpectedRevenue'] == null) {
			$data['ExpectedRevenue'] = 0;
		}
		$opportunity = $this->fetchRow(array('_idUser=?' => $data['_idUser'], 'Id=?' => $data['Id']));
		if (!$opportunity) {
			$this->insert($data);
		} else {
			$opportunity->setFromArray($data);
			$opportunity->save();
		}
	}
}
