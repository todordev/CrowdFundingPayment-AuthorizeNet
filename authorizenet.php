<?php
/**
 * @package      CrowdFunding
 * @subpackage   Plugins
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2013 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

/**
 * CrowdFunding AuthorizeNet Payment Plugin
 *
 * @package      CrowdFunding
 * @subpackage   Plugins
 * 
 * @todo Use $this->app and $autoloadLanguage to true, when Joomla! 2.5 is not actual anymore.
 */
class plgCrowdFundingPaymentAuthorizeNet extends JPlugin {
    
    /**
     * This method prepares a payment gateway - buttons, forms,...
     * That gateway will be displayed on the summary page as a payment option.
     *
     * @param string 	$context	This string gives information about that where it has been executed the trigger.
     * @param object 	$item	    A project data.
     * @param JRegistry $params	    The parameters of the component
     */
    public function onProjectPayment($context, $item, $params) {
        
        $app = JFactory::getApplication();
        /** @var $app JSite **/

        if($app->isAdmin()) {
            return;
        }

        $doc     = JFactory::getDocument();
        /**  @var $doc JDocumentHtml **/
        
        // Check document type
        $docType = $doc->getType();
        if(strcmp("html", $docType) != 0){
            return;
        }
       
        if(strcmp("com_crowdfunding.payment", $context) != 0){
            return;
        }
        
        // Load language
        $this->loadLanguage();
        
        // This is a URI path to the plugin folder
        $pluginURI = "plugins/crowdfundingpayment/authorizenet";
        
        // Load the script that initialize the select element with banks.
        if(version_compare(JVERSION, "3", ">=")) {
            JHtml::_("jquery.framework");
        }
        $doc->addScript($pluginURI."/js/plg_crowdfundingpayment_authorizenet.js");
        
        $notifyUrl = $this->getNotifyUrl();
        
        // Get intention
        $userId        = JFactory::getUser()->id;
        $aUserId       = $app->getUserState("auser_id");
        
        $intention     = CrowdFundingHelper::getIntention($userId, $aUserId, $item->id);
        
        // Prepare custom data
        $custom = array(
            "intention_id" =>  $intention->getId(),
            "gateway"	   =>  "AuthorizeNet"
        );
        $custom = base64_encode( json_encode($custom) );
        
        $keys = array(
            "api_login_id"    => JString::trim($this->params->get('authorizenet_login_id')),
            "transaction_key" => JString::trim($this->params->get('authorizenet_transaction_key'))
        );
        
        jimport("itprism.payment.authorizenet.authorizenet");
        $authNet = ITPrismPaymentAuthorizeNet::factory("DPM", $keys);
        /** @var $authNet ITPrismPaymentAuthorizeNetDpm **/
        
        $description = JText::sprintf("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_INVESTING_IN_S", htmlentities($item->title, ENT_QUOTES, "UTF-8"));
        
        $authNet
            ->setAmount($item->amount)
            ->setCurrency($item->currencyCode)
            ->setDescription($description)
            ->setSequence($intention->getId())
            ->setRelayUrl($notifyUrl)
            ->setType("AUTH_CAPTURE")
            ->setMethod("CC")
            ->setCustom($custom)
            ->enableRelayResponse();
        
        $html   =  array();
        $html[] = '<h4><img src="'.$pluginURI.'/images/authorizenet_icon.png" width="50" height="32" alt="AuthorizeNet" />'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_TITLE").'</h4>';
        
        $html[] = '<button class="btn btn-mini" id="js-cfpayment-toggle-fields">'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_TOGGLE_FIELDS")."</button>";
        
        if($this->params->get("authorizenet_display_fields", 0)) {
            $html[] = '<div id="js-cfpayment-authorizenet">';
        } else {
            $html[] = '<div id="js-cfpayment-authorizenet" style="display: none;">';
        }
        
//         $html[] = '<p>'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_INFO").'</p>';
        
        if(!$this->params->get('authorizenet_sandbox', 1)) {
            $html[] = '<form action="'.JString::trim($this->params->get('authorizenet_url')).'" method="post">';
            $authNet->disableTestMode();
        }  else {
            $html[] = '<form action="'.JString::trim($this->params->get('authorizenet_sandbox_url')).'" method="post">';
            $authNet->enableTestMode();
        }
        
        $hiddenFields = $authNet->getHiddenFields();
        
        $html   = array_merge($html, $hiddenFields);
        
        $html[] = '<fieldset>';
        $html[] = '<div class="control-group">';
        $html[] = '     <div class="control-label">'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_CREDIT_CARD_NUMBER").'</div>';
        $html[] = '     <div class="controls"><input type="text" name="x_card_num" value="" /></div>';
        $html[] = '</div>';
        $html[] = '<div class="control-group">';
        $html[] = '     <div class="control-label">'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_EXPIRES").'</div>';
        $html[] = '     <div class="controls"><input type="text" name="x_exp_date" value="" /></div>';
        $html[] = '</div>';
        $html[] = '<div class="control-group">';
        $html[] = '     <div class="control-label">'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_CCV").'</div>';
        $html[] = '     <div class="controls"><input type="text" name="x_card_code" value="" /></div>';
        $html[] = '</div>';
        $html[] = '</fieldset>';
        
        $html[] = '<fieldset>';
        $html[] = '<div class="control-group">';
        $html[] = '     <div class="control-label">'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_FIRST_NAME").'</div>';
        $html[] = '     <div class="controls"><input type="text" name="x_first_name" value="" /></div>';
        $html[] = '</div>';
        $html[] = '<div class="control-group">';
        $html[] = '     <div class="control-label">'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_LAST_NAME").'</div>';
        $html[] = '     <div class="controls"><input type="text" name="x_last_name" value="" /></div>';
        $html[] = '</div>';
        $html[] = '</fieldset>';
        
        $html[] = '<fieldset>';
        $html[] = '<div class="control-group">';
        $html[] = '     <div class="control-label">'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_ADDRESS").'</div>';
        $html[] = '     <div class="controls"><input type="text" name="x_address" value="" /></div>';
        $html[] = '</div>';
        $html[] = '<div class="control-group">';
        $html[] = '     <div class="control-label">'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_CITY").'</div>';
        $html[] = '     <div class="controls"><input type="text" name="x_city" value="" /></div>';
        $html[] = '</div>';
        $html[] = '<div class="control-group">';
        $html[] = '     <div class="control-label">'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_STATE").'</div>';
        $html[] = '     <div class="controls"><input type="text" name="x_state" value="" /></div>';
        $html[] = '</div>';
        $html[] = '<div class="control-group">';
        $html[] = '     <div class="control-label">'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_ZIP_CODE").'</div>';
        $html[] = '     <div class="controls"><input type="text" name="x_zip" value="" /></div>';
        $html[] = '</div>';
        $html[] = '<div class="control-group">';
        $html[] = '     <div class="control-label">'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_COUNTRY").'</div>';
        $html[] = '     <div class="controls"><input type="text" name="x_country" value="" /></div>';
        $html[] = '</div>';
        $html[] = '</fieldset>';
        
        $html[] = '<input type="submit" value="'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_SUBMIT").'" class="btn btn-primary">';
        
    	$html[] = '</form>';
        
        if($this->params->get('authorizenet_sandbox', 1)) {
            $html[] = '<p class="sticky">'.JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_WORKS_SANDBOX").'</p>';
        }
        
        $html[] = '</div>';
        
        return implode("\n", $html);
        
    }
    
    /**
     * This method processes transaction data that comes from the paymetn gateway.
     *  
     * @param string 	$context	This string gives information about that where it has been executed the trigger.
     * @param JRegistry $params	    The parameters of the component
     */
    public function onPaymenNotify($context, $params) {
        
        $app = JFactory::getApplication();
        /** @var $app JSite **/
        
        if($app->isAdmin()) {
            return;
        }

        $doc     = JFactory::getDocument();
        /**  @var $doc JDocumentHtml **/
        
        // Check document type
        $docType = $doc->getType();
        if(strcmp("raw", $docType) != 0){
            return;
        }
       
        if(strcmp("com_crowdfunding.notify", $context) != 0){
            return;
        }
        
        // Validate request method
        $requestMethod = $app->input->getMethod();
        
        if(strcmp("POST", $requestMethod) != 0) {
            return null;
        }
        
        // Decode custom data
        $custom    = JArrayHelper::getValue($_POST, "custom");
        $custom    = json_decode(base64_decode($custom), true);
        
        // Verify gateway. Is it AuthorizeNet?
        if(!$this->isAuthorizeNetGateway($custom)) {
            return null;
        }
        
        // Load language
        $this->loadLanguage();
        
        // Prepare the array that will be returned by this method
        $result = array(
        	"project"          => null, 
        	"reward"           => null, 
        	"transaction"      => null,
            "payment_service"  => "authorizenet"
        );
        
        // Get currency
        jimport("crowdfunding.currency");
        $currencyId      = $params->get("project_currency");
        $currency        = CrowdFundingCurrency::getInstance($currencyId);
        
        // Get intention data
        $intentionId     = JArrayHelper::getValue($custom, "intention_id", 0, "int");
        
        jimport("crowdfunding.intention");
        $intention       = new CrowdFundingIntention($intentionId);
        
        // Validate transaction data
        $validData = $this->validateData($_POST, $currency->getAbbr(), $intention);
        if(is_null($validData)) {
            return $result;
        }
        
        // Check for valid project
        jimport("crowdfunding.project");
        $projectId = JArrayHelper::getValue($validData, "project_id");
        
        $project   = CrowdFundingProject::getInstance($projectId);
        if(!$project->getId()) {
            $error  = JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_ERROR_INVALID_PROJECT");
            $error .= "\n". JText::sprintf("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_TRANSACTION_DATA", var_export($validData, true));
			JLog::add($error);
			return $result;
        }
        
        // Set the receiver of funds
        $validData["receiver_id"] = $project->getUserId();
        
        // Save transaction data.
        // If it is not completed, return empty results.
        // If it is complete, continue with process transaction data
        if(!$this->storeTransaction($validData, $project)) {
            return $result;
        }
        
        // Validate and Update distributed value of the reward
        $rewardId  = JArrayHelper::getValue($validData, "reward_id");
        $reward    = null;
        if(!empty($rewardId)) {
            $reward = $this->updateReward($validData);
        }
        
        //  Prepare the data that will be returned
        
        $result["transaction"]    = JArrayHelper::toObject($validData);
        
        // Generate object of data based on the project properties
        $properties               = $project->getProperties();
        $result["project"]        = JArrayHelper::toObject($properties);
        
        // Generate object of data based on the reward properties
        if(!empty($reward)) {
            $properties           = $reward->getProperties();
            $result["reward"]     = JArrayHelper::toObject($properties);
        }
        
        // Remove intention
        $intention->delete();
        unset($intention);
        
        return $result;
                
    }
    
    /**
     * This metod is executed after complete payment.
     * It is used to be sent mails to user and administrator
     * 
     * @param object     $transaction   Transaction data
     * @param JRegistry  $params        Component parameters
     * @param object     $project       Project data
     * @param object     $reward        Reward data
     */
    public function onAfterPayment($context, &$transaction, $params, $project, $reward) {
        
        $app = JFactory::getApplication();
        /** @var $app JSite **/
        
        if($app->isAdmin()) {
            return;
        }

        $doc     = JFactory::getDocument();
        /**  @var $doc JDocumentHtml **/
        
        // Check document type
        $docType = $doc->getType();
        if(strcmp("raw", $docType) != 0){
            return;
        }
       
        if(strcmp("com_crowdfunding.notify.authorizenet", $context) != 0){
            return;
        }
        
        // Send email to the administrator
        if($this->params->get("authorizenet_send_admin_mail", 0)) {
        
            $subject = JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_NEW_INVESTMENT_ADMIN_SUBJECT");
            $body    = JText::sprintf("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_NEW_INVESTMENT_ADMIN_BODY", $project->title);
            $return  = JFactory::getMailer()->sendMail($app->getCfg("mailfrom"), $app->getCfg("fromname"), $app->getCfg("mailfrom"), $subject, $body);
            
            // Check for an error.
            if ($return !== true) {
                $error = JText::sprintf("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_ERROR_MAIL_SENDING_ADMIN");
                JLog::add($error);
            }
        }
        
        // Send email to the user
        if($this->params->get("authorizenet_send_user_mail", 0)) {
        
            jimport("itprism.string");
            $amount  = ITPrismString::getAmount($transaction->txn_amount, $transaction->txn_currency);
            
            $user    = JUser::getInstance($project->user_id);
            
             // Send email to the administrator
            $subject = JText::sprintf("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_NEW_INVESTMENT_USER_SUBJECT", $project->title);
            $body    = JText::sprintf("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_NEW_INVESTMENT_USER_BODY", $amount, $project->title);
            $return  = JFactory::getMailer()->sendMail($app->getCfg("mailfrom"), $app->getCfg("fromname"), $user->email, $subject, $body);
    		
    		// Check for an error.
    		if ($return !== true) {
    		    $error = JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_ERROR_MAIL_SENDING_USER");
    			JLog::add($error);
    		}
    		
        }
        
        $returnUrl = $this->getReturnUrl($project->slug, $project->catslug);
        
        echo '<html><head><script>
        <!--
        window.location="'.$returnUrl.'";
        //-->
        </script>
        </head><body><noscript><meta http-equiv="refresh" content="1;url='.$returnUrl.'"></noscript></body></html>';
        
    }
    
	/**
     * Validate transaction
     * 
     * @param array $data
     * @param string $currency
     * @param array $intention
     * 
     * @todo It must be tested with international transaction ( currency other than USD ), 
     * bacause the response in test mode does not return currency value ( x_currency_code ). 
     */
    protected function validateData($data, $currency, $intention) {
        
        jimport("itprism.payment.authorizenet.authorizenetresponse");
        
        $authResponse = new ITPrismPaymentAuthorizeNetResponse($data);
        
        $apiLoginId = JString::trim($this->params->get("authorizenet_login_id"));
        $md5Setting = JString::trim($this->params->get("authorizenet_md5_hash"));
        
        $authResponse->setApiLoginId($apiLoginId);
        $authResponse->setMd5Setting($md5Setting);
        
        // Check for valid response.
        if(!$authResponse->isAuthorizeNet()) {
            $error  = JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_ERROR_INVALID_RESPONSE");
            $error .= "\n". JText::sprintf("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_RESPONSE_DATA", var_export($authResponse, true));
            JLog::add($error);
            return null;
        }
        
        // Get date
        $date    = new JDate();
        
        // Get currency
        $txnCurrency = (!$authResponse->getCurrency()) ? $currency : $authResponse->getCurrency();
        
        // If it is test mode, set fake transaction ID.
        if($this->params->get("authorizenet_sandbox", 0)) {
            jimport("itprism.string");
            $authResponse->setTransactionId(ITPrismString::generateRandomString(10, "TEST"));
            $txnCurrency = "USD";
        }
        
        // Prepare transaction data
        $transaction = array(
            "investor_id"		     => (int)$intention->getUserId(),
            "project_id"		     => (int)$intention->getProjectId(),
            "reward_id"			     => ($intention->isAnonymous()) ? 0 : (int)$intention->getRewardId(),
        	"service_provider"       => "AuthorizeNet",
        	"txn_id"                 => $authResponse->getTransactionId(),
        	"txn_amount"		     => $authResponse->getAmount(),
            "txn_currency"           => $txnCurrency,
            "txn_date"               => $date->toSql(),
        ); 
        
        if($authResponse->isApproved()) {
            $transaction["txn_status"] = "completed";
        } else {
            $transaction["txn_status"] = "error";
            $transaction["extra_data"] = array(
                "x_response_reason_code" => $authResponse->getResponseReasonCode(),
                "x_response_reason_text" => $authResponse->getResponseReasonText()
            );
        }
        
        // Check Project ID and Transaction ID
        if(!$transaction["project_id"] OR !$transaction["txn_id"]) {
            $error  = JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_ERROR_INVALID_TRANSACTION_DATA");
            $error .= "\n". JText::sprintf("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_TRANSACTION_DATA", var_export($transaction, true));
            JLog::add($error);
            return null;
        }
        
        // Check currency
        if(strcmp($transaction["txn_currency"], $currency) != 0) {
            $error  = JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_ERROR_INVALID_TRANSACTION_CURRENCY");
            $error .= "\n". JText::sprintf("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_TRANSACTION_DATA", var_export($transaction, true));
            $error .= "\n". JText::sprintf("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_CURRENCY_DATA", var_export($currency, true));
            JLog::add($error);
            return null;
        }
        
        return $transaction;
    }
    
    protected function updateReward(&$data) {
        
        jimport("crowdfunding.reward");
        $keys   = array(
        	"id"         => $data["reward_id"], 
        	"project_id" => $data["project_id"]
        );
        $reward = new CrowdFundingReward($keys);
        
        // Check for valid reward
        if(!$reward->getId()) {
            $error  = JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_ERROR_INVALID_REWARD");
            $error .= "\n". JText::sprintf("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_TRANSACTION_DATA", var_export($data, true));
			JLog::add($error);
			
			$data["reward_id"] = 0;
			return null;
        }
        
        // Check for valida amount between reward value and payed by user
        $txnAmount = JArrayHelper::getValue($data, "txn_amount");
        if($txnAmount < $reward->getAmount()) {
            $error  = JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_ERROR_INVALID_REWARD_AMOUNT");
            $error .= "\n". JText::sprintf("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_TRANSACTION_DATA", var_export($data, true));
			JLog::add($error);
			
			$data["reward_id"] = 0;
			return null;
        }
        
        // Verify the availability of rewards
        if($reward->isLimited() AND !$reward->getAvailable()) {
            $error  = JText::_("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_ERROR_REWARD_NOT_AVAILABLE");
            $error .= "\n". JText::sprintf("PLG_CROWDFUNDINGPAYMENT_AUTHORIZENET_TRANSACTION_DATA", var_export($data, true));
			JLog::add($error);
			
			$data["reward_id"] = 0;
			return null;
        }
        
        // Increase the number of distributed rewards 
        // if there is a limit.
        if($reward->isLimited()) {
            $reward->increaseDistributed();
            $reward->store();
        }
        
        return $reward;
    }
    
    /**
     * Save transaction
     * 
     * @param array               $data
     * @param CrowdFundingProject $project
     * 
     * @return boolean
     */
    public function storeTransaction($data, $project) {
        
        // Get transaction by txn ID
        jimport("crowdfunding.transaction");
        $keys = array(
            "txn_id" => JArrayHelper::getValue($data, "txn_id")
        );
        
        $transaction = new CrowdFundingTransaction($keys);
        
        // Check for existed transaction
        if($transaction->getId()) {
            
            // If the current status if completed,
            // stop the process.
            if($transaction->isCompleted()) {
                return false;
            } 
            
        }

        // Store the new transaction data.
        $transaction->bind($data);
        $transaction->store();
        
        $txnStatus = JArrayHelper::getValue($data, "txn_status");
        
        // If it is not completed (it might be pending or other status),
        // stop the process. Only completed transaction will continue 
        // and will process the project, rewards,...
        if(!$transaction->isCompleted()) {
            return false;
        }
        
        // If the new transaction is completed, 
        // update project funded amount.
        $amount = JArrayHelper::getValue($data, "txn_amount");
        $project->addFunds($amount);
        $project->store();
        
        return true;
    }
    
    
    private function getNotifyUrl() {
        
        $notifyPage = JString::trim($this->params->get('authorizenet_notify_url'));
        
        $uri        = JURI::getInstance();
        $domain     = $uri->toString(array("host"));
        
        if( false == strpos($notifyPage, $domain) ) {
            $notifyPage = JURI::root().str_replace("&", "&amp;", $notifyPage);
        }
        
        return $notifyPage;
        
    }
    
    private function getReturnUrl($slug, $catslug) {
        
        $returnPage = JString::trim($this->params->get('authorizenet_return_url'));
        if(!$returnPage) {
            $uri        = JURI::getInstance();
            $returnPage = $uri->toString(array("scheme", "host")).JRoute::_(CrowdFundingHelperRoute::getBackingRoute($slug, $catslug, "share"), false);
        } 
        
        return $returnPage;
        
    }
    
    private function isAuthorizeNetGateway($custom) {
        
        $paymentGateway = JArrayHelper::getValue($custom, "gateway");

        if(strcmp("AuthorizeNet", $paymentGateway) != 0 ) {
            return false;
        }
        
        return true;
    }
}