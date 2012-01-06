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
		if (!isset($data['ExpectedRevenue']) || $data['ExpectedRevenue'] == null) {
			$data['ExpectedRevenue'] = 0;
		}

		$dateParts = explode("T", $data['CreatedDate']);
		$data['CreatedDate'] = $dateParts[0];

		$this->insertOrSet($data);
	}
}
