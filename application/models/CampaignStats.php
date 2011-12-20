<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_CampaignStats extends App_Db_Table
{
	protected $_name = 'campaignStats';

	/**
	 * @param $idAccount
	 * @param $idCampaign
	 * @param $date
	 * @param $stats
	 * @return array
	 */
	public function prepareValues($idAccount, $idCampaign, $date, $stats)
	{
		return array(
			'idAccount'		=> $idAccount,
			'idCampaign'	=> $idCampaign,
			'date'			=> $date,
			'clicks'		=> $stats['clicks'],
			'impressions'	=> $stats['impressions'],
			'cost'			=> $stats['cost']
		);
	}

	/**
	 * @param $idAccount
	 * @param $idCampaign
	 * @param $date
	 * @param $stats
	 * @return null|Zend_Db_Table_Row_Abstract
	 */
	public function add($idAccount, $idCampaign, $date, $stats)
	{
		$newValues = $this->prepareValues($idAccount, $idCampaign, $date, $stats);

		$campaignStat = $this->fetchRow(array('idAccount=?' => $idAccount, 'idCampaign=?' => $idCampaign, 'date=?' => $date));
		if ($campaignStat) {
			$campaignStat->setFromArray($newValues);
			return $campaignStat;
		} else {
			$idCampaignStat = $this->insert($newValues);
			return $this->fetchRow(array('id=?' => $idCampaignStat));
		}
	}
}
