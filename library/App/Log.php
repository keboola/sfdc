<?php
/**
 *
 * User: Martin Halamíček
 * Date: 28.2.12
 * Time: 14:55
 *
 */

class App_Log extends Zend_Log
{

	/**
	 * @var App_Log_DebugLogUploader
	 */
	protected $_debugLogUploader;


	public function __construct(App_Log_DebugLogUploader $debugLogUploader, Zend_Log_Writer_Abstract $writer = null)
	{
		parent::__construct($writer);
		$this->setDebugLogUploader($debugLogUploader);
	}

	public function logWithAttachment($message, $priority, $attachment, $extras = null)
	{
		$extras = (array) $extras;
		$extras['attachment']  = $this->uploadAttachment($attachment);

		$this->log($message, $priority, $extras);
	}

	public function uploadAttachment($filePath, $contentType = 'text/plain')
	{
		try {
			return $this->_debugLogUploader->upload($filePath, $contentType);
		} catch (Exception $e) {
			return  'Upload failed';
		}
	}

	public function getDebugLogUploader()
	{
		return $this->_debugLogUploader;
	}

	public function setDebugLogUploader(App_Log_DebugLogUploader $attachmentUploader)
	{
		$this->_debugLogUploader = $attachmentUploader;
	}

}