<?php

class App_View_Helper_FlashMessages extends Zend_View_Helper_Abstract
{

	/**
	 * flashMessages function.
	 *
	 * Takes a specially formatted array of flash messages and prepares them
	 * for output.
	 *
	 * SAMPLE INPUT (in, say, a controller):
	 *    $this->_flashMessenger->addMessage(array('message' => 'Success message #1', 'status' => 'success'));
	 *    $this->_flashMessenger->addMessage(array('message' => 'Error message #1', 'status' => 'error'));
	 *    $this->_flashMessenger->addMessage(array('message' => 'Warning message #1', 'status' => 'warning'));
	 *    $this->_flashMessenger->addMessage(array('message' => 'Success message #2', 'status' => 'success'));
	 *
	 * OR
	 *    $this->_helper->flashMessanger->addMessage('sucess|Success message #1');
	 *    $this->_helper->flashMessanger->addMessage('error|Error message #1');
	 *    $this->_helper->flashMessanger->addMessage('warning|Warning message #1');
	 *    $this->_helper->flashMessanger->addMessage('sucess|Sucess message #2');
	 *
	 * SAMPLE OUTPUT (in a view):
	 *    <div class="success">
	 *        <ul>
	 *            <li>Success message #1</li>
	 *            <li>Success message #2</li>
	 *        </ul>
	 *    </div>
	 *    <div class="error">Error message #1</div>
	 *    <div class="warning">Warning message #2</div>
	 *
	 * @access public
	 * @return string HTML of output messages
	 */
	public function flashMessages()
	{
		$messages = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger')->getMessages();
		$statMessages = array();
		$output = '';

		if (count($messages) > 0) {
			// This chunk of code takes the messages (formatted as in the above sample
			// input) and puts them into an array of the form:
			//    Array(
			//        [status1] => Array(
			//            [0] => "Message 1"
			//            [1] => "Message 2"
			//        ),
			//        [status2] => Array(
			//            [0] => "Message 1"
			//            [1] => "Message 2"
			//        )
			//        ....
			//    )
			foreach ($messages as $message) {
				// if message is not an array, but string formatted like this:
				// message_status|message
				if (! is_array($message)) {
					$message_tmp = explode("|", $message);
					$message = array();

					if(sizeof($message_tmp) == 1) {
						// default status is info
						$message['status'] = 'info';
					} else {
						$message['status'] = array_shift($message_tmp);
					}
					// if in message occured '|' characters we have to bring them back
					$message['message'] = implode("|", $message_tmp);
				}

				if (!array_key_exists($message['status'], $statMessages))
					$statMessages[$message['status']] = array();

				array_push($statMessages[$message['status']], $message['message']);
			}

			// This chunk of code formats messages for HTML output (per
			// the example in the class comments).
			foreach ($statMessages as $status => $messages) {
				$output .= '<p class="notice ' . $status . '"><span class="wrap">';

				if (count($messages) == 1)
					$output .=  $messages[0];

				else {
					$output .= implode('<br />', $messages);
				}

				$output .= '</span></p>';
			}

			// Return the final HTML string to use.
			return '<div class="flashMessages"><div class="wrap">'.$output.'</div></div>';
		}

	}


}