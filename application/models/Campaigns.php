<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2. 9. 2011
 *
 */

class Model_Campaigns extends App_Db_Table
{
	protected $_name = 'campaigns';

	/**
	 * @param $idAccount
	 * @param $response
	 * @return array
	 */
	public function prepareValues($idAccount, $response)
	{
		return array(
			'idAccount'	=> $idAccount,
			'idAdWords'	=> $response->id,
			'name'		=> App_GoodData::escapeString($response->name),
			'startDate'	=> date('Y-m-d', strtotime($response->startDate)),
			'endDate'	=> date('Y-m-d', strtotime($response->endDate))
		);
	}

	/**
	 * @param $idAccount
	 * @param $response
	 * @return App_Model_Row_Campaign
	 */
	public function add($idAccount, $response)
	{
		$newValues = $this->prepareValues($idAccount, $response);

		$campaign = $this->fetchRow(array('idAccount=?' => $idAccount, 'idAdWords=?' => $newValues['idAdWords']));
		if ($campaign) {
			$campaign->setFromArray($newValues);
			return $campaign;
		} else {
			$idCampaign = $this->insert($newValues);
			return $this->fetchRow(array('id=?' => $idCampaign));
		}
	}
}
