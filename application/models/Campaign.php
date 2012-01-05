<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_Campaign extends App_Db_Table
{
	protected $_name = 'Campaign';
	protected $_snapshotTableClass = 'Model_CampaignSnapshot';

	/**
	 * @param $idUser
	 * @param $data
	 */
	public function add($data)
	{
		if ($data['ExpectedRevenue'] == null) {
			$data['ExpectedRevenue'] = 0;
		}
		if ($data['BudgetedCost'] == null) {
			$data['BudgetedCost'] = 0;
		}
		if ($data['ActualCost'] == null) {
			$data['ActualCost'] = 0;
		}
		if ($data['Type'] == null) {
			$data['Type'] = '--empty--';
		}
		if ($data['Status'] == null) {
			$data['Status'] = '--empty--';
		}
		if ($data['StartDate'] == null) {
			$data['StartDate'] = '1900-01-01';
		}

		$this->insertOrSet($data);

	}

}
