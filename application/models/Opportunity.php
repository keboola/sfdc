<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_Opportunity extends App_Db_Table
{
	protected $_name = 'Opportunity';
	protected $_snapshotTableClass = 'Model_OpportunitySnapshot';

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
		if ($data['AccountId'] == null) {
			$data['AccountId'] = '--empty--';
		}
		if ($data['OwnerId'] == null) {
			$data['OwnerId'] = '--empty--';
		}

		$dateParts = explode("T", $data['CreatedDate']);
		$data['CreatedDate'] = $dateParts[0];

		$this->insertOrSet($data);
	}
}
