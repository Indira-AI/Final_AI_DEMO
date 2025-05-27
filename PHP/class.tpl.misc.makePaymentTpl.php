<?php
/* * **************************************************************************
 * @File            create Agency
 * @Description     This class file holds all employee related informations. in this we can get the itienary dispaly using both order id and package id.
 * @Author          Taslim
 * @Created Date    23/08/2016
 * @Tables used     billing_details, order_details, payment_details
 * *************************************************************************** */
use \Logger\MongoLogger as MongoLogger;
require_once __DIR__."/../../../../../classes/class.mongologger.php";

pluginFileRequire('misc/corporate/harinim/', 'classes/class.bookingDetailsSync.php');
fileRequire('plugins/airDomestic/personal/harinim/classes/class.airRequest.php');
fileRequire('plugins/airDomestic/personal/harinim/classes/class.package.php');
fileRequire('plugins/airDomestic/personal/harinim/classes/class.airline.php');
fileRequire('plugins/airDomestic/personal/harinim/classes/class.passenger.php');
fileRequire('plugins/misc/personal/harinim/classes/class.payment.php');
pluginFileRequire('common/', 'interface/commonConstants.php');

class makePaymentTpl implements commonConstants
{

    //Class variables
    public $_Oconnection;

    public function __construct()
    {

        $this->_AapplicationError[] = '';
        $this->_OapplicationSettings = new personal\applicationSettings();
        $this->_OPayment = new personal\payment();
        $this->_Oreschedule = new \reschedule();
        $this->_OcommonDBO = new commonDBO();
        $this->_OcommonMethods = new commonMethods();
        $this->_OpackageDetails = new personal\package();
        $this->_ObookingDetailsSync = common::_checkClassExistsInNameSpace('bookingDetailsSync');
        $this->_SrescheduleStatus = 'N';
        $this->_viewStatus = '';
        $this->_postSSR = 'N';
    }

    /*
     * @Description  this function handles the create agency logic and template to be ovewridden and displayed
     * @param
     * @return
     */
    public function _getDisplayInfo()
    {
        // $expireTime = ($_SESSION['EXPIRE_TIME'] - time())/60;
        // if($expireTime < 15){
        //     $_SESSION['EXPIRE_TIME'] = strtotime("+15 minutes");
        // }
        //for hotel array forming
        fileWrite(print_r($this->_IinputData,1),"PAYYYY",'a+');
        if ($this->_IinputData['serviceResponse']['mode'] == 'hotel') {
            $this->_IinputData = $this->_IinputData['serviceResponse'];
            $this->_IinputData['action'] = 'makePayment';
        }

        $this->_OAgency->_IinputData['corporateId'] = $_SESSION['corporateId'];

        $this->_action = isset($this->_IinputData['action']) && !empty($this->_IinputData['action']) ? $this->_IinputData['action'] : '';
        $this->_IinputData['package_id'] = isset($this->_IinputData['serviceResponse']['package_id']) ? $this->_IinputData['serviceResponse']['package_id'] : $this->_IinputData['package_id'];
        $this->_IinputData['package_id'] = !isset($this->_IinputData['package_id']) || empty($this->_IinputData['package_id']) ? $this->_IinputData['serviceResponse']['moduleInputData']['r_package_id'] : $this->_IinputData['package_id'];
        $this->_IinputData['order_id'] = isset($this->_IinputData['serviceResponse']['order_id']) ? $this->_IinputData['serviceResponse']['order_id'] : $this->_IinputData['order_id'];

        if (isset($this->_IinputData['serviceResponse']['payment'])) {
            foreach ($this->_IinputData['serviceResponse']['payment'] as $key => $val) {
                $this->_IinputData[$key] = $val;
            }
        }

        if (isset($this->_IinputData['serviceResponse']['message'])) {
            unset($this->_IinputData['serviceResponse']['message']);
        }

        if (isset($this->_IinputData['serviceResponse']['status'])) {
            unset($this->_IinputData['serviceResponse']['status']);
        }

        if (isset($this->_IinputData['serviceResponse']['package_id'])) {
            unset($this->_IinputData['serviceResponse']['package_id']);
        }
        if (isset($this->_IinputData['serviceResponse']['order_id'])) {
            unset($this->_IinputData['serviceResponse']['order_id']);
        }

        if (isset($this->_IinputData['serviceResponse']['payment'])) {
            unset($this->_IinputData['serviceResponse']['payment']);
        }
        $this->_IinputData['orderDetailsArray'] = $this->_IinputData['serviceResponse'];
        unset($this->_IinputData['serviceResponse']);
        fileWrite('after :'.print_r($this->_IinputData,1),"PAYYYY",'a+');
        
        switch ($this->_action) {

            case 'creditcard':
                $this->_SpaymentType = 'creditcard';
                $this->_paymentTypeId = 1;
                //default header display
                $this->_handleCardPayment();

                //$this->_AfinalResponse =  'test data';
                return $this->_AfinalResponse;
                break;

            case 'debitcard':
                $this->_SpaymentType = 'debitcard';
                $this->_paymentTypeId = 5;
                //default header display
                $this->_handleCardPayment();

                //$this->_AfinalResponse =  'test data';
                return $this->_AfinalResponse;
                break;

            case 'sabre_payment_gateway':

                $this->_SpaymentType = 'sabrePG';
                $this->_paymentTypeId = 8;
                $this->_handleCardPayment();
                break;

            case 'makePayment':

                $this->_handleCardPayment();
                return $this->_AfinalResponse;
                break;

            case 'bdesk':

                $this->_SpaymentType = 'bdesk';
                $this->_paymentTypeId = 9;

                $this->_IinputData['package_id'] = $this->_IinputData['package_id'];
                $this->_IinputData['payment_type'] = $this->_SpaymentType;

                $this->_OPayment->_updatePenaltyCharges($this->_IinputData['orderDetailsArray']);

                $this->_handleCardPayment();
                return $this->_AfinalResponse;

                break;

            case 'hdfc':

                $this->_SpaymentType = 'hdfc';
                $this->_paymentTypeId = 8;

                $this->_IinputData['package_id'] = $this->_IinputData['package_id'];
                $this->_IinputData['payment_type'] = $this->_SpaymentType;

                $this->_OPayment->_updatePenaltyCharges($this->_IinputData['orderDetailsArray']);

                $this->_handleCardPayment();
                return $this->_AfinalResponse;

                break;

            case 'updatePenaltyChargeandMakePayment':

                if ($this->_IinputData['orderDetailsArray']['payment_type'] == 'credit_card') {
                    $this->_SpaymentType = 'creditcard';
                    $this->_paymentTypeId = 1;
                } else if ($this->_IinputData['orderDetailsArray']['payment_type'] == 'debit_card') {
                    $this->_SpaymentType = 'debitcard';
                    $this->_paymentTypeId = 5;
                } else if ($this->_IinputData['orderDetailsArray']['payment_type'] == 'hdfc') {
                    $this->_SpaymentType = 'hdfc';
                    $this->_paymentTypeId = 8;
                } else if ($this->_IinputData['orderDetailsArray']['payment_type'] == 'bdesk') {
                    $this->_SpaymentType = 'bdesk';
                    $this->_paymentTypeId = 9;
                } else if ($this->_IinputData['orderDetailsArray']['payment_type'] == 'amadeus') {
                    $this->_SpaymentType = 'amadeus';
                    $this->_paymentTypeId = 10;
                }
                $this->_IinputData['package_id'] = $this->_IinputData['package_id'];
                $this->_IinputData['payment_type'] = $this->_SpaymentType;

                //default header display
                //update rescheudle & cancellation details in passenger_via_details table
                unset($this->_IinputData['orderDetailsArray']['payment_type']);
                $this->_OPayment->_updatePenaltyCharges($this->_IinputData['orderDetailsArray']);

                $this->_handleCardPayment();
                return $this->_AfinalResponse;
                break;

            case 'updateFareIncrease':
                $this->_AfinalResponse = $this->_updateFareIncreaseProcess();
                $this->_AfinalResponse['flagCFS'] = $_SESSION['CONTINUE_FLIGHT_SEARCH'];
                return $this->_AfinalResponse;
                break;

            case 'cancelPaymentProcess':
                $this->_AfinalResponse = $this->_cancelPaymentProcess();
                return $this->_AfinalResponse;
                break;

            case 'checkCaptcha':
                $this->_AfinalResponse = $this->_OPayment->_checkCaptchaSession($this->_IinputData['security_code']);
                return $this->_AfinalResponse;
                break;
            case 'sendPaymentLink':
                $this->_AfinalResponse = $this->_OPayment->_checkCaptchaSession($this->_IinputData['totalAmount']);
                return $this->_AfinalResponse;
                break;

            case 'insertView':
            case 'viewItienary':
                $this->_AfinalResponse = $this->_viewItienary();
                return $this->_AfinalResponse;
                break;

            case 'checkBlockedCard':
                $this->_AfinalResponse = $this->_checkBlockedCard($this->_IinputData['cardno']);
                return $this->_AfinalResponse;
                break;

            case 'checkDebitInsertion':
                $this->_AfinalResponse = $this->_checkDebitInsertion();
                $this->_AfinalResponse['lang_data'] = $this->getLanguageFromSession(); 
                return $this->_AfinalResponse;
                break;

            case 'processAutoTicketing':
                $this->_doAutoTicketing();
                break;

            case 'getCountryInfo':
                $this->_AfinalResponse['countryInfo'] = $this->_getCountryInfo();
                return $this->_AfinalResponse;
                break;

            default:
                $this->_templateAssign();
        }
    }

        public function getLanguageFromSession(){
        $fileName = __DIR__."/../../../../../view/corporate/harinim/languages/".$_SESSION['locale']."/client.json";
        $data = json_decode(file_get_contents($fileName),1);
        return $data;
    }

    /*
     * @Description initiate auto ticketing process
     * @param
     * @return array|$response
     */
    private function _doAutoTicketing()
    {

        fileWrite(print_r($this->_IinputData, true), "AUTOTICKET");

        $this->_OcommonMethods = new commonMethods();

        if (isset($this->_IinputData['packageId']) && $this->_IinputData['packageId'] != '') {

            $resultPackage = $this->_OpackageDetails->_getPaidPackageDetails($this->_IinputData['packageId']);

            fileWrite(print_r($resultPackage, true), "AUTOTICKET", "a+");

            $resultOrderIdArray = array_column($resultPackage, 'sync_order_id');

            fileWrite(print_r($resultOrderIdArray, true), "AUTOTICKET", "a+");

            $url = CORPORATE_AUTOMATE_URL;

            foreach ($resultOrderIdArray as $value) {
                $url = $url . $value;
                fileWrite("URL1:" . $url, "AUTOTICKET", "a+");
                $this->_OcommonMethods->_curlRequest($url);
            }
            $response['status'] = 'yes';
        } else {
            $response['status'] = 'no';
        }
        return $response;
    }

    /*
     * @Description update fare increase
     * @param
     * @return
     */
    private function _updateFareIncreaseProcess()
    {
        $response = $this->_OPayment->_updateFareIncrease($this->_IinputData['orderDetailsArray'], $this->_IinputData['discountData']);
        return $response;
    }

    /*
     * @Description function handles credit card payment logic
     * @param
     * @return
     */
    private function _cancelPaymentProcess()
    {

        $_AorderId = $this->_OPayment->_getPackageOrderIds($this->_IinputData['package_id']);
        $_orderIdCSV = implode(',', $_AorderId);

        $updateArray = array();
        $updateArray['r_ticket_status_id'] = PAYMENT_STATUS_NOTPAID;

        $this->_OPayment->_updateOrderDetails($_orderIdCSV, $updateArray);

        $responseArray['error_alert'] = 'Payment not done';
        $responseArray['status'] = 0;

        return $responseArray;
    }

    /*
     * @Description function handles credit card payment logic
     * @param
     * @return
     */
    private function _handleCardPayment()
    {
        $this->_AfinalResponse['status'] = 1;

        $validateData['security_code'] = $this->_IinputData['security_code'];

        if ($this->_IinputData['payment_type'] == 'credit_card') {
            $this->_SpaymentType = 'creditcard';
            $this->_paymentTypeId = 1;
        } else if ($this->_IinputData['payment_type'] == 'debit_card') {
            $this->_SpaymentType = 'debitcard';
            $this->_paymentTypeId = 5;
        } else if ($this->_IinputData['payment_type'] == 'hdfc') {
            $this->_SpaymentType = 'hdfc';
            $this->_paymentTypeId = 8;
        } else if ($this->_IinputData['payment_type'] == 'bdesk') {
            $this->_SpaymentType = 'bdesk';
            $this->_paymentTypeId = 9;
        } else if ($this->_IinputData['payment_type'] == 'amadeus') {
            $this->_SpaymentType = 'amadeus';
            $this->_paymentTypeId = 10;
        } 

        $validationArray = $this->_OPayment->_validatePayment($this->_IinputData['package_id'], $this->_IinputData['cardno'], $this->_SpaymentType);
        if ($validationArray['status']) {

            $billingDetailsArray = array();

            $billingDetailsArray['address_one'] = isset($this->_IinputData['address1']) && !empty($this->_IinputData['address1']) ? $this->_IinputData['address1'] : '';
            $billingDetailsArray['address_two'] = isset($this->_IinputData['address2']) && !empty($this->_IinputData['address2']) ? $this->_IinputData['address2'] : '';
            $billingDetailsArray['address_three'] = isset($this->_IinputData['address3']) && !empty($this->_IinputData['address3']) ? $this->_IinputData['address3'] : '';
            $billingDetailsArray['city'] = isset($this->_IinputData['city']) && !empty($this->_IinputData['city']) ? $this->_IinputData['city'] : '';
            $billingDetailsArray['state'] = isset($this->_IinputData['state']) && !empty($this->_IinputData['state']) ? $this->_IinputData['state'] : '';
            $billingDetailsArray['country'] = isset($this->_IinputData['country']) && !empty($this->_IinputData['country']) ? $this->_IinputData['country'] : '';
            $billingDetailsArray['pincode'] = isset($this->_IinputData['pincode']) && !empty($this->_IinputData['pincode']) ? $this->_IinputData['pincode'] : '';
            $billingDetailsArray['mobile'] = isset($this->_IinputData['phone']) && !empty($this->_IinputData['phone']) ? $this->_IinputData['phone'] : $_SESSION['mobileNo'];
            $billingDetailsArray['phone'] = isset($this->_IinputData['phone']) && !empty($this->_IinputData['phone']) ? $this->_IinputData['phone'] : $_SESSION['mobileNo'];
            $billingDetailsArray['email_id'] = isset($this->_IinputData['emailid']) && !empty($this->_IinputData['emailid']) ? $this->_IinputData['emailid'] : $_SESSION['loginEmail'];
            $billingDetailsArray['package_id'] = isset($this->_IinputData['package_id']) && !empty($this->_IinputData['package_id']) ? $this->_IinputData['package_id'] : '';
            $billingDetailsInsert = $this->_OPayment->_addBillingDetails($billingDetailsArray);

            //get the package amount to be paid from database
            $paymentAmount = $this->_OPayment->_getPackageTotalAmount($this->_IinputData['package_id']);
            // print_r($this->_IinputData);exit();
            $this->_OcommonDBO = new commonDBO();
            if($this->_IinputData['orderDetailsArray']['rescheduleStatus'] == 'Y'){
                $paymentAmount = $this->_OcommonDBO->_select("order_details","total_amount","order_id",$this->_IinputData['order_id'])[0]['total_amount'];
            }
            fileWrite('paymentAmount : '.print_r($paymentAmount,1),"paymentAmount",'a+');
            if ($billingDetailsInsert) {

                if($this->_IinputData['order_id'] != '')
                    $orderId = $this->_IinputData['order_id'];
                else
                    $orderId = $this->_OcommonDBO->_select('fact_booking_details','r_order_id','r_package_id',$this->_IinputData['package_id'])[0]['r_order_id'];

                $requestId = $this->_OcommonDBO->_select("order_details",'request_id','order_id',$orderId)[0]['request_id'];

                if(NEW_PNR_BLOCK_FLOW == 'Y'){
                    $input['order_id'] = $orderId;
                    $input['sync_order_id'] = $this->_OcommonDBO->_select("order_details",'sync_order_id','order_id',$orderId)[0]['sync_order_id'];
                    $input['status_id'] = PAYMENT_NOT_YET_DONE;
                    $input['payment_type'] = $this->_OcommonDBO->_select("dm_payment_type","payment_type_code","payment_type_id",$this->_paymentTypeId)[0]['payment_type_code'];
                    $this->_OSync = new sync();
                    // $this->_OSync->_statusUpdateSync($input);
                    $request = array("statusUpdateSync" => $input);
                    $this->_OSync->_getdata($request,'statusUpdateSync');
                }

                //if billing details inserted redirectied ot payment gateway
                //calculate total amount for this package to make payment

                $paymentDetailsArray = array();
                ### BILL DESK
                if ($this->_IinputData['payment_type'] == 'bdesk') {

                    ### calculate PG charges
                    $ExtraCharge = $this->_getExtraEBSCharge($this->_IinputData['package_id'], $this->_paymentTypeId);
                    $totalAmount = $paymentAmount + $ExtraCharge;

                    fileRequire('plugins/payment/corporate/harinim/classes/class.pgBillDesk.php');

                    $_OBDESK = new pgBillDesk;
                    $_OBDESK->_SPaymentMode = BDESK_PG_MODE;

                    $packagePaymentAmount = $paymentAmount;

                    $packageTotalAmount = $packagePaymentAmount + $ExtraCharge;
                    $_SESSION['booking_history'][$this->_IinputData['package_id']]['extra_charge'] = $ExtraCharge;

                    $paymentDetailsId = $this->_OPayment->_insertPaymentDetails($this->_IinputData['package_id'], $this->_paymentTypeId, $packageTotalAmount, $packagePaymentAmount, $ExtraCharge);

                    $billingDetailsArray['payment_id'] = $paymentDetailsId ? $paymentDetailsId : '';

                    fileWrite("billingDetailsArray: " . print_r($billingDetailsArray, 1), "testt", "a+");
                    fileWrite("totalAmount: " . print_r($totalAmount, 1), "testt", "a+");

                    //for developer login
                    if ($_SESSION['userTypeId'] == 6) {
                        $totalAmount = 1;
                    }

                    $paymentDetailsArray = $_OBDESK->preparePGNonSeamlessPostArray($billingDetailsArray, $totalAmount);

                    fileWrite("After totalAmount: " . print_r($totalAmount, 1), "testt", "a+");
                    fileWrite("After paymentDetailsArray: " . print_r($paymentDetailsArray, 1), "testt", "a+");

                    $_OBDESK->_insertTransactionDetails($_OBDESK->_SPGTYPE, $packageTotalAmount, $paymentDetailsId, PAYMENT_REQUEST_SOURCE, json_encode($paymentDetailsArray));

                    //set transaction package id
                    $_SESSION['transactionPackageId'] = $this->_IinputData['package_id'];
                    fileWrite(print_r($_SESSION['transactionPackageId'], 1), "transactionPackageId", "a+");

                    $this->_AfinalResponse = $_OBDESK->_doPaymentGatewayRequest($paymentDetailsArray);

                    #### ICICI / EBS Payment Gateway
                } else if ($this->_IinputData['payment_type'] != 'hdfc' && $this->_IinputData['payment_type'] != 'amadeus') {

                    $paymentDetailsArray['requestid'] = 0;
                    $paymentDetailsArray['requestsource'] = PAYMENT_REQUEST_SOURCE;
                    $paymentDetailsArray['passengertype'] = '';
                    $paymentDetailsArray['mealtype'] = '';
                    $paymentDetailsArray['Totalamount'] = $paymentAmount;
                    $paymentDetailsArray['UserName'] = isset($this->_IinputData['cardholdername']) ? $this->_IinputData['cardholdername'] : $this->_IinputData['cardholderfirstname'] . ' ' . $this->_IinputData['cardholdermiddlename'] . ' ' . $this->_IinputData['cardholderlastname'];
                    $paymentDetailsArray['Baddress1'] = $billingDetailsArray['address_one'];
                    $paymentDetailsArray['Baddress2'] = $billingDetailsArray['address_two'];
                    $paymentDetailsArray['Bcity'] = $billingDetailsArray['city'];
                    $paymentDetailsArray['Bstate'] = $billingDetailsArray['state'];
                    $paymentDetailsArray['Bcountry'] = $billingDetailsArray['country'];
                    $paymentDetailsArray['Bpincode'] = $billingDetailsArray['pincode'];
                    $paymentDetailsArray['Bphoneno'] = $billingDetailsArray['phone'];
                    $paymentDetailsArray['Bmobileno'] = $billingDetailsArray['mobile'];
                    $paymentDetailsArray['Bemailid'] = $billingDetailsArray['email_id'];
                    $paymentDetailsArray['Cardtype'] = $this->_IinputData['cardtype'];
                    $paymentDetailsArray['Cardno'] = $this->_IinputData['cardno'];
                    $paymentDetailsArray['Expyear'] = $this->_IinputData['expyear'];
                    $paymentDetailsArray['Expmonth'] = $this->_IinputData['expmonth'];
                    $paymentDetailsArray['Cvv'] = $this->_IinputData['cvv'];
                    $paymentDetailsArray['orderid'] = $this->_IinputData['package_id']; // sending package id instead for order id for handling package
                    $paymentDetailsArray['ordid'] = $this->_IinputData['package_id'];
                    $paymentDetailsArray['status'] = 'N';
                    $paymentDetailsArray['Cardno1'] = '';
                    $paymentDetailsArray['Cardno2'] = '';
                    $paymentDetailsArray['Cardno3'] = '';
                    $paymentDetailsArray['Cardno4'] = '';
                    $paymentDetailsArray['paymentType'] = $this->_SpaymentType;

                    if ($paymentDetailsArray['Cardtype'] == 'AMEX') {
                        $paymentDetailsArray['amexFirstname'] = $this->_IinputData['cardholderfirstname'];
                        $paymentDetailsArray['amexMiddlename'] = $this->_IinputData['cardholdermiddlename'];
                        $paymentDetailsArray['amexLastname'] = $this->_IinputData['cardholderlastname'];
                    } else {
                        $paymentDetailsArray['Cardholder'] = $this->_IinputData['cardholdername'];
                    }

                    //assign Baddress1 to Baddress2
                    $paymentDetailsArray['Baddress2'] = $paymentDetailsArray['Baddress1'];
                    $paymentDetailsArray['Bemailid'] = $billingDetailsArray['email_id'];
                    $paymentDetailsArray['Bphoneno'] = $billingDetailsArray['phone'];
                    $paymentDetailsArray['Bmobileno'] = $paymentDetailsArray['Bphoneno'];
                } else if ($this->_IinputData['payment_type'] == 'hdfc') {
                    #### HDFC payment gateway
                    #
                    $updadteSql = "UPDATE order_details od INNER JOIN fact_booking_details fbd ON od.order_id = fbd.r_order_id SET od.r_ticket_status_id = ".NOT_PAID." WHERE fbd.r_package_id = ".$this->_IinputData['package_id'];
                    $this->_OcommonDBO->_getResult($updadteSql);
                    $this->_OcommonDBO->_update("order_details",$updateArray,"order_id",$this->_IinputData['order_id']);
                    fileWrite("billingDetailsArray: " . print_r($billingDetailsArray, 1), "testt", "a+");
                    fileWrite("billingDetailsArray: " . print_r($this->_IinputData, 1), "testt", "a+");
                    fileWrite("totalAmount: " . print_r($_SESSION, 1), "testt", "a+");

                    ## payment type HDFC payment gateway handle response accordingly
                    $ExtraCharge = 0;

                    ### extra charge not deducted for sabre payment gateway
                    $ExtraCharge = $this->_getExtraEBSCharge($this->_IinputData['package_id'], $this->_paymentTypeId);

                    $packageTotalAmount = $paymentAmount + $ExtraCharge;

                    $paymentDetailsArray['txnid'] = $this->_IinputData['package_id'];
                    $paymentDetailsArray['amount'] = (float) $packageTotalAmount;
                    $paymentDetailsArray['productinfo'] = 'agencyauto';
                    $paymentDetailsArray['firstname'] = isset($this->_IinputData['cardholdername']) ? $this->_IinputData['cardholdername'] : $_SESSION['userName'];
                    $paymentDetailsArray['email'] = $billingDetailsArray['email_id'];
                    $paymentDetailsArray['phone'] = $billingDetailsArray['phone'];
                    $paymentDetailsArray['lastname'] = $this->_IinputData['cardholderlastname'];
                    $paymentDetailsArray['address1'] = $billingDetailsArray['address_one'];
                    $paymentDetailsArray['address2'] = $billingDetailsArray['address_two'];
                    $paymentDetailsArray['city'] = $billingDetailsArray['city'];
                    $paymentDetailsArray['state'] = $billingDetailsArray['state'];
                    $paymentDetailsArray['country'] = $billingDetailsArray['country'];
                    $paymentDetailsArray['zipcode'] = $billingDetailsArray['pincode'];
                    $paymentDetailsArray['udf1'] = PAYMENT_REQUEST_SOURCE;
                    $paymentDetailsArray['surl'] = HDFC_PAYMENT_CALLBACK_PERSONAL_URL;
                    $paymentDetailsArray['furl'] = HDFC_PAYMENT_CALLBACK_PERSONAL_URL;
                    $paymentDetailsArray['curl'] = HDFC_PAYMENT_CALLBACK_PERSONAL_URL;
                    $paymentDetailsArray['custom_note'] = 'Payment gateway charges of 2.5% will be applicable';

                    fileRequire('plugins/payment/corporate/harinim/classes/class.pgHdfc.php');

                    $_OHDFCPG = new pgHdfc;
                    $_OHDFCPG->_SPaymentMode = HDFC_PG_MODE;

                    $_SESSION['booking_history'][$this->_IinputData['package_id']]['extra_charge'] = $ExtraCharge;

                    $paymentDetailsArray['amount'] = $packageTotalAmount;

                    $paymentDetailsId = $this->_OPayment->_insertPaymentDetails($this->_IinputData['package_id'], $this->_paymentTypeId, $packageTotalAmount, $packagePaymentAmount, $ExtraCharge,'',$this->_IinputData['order_id']);
                    $paymentDetailsArray['payment_id'] = $paymentDetailsId ? $paymentDetailsId : '';

                    $paymentDetailsArray = $_OHDFCPG->preparePGNonSeamlessPostArray($paymentDetailsArray);
                    $paymentDetailsArray['payment_id'] = $paymentDetailsId ? $paymentDetailsId : '';
                    ### make payment request hit
                    $_OHDFCPG->_insertTransactionDetails($_OHDFCPG->_SPGTYPE, $packageTotalAmount, $paymentDetailsId, PAYMENT_REQUEST_SOURCE, json_encode($paymentDetailsArray));

                    $this->_AfinalResponse = $_OHDFCPG->_doPaymentGatewayRequest($paymentDetailsArray,'','','HDFC',$requestId);
                }
                else {
                    #### Amedeus payment gateway
                    #
                    $updadteSql = "UPDATE order_details od INNER JOIN fact_booking_details fbd ON od.order_id = fbd.r_order_id SET od.r_ticket_status_id = ".NOT_PAID." WHERE fbd.r_package_id = ".$this->_IinputData['package_id'];
                    $this->_OcommonDBO->_getResult($updadteSql);
                    $this->_OcommonDBO->_update("order_details",$updateArray,"order_id",$this->_IinputData['order_id']);
                    fileWrite("billingDetailsArray: " . print_r($billingDetailsArray, 1), "testtame", "a+");
                    fileWrite("billingDetailsArray: " . print_r($this->_IinputData, 1), "testtame", "a+");
                    fileWrite("totalAmount: " . print_r($_SESSION, 1), "testtame", "a+");

                    ## payment type HDFC payment gateway handle response accordingly
                    $ExtraCharge = 0;

                    ### extra charge not deducted for sabre payment gateway
                    $ExtraCharge = $this->_getExtraEBSCharge($this->_IinputData['package_id'], $this->_paymentTypeId);

                    $packageTotalAmount = $paymentAmount + $ExtraCharge;
                    $packageTotalAmount = number_format((float)$packageTotalAmount, 2, '.', '');

                    fileWrite('packageTotalAmount : '.print_r($packageTotalAmount,1),'paymentAmount','a+');
                    $paymentDetailsArray['txnid'] = $this->_IinputData['package_id'];
                    $paymentDetailsArray['amount'] = $packageTotalAmount;
                    $paymentDetailsArray['productinfo'] = 'agencyauto';
                    $paymentDetailsArray['firstname'] = isset($this->_IinputData['cardholdername']) ? $this->_IinputData['cardholdername'] : $_SESSION['userName'];
                    $paymentDetailsArray['email'] = $billingDetailsArray['email_id'];
                    $paymentDetailsArray['phone'] = $billingDetailsArray['phone'];
                    $paymentDetailsArray['lastname'] = $this->_IinputData['cardholderlastname'];
                    $paymentDetailsArray['address1'] = $billingDetailsArray['address_one'];
                    $paymentDetailsArray['address2'] = $billingDetailsArray['address_two'];
                    $paymentDetailsArray['city'] = $billingDetailsArray['city'];
                    $paymentDetailsArray['state'] = $billingDetailsArray['state'];
                    $paymentDetailsArray['country'] = $billingDetailsArray['country'];
                    $paymentDetailsArray['zipcode'] = $billingDetailsArray['pincode'];
                    $paymentDetailsArray['udf1'] = PAYMENT_REQUEST_SOURCE;
                    $paymentDetailsArray['surl'] = HDFC_PAYMENT_CALLBACK_PERSONAL_URL;
                    $paymentDetailsArray['furl'] = HDFC_PAYMENT_CALLBACK_PERSONAL_URL;
                    $paymentDetailsArray['curl'] = HDFC_PAYMENT_CALLBACK_PERSONAL_URL;
                    $paymentDetailsArray['custom_note'] = 'Payment gateway charges of 2.5% will be applicable';

                    fileRequire('plugins/payment/corporate/harinim/classes/class.pgAmedeus.php');

                    $_OAMEDEUSPG = new pgAmedeus;
                    $_OAMEDEUSPG->_SPaymentMode = AMEDEUS_PG_MODE;

                    $_SESSION['booking_history'][$this->_IinputData['package_id']]['extra_charge'] = $ExtraCharge;

                    $paymentDetailsArray['amount'] = $packageTotalAmount;

                    $paymentDetailsId = $this->_OPayment->_insertPaymentDetails($this->_IinputData['package_id'], $this->_paymentTypeId, $packageTotalAmount, $packagePaymentAmount, $ExtraCharge,'',$this->_IinputData['order_id']);
                    $paymentDetailsArray['payment_id'] = $paymentDetailsId ? $paymentDetailsId : '';

                    $paymentDetailsArray = $_OAMEDEUSPG->preparePGNonSeamlessPostArray($paymentDetailsArray);
                    $paymentDetailsArray['payment_id'] = $paymentDetailsId ? $paymentDetailsId : '';
                    ### make payment request hit
                    $_OAMEDEUSPG->_insertTransactionDetails($_OHDFCPG->_SPGTYPE, $packageTotalAmount, $paymentDetailsId, PAYMENT_REQUEST_SOURCE, json_encode($paymentDetailsArray));

                    fileWrite(print_r($this->_IinputData,1),"IIIO",'a+');
                    
                    $PNR = $this->_OcommonDBO->_select("passenger_via_details","pnr","r_order_id",$orderId)[0]['pnr'];

                    $this->_AfinalResponse = $_OAMEDEUSPG->_doPaymentGatewayRequest($paymentDetailsArray,$PNR,'','AMADEUS',$requestId);
                }
                if ($this->_paymentTypeId == 1) {

                    ## payment type credit card hitting payment gateway handle respone accordingly
                    $ExtraCharge = 0;
                    $packagePaymentAmount = $paymentDetailsArray['Totalamount'];

                    $ExtraCharge = $this->_getExtraEBSCharge($this->_IinputData['package_id'], $this->_paymentTypeId);

                    $packageTotalAmount = $packagePaymentAmount + $ExtraCharge;

                    $_SESSION['booking_history'][$this->_IinputData['package_id']]['extra_charge'] = $ExtraCharge;

                    $paymentDetailsArray['Totalamount'] = $packageTotalAmount;

                    /* if($_SESSION['userTypeId'] == 6) {
                    $paymentDetailsArray['Totalamount'] = 1;
                    $packageTotalAmount = $packagePaymentAmount =  1;
                    $ExtraCharge = 0;
                    } */

                    $paymentDetailsId = $this->_OPayment->_insertPaymentDetails($this->_IinputData['package_id'], $this->_paymentTypeId, $packageTotalAmount, $packagePaymentAmount, $ExtraCharge);

                    $paymentDetailsArray['Paytype'] = $paymentDetailsArray['Paytype2'] = 'Credit Card';
                    $paymentDetailsArray['Cardno1'] = substr($paymentDetailsArray['Cardno'], 0, 4);
                    $paymentDetailsArray['Cardno2'] = substr($paymentDetailsArray['Cardno'], 4, 4);
                    $paymentDetailsArray['Cardno3'] = substr($paymentDetailsArray['Cardno'], 8, 4);
                    $paymentDetailsArray['Cardno4'] = substr($paymentDetailsArray['Cardno'], 12, 4);

                    if ($paymentDetailsArray['Cardtype'] == 'AMEX') {
                        //for amex cards

                        //make payment request hit

                        $paymentResponse = $this->_OPayment->_doPaymentCreditCardRequestAMEX($paymentDetailsArray);

                        //update amex card response in payment_details table
                        $updateArray = array();
                        $updatePaymentArray['payment_response'] = json_encode($paymentResponse);
                        $this->_OPayment->_updatePaymentDetails($this->_IinputData['package_id'], $updatePaymentArray);

                        $this->_handleCreditCardResponse($paymentResponse, $paymentAmount, $this->_IinputData['package_id']);
                    } else {
                        // for master card & visa
                        $paymentDetailsArray['redirectSource'] = ICICI_PAYMENT_CALLBACK_URL_PERSONAL;
                        $paymentDetailsArray['modulenamehidden'] = 'paymentview';
                        $paymentDetailsArray['couponcheck'] = 0;
                        $paymentDetailsArray['accountid'] = '';
                        $paymentDetailsArray['tot'] = $paymentDetailsArray['Totalamount'];
                        $paymentDetailsArray['travelid'] = '';
                        $paymentDetailsArray['traveltype'] = '';
                        $paymentDetailsArray['Cardtype'] = 'VISA';

                        $this->_AfinalResponse = $this->_OPayment->_doPaymentCreditCardRequestVISAMC($paymentDetailsArray);
                    }
                } else if ($this->_paymentTypeId == 5) {
                    ## payment thype debitcard / EBS payment gateway handle response accordingly for EBS payment gateway add extra commission percentage
                    $ExtraCharge = 0;

                    $packagePaymentAmount = $paymentDetailsArray['Totalamount'];
                    $ExtraCharge = $this->_getExtraEBSCharge($this->_IinputData['package_id']);

                    $packageTotalAmount = $packagePaymentAmount + $ExtraCharge;
                    $_SESSION['booking_history'][$this->_IinputData['package_id']]['extra_charge'] = $ExtraCharge;

                    $paymentDetailsArray['Totalamount'] = $packageTotalAmount;
                    /* if($_SESSION['userTypeId'] == 6) {
                    $paymentDetailsArray['Totalamount'] = 1;
                    $packageTotalAmount = $packagePaymentAmount =  1;
                    $ExtraCharge = 0;
                    } */

                    $paymentDetailsId = $this->_OPayment->_insertPaymentDetails($this->_IinputData['package_id'], $this->_paymentTypeId, $packageTotalAmount, $packagePaymentAmount, $ExtraCharge);

                    $paymentDetailsArray['UserName'] = !empty($_SESSION['userName']) ? $_SESSION['userName'] : $_SESSION['employeeEmailId'];
                    $paymentDetailsArray['redirectSource'] = EBS_PAYMENT_CALLBACK_URL_PERSONAL;

                    $this->_AfinalResponse = $this->_OPayment->_doPaymentEBSDebitCardRequest($paymentDetailsArray);
                } else {
                    fileWrite('payment type not defined:' . $this->_paymentTypeId, 'applicationError', 'a+');
                }
            } else {
                $this->_AfinalResponse['error_alert'] = 'Payment cannot be done';
                fileWrite('billing_details table does not exist', 'applicationError');
            }
        } else {
            //invalid payment cannot be done
            $this->_AfinalResponse['error_alert'] = $validationArray['error_alert'] != '' ? $validationArray['error_alert'] : 'Unable to make payment';
        }
    }

    public function _getExtraEBSCharge($packageId, $paymentTypeId)
    {

        $this->_OcommonDBO = new commonDBO();
        $_Opayment = new payment();

        $travelModeId = $this->_OcommonDBO->_select('fact_booking_details', 'travel_mode', 'r_package_id', $packageId)[0]['travel_mode'];

        //get pg charges percentage with respect to the payment type id and agency.
        $pgPercentage = $_Opayment->_getPGcharges($paymentTypeId, $travelModeId);

        //get package info
        $checkEbsExtraCharge = "SELECT
                                    od.total_amount,
                                    od.r_travel_mode_id,
                                    od.order_id
                                FROM
                                    order_details od INNER JOIN fact_booking_details fb ON od.order_id = fb.r_order_id
                                WHERE fb.r_package_id = " . $packageId . " ";

        $result = $this->_OcommonDBO->_getResult($checkEbsExtraCharge);
        foreach ($result as $key => $value) {

            if (($value['r_travel_mode_id'] == 1 || $value['r_travel_mode_id'] == 0 || $value['r_travel_mode_id'] == 9)) {

                //get pg charges
                $airExtraCharges = ($pgPercentage['valueType'] == 'Percentage') ? round(($value['total_amount'] * $pgPercentage['value']) / 100) : $pgPercentage['value'];

                //set pg charges
                $airEBSCharge += $_SESSION['booking_history'][$packageId][$value['order_id']]['extra_charges'] = $airExtraCharges;

                //find GST for the pg charges
                $airExtraChargesGST += $airEBSChargeGST = round(($_SESSION['booking_history'][$packageId][$value['order_id']]['extra_charges'] * PG_GST_PERCENTAGE) / 100);

                //set pg charges GST
                $_SESSION['booking_history'][$packageId][$value['order_id']]['extra_charges_gst'] = $airExtraChargesGST;
            } else if ($value['r_travel_mode_id'] == 2) {
                $hotelEBSCharge += $_SESSION['booking_history'][$packageId][$value['order_id']]['extra_charges'] = ($pgPercentage['valueType'] == 'Percentage') ? round(($value['total_amount'] * $pgPercentage['value']) / 100) : $pgPercentage['value'];
            }

            //set value in session
            $_SESSION['booking_history'][$packageId][$value['order_id']]['package_amount'] = $value['total_amount'];
            $_SESSION['booking_history'][$packageId][$value['order_id']]['total_amount'] = $value['total_amount'] + $_SESSION['booking_history'][$packageId][$value['order_id']]['extra_charges'];
            $_SESSION['booking_history'][$packageId][$value['order_id']]['paid_amount'] = $_SESSION['booking_history'][$packageId][$value['order_id']]['total_amount'];
        }
        $totalAmount = $airEBSCharge + $hotelEBSCharge + $airEBSChargeGST;
        return $totalAmount;
    }

    public function _handleDebitCardResponse($packageId, $paymentTypeId, $paymentAmount, $responseCode, $paymentResponse,$paymentId,$paymentFlag = '')
    {
        if ($packageId == '') {
            return false;
        }
        $this->_OPayment->_checkPackagePaymentStatus($packageId,$paymentId);

        $orderIdsCSV = implode(",", $this->_OPayment->_AorderIds[$packageId]);

        $updatePaymentArray = array();

        fileWrite('$responseCode' . $responseCode, 'paymentResponse', 'a+');

        if ($responseCode == 0) {
            //payment successfull

            $updatePaymentArray['payment_status'] = 'Y';
            $updatePaymentArray['updated'] = '1';
            $updatePaymentArray['payment_date'] = date('Y-m-d H:i:s');

            $paymentDetailsId = $this->_OPayment->_updatePaymentDetails($paymentId, $updatePaymentArray);

            $updateArray = array();
            if(PAYMENT_APPLICATION_TYPE == 'B2C') {
                $updateArray['r_payment_status_id'] = 16;
                $updateArray['r_ticket_status_id'] = 16;
            } else {
                $updateArray['r_payment_status_id'] = PAYMENT_DONE;
                $updateArray['r_ticket_status_id'] = PAID;
            }
            $this->_OPayment->_updateOrderDetails($orderIdsCSV, $updateArray);
            // $paymentDetailsId = $this->_OPayment->_getPaymentDetailsId($packageId);
            $paymentDetailsId = $paymentId;

            //update payment type id in fact_booking_details table
            $updateArray = array();
            $updateArray['r_payment_id'] = $paymentDetailsId;
            $this->_OPayment->_updateFactBookingDetails($orderIdsCSV, $updateArray);

            //not to sync for the developer login
            if ($_SESSION['userTypeId'] != 6) {
                fileWrite("LOGIN:" . $_SESSION['userTypeId'], "testt", "a+");
                ##sync the booking details with agency

                $this->_callDataSync($packageId,'','',$orderIdsCSV,$paymentId ,$paymentFlag);
            }

            $responseArray['error_alert'] = 'Payment success';
            $responseArray['status'] = 0;
        } else {
            //payment unsuccessfull
            $updatePaymentArray = array();
            $updatePaymentArray['updated'] = '1';
            $paymentDetailsId = $this->_OPayment->_updatePaymentDetails($packageId, $updatePaymentArray);
            $paymentDetailsId = $this->_OPayment->_getPaymentDetailsId($packageId);
            //update payment type id in fact_booking_details table
            $paymentUpdateArray = array();
            $paymentUpdateArray['r_payment_id'] = $paymentDetailsId;
            $this->_OPayment->_updateFactBookingDetails($orderIdsCSV, $paymentUpdateArray);
            //update the ticket status in order details.
            $updateArray = array();
             if(PAYMENT_APPLICATION_TYPE == 'B2C') {
                $updateArray['r_ticket_status_id'] = PAYMENT_NOT_DONE;
             } else {
                $updateArray['r_ticket_status_id'] = PAYMENT_STATUS_NOTPAID;
             }
            $this->_OPayment->_updateOrderDetails($orderIdsCSV, $updateArray);

            //not to sync for the developer login
            if ($_SESSION['userTypeId'] != 6) {

                fileWrite("LOGIN:" . $_SESSION['userTypeId'], "testt", "a+");
                //get the not paid package details and sync the booking to backend
                $packageDetails = $this->_OpackageDetails->_getPaymentFailedDetails($packageId, 0, 0);
                fileWrite("payment failed update :".print_r($packageDetails,1),"mongoLoggerFileWrite","a+");
                if($packageDetails['r_travel_mode_id'] == 2){
                    $this->_UPDATELOG['packageId'] = $packageDetails['package_id'];
                    $this->_UPDATELOG['orderId'] = $packageDetails['r_order_id'];
                    $this->_UPDATELOG['key'] = "paymentFailed";
                    fileWrite("payment failed update :".print_r($this->_UPDATELOG,1),"mongoLoggerFileWrite","a+");
                    MongoLogger::_updateHotelRequest($this->_UPDATELOG);
                }

                foreach ($packageDetails as $res) {
                    $this->checkPaymentFailure = 'Y';
                    // $this->_ObookingDetailsSync->_callFailureBookingSync($res, $packageId, $paymentTypeId, $this->checkPaymentFailure);
                }
                if(PAYMENT_APPLICATION_TYPE == 'B2C') {
                    # Only payment success booking will be synced to backend for the b2c Flow
                } else {

                    $this->_callDataSync($packageId,'','',$orderIdsCSV,$paymentId ,'Y');
                }
            }
            $responseArray['error_alert'] = 'Payment failure';
            $responseArray['status'] = 1;
        }

        //tracking table entry
        $packageType = $_SESSION['booking_history'][$packageId]['package_type'];
        // if ($paymentTypeId != 8) {
            $this->_createBookingHistory($packageId, $responseCode, $paymentAmount);
        // }
        $this->_sendBookingMail($packageId, $packageType, $responseCode, $paymentResponse, 'EBS');
        return $responseArray;
    }

    /*
     * @Description: this function used to update continue flight search payment details
     * @param payment rsponse
     * @return template
     */
    public function _handleContinueFlightSearchResponse($packageId, $paymentTypeId, $paymentAmount, $responseCode, $paymentResponse, $paymentId)
    {
        $orderId = $this->_OcommonDBO->_select('payment_details', 'r_order_id', 'payment_id', $paymentId)[0]['r_order_id'];

        $packageId = $this->_OcommonDBO->_select('fact_booking_details', 'r_package_id', 'r_order_id', $orderId)[0]['r_package_id'];

        $updatePaymentArray = array();

        $_Otwig = init();

        fileWrite('$responseCode' . $responseCode, 'paymentResponse', 'a+');

        //payment successfull
        if ($responseCode == 0) {

            //update payment_details table
            $updatePaymentArray['payment_status'] = 'Y';
            $updatePaymentArray['updated'] = '1';
            $updatePaymentArray['payment_date'] = $this->_OcommonMethods->_getUTCTime();
            $paymentDetailsId = $this->_OPayment->_updatePaymentResponse($paymentId, $updatePaymentArray);

            //update  order_details table
            $updateArray = array();
            $updateArray['r_payment_status_id'] = PAYMENT_DONE;
            $updateArray['r_ticket_status_id'] = PAID;
            $this->_OPayment->_updateOrderDetails($orderId, $updateArray);

            //update booking_history total paid amount
            $this->_OPayment->_updateBookingHistoryAmount($orderId, $paymentAmount);

            ##sync the booking details with agency

            $this->_callDataSync('', '', '', $orderId, $paymentId);
            $_STemplateDisplay = $_Otwig->render('additionalpaymentSuccessResponse.html');
        }
        //payment unsuccessfull
        else {

            //update payment details table
            $updatePaymentArray = array();
            $updatePaymentArray['updated'] = '1';
            $paymentDetailsId = $this->_OPayment->_updatePaymentResponse($paymentId, $updatePaymentArray);

            //update the ticket status in order details.
            $updateArray = array();
            $updateArray['r_ticket_status_id'] = PAYMENT_STATUS_NOTPAID;
            $this->_OPayment->_updateOrderDetails($orderId, $updateArray);

            //get the not paid package details and sync the booking to backend
            $packageDetails = $this->_OpackageDetails->_getPaymentFailedDetails(0, 0, $orderId);

            $this->_ObookingDetailsSync->_paymentTypeId = $paymentTypeId;

            //set method name
            $requestMethod = SELF::AIR_SYNC_METHOD;

            $_AairRequestData[] = $this->_ObookingDetailsSync->_getBookingFullFillmentRequest($packageDetails[0], 0, $orderId, $packageDetails[0]['r_travel_mode_id'], $packageDetails[0]['booking_type'], 0, 'Y');

            $this->_ObookingDetailsSync->_callBookingSyncMethod($_AairRequestData, $requestMethod, $packageId, $res['booking_type']);

            $_STemplateDisplay = $_Otwig->render('additionalpaymentFailureResponse.html');
        }

        echo $_STemplateDisplay;
        exit();
    }
    public function _handleHDFCPaymentResponse($packageId, $paymentTypeId, $paymentAmount, $responseCode, $paymentResponse)
    {
        $this->_OPayment->_checkPackagePaymentStatus($packageId);

        $orderIdsCSV = implode(",", $this->_OPayment->_AorderIds[$packageId]);

        $updatePaymentArray = array();

        if ($responseCode == 0) {
            //payment successfull
            $updatePaymentArray['payment_status'] = 'Y';
            $updatePaymentArray['updated'] = '1';
            $updatePaymentArray['payment_date'] = $this->_OcommonMethods->_getUTCTime();
            $paymentDetailsId = $this->_OPayment->_updatePaymentDetails($packageId, $updatePaymentArray);

            $updateArray = array();
            $updateArray['r_payment_status_id'] = PAYMENT_DONE;
            $updateArray['r_ticket_status_id'] = PAID;
            $this->_OPayment->_updateOrderDetails($orderIdsCSV, $updateArray);
            $paymentDetailsId = $this->_OPayment->_getPaymentDetailsId($packageId);
            //update payment type id in fact_booking_details table
            $updateArray = array();
            $updateArray['r_payment_id'] = $paymentDetailsId;
            $this->_OPayment->_updateFactBookingDetails($orderIdsCSV, $updateArray);

            ##sync the booking details with agency

            $this->_callDataSync($packageId);

            $responseArray['error_alert'] = 'Payment failure';
            $responseArray['status'] = 1;
        } else {
            //payment unsuccessfull
            $updatePaymentArray = array();
            $updatePaymentArray['updated'] = '1';
            $paymentDetailsId = $this->_OPayment->_updatePaymentDetails($packageId, $updatePaymentArray);

            $updateArray = array();
            $updateArray['r_ticket_status_id'] = PAYMENT_STATUS_NOTPAID;
            $this->_OPayment->_updateOrderDetails($orderIdsCSV, $updateArray);

            $responseArray['error_alert'] = 'Payment failure';
            $responseArray['status'] = 1;
        }

        //tracking table entry
        $packageType = $_SESSION['booking_history'][$packageId]['package_type'];
        $this->_createBookingHistory($packageId, $responseCode, $paymentAmount);
        $this->_sendBookingMail($packageId, $packageType, $responseCode, $paymentResponse, 'EBS');
        return $responseArray;
    }

    public function _sendBookingMail($packageId, $packageType, $responseCode = 0, $paymentResponse, $PGType)
    {
        //send mail only when payment is successfull otherwise do not trigget mail
        $this->_OapplicationSettings->_Otwig = $this->_Otwig;
        $this->_OapplicationSettings->_mailBookingInfo($packageId, $packageType);
    }

    private function _createBookingHistory($packageId, $responseCode, $paymentAmount)
    {
        fileWrite(print_r(DEBUG_ORDER_TRACKING, 1), "_createBookingHistory");
        //tracking table entry
        if (DEBUG_ORDER_TRACKING) {
            $applicationSettings = new applicationSettings();
            fileWrite(print_r($applicationSettings, 1), "_createBookingHistory", "a+");
            $agency = new agency();

            if (isset($_SESSION['corporateId']) && !empty($_SESSION['corporateId'])) {
                $result = $agency->_getMappedAgencyListForCorporate($_SESSION['corporateId']);
                $agencyId = $result[0]['dm_agency_id'];
            }
            fileWrite(print_r($agencyId, 1), "_createBookingHistory", "a+");
            fileWrite(print_r($this->_OPayment->_AorderIds, 1), "_createBookingHistory", "a+");

            foreach ($this->_OPayment->_AorderIds[$packageId] as $res) {
                $bookingArray = array();
                $bookingArray = $_SESSION['booking_history'][$packageId][$res];
                $bookingArray['r_booked_by'] = $_SESSION['employeeId'];
                $bookingArray['r_corporate_id'] = $_SESSION['corporateId'];
                $bookingArray['r_agency_id'] = $agencyId;
                $bookingArray['package_id'] = $packageId;
                $bookingArray['order_id'] = $res;
                $bookingArray['payment_status'] = ($responseCode == 0) ? 'Y' : 'N';
                $bookingArray['payment_date'] = ($responseCode == 0) ? date('Y-m-d H:i:s') : '';
                $bookingArray['transaction_type'] = 'booking';
                fileWrite(print_r($bookingArray, 1), "_createBookingHistory", "a+");
                $applicationSettings->_addBookingHistory($bookingArray);
            }
            unset($_SESSION['booking_history']);
        }
    }

    public function _handleCreditCardResponse($paymentResponse, $paymentAmount, $packageId)
    {
        $this->_OPayment->_checkPackagePaymentStatus($packageId);

        if ($paymentResponse[0] == 'Y') {

            $responseCode = 0;

            //payment success
            //insert details into payment_details
            if ($paymentResponse[3] == $packageId) {

                $updateArray = array();
                $updateArray['payment_status'] = 'Y';
                $updateArray['updated'] = '1';
                $updateArray['payment_date'] = $this->_OcommonMethods->_getUTCTime();
                $paymentDetailsId = $this->_OPayment->_updatePaymentDetails($packageId, $updateArray);
                $orderIdsCSV = implode(",", $this->_OPayment->_AorderIds[$packageId]);

                //update PAID & status in order_details tables
                $updateArray = array();
                $updateArray['r_payment_status_id'] = PAYMENT_DONE;
                $updateArray['r_ticket_status_id'] = PAID;
                $this->_OPayment->_updateOrderDetails($orderIdsCSV, $updateArray);
                $paymentDetailsId = $this->_OPayment->_getPaymentDetailsId($packageId);

                //update payment type id in fact_booking_details table
                $updateArray = array();
                $updateArray['r_payment_id'] = $paymentDetailsId;
                $this->_OPayment->_updateFactBookingDetails($orderIdsCSV, $updateArray);

                //calling sync of data with corporate website - agency website
                if ($this->_paymentTypeId != 8) {
                    $this->_callDataSync($packageId);
                }

                $this->_AfinalResponse['error_alert'] = 'Payment done successfully';
                $this->_AfinalResponse['status'] = 0;
            } else {
                fileWrite('Invalid reference number receivied not updated', 'paymentResponse', 'a+');

                $updateArray = array();
                $updateArray['updated'] = '1';
                $paymentDetailsId = $this->_OPayment->_updatePaymentDetails($packageId, $updateArray);

                //payment failure
                $updateArray = array();
                $updateArray['r_ticket_status_id'] = PAYMENT_STATUS_NOTPAID;
                $this->_OPayment->_updateOrderDetails($orderIdsCSV, $updateArray);

                $this->_AfinalResponse['error_alert'] = 'Payment not done - order mismatch';
                $this->_AfinalResponse['status'] = 1;
            }
        } else {
            $responseCode = 1;
            //payment failure
            $orderIdsCSV = implode(",", $this->_OPayment->_AorderIds[$packageId]);

            $updatePaymentArray = array();
            $updatePaymentArray['updated'] = '1';
            $paymentDetailsId = $this->_OPayment->_updatePaymentDetails($packageId, $updatePaymentArray);

            $updateArray = array();
            $updateArray['r_ticket_status_id'] = PAYMENT_STATUS_NOTPAID;
            $this->_OPayment->_updateOrderDetails($orderIdsCSV, $updateArray);

            $this->_AfinalResponse['error_alert'] = 'Payment failure';
            $this->_AfinalResponse['status'] = 1;
        }

        //tracking table entry
        $packageType = $_SESSION['booking_history'][$packageId]['package_type'];

        $this->_createBookingHistory($packageId, $responseCode, $paymentAmount);
        $this->_sendBookingMail($packageId, $packageType, $responseCode);
    }

    /*
     * @Description  this function assigns the values to be sent to the template view
     * @param
     * @return
     */
    public function _templateAssign()
    {
        $this->_AtwigOutputArray['action'] = $this->_action;
        $this->_AtwigOutputArray['moduleName'] = $this->_SmoduleName;
    }

    public function _callDataSync($packageId, $paymentTypeId = '', $paymentDetailsArray = '', $orderId = '', $paymentId = '', $paymentFlag = '')
    {
        if(PAYMENT_APPLICATION_TYPE == 'B2C') {
            $this->_ObookingDetailsSync->_automationProcess = 'YES';
        } else {
            fileWrite(print_r($_SESSION,1),"aqwe","a+");
            fileWrite(print_r($packageId,1),"aqwe","a+");
        }
            //insert Activity
            $this->_Ocommon = new \App\Application\Actions\Common;
            
            $activity_data['employee_id'] = $_SESSION['employeeId'];
            $activity_data['action'] = 'Payment was Done on <b>###time###</b> by <b>###user###</b>';
            $activity_data['order_id'] = $orderId;
            $activity_data['activity'] = 'Payment_Done';
            
            if(isset($_SESSION['emulate']) && !empty($_SESSION['emulate'])){
                $activity_data['employee_id'] = $_SESSION['emulate']['emulateEmployeeId']; 
                $activity_data['activity'] = 'Emulate_'.$activity_data['activity'];
            }
            $activity_update = $this->_Ocommon->_insertActivity($activity_data);

        $this->_ObookingDetailsSync->_Otwig = $this->_Otwig;
        if ($paymentTypeId != '') {
            $this->_ObookingDetailsSync->_paymentTypeId = $paymentTypeId;
            $this->_ObookingDetailsSync->_cardDetailsArray = $paymentDetailsArray;
        }
        $this->_ObookingDetailsSync->_syncBookingDetails($packageId, $orderId, '', $paymentId, $paymentFlag);
    }

    public function _viewItienary()
    {

        $adtCount = 0;
        $chdCount = 0;
        $infCount = 0;

        $this->_paxCountStatus = 'N';
        $this->_OcommonDBO = new \commonDBO();
        $this->_OpassengerDetails = new passenger();
        $this->_OairRequest = new airRequest();
        $this->_ApassengerType = array('ADT' => 'Adult', 'INF' => 'Infant', 'CNN' => 'Child');
        $this->_Ostatus_value = new personal\applicationSettings();

        $this->_OairlineDetails = new airline();
        $this->_OflightItinerary = new flightItinerary();
        /*$this->_OhotelItinerary = new hotelItinerary();
        $this->_OhotelRequest = new hotelRequest();*/
        $this->_ObookingRequest = new \bookings();
        $this->_AitineraryDetails = [];
        $this->_AorderDetails = [];
        $this->order = [];
        $total_amount = 0;
        $cancellation = new cancellation();
        $cancellation->_Oconnection = $this->_Oconnection;

        //condition to check whether the package id is set or not for package based display
        if ((isset($this->_IinputData['packageId'])) && ($this->_IinputData['packageId'] != '')) {

            $orderArray = $this->_OpackageDetails->_getPaidPackageDetails($this->_IinputData['packageId']);
            $this->orderIdInfo = $orderArray;

            /*             * * to get the basic info of booking * */
            $this->_IinputData['order_id'] = $orderArray[0]['r_order_id'];
            $this->_IinputData['travel_mode'] = $orderArray[0]['r_travel_mode_id'];
            foreach ($orderArray as $key => $value) {
                $_AorderDetails = $this->_OpackageDetails->_getOrderDetailsInfo($value['r_order_id']);
                if ($_AorderDetails != '') {
                    $this->order = $this->_OpackageDetails->_getOrderInfo($_AorderDetails);
                    if ($key == 0) {
                        $this->_AorderDetails = $this->order;
                    } else {
                        $this->_AorderDetails[0]['total_amount'] += $this->order[0]['total_amount'];
                    }
                }
            }

            $this->_OtravelType = $this->_OairRequest->_getAirRequest($this->_AorderDetails['0']['r_request_id'], 'travel_type');
            foreach ($orderArray as $key => $value) {

                /*** to get the itinerary info of booking **/
                if ($this->_AorderDetails[0]['package_type'] == '2') {
                    //to get hotel itenary details
                    $this->_gethotelItinerary($value['r_order_id']);
                    $this->_viewStatus = 'Hotel';
                } else if ($this->_AorderDetails[0]['package_type'] == '0' || $this->_AorderDetails[0]['package_type'] == '1') {
                    // to get air itenary details
                    $_AitineraryDetails = $this->_OflightItinerary->_getFlightItineraryDetails($value['r_order_id']);
                    if ($_AitineraryDetails != '') {
                        $temp = $this->_OflightItinerary->_getItineraryInfo($_AitineraryDetails);
                        $this->_AitineraryDetails = array_merge($this->_AitineraryDetails, $temp);
                    }
                    if ($key != 0) {
                        $this->_AitineraryDetails[0]['airlineBaseFare'] += $temp[0]['airlineBaseFare'];
                        $this->_AitineraryDetails[0]['airlineTaxFare'] += $temp[0]['airlineTaxFare'];
                        $this->_AitineraryDetails[0]['airlineServiceTax'] += $temp[0]['airlineServiceTax'];
                        $this->_AitineraryDetails[0]['total_amount'] += $temp[0]['total_amount'];
                        $this->_AitineraryDetails[0]['transaction_fee'] += $temp[0]['transaction_fee'];
                        $this->_AitineraryDetails[0]['discount_amount'] += $temp[0]['discount_amount'];
                        $this->_AitineraryDetails[0]['system_usage_fee'] += $temp[0]['system_usage_fee'];
                    } else {
                        $this->_AtwigOutputArray['initialamount'] = $temp[0]['total_amount'];
                    }
                    $this->_viewStatus = 'Air';
                } else {
                    $travelType = $this->_OpackageDetails->_getTravelType($value['r_order_id']);
                    if ($travelType[0]['r_travel_mode_id'] == 1) {
                        $this->_getAirlItinerary($value['r_order_id']);
                    }
                    $this->_gethotelItinerary($value['r_order_id']);
                    $this->_viewStatus = 'combo';
                }
            }

            $this->_ApaxDetails = $this->_OpassengerDetails->_getPassengerDetails($this->_IinputData['order_id']);
            foreach ($this->_ApaxDetails as $key => $values) {
                if ($values['passenger_type'] == 'ADT') {
                    $adtCount = $adtCount + 1;
                } elseif ($values['passenger_type'] == 'CNN') {
                    $chdCount = $chdCount + 1;
                } elseif ($values['passenger_type'] == 'INF') {
                    $infCount = $infCount + 1;
                }
            }

            if ($adtCount > 2 || $chdCount > 2 || $infCount > 2) {
                $this->_paxCountStatus = 'Y';
            }

            /*** to check the order id is rescheduled or not ***/
            $rescheduleDetails = $this->_Oreschedule->_getDmRescheduleDetailsSync($this->_IinputData['order_id']);

            if (isset($rescheduleDetails) && $rescheduleDetails != '') {
                $this->_SrescheduleStatus = 'Y';
                $this->_ArescheduleAmountBreakUp = $this->_Oreschedule->_rescheduleAmountBreakup($this->_IinputData['order_id'], 'Y');
            }
            $this->_itienarytemplateAssign();
        } elseif ((isset($this->_IinputData['orderId'])) && ($this->_IinputData['orderId'] != '')) {

            $_AorderDetails = $this->_OpackageDetails->_getOrderDetailsInfo($this->_IinputData['orderId']);
            if ($_AorderDetails != '') {
                $this->_AorderDetails = $this->_OpackageDetails->_getOrderInfo($_AorderDetails);
            }

            $this->_OtravelType = $this->_OairRequest->_getAirRequest($this->_AorderDetails['0']['r_request_id'], 'travel_type');

            /*** to get the itinerary info of booking **/
            $_AitineraryDetails = $this->_OflightItinerary->_getFlightItineraryDetails($this->_IinputData['orderId']);
            if ($_AitineraryDetails != '') {
                $this->_AitineraryDetails = $this->_OflightItinerary->_getItineraryInfo($_AitineraryDetails);
            }

            $this->_ApaxDetails = $this->_OpassengerDetails->_getPassengerDetails($this->_IinputData['orderId']);

            foreach ($this->_ApaxDetails as $key => $values) {
                if ($values['passenger_type'] == 'ADT') {
                    $adtCount = $adtCount + 1;
                } elseif ($values['passenger_type'] == 'CNN') {
                    $chdCount = $chdCount + 1;
                } elseif ($values['passenger_type'] == 'INF') {
                    $infCount = $infCount + 1;
                }
            }
            if ($adtCount > 2 || $chdCount > 2 || $infCount > 2) {
                $this->_paxCountStatus = 'Y';
            }
            $this->_itienarytemplateAssign();
        } else if (isset($this->_IinputData['status']) && $this->_IinputData['status'] == 'updated') {
            $this->_AtwigOutputArray['packageStatus'] = 'updated';
        } else {
            $this->_AtwigOutputArray['packageStatus'] = 'fail';
        }
    }

    public function _itienarytemplateAssign()
    {
        $this->_AtwigOutputArray['packageStatus'] = 'success';
        $this->_AtwigOutputArray['orderInfo'] = $this->_AorderDetails;
        $this->_AtwigOutputArray['paxInfo'] = $this->_ApaxDetails;
        $this->_AtwigOutputArray['paxCountStatus'] = $this->_paxCountStatus;
        $this->_AtwigOutputArray['cancelInfo'] = $this->_AcancelDetails;
        $this->_AtwigOutputArray['itineraryInfo'] = $this->_AitineraryDetails;
        $this->_AtwigOutputArray['hotelitineraryInfo'] = $this->_HitineraryDetails;
        $this->_AtwigOutputArray['statusId'] = NOTHING_REQUESTED;
        $this->_AtwigOutputArray['travelType'] = $this->_OtravelType['0']['travel_type'];
        $this->_AtwigOutputArray['rescheduledStatus'] = $this->_SrescheduleStatus;
        $this->_AtwigOutputArray['rescheduledBreakup'] = $this->_ArescheduleAmountBreakUp;
        $this->_AtwigOutputArray['action'] = $this->_action;
        $this->_AtwigOutputArray['viewStatus'] = $this->_viewStatus;
        $this->_AtwigOutputArray['packageType'] = $this->_AorderDetails[0]['package_type'];
        $this->_AtwigOutputArray['orderIdInfo'] = $this->orderIdInfo;
        fileWrite(print_r($this->_AtwigOutputArray, 1), 'makePaymentItineary');
    }

    /*
     * @Description  this function handles the itenary display details for hotel
     * @param  orderId
     * @return
     */
    public function _gethotelItinerary($orderId)
    {
        $this->_HitineraryDetails = $this->_OhotelItinerary->_gethotelItineraryDetails($orderId);
        $myString = $this->_HitineraryDetails[0]['amenties_name'];
        $text = str_replace('.jpg', '', $myString);
        $text1 = str_replace('images/', '', $text);
        $this->_HitineraryDetails[0]['imageCode'] = explode(',', $text1);
        $_itenaryRoomType = $this->_OhotelRequest->_getHotelGuestRoomInfo($this->_HitineraryDetails[0]['hotel_request_id']);
        foreach ($_itenaryRoomType as $key => $value) {
            // gettting room type value
            if ($value['r_room_type_id'] != '') {
                $roomType = $this->_OhotelRequest->_roomType($value['r_room_type_id']);
                $value['room_type'] = $roomType[0]['room_type'];
            }

            $data[$key] = $value['room_type'];
        }
        $this->_HitineraryDetails[0]['room_type'] = $data;
        $feeValue = $this->_ObookingRequest->_getBookingAgentFeeDetails($orderId);
        foreach ($feeValue as $key => $value) {
            $this->_HitineraryDetails[0][$value['agent_fee_name']] = $value['fee_value'];
        }
    }

    /*
     * @Description  this function handles the itenary display details for air
     * @param  orderId
     * @return
     */
    public function _getAirlItinerary($orderId)
    {
        $_AitineraryDetails = $this->_OflightItinerary->_getFlightItineraryDetails($orderId);
        if ($_AitineraryDetails != '') {
            $temp = $this->_OflightItinerary->_getItineraryInfo($_AitineraryDetails);
            $this->_AitineraryDetails = array_merge($this->_AitineraryDetails, $temp);
        }
        if ($key != 0) {
            $this->_AitineraryDetails[0]['airlineBaseFare'] += $temp[0]['airlineBaseFare'];
            $this->_AitineraryDetails[0]['airlineTaxFare'] += $temp[0]['airlineTaxFare'];
            $this->_AitineraryDetails[0]['airlineServiceTax'] += $temp[0]['airlineServiceTax'];
            $this->_AitineraryDetails[0]['total_amount'] += $temp[0]['total_amount'];
            $this->_AitineraryDetails[0]['transaction_fee'] += $temp[0]['transaction_fee'];
            $this->_AitineraryDetails[0]['discount_amount'] += $temp[0]['discount_amount'];
            $this->_AitineraryDetails[0]['system_usage_fee'] += $temp[0]['system_usage_fee'];
        } else {
            $this->_AtwigOutputArray['initialamount'] = $temp[0]['total_amount'];
        }
    }

    /**
     * @Description During login check the payment response
     * @author JK Thirumal
     * @param null
     * @return
     */
    private function _checkDebitInsertion()
    {

        if (isset($_SESSION['paymentProcessType']) && $_SESSION['paymentProcessType'] != '') {
            $response['paymentProcessType'] = $_SESSION['paymentProcessType'];
        }
        if (isset($_SESSION['bookingInsertion']) && $_SESSION['bookingInsertion'] != '') {
            $response['packageId'] = isset($_SESSION['bookingInsertion']['orderId']) ? $_SESSION['bookingInsertion']['orderId'] : $_SESSION['bookingInsertion']['packageId'];
            $response['status'] = $_SESSION['bookingInsertion']['status'];
            $response['response'] = $_SESSION['bookingInsertion']['response'];
            $response['pgRefNum'] = $_SESSION['bookingInsertion']['pgRefNum'];
            unset($_SESSION['bookingInsertion']);

            //checking log in through bookink link( if came from booking menu bar will be  hided)
            if (isset($_SESSION['loginName']) && !empty($_SESSION['loginName'])) {
                $response['bookingLinkMenuHide'] = 'Y';
            }
        } else if ($_SESSION['loginName'] && $_SESSION['loginName'] != "") {
            $response['hideMenu'] = $_SESSION['hideMenu'];
            $response['loginName'] = $_SESSION['loginName'];
            $response['moduleName'] = $_SESSION['moduleName'];
            $response['moduleInputs'] = $_SESSION['moduleInputs'];
            $response['wsauthtype'] = $_SESSION['wsauthtype'];
        } else {
            $response['status'] = 'no'; //During login module status will be no
            $response['pgRefNum'] = $_SESSION['bookingInsertion']['pgRefNum'];
        }
        return $response;
    }

    public function _getCountryInfo()
    {
        $this->_OcommonDBO = new commonDBO();
        $countrySql = "SELECT country_code_ISO3,country_name FROM dm_country WHERE country_code_ISO3 != ''";
        $resultCountryInfo = $this->_OcommonDBO->_getResult($countrySql);
        return $resultCountryInfo;
    }

    //function for handling the additional payment response
    public function _handleAdditionPaymentResponse($packageId, $paymentTypeId, $paymentAmount, $responseCode, $paymentResponse, $requestEmailId, $paymentStatusCode, $paymentTypeCode, $paymentId = '')
    {

        global $CFG;
        $updatePaymentArray = array();
        $this->_Otwig = init();
        fileWrite('$responseCode' . $responseCode, 'paymentResponse', 'a+');
        if ($responseCode == 0) {
            //payment successfull
            $updatePaymentArray['payment_status'] = 'Y';
            $updatePaymentArray['updated'] = '1';
            $updatePaymentArray['payment_date'] = $this->_OcommonMethods->_getUTCTime();
            $this->_OcommonDBO->_update('payment_details', $updatePaymentArray, 'payment_id', $paymentId);
            $updateArray['payment_status'] = 'Y';
            $updateArray['payment_type'] = $CFG['paymentType'][9][$paymentTypeCode];
            $this->_OcommonDBO->_update('additional_payment', $updateArray, 'r_payment_id', $paymentId);
            $_STemplateDisplay = $this->_Otwig->render('additionalpaymentSuccessResponse.html');
        } else {
            //payment unsuccessfull
            $updatePaymentArray = array();
            $updatePaymentArray['updated'] = '1';
            $updatePaymentArray['payment_status'] = 'N';
            $this->_OcommonDBO->_update('payment_details', $updatePaymentArray, 'payment_id', $paymentId);
            $updateArray['payment_status'] = 'N';
            $updateArray['payment_type'] = $CFG['paymentType'][9][$paymentTypeCode];
            $this->_OcommonDBO->_update('additional_payment', $updateArray, 'r_payment_id', $paymentId);
            $_STemplateDisplay = $this->_Otwig->render('additionalpaymentFailureResponse.html');
        }
        $this->_ObookingDetailsSync->_syncAdditionalPayment($paymentId, $paymentAmount, $packageId);
        echo $_STemplateDisplay;
        exit();
    }

}
