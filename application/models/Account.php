<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_Account extends App_Db_Table
{
	protected $_name = 'Account';
	protected $_snapshotTableClass = 'Model_AccountSnapshot';
}
