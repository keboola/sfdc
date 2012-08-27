<?php
/**
 * 
 * User: Martin Halamíček
 * Date: 28.2.12
 * Time: 14:55
 * 
 */

namespace Keboola\Log;

class Log extends \Zend_Log
{

	/**
	 * @var DebugLogUploader
	 */
	protected $_debugLogUploader;

	public function __construct(DebugLogUploader $debugLogUploader, \Zend_Log_Writer_Abstract $writer = NULL)
	{
		parent::__construct($writer);
		$this->setDebugLogUploader($debugLogUploader);
	}

	/**
	 * Log a message at a priority
	 * Exception stack trace is uploaded to S3 if present
	 *
	 * @param  string   $message   Message to log
	 * @param  integer  $priority  Priority of message
	 * @param  mixed    $extras    Extra information to log in event
	 * @return void
	 * @throws Zend_Log_Exception
	 */
	public function log($message, $priority, $extras = null)
	{
		if (isset($extras['exception']) && $extras['exception'] instanceof \Exception) {
			try {
				$extras['exception'] = array(
					'message' => $extras['exception']->getMessage(),
					'code' => $extras['exception']->getCode(),
					'attachment' => $this->uploadException($extras['exception']),
				);
			} catch (\Exception $e) {
				// nothing to do
				$this->log('Cannot upload exception to S3', self::WARN);
			}
		}
		parent::log($message, $priority, $extras);
	}

	/**
	 * @param \Exception $e
	 */
	public function uploadException(\Exception $e)
	{
		$serialized = \Zend_Json::prettyPrint(\Zend_Json::encode((array) $e));
		return $this->_debugLogUploader->uploadString('exception', $serialized, 'text/plain');
	}

	public function logWithAttachment($message, $priority, $attachment, $extras = NULL)
	{
		$extras = (array) $extras;
		$extras['attachment']  = $this->uploadAttachment($attachment);

		$this->log($message, $priority, $extras);
	}

	public function uploadAttachment($filePath, $contentType = 'text/plain')
	{
		try {
			return $this->_debugLogUploader->upload($filePath, $contentType);
		} catch (\Exception $e) {
			return  'Upload failed';
		}
	}

	public function getDebugLogUploader()
	{
		return $this->_debugLogUploader;
	}

	public function setDebugLogUploader(DebugLogUploader $attachmentUploader)
	{
		$this->_debugLogUploader = $attachmentUploader;
	}

}