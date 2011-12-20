<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-14
 *
 */

class Model_Users extends App_Db_Table
{
	protected $_name = 'users';

	/**
	 * @param $email
	 * @param null $pid
	 * @param $oauthToken
	 * @param $oauthTokenSecret
	 * @return mixed
	 */
	public function add($email, $pid=null, $oauthToken, $oauthTokenSecret)
	{
		$u = $this->fetchRow(array('email=?' => $email));
		if (!$u) {
			return $this->insert(array(
				'email'				=> $email,
				'idGD'				=> $pid,
				'oauthToken'		=> $oauthToken,
				'oauthTokenSecret'	=> $oauthTokenSecret
			));
		} else {
			return $u->id;
		}
	}
}
