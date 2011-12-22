<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2011-12-12
 *
 */

class Model_Row_BiUser extends Zend_Db_Table_Row_Abstract
{
	public function revalidateAccessToken() {

		$registry = Zend_Registry::getInstance();
		$url = $registry->config->salesForce->loginUri . "/services/oauth2/token";

		$params = "grant_type=refresh_token&client_id=" . $registry->config->salesForce->clientId . "&client_secret=" . $registry->config->salesForce->clientSecret . "&refresh_token=" . $this->refreshToken;

		$curl = curl_init($url);

		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth {$this->accessToken}"));

		$json_response = curl_exec($curl);
		curl_close($curl);

		$response = json_decode($json_response, true);
		$this->accessToken = $response['access_token'];
		$this->instanceUrl = $response['instance_url'];
		$this->save();

	}
}
