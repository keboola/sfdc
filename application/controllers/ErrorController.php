<?php
use Keboola\Controller\Action\Exception as HttpException;
class ErrorController extends Zend_Controller_Action
{

	public function errorAction()
	{
		$this->jsonErrorAction();
		/*
		$this->logError();

		$errors = $this->_getParam('error_handler');

		switch ($errors->type) {
			case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
			case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
			case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:

				// 404 error -- controller or action not found
				$this->getResponse()->setHttpResponseCode(404);
				$this->view->message = "Uh-oh, the page or file you requested does not exist!";
				$this->view->pageTitle = 'Page Not Found';
				break;
			default:
				// application error
				$this->getResponse()->setHttpResponseCode(500);
				$this->view->pageTitle = "Application Error";
				$this->view->info = $errors->exception->getMessage();

				break;
		}

		$this->view->exception = $errors->exception;

		$this->view->request = $errors->request;
		$this->view->env = $this->getInvokeArg('bootstrap')->getEnvironment();
		*/
	}

	public function jsonErrorAction()
	{

		$exceptionId = $this->logError();

		$this->_helper->viewRenderer->setNoRender(TRUE);

		$errors = $this->_getParam('error_handler');
		switch ($errors->type) {
			case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
			case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
			case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:

				// 404 error -- controller or action not found
				$this->getResponse()->setHttpResponseCode(404);
				$this->_helper->json(array(
					'error' => 'resource not found',
				));
				break;
			default:
				// Maintenance
				if ($errors->exception instanceof \Keboola\StorageApi\Exception) {
					if ($errors->exception->getCode() == "MAINTENANCE") {
						$this->getResponse()->setHttpResponseCode(503);
						$this->_helper->json($errors->exception->getContextParams());
					}
				}
				// application error
				$logMessage = 'Application error.';
				if ($errors->exception instanceof HttpException) {
					$this->getResponse()->setHttpResponseCode($errors->exception->getCode());
					$logMessage = $errors->exception->getMessage();
				} else {
					$this->getResponse()->setHttpResponseCode(500);
				}

				$response = array(
					'status' => 'error',
					'error' => $logMessage,
				);

				$stringCode = $this->_getExceptionStringCode($errors->exception);
				if ($stringCode) {
					$response['code'] = $stringCode;
				}

				$response["message"] = $errors->exception->getMessage();
				$response["exceptionId"] = $exceptionId;

				$this->_helper->json($response);

				break;
		}
	}

	protected function _getExceptionStringCode(\Exception $e)
	{
		if (!$e instanceof \Keboola\Exception) {
			return FALSE;
		}

		return $e->getStringCode();
	}

	protected function _getExceptionContextParams(\Exception $e)
	{
		if (!$e instanceof \Keboola\Exception) {
			return FALSE;
		}

		return $e->getContextParams();
	}

	public function logError()
	{
		$errors = $this->_getParam('error_handler');
		$logData = array();
		$logData['exceptionId'] = "extractor-sfdc-" . md5(uniqid("transformation", true));

		$logPriority = Zend_Log::ERR;
		if ($errors) {
			$exception = $errors['exception'];
			$logMessage = $exception->getMessage();
			$logData['exception'] = $exception;
			$logData['code'] = $this->_getExceptionStringCode($exception);
			if ($this->_getExceptionContextParams($exception)) {
				$logData['context'] = $this->_getExceptionContextParams($exception);
			}

			// log 404 as notice
			$clientErrors = array(
				HttpException::BAD_REQUEST,
				HttpException::FORBIDDEN,
				HttpException::NOT_FOUND,
				HttpException::UNAUTHORIZED,
				HttpException::METHOD_NOT_ALLOWED,
			);
			if (in_array($errors->exception->getCode(), $clientErrors) || $errors->type == Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER) {
				$logPriority = Zend_Log::NOTICE;
			}

			$body = $this->getRequest()->getRawBody();
			$devel = $this->getRequest()->getParam("devel");
			$jsonParams = array();
			if (strlen($body)) {
				try {
					$jsonParams = Zend_Json::decode($body);
				} catch (\Exception $e) {
				}
			}
			if (isset($jsonParams["devel"]) && $jsonParams["devel"] || $devel) {
				$logPriority = Zend_Log::NOTICE;
			}
			if ($logData['code'] == "MAINTENANCE") {
				$logPriority = Zend_Log::NOTICE;
			}

		} else {
			$logMessage = 'Unknown error';
		}

		$this->getLog()->log($logMessage, $logPriority, $logData);
		return $logData["exceptionId"];
	}

	/**
	 * @return Zend_Log
	 */
	public function getLog()
	{
		$bootstrap = $this->getInvokeArg('bootstrap');
		if (!$bootstrap->hasPluginResource('Log')) {
			return FALSE;
		}
		$log = $bootstrap->getResource('Log');
		return $log;
	}


}

