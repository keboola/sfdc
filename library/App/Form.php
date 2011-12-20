<?php
/**
 * basic form setup
 *
 * @author Jakub Matejka <jakub@keboola.com>
 */

require_once 'Zend/Form.php';

class App_Form extends Zend_Form
{
	/**
	 * Decorators for hidden elements
	 *
	 * @var array $_hiddenDecorators
	 */
	protected $_hiddenDecorators = array(
		array('ViewHelper')
	);

	/**
	 * Decorators for radio and checkbox elements
	 *
	 * @var array $_choiceDecorators
	 */
	protected $_choiceDecorators = array(
		array('ViewHelper'),
		array('Errors'),
		array('Label', array('placement' => 'append', 'escape'=>false)),
		array('Description', array('placement' => 'append', 'tag' => 'span', 'class' => 'note', 'escape' => false)),
		array(array('row' => 'HtmlTag'), array('tag' => 'div', 'class' => 'formElement choiceElement')),
	);

	/**
	 * Decorators for button elements
	 *
	 * @var array $_buttonDecorators
	 */
	protected $_buttonDecorators = array(
		array('ViewHelper'),
		array('Description', array('placement' => 'append', 'tag' => 'span', 'class' => 'note', 'escape' => false)),
		array(array('row' => 'HtmlTag'), array('tag' => 'div', 'class' => 'formElement noLabel'))
	);

	/**
	 * Decorators for text elements
	 *
	 * @var array $_textDecorators
	 */
	public $_textDecorators = array(
		array('ViewHelper'),
		array('Errors'),
		array('Label', array('requiredSuffix' => '*', 'escape' => false)),
		array('Description', array('placement' => 'append', 'tag' => 'span', 'class' => 'note', 'escape' => false)),
		array(array('row' => 'HtmlTag'), array('tag' => 'div', 'class' => 'formElement'))
	);

	/**
	 * Decorators for text elements
	 *
	 * @var array $_textDecorators
	 */
	protected $_captchaDecorators = array(
		array('Errors'),
		array('Label', array('requiredSuffix' => '*', 'escape' => false)),
		array('Description', array('placement' => 'append', 'tag' => 'span', 'class' => 'note', 'escape' => false)),
		array(array('row' => 'HtmlTag'), array('tag' => 'div', 'class' => 'formCaptcha'))
	);

	/**
	 * Decorators for file elements
	 *
	 * @var array $_fileDecorators
	 */
	protected $_fileDecorators = array(
		array('File'),
		array('Errors'),
		array('Label', array('requiredSuffix' => '*', 'escape' => false)),
		array('Description', array('placement' => 'append', 'tag' => 'span', 'class' => 'note', 'escape' => false)),
		array(array('row' => 'HtmlTag'), array('tag' => 'div', 'class' => 'formElement'))
	);

	public function  __construct($options = null) {
		$this->setElementFilters(array('StringTrim'));

		parent::__construct($options);
	}

	/**
	 * Load the default decorators
	 *
	 * @return void
	 */
	public function loadDefaultDecorators()
	{
		if ($this->loadDefaultDecoratorsIsDisabled()) {
			return;
		}

		$this->setDecorators(array(
			array('FormElements'),
			array('HtmlTag', array('tag' => '<div>')),
			array('Form'),
			new App_Form_Decorator_Validate()
		));

		$this->setDisplayGroupDecorators(array(
			array('FormElements'),
			array('Description', array('placement' => 'prepend')),
			array('Fieldset')
		));

		foreach ($this->getElements() as $element) {
			if (get_class($element) == 'Zend_Form_Element_Captcha') {
				$decorators = $this->_captchaDecorators;
			} else
				switch ($element->helper) {
					case 'formHash':
					case 'formHidden':
						$class = '';
						$decorators = $this->_hiddenDecorators;
						break;
					case 'formRadio':
						$class = 'inputRadio';
						$decorators = $this->_choiceDecorators;
						break;
					case 'formCheckbox':
						$class = 'inputCheckbox';
						$decorators = $this->_choiceDecorators;
						break;
					case 'formSelect':
						$class = 'inputSelect';
						$decorators = $this->_textDecorators;
						break;
					case 'formButton':
					case 'formSubmit':
						$class = 'inputButton';
						$decorators = $this->_buttonDecorators;
						break;
					case 'formText':
						$class = 'inputText';
						$decorators = $this->_textDecorators;
						break;
					case 'formTextarea':
						$class = 'inputTextarea';
						$decorators = $this->_textDecorators;
						break;
					case 'formPassword':
						$class = 'inputPassword';
						$decorators = $this->_textDecorators;
						break;
					case 'formFile':
						$class = 'inputFile';
						$decorators = $this->_fileDecorators;
						break;
					default:
						$class = '';
						$decorators = $this->_textDecorators;
						break;
				}
			if(!$element->loadDefaultDecoratorsIsDisabled()) {
				$element->setDecorators($decorators);
			}
			$element->setAttrib('class', $class.' '.$element->getAttrib('class'));

		}
	}


	/**
	 * setup formu
	 */
	public function init()
	{
		
	}
}
