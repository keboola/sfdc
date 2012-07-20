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

		$clientId = $registry->config->salesForce->clientId;
		if($this->sfdcClientId) {
			$clientId = $this->sfdcClientId;
		}
		$clientSecret = $registry->config->salesForce->clientSecret;
		if($this->sfdcClientSecret) {
			$clientSecret = $this->sfdcClientSecret;
		}

		$params = "grant_type=refresh_token&client_id=" . $clientId . "&client_secret=" . $clientSecret . "&refresh_token=" . $this->sfdcRefreshToken;

		$curl = curl_init($url);

		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth {$this->sfdcAccessToken}"));

		$json_response = curl_exec($curl);
		curl_close($curl);

		$response = json_decode($json_response, true);
		if (isset($response['error'])) {
			throw new Exception("Refreshing OAuth access token for user {$this->strId} ({$this->id}) failed: " . $response['error'] . ": " . $response['error_description']);
		}
		$this->sfdcAccessToken = $response['access_token'];
		$this->sfdcInstanceUrl = $response['instance_url'];
		$this->save();

	}

}
