<?php
/**
 * Class for debugging
 */
class App_Debug
{
	/**
	 * Method adds log entry to chosen file
	 * @param mixed $data
	 * @param string $file
	 */
	public static function log($data, $file='debug.log')
	{
		$output = date("Y-m-d H:i:s")."\n";
		$output .= print_r($data, true);

		error_log(
			$output."\n",
			3,
			APPLICATION_PATH . '/../logs/'.$file
		);
	}

	/**
	 * Method sends log to email
	 * @param mixed $data
	 * @param string $email
	 * @param string $attachment
	 */
	public static function send($data, $email=null, $attachment=null)
	{
		if (APPLICATION_ENV != 'development') {
			$c = Zend_Registry::get('config');

			$m = new Zend_Mail('utf8');
			$m->setFrom($c->app->email);
			$m->addTo($c->app->admin);
			$m->setSubject('SalesForce-GoodData connector error');
			$m->setBodyText($data);

			if ($attachment && file_exists($attachment)) {
				$a = new Zend_Mime_Part(file_get_contents($attachment));
				$a->filename = basename($attachment);
				$a->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
				$m->addAttachment($a);
			}

			$m->send();
		}
	}
}