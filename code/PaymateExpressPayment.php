<?php
/** 
 * PaymateExpressPayment - payment gateway hosted by Paymate
 * @author jeremy [at] burnbright.co.nz
 * @package payment
 * 
 * Compatible with Payment v0.3
 * 
 *  Configuration mysite/_config.php
 *  ================================
 *  You will firstly need to enable the payment module with something like:
 *  Payment::set_supported_methods(array(
 *    'PaymateExpressPayment ' => 'Credit Card (Paymate)'));
 *
 * Then Set up Paymate account details
 * Set your Paymate merchant ID, this is the id you used to register and login with Paymate
 * PaymateExpressPayment::SetMerchantId("test");
 * // Use _ONE_ of the following Paymate URLs
 * PaymateExpressPayment::SetPaymateUrl("https://www.paymate.com.au/PayMate/ExpressPayment");		// Set Paymate to use LIVE site
 * PaymateExpressPayment::SetPaymateUrl("https://www.paymate.com.au/PayMate/TestExpressPayment");	// Set Paymate to use TEST site
 * 
 * Test credit card numbers
 * (use an expiry date that is in the future)
 * 4564456445644564 (VISA) - use for Accepted purchase (PA)
 * 5424000000000015 - use for Failed purchase (PD)
 * Any other card - use for Pending purchase (PP)
 * 
 * Further technical integration documentation can be found here:
 * http://www.paymate.com/cms/index.php?option=com_content&view=article&id=199&Itemid=231
 * 
 * You need to pay to have a custom skin installed
 * http://www.paymate.com/cms/index.php?option=com_content&view=article&id=186&Itemid=229
 * 
 */

class PaymateExpressPayment extends Payment {
    
    /* Properties */
    protected static $merchant_id;
    protected static $paymate_url;
    
    /**
     * Set to either
     * https://www.paymate.com.au/PayMate/ExpressPayment
     * or
     * https://www.paymate.com.au/PayMate/TestExpressPayment
     */
    static function SetMerchantId($id) {
        self::$merchant_id = $id;
    }
    static function GetMerchantId() {
        return self::$merchant_id;
    }

    static function SetPaymateUrl($id) {
        self::$paymate_url = $id;
    }

	static function GetPaymateUrl() {
      return self::$paymate_url;
	}

	protected static $credit_cards = array(
		'Visa' => 'payment/images/payments/methods/visa.jpg',
		'MasterCard' => 'payment/images/payments/methods/mastercard.jpg'
	);
    
	function getPaymentFormFields() {
		$logo = '<img src="https://www.paymate.com/PayMate/prep/images/paymate_exp_logo.gif" alt="Credit card payments powered by Paymate" title="Credit card payments powered by Paymate" />';
		$paymentsList = "";
		foreach(self::$credit_cards as $name => $image) $paymentsList .= '<img src="' . $image . '" alt="' . $name . '"/>';
		$fields = new FieldSet(
			new LiteralField('Logo', $logo),
			new LiteralField('CardList', $paymentsList)
			//TODO: include link to paymate Terms&Cconditions
		);
		return $fields;
	}	
	
	/**
	 * No requirements for this payment type.
	 */
    function getPaymentFormRequirements() {
        return null;
    }
    
    /*
     * Standard function required by all payments.
     */
	function processPayment($data, $form) {
		$page = new Page();
		$page->Title = "Make payment";
		$page->Form = $this->PaymateForm($data);
		$page->Logo = '<img src="https://www.paymate.com/PayMate/prep/images/paymate_exp_logo.gif" alt="Credit card payments powered by Paymate" title="Credit card payments powered by Paymate" />';

		$controller = new Page_Controller($page);
		$form = $controller->renderWith("PaymentProcessingPage");
        
		return new Payment_Processing($form);
	}
    
    //TODO: disconnect this from e-commerce
    function PaymateForm($data) {

		//TODO: remove eCommerce specific code
		$o = $this->Order();
		$m = $o->Member();

        //Get the call back URL
        $callbackURL = Director::absoluteBaseURL(). 'PaymateExpressPayment_Handler/paid/' . $this->ID;
        
        //TODO: these fields should be based on $data, not member ..unless member becomes some standard payment practice
        $info = array(
			"amt_editable"		=> "N",
			"mid"				=> self::$merchant_id, 
			"ref"				=> $this->ID,
			"currency"			=> $this->Amount->Currency,
			"amt"				=> $this->Amount->Amount,
			"return"			=> $callbackURL,
			"popup"			=> "false",
			
			"pmt_contact_firstname"	=> $m->FirstName,	//$data['FirstName']
			"pmt_contact_surname"	=> $m->Surname,		//$data['Surname']
			"pmt_sender_email"	=> $m->Email,			//$data['Email']
			"pmt_contact_phone"	=> $m->HomePhone, 		//$data['HomePhone']
			"pmt_country"		=> $m->Country, 		//$data['Country']

			"regindi_address1"	=> $m->Address, 		//$data['Address']
			"regindi_address2"	=> $m->AddressLine2,	//$data['AddressLine2']
			"regindi_sub"		=> $m->City,			//$data['AddressLine2']
			"regindi_state"		=> $m->State, 			//$data['State']
			"regindi_pcode"		=> $m->PostalCode 		//$data['PostalCode']
		);

		//if($member->hasMethod('getPostCode')) $info['regindi_pcode'] = $member->getPostCode();
        
        $fields = '';
        foreach($info as $k => $v) {
            $fields .= "<input type=\"hidden\" name=\"" . Convert::raw2att($k) . "\" value=\"" . Convert::raw2att($v) . "\" />\n";
        }
        $merchant_id = self::$merchant_id;
	    $paymate_url = self::$paymate_url;
	    
	    
	    Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
        $redirecthtml = <<<HTML
        
			<form id="PaymentForm" method="post" action="$paymate_url">
				<h2>Now forwarding you to Paymate...</h2>
				$fields
			
				<p class="Actions" id="Submit">
				   <input type="submit" value="Make Payment" />
				</p>
				<p id="Submitting" style="display: none">We are now redirecting you to Paymate...</p>
			</form>
			
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery("input[type='submit']").hide();
					jQuery('#PaymentForm').submit();
				});
			</script>
			
HTML;
		return $redirecthtml;
    }
    
    function getPaymentFormRequiredFields() {
        return array();
    }
}

/**
 * Handler for responses from the Paymate site
 * @TODO find out how the checkout confirmation page is meant to be displayed - it could be broken in this trunk build
 */
class PaymateExpressPayment_Handler extends Controller {

	function paid(){
		
		$redirectURL = 'home';
		
		if(isset($_POST['ref']) && $payment = DataObject::get_by_id('PaymateExpressPayment', $_POST['ref'])){			
			
			//save post data into Payment and Member where appropriate.
			$payment->TxnRef = $_POST['transactionID'];
			$payment->Message = "Paymate Transaction ref: " . $_POST['transactionID'];
			$payment->dbObject('Amount')->setAmount($_POST['paymentAmount']);
			$payment->dbObject('Amount')->setCurrency($_POST['currency']);
			
			switch ($_POST['responseCode']){
				case "PA": // Payment was Approved
					$payment->Status = 'Success';
					$payment->Message .= " \nPayment was Approved by Paymate";
					break;
				case "PD": // payment was declined
					$payment->Status = 'Failure';
					$payment->Message .= " \nPayment was Declined by Paymate";
					break;
				case "PP": // Payment was authorised and is pending
					$payment->Status = 'Pending';
					$payment->Message .= " \nPayment is Pending with Paymate";
					
					//TODO: do a paymate transaction inquiry as detailed here:
					//http://www.paymate.com/cms/index.php?option=com_content&id=190&Itemid=231
					
					break;
				default:
					$payment->Status = 'Failure';
					$payment->Message .= " \nResponse code not recognised :" . $_POST['responseCode'];
					break;
			}
				
			$payment->write();
			$redirectURL = ($payment->PaidObject() && $payment->PaidObject()->Link()) ? $payment->PaidObject()->Link() : 'home';
		}
		
		Director::redirect($redirectURL);
		return;
	}
}

?>