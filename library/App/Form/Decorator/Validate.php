<?php
/**
 * jQuery validation
 *
 * @author Jakub Matejka <jakub@keboola.com>
 */
class App_Form_Decorator_Validate extends Zend_Form_Decorator_Abstract
{

	public function render($content)
	{
		$elements = $this->getElement()->getElements();

		$count	= 0;
		$rules	= '';
		$messages = '';
		foreach($elements as $k=>$v){
			$validators = $v->getValidators();
			if(count($validators)) {
				$rules	.= "\t\t\t'".$k."': {\n";
				$messages .= "\t\t\t'".$k."': {\n";
				$count2	= 0;
				$validate  = false;
				foreach($validators as $k2=>$v2){
					switch($k2) {
						case 'Zend_Validate_NotEmpty':
							$rules	.= "\t\t\t\trequired: true,\n";
							$messages .= "\t\t\t\trequired: 'Field can\'t be empty.',\n";
							$validate = true;
							break;
						case 'Zend_Validate_Date':
							$rules	.= "\t\t\t\tdateDE: true,\n";
							$messages .= "\t\t\t\tdateDE: '"._t('v.invalidDate')."',\n";
							$validate = true;
							break;
						case 'Zend_Validate_Digits':
							$rules	.= "\t\t\t\tdigits: true,\n";
							$messages .= "\t\t\t\tdigits: '"._t('v.invalidDigits')."',\n";
							$validate = true;
							break;
						case 'Zend_Validate_EmailAddress':
							$rules	.= "\t\t\t\temail: true,\n";
							$messages .= "\t\t\t\temail: 'Field has to be an email address.',\n";
							$validate = true;
							break;
					}
					$count2++;
				}

				if($validate) {
					$rules = substr($rules, 0, strlen($rules)-2)."\n";
					$messages = substr($messages, 0, strlen($messages)-2)."\n";
				}

				$rules	.= "\t\t\t},\n";
				$messages .= "\t\t\t},\n";
				$count++;
			}
		}
		$rules	= substr($rules, 0, strlen($rules)-2)."\n";
		$messages = substr($messages, 0, strlen($messages)-2)."\n";

		$formName = $this->getElement()->getName();
		if(!$formName) {
			$formName = 'form';
		} else {
			$formName = '#'.$formName;
		}
		$r = '
<script type="text/javascript" src="/js/jquery.validate/jquery.validate.min.js"></script>
<script type="text/javascript"><!--
$().ready(function() {
	$("'.$formName.'").validate({
		errorElement: \'strong\',
		rules: {'."\n";
		$r .= $rules;
		$r .= "\t\t},\n\t\tmessages: {\n";
		$r .= $messages;
		$r .= "\t\t}
	});
});
//--></script>";
		return $r.$content;
	}
}