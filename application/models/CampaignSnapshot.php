<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_CampaignSnapshot extends App_Db_Table
{
	protected $_name = 'CampaignSnapshot';
	protected $_snapshotTableClass = 'Model_ContactSnapshot';

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
		$user = $this->fetchRow(array('_idUser=?' => $data['_idUser'], 'Id=?' => $data['Id'], 'snapshotNumber=?' => $data['snapshotNumber']));
		if (!$user) {
			$this->insert($data);
		} else {
			$user->setFromArray($data);
			$user->save();
		}
	}
}
