<?php
/**
 * Register controller
 *
 * create an Account
 *
 * @author miro@keboola.com
 */
class RegisterController extends Zend_Controller_Action
{

	public function init()
	{
		$session = new Zend_Session_Namespace('salesforceUser');
		$registry = Zend_Registry::getInstance();

		if ($session->userId) {
			$userTable = new Model_BiUser();
			$userRow = $userTable->find(array('id' => $session->userId));
			$registry->user = $userRow->current();
		} else {
			throw new Zend_Exception('No user given');
		}
		parent::init();
	}

	public function indexAction()
	{
		$config = Zend_Registry::get('config');

		$auth_url = $config->salesForce->loginUri
					. "/services/oauth2/authorize?response_type=code&client_id="
					. $config->salesForce->clientId . "&redirect_uri=" . urlencode($config->salesForce->redirectUri)
					. "&scope=id api full refresh_token web";
		header('Location: ' . $auth_url);
	}

	public function callbackAction()
	{
		$registry = Zend_Registry::getInstance();

		$token_url = $registry->config->salesForce->loginUri . "/services/oauth2/token";

		$code = $_GET['code'];

		if (!isset($code) || $code == "") {
			die("Error - code parameter missing from request!");
		}

		$params = "code=" . $code
			. "&grant_type=authorization_code"
			. "&client_id=" . $registry->config->salesForce->clientId
			. "&client_secret=" . $registry->config->salesForce->clientSecret
			. "&redirect_uri=" . urlencode($registry->config->salesForce->redirectUri);

		$curl = curl_init($token_url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $params);

		$json_response = curl_exec($curl);

		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if ( $status != 200 ) {
			die("Error: call to token URL $token_url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
		}

		curl_close($curl);

		$response = json_decode($json_response, true);
/*
		$access_token = $response['access_token'];
		$instance_url = $response['instance_url'];

		if (!isset($access_token) || $access_token == "") {
			die("Error - access token missing from response!");
		}

		if (!isset($instance_url) || $instance_url == "") {
			die("Error - instance URL missing from response!");
		}
*/

		$this->view->response = $response;

		$registry->user->accessToken = $response['access_token'];
		$registry->user->instanceUrl = $response['instance_url'];
		$registry->user->refreshToken = $response['refresh_token'];
		$registry->user->save();

	}

}
