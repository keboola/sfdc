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
		if($this->clientId) {
			$clientId = $this->clientId;
		}
		$clientSecret = $registry->config->salesForce->clientSecret;
		if($this->clientSecret) {
			$clientSecret = $this->clientSecret;
		}

		$params = "grant_type=refresh_token&client_id=" . $clientId . "&client_secret=" . $clientSecret . "&refresh_token=" . $this->refreshToken;

		$curl = curl_init($url);

		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: OAuth {$this->accessToken}"));

		$json_response = curl_exec($curl);
		curl_close($curl);

		$response = json_decode($json_response, true);
		if (isset($response['error'])) {
			throw new Exception($response['error'] . ": " . $response['error_description']);
		}
		$this->accessToken = $response['access_token'];
		$this->instanceUrl = $response['instance_url'];
		$this->save();

	}

	/**
	 * Checks for valid data including date
	 * @return bool
	 */
	public function hasValidData() {
		$timeZone = date_default_timezone_get();
		$valid = false;
		$lastImportTimestampUtc = strtotime($this->lastImportDate);
		$lastExportTimestampUtc = strtotime($this->lastExportDate);

		// GoodData timezone
		date_default_timezone_set("America/Los_Angeles");

		if (date("Y-m-d", $lastImportTimestampUtc) == date("Y-m-d") &&
			date("Y-m-d", $lastExportTimestampUtc) == date("Y-m-d") &&
			$lastImportTimestampUtc < $lastExportTimestampUtc)
		{
			$valid = true;
		}
		date_default_timezone_set($timeZone);
		return $valid;
	}

}
