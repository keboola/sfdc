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
	protected $_gapi;

	public function init()
	{
		parent::init();
	}

	public function indexAction()
	{
		$config = Zend_Registry::get('config');
		$form = new Form_AddAccount();

		$ns = new Zend_Session_Namespace('awRegister');

		if($this->_request->isPost()) {
			if ($form->isValid($this->_request->getParams())) {
				$_u = new Model_Users();
				$_u->add(
					$this->_request->email,
					$this->_request->idGD,
					$ns->oauthToken,
					$ns->oauthTokenSecret
				);
				unset($ns->oauthToken);
				unset($ns->oauthTokenSecret);

				$this->_helper->getHelper('FlashMessenger')->addMessage('success|The account has been saved!');
				$this->_redirect('/');
			} else {
				$form->populate($this->_request->getParams());
			}
		} else {
			require_once 'Google/Api/Ads/AdWords/Lib/AdWordsUser.php';

			if (empty($this->_request->oauth_token)) {
				$user = new AdWordsUser();
				$user->SetOAuthInfo(array(
					'oauth_consumer_key' => $config->adwords->oauthKey,
					'oauth_consumer_secret' => $config->adwords->oauthSecret
				));
				$user->RequestOAuthToken("http://".$_SERVER['HTTP_HOST']."/register/index");
				$authUrl = $user->GetOAuthAuthorizationUrl();

				$oauthInfo = $user->GetOAuthInfo();
				$ns->token = $oauthInfo["oauth_token"];
				$ns->tokenSecret = $oauthInfo["oauth_token_secret"];

				header("Location: $authUrl");
			} else {
				$user = new AdWordsUser();
				$user->SetOAuthInfo(array(
					'oauth_consumer_key' => $config->adwords->oauthKey,
					'oauth_consumer_secret' => $config->adwords->oauthSecret,
					'oauth_token' => $ns->token,
					'oauth_token_secret' => $ns->tokenSecret
				));
				$user->upgradeOAuthToken($this->_request->oauth_verifier);
				unset($ns->token);
				unset($ns->tokenSecret);

				$oauthInfo = $user->GetOAuthInfo();
				$ns->oauthToken = $oauthInfo['oauth_token'];
				$ns->oauthTokenSecret = $oauthInfo['oauth_token_secret'];
			}
		}


		$this->view->form = $form;
	}

}
