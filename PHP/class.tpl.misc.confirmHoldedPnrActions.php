<?php
/* * **************************************************************************
 * @File            file used to Confirm a holded pnr
 * @Author          Seemon.G
 * @Created Date    2024-06-20 16:48:11
 * *************************************************************************** */

use \QB\queryBulider as QB;
use \Logger\MongoLogger as MongoLogger;

fileRequire('classes/class.sync.php');
fileRequire("plugins/misc/personal/harinim/classesTpl/class.tpl.misc.holdPnrActions.php");
fileRequire("plugins/airDomestic/corporate/harinim/classesTpl/class.tpl.airDomestic.fareCheckProcessTpl.php");
fileRequire("plugins/misc/corporate/harinim/classes/class.package.php");
// fileRequire("services/src/Application/Actions/Payment/FareCheckAction.php");
fileRequire("plugins/misc/personal/harinim/classesTpl/class.tpl.misc.MyBookingsDisplay.php");
fileRequire("/plugins/misc/corporate/harinim/classesTpl/class.tpl.misc.agencyMarkupManagementTpl.php");
require_once __DIR__ . "/../../../../../classes/class.mongologger.php";
fileRequire('classes/class.retailCommon.php');

// use App\Application\Actions\Payment\FareCheckAction;


class confirmHoldedPnrActions
{
    public $_OcommonDBO= NULL;
    public $_Ocommon = NULL;
    public $_Ocommission = NULL;
    public $_OMyBookingsDisplay = NULL;
    public $_OfareCheckProcessTpl = NULL;
    public $_OretailCommon = NULL;
    public $_Osync = NULL;
    public $_Opackage = NULL;
    public $_OholdPnrActions = NULL;
    public $_OagencyMarkupManagement = NULL;
    public $_OcommonMethods = NULL;
    public function __construct(){
        $this->_Osync = new sync();
        $this->_OretailCommon = new retailCommon();
        $this->_OcommonMethods = new commonMethods();
        $this->_OholdPnrActions = new holdPnrActions();
        $this->_OfareCheckProcessTpl = new fareCheckProcessTpl();
        $this->_Opackage = new package();
        // $this->_OFareCheckAction = new FareCheckAction();	
        $this->_OMyBookingsDisplay = new MyBookingsDisplay();
		$this->_Ocommon = new \App\Application\Actions\Common;
		$this->_OagencyMarkupManagement = new \agencyMarkupManagementTpl();
		$this->_Ocommission = new \App\Application\Actions\Commission;
        $this->_Ocommission->_Ocommon = $this->_Ocommon;
    }
	
	/**
	 * get Display info
     * @description  confirm the PNR
	 * @method      _getDisplayInfo
	 * @Author_name seemon.G <seemon.g@infinitisoftware.net>
	 * @datetime   2024-05-22 17:07:56
	 * @return      void
	 */
	
    public function _getDisplayInfo() {
        # Assing the Input
        $_Ainput = array();
        $_Ainput = $this->_IinputData;
        fileWrite(print_r($_Ainput,true),'0_RELINPUT','a+');
        #Return the Response
        $_Aresponse = $this->_confirmHoldedPnr($_Ainput); 
        $this->_AserviceResponse['data'] = $_Aresponse;     
    }


    /**
      * @description  Action based functionality
	    * @method      _confirmHoldedPnr
	    * @Author_name seemon.g <seemon.g@infinitisoftware.net>
	    * @datetime    2024-05-22 17:08:17
	    * @return      void
	    */

    public function _confirmHoldedPnr($_Ainput){

        # Variable Decalarations
        $_Arequest =array();
        $_Adetails = array();
        $_AserviceResponse = array();

		if(!$this->_checkDuplicatePayment($_Ainput['reference_id'])){
			return ["show_alert" => true,"status_message" => "The payment for this booking has already been processed."];			
		}

        # Get the booking details against package ID.
        $_Adetails = QB::table('order_additional_details oad')
                     ->select(["oad.r_order_id as orderId",
                               "oad.r_package_id as packageId",
        			                 "JSON_UNQUOTE(JSON_EXTRACT(oad.other_info,'$.galileoPnr')) as galileoPnr",
        			                 "JSON_UNQUOTE(JSON_EXTRACT(oad.other_info,'$.gdsPnr')) as gdsPnr",
        			                 "JSON_UNQUOTE(JSON_EXTRACT(oad.other_info,'$.clientId')) as clientId",
        			                 "JSON_UNQUOTE(JSON_EXTRACT(oad.other_info,'$.tripType')) as tripType",
        			                 "JSON_UNQUOTE(JSON_EXTRACT(oad.other_info,'$.orderRequestId')) as requestId",
        			                 "JSON_UNQUOTE(JSON_EXTRACT(oad.other_info,'$.releasedBy')) as releasedBy",
        			                 "JSON_UNQUOTE(JSON_EXTRACT(oad.other_info,'$.confirmedBy')) as confirmedBy",
                                "oad.expiry_ttl_time",
				 "oad.updated_date",
                                "oad.onward_pnr",
                                "oad.return_pnr",
                                "dtm.travel_mode_code",
                                "dp.reservation_code",
                                "dm.status_code",
								"oad.pnr_status"
                               ])
                     ->join('dm_status dm','dm.status_id','=','oad.r_status_id','INNER JOIN')
                     ->join('dm_travel_mode dtm','dtm.travel_mode_id','=','oad.r_travel_mode_id','INNER JOIN')
                     ->join('dm_package dp','dp.package_id','=','oad.r_package_id','INNER JOIN')
                     ->where('oad.r_package_id','=',$_Ainput['reference_id'])
                     ->andWhere('oad.process_type','=','HOLD')
                     ->getResult()[0];
        $_AgalileoPnr = [];
        if(!empty($_Adetails) && $_Adetails['status_code'] == "PNRH"){
            $_DcurrentdateTime = $this->_OcommonMethods->_getUTCTime();
            fileWrite(print_r($_DcurrentdateTime,1),"_DcurrentdateTime","a+");
            fileWrite(print_r($_Adetails['expiry_ttl_time'],1),"_DcurrentdateTime","a+");
            if(strtotime($_DcurrentdateTime) >= strtotime($_Adetails['expiry_ttl_time']) && $_Adetails['expiry_ttl_time'] != '0000-00-00 00:00:00'){
                return  ["show_alert" => true,"status_message" => "Sorry, you can't confirm the booking as the expiry time limit has expired. The booking will be released automatically."];                
            }
            #get a pnr released by HX
            $_StravelModeCode = $_Adetails['travel_mode_code'];
            $_SreservationCode = $_Adetails['reservation_code'];
            $_AgalileoPnr = json_decode($_Adetails['galileoPnr'],true);
            $_AserviceInput = ["travelType" => $_StravelModeCode, "serviceProvoider" => $_SreservationCode, "method" => "hxCron", "requestId" => $_Adetails['requestId'], "galileoPnr" => $_AgalileoPnr,"from" => $_Ainput['method']];
			$_ApnrInactiveMsg = array("C" => "Sorry, you can't confirm the booking as the PNR has been cancelled. The booking will be released automatically.", "R" => "Sorry, you can't confirm the booking as the PNR has been Rescheduled. Please contact support team.");
			if($_Adetails['pnr_status'] == 'C' || $_Adetails['pnr_status'] == 'R'){
                return array("show_alert" => true,"status_message" => $_ApnrInactiveMsg[$_Adetails['pnr_status']]);
			}
            // $_AwsHoldedPnr = $this->_Osync->_getReleasePnrByHx($_AserviceInput);
            $_AwsHoldedPnr = $this->_Osync->_getdata($_AserviceInput,'getReleasePnrHx');
			if($_AwsHoldedPnr['responseData']['hxCron']['status'] || (!empty($_AwsHoldedPnr['responseData']['reScheduleCron']) && $_AwsHoldedPnr['responseData']['reScheduleCron']['status'])){
				$_SpnrStatus = !empty($_AwsHoldedPnr['responseData']['hxCron']['status']) ? 'C' : 'R';
				$this->_OretailCommon->_update('order_additional_details',array('pnr_status'=> $_SpnrStatus),'r_order_id','=',$_Adetails['orderId']);
				return array("show_alert" => true, "status_message" =>  $_ApnrInactiveMsg[$_SpnrStatus]);
			}
			#get my booking display data
			$this->_OMyBookingsDisplay->_IinputData = ["type" => "confirmFlow","reference_id" => $_Adetails['packageId'],"status" =>false];
			$_AbookingData = $this->_OMyBookingsDisplay->_fetchBooking(["type" => "","reference_id" => $_Adetails['packageId'],"status" =>false]);
			if(!empty($_Ainput['method']) && $_Ainput['method'] == 'fareCheck'){
				#prepare repricing service array
				$_ArepriceInput = array(
					"travelType" => $_StravelModeCode,
					"clientId" => $_Adetails['clientId'],
					"reservationName" => $_SreservationCode,
					"accountType" => "RF",
					"subAgencyCode" => 117,
					"gdsPnr" => $_Adetails['gdsPnr'],
					"orderId" => $_Adetails['orderId'],
					"packageId" => $_Adetails['packageId'],
					"tripType" => $_Adetails['tripType']
				);
				$_AserviceResponse = $this->_pnrRePricing($_ArepriceInput);	
				#update orderId in coupon
				if(!empty($_Ainput['userRequest']['couponCode'])){
				 QB::table('coupon_apply_details')
					->update(array('r_order_id'=>$_Adetails['orderId']))
					->where('ws_request_id','=',$_Adetails['requestId'])
					->getResult();
				}
				fileWrite(print_r($_AserviceResponse,1),"repricingService","a+");
				// $this->_OFareCheckAction->InputData = ["data" => $_ArepriceInput];
				$_AserviceResponse = $this->_prepareResponseArray($_AserviceResponse,$_AbookingData);
				// $_Aresponse = $this->_OFareCheckAction->setAcontrollerOutput($_AserviceResponse,$_AbookingData);
				fileWrite(print_r($_AserviceResponse,1),"_Aresponsesss","a+");
				// $this->_OFareCheckAction->_makeResponse();
				$_SESSION['currentPackageId'] = $_Adetails['packageId'];
				if(!$_AserviceResponse['show_alert']){
					$_AserviceResponse['bookingDetails']['packageId'] = $_Adetails['packageId'];
				}
			} else {
				$_AserviceResponse = array("show_alert" => false); 
				$_AserviceResponse['status_message'] = array("packageId" => $_Adetails['packageId']);
			}
			$_AserviceResponse['bookingDetails'] = $_AbookingData;
			return $_AserviceResponse;
        }else{
            $_DreleasedDateandTime = $this->_OretailCommon->_timezoneConversion($_Adetails["updated_date"]);
            $_DreleasedDateandTime = strtotime($_DreleasedDateandTime);
            $_DreleasedDateandTimeFormat = date('d M Y H:i', $_DreleasedDateandTime);
            switch ($_Adetails['status_code']) {
                case 'PNRHR':
                    $_Smessage = "Sorry, you can't confirm the booking as it was already released by " . $_Adetails['releasedBy'] . " on " . $_DreleasedDateandTimeFormat . ".";
                    break;
                case 'PNRRRQ':
                    $_Smessage = "Your Required  booking  is Already released in queue";
                    break;
				case 'P':
					$_Smessage = "Sorry, you can't confirm the booking as it was already confirmed by " . $_Adetails['confirmedBy'] . " on " . $_DreleasedDateandTimeFormat . ".";
					break;
				default:
                    $_Smessage = "Sorry, you can't confirm the booking as either payment has already been initiated or the release is currently in progress";
                    break;
            }
          $_AserviceResponse = array('show_alert'=>true , 'status_message' => $_Smessage);
        }
        return $_AserviceResponse;
    }
    /**
      * @description  Method to hit repricing service
	    * @method      _pnrRePricing
	    * @Author_name seemon.g <seemon.g@infinitisoftware.net>
	    * @datetime    2024-05-22 17:08:17
	    * @return      void
	*/
    private function _pnrRePricing($_Ainput)
    {
        # code...
        $orderArray = $this->_Opackage->_getPaidPackageDetails($_Ainput['packageId']);
        $this->_OfareCheckProcessTpl->_Bhold = true;
        $this->_OfareCheckProcessTpl->_BfareCheckOnly = true;
        $this->_OfareCheckProcessTpl->_ArepriceInput = $_Ainput;
		$this->IS_SINGLE_PNR = 'N';
		$this->_OfareCheckProcessTpl->IS_SINGLE_PNR = 'N';
		// if the traveltype is international and the trip type is roundtrip then its a single PNR process and enable the RTfare
        if($_Ainput['travelType'] == "I" && $_Ainput['tripType'] == "ROUNDTRIP"){
            $this->_OfareCheckProcessTpl->IS_SINGLE_PNR = 'Y';
			$this->IS_SINGLE_PNR = 'Y';
        }
		$this->_OfareCheckProcessTpl->_IinputData = ["packageId" => $_Ainput['packageId']];
        return $this->_OfareCheckProcessTpl->_callFareCheck($orderArray);
    }

    /**
      * @description  Method to prepare repricing response
	    * @method      _pnrRePricing
	    * @Author_name seemon.g <seemon.g@infinitisoftware.net>
	    * @datetime    2024-05-22 17:08:17
	    * @return      void
	*/
    private function _prepareResponseArray($_Aresponse,$data){
		global $CFG;
        fileWrite(print_r($_SESSION,1),"session","a+");
        $this->_IS_RT_FARE = 'N';
        if(count($data['userRequest']['tripWise']) == 2 && $data['userRequest']['tripType'] == "R" && count($data['selectFlights']) == 2 && $this->IS_SINGLE_PNR == 'Y') {
            $IS_RT_FARE = "Y";
            $this->_IS_RT_FARE = 'Y';
        }
		$this->_AcontrollerOutput['serviceResponse']['fareCheckResponse'] = $_Aresponse;
		$this->_AcontrollerOutput['serviceResponse']['package_id'] = $_Aresponse['package_id'];
		$_SESSION['reservation_type'] = $data['selectFlights'][0]['reservation_type'];
		if(AIRLINE_BENEFITS['status'] == 'Y'){
			$this->_ApromoCodeDetails = $data['selectFlights'][0]['promocode'];
			$this->_AtourCodeDetails = $data['selectFlights'][0]['tourcode'];
			foreach($this->_ApromoCodeDetails as $k=>$v){
				$_SESSION[$k]=$v;
			}
			foreach($this->_AtourCodeDetails as $k=>$v){
				$_SESSION[$k]=$v;
			}
		}
		if($_SESSION['reservation_type'] == 'INVENTORY'){
			$otherReservationTypes = array_values(array_diff(array_column($data['selectFlights'],'reservation_type'),array('INVENTORY')));
			$_SESSION['reservation_type'] = (!empty($otherReservationTypes)) ? $otherReservationTypes[0] : "SERVICES";
		}
		if(empty($data['userRequest']['importPNR'])){
			$request = $data;
			unset($request['userRequest']);
			$this->_SEARCHLOG = array_merge($data['userRequest'], $request);
			$this->_SEARCHLOG['orderId'] = $CFG[$data['selectFlights']['0']['commission_unique_id']]['order_id'];
			$this->_SEARCHLOG['packageId'] = $CFG[$data['selectFlights']['0']['commission_unique_id']]['package_id'];
	        $this->_SEARCHLOG['requestFrom'] = 'fareCheckProcess';
	        $this->_SEARCHLOG['analyticsId'] = $data['selectFlights']['0']['commission_unique_id'];
	        unset($this->_SEARCHLOG['farerule']);
	        MongoLogger::_insertFlightRequest($this->_SEARCHLOG);
		}
        $CFG[$data['selectFlights']['0']['commission_unique_id']] = Null;

		// if(isset($this->_AcontrollerOutput['coupon']) && $this->_AcontrollerOutput['coupon'] == 'Yes'){
		// 	$this->_AcontrollerOutput['proceed'] = true;
		// 	$this->_AcontrollerOutput['status_message'] = 'Coupon code expired';
		// 	return $this->respondWithData($this->_AcontrollerOutput);
		// }

		$response = array();

		// if(!$couponResponse['status']){
		// 	$couponResponse['proceed'] = true;
		// 	$couponResponse['status_message'] .= ' continue without coupon code.';
		// 	return $this->respondWithData($couponResponse);
		// }

		if ($this->_AcontrollerOutput['serviceResponse']['fareCheckResponse']['status'] == 'E') {
			$response['show_alert'] = true;
			$response['status'] = false;
			$response['status_message'] = $this->_AcontrollerOutput['serviceResponse']['fareCheckResponse']['message'];

		}
		elseif (!$this->_AcontrollerOutput['serviceResponse']['pnr_status'] && $_SESSION['reservation_type'] == 'AMADEUS' && SERVICE_TYPE != 'UNIFIED_API') {
			$response['show_alert'] = true;
			$response['status'] = false;
			$response['status_message'] = $this->_AcontrollerOutput['serviceResponse']['pnr_message'];
		}
		elseif ($this->_AcontrollerOutput['serviceResponse']['fareCheckResponse']['status'] == 'Y' && $_SESSION['reservation_type'] == 'SERVICES') {
			$response['show_alert'] = true;
			$response['status'] = false;
			$response['order_id'] = $this->_AcontrollerOutput['serviceResponse']['fareCheckResponse']['order_id'];
			$_AserviceResponse = $this->_AcontrollerOutput['serviceResponse']['fareCheckResponse'][$response['order_id']];
			$response['fareIncreaseStatus'] = true;
			/**
			 * Get Markup Fare from fare_details table
			 */
			$_AfareDetails = QB::table('fare_details')->select(['tax_breakup'])->where('r_order_id', '=', $response['order_id'])->getResult()[0]['tax_breakup'];
			#coupon details
            if(!empty($data['userRequest']['couponCode'])){
            	fileWrite('res : '.print_r($response,true),'fareincrease','a+');
                $_AcouponDetails = QB::table('coupon_apply_details')
                	->select(['coupon_details', 'ws_request_id'])
                	->where('r_order_id','=',':oi')
                	->setParameters(['oi'=>$response['order_id']])
                	->getResult();

				if(!empty($_AcouponDetails)){
					$_AcouponDetails[0]['coupon_details'] = json_decode($_AcouponDetails[0]['coupon_details'],true);
				}
            }
			# Agency markup calcultion
			# fetch existing markup plans
			if(BOOKING_TOOL_MENU['subagencyMarkup']['status'] == "Y"){
				if(isset($_SESSION['subagencyId'])&&!empty($_SESSION['subagencyId'])){
					$subagencyMarkupQuery = QB::table('agency_markup')
					->select('*')
					->where('agency_id','=',$_SESSION['subagencyId'])
					->andWhere('status','=','Y')
					->andWhere('r_travel_mode_id','=',1);

					$subagencyMarkup=$subagencyMarkupQuery->getResult();
				}

				#adding the criteria in each set of values
				if(!empty($subagencyMarkup)){
					array_walk($subagencyMarkup,function(&$val,$key){
						$criteriaValues= json_decode($val['criteria'],true);
						$criteriaValues['travel_period']= json_decode($val['travel_period'],true);
						$criteriaValues['booking_period']= json_decode($val['booking_period'],true);
						$val = array_merge($val,$criteriaValues);
					});    
				}
			}
			fileWrite(print_r($subagencyMarkup,true),"farecheckDataRes","a+");

	        $totalTax = $totalBF = $count = $penaltyAmount= $differentAmount = $_IssrAmount = $_IoldBf = $_IoldTax = 0;
	        $count = 0;
	        $_AcouponDiscount = [];
	        foreach ($_AserviceResponse['passengerFareArray'] as $key => $value) {
				
				$_IcouponViaFlightCount = 0;
				if($data['userRequest']['travelType'] == 'I' && $data['userRequest']['tripType'] == 'R'){
					$_IcouponViaFlightCount = count($data['selectFlights'][0]['via_flights']) + count($data['selectFlights'][1]['via_flights']);
				}
				else{
					$_IcouponViaFlightCount = count($data['selectFlights'][$count]['via_flights']);
				}
				
	        	$feeDetails =($data['selectFlights'][$count]['commissionFLow'] == 'new') ? $data['selectFlights'][$count]['passenger_fare'][0]['commission']['commissionDetails'] : $data['selectFlights'][$count]['passenger_fare'][0]['commission'];
	        	$value = $value['response'];
                foreach ($value['segment'][0]['passengerFare'] as $pk => $pv) {
                	$_IoldBf += ($data['userRequest']['paxInfo'][$pv['passengerType']] * $data['selectFlights'][$count]['passenger_fare'][$pk]['base_fare']);
                	$_IoldTax += ($data['userRequest']['paxInfo'][$pv['passengerType']] * $data['selectFlights'][$count]['passenger_fare'][$pk]['tax']);

                    /**
                     * Markup logic for sub agent and agent
                     */
                    # agent markup add - 1
                    if(!empty($_AfareDetails) && $count == 0 && $pv['passengerType'] == 'ADT') {
                    	$_AfareDetails = $_AfareDetails != '' ? json_decode($_AfareDetails, true) : [];
                    	if(isset($_AfareDetails['AGENT_MARKUP_FEE']) && !empty($_AfareDetails['AGENT_MARKUP_FEE'])) {
	                        $_AagentMarkupData['markup'] = $_AfareDetails['AGENT_MARKUP_FEE']['ADT']['tracking_data'];
	                        $_AagentDetails = $this->_Ocommon->_calculateAgentFee($_AagentMarkupData, $pv['passengerType'], $pv['baseFare'], $pv['totalTax']);
	                        if(isset($_AagentDetails['amount']) && $_AagentDetails['amount']) {
								$data['selectFlights'][$count]['increasedMarkupFare'][$pv['passengerType']]['originalBaseFare'] = $pv['baseFare'];
								$data['selectFlights'][$count]['increasedMarkupFare'][$pv['passengerType']]['originalTax'] = $pv['totalTax'];
	                            $pv['baseFare'] += $_AagentDetails['amount'];
								$data['selectFlights'][$count]['increasedMarkupFare']['agentMarkupFeeDetails'][$pv['passengerType']] = $_AagentDetails;
	                        }
	                    }

	                    # Sub agent markup add - 2 
	                    if(isset($_AfareDetails['SUB_AGENT_MARKUP_FEE']) && !empty($_AfareDetails['SUB_AGENT_MARKUP_FEE'])) {
	                        $_AsubAgentMarkupApplicable['ADT'] = 'Y';

	                        $_AsubAgentMarkupDetails = $this->_Ocommon->_calculateSubAgentFee($_AsubAgentMarkupApplicable, $_AfareDetails['SUB_AGENT_MARKUP_FEE']['ADT']['amount'], $pv['passengerType'], $pv['baseFare']);

	                        if(isset($_AsubAgentMarkupDetails['amount']) && $_AsubAgentMarkupDetails['amount']) {
								$data['selectFlights'][$count]['increasedMarkupFare'][$pv['passengerType']]['originalBaseFare'] = $pv['baseFare'];
								$data['selectFlights'][$count]['increasedMarkupFare'][$pv['passengerType']]['originalTax'] = $pv['totalTax'];
	                            $pv['baseFare'] += $_AsubAgentMarkupDetails['amount'];
								$data['selectFlights'][$count]['increasedMarkupFare']['subAgentMarkupFeeDetails'][$pv['passengerType']] = $_AsubAgentMarkupDetails;
	                        }
	                        
	                    }
                    }
					# For Admin Markup in Commission
					if(!empty($feeDetails)) {
						$data['selectFlights'][$count]['increasedAdminMarkupFare']['fareBeforeCommissionDetails'][$pv['passengerType']]['originalBaseFare'] = $pv['baseFare'];
						$data['selectFlights'][$count]['increasedAdminMarkupFare']['fareBeforeCommissionDetails'][$pv['passengerType']]['originalTax'] = $pv['totalTax'];
					}

					#get tax description
					$_AtaxDescription = array_map(function($key, $val) {
						return array(
							'taxCode' => $key,
							'taxDescription' => $key,
							'taxAmount' => $val
						);
					}, array_keys($pv['tax']), $pv['tax']);

                    $data['selectFlights'][$count]['passenger_fare'][$pk]['passenger_type'] = $pv['passengerType'];
                    $data['selectFlights'][$count]['passenger_fare'][$pk]['base_fare'] = $pv['baseFare'];
                    $data['selectFlights'][$count]['passenger_fare'][$pk]['tax'] = $pv['totalTax'];
                    $data['selectFlights'][$count]['passenger_fare'][$pk]['totalAmount'] = $pv['baseFare'] + $pv['totalTax'];
                    $data['selectFlights'][$count]['passenger_fare'][$pk]['newTotalAmount'] = $pv['baseFare'] + $pv['totalTax'];
                    $data['selectFlights'][$count]['passenger_fare'][$pk]['old_base_fare'] = $pv['old_baseFare'];
                    $data['selectFlights'][$count]['passenger_fare'][$pk]['old_tax'] = $pv['old_totalTax'];
                    $data['selectFlights'][$count]['passenger_fare'][$pk]['old_totalAmount'] = $pv['old_totalFare'];
                    $data['selectFlights'][$count]['passenger_fare'][$pk]['penaltyAmount'] = $pv['penaltyAmount'];
                    $data['selectFlights'][$count]['passenger_fare'][$pk]['differentAmount'] = $pv['differentAmount'];
                    $data['selectFlights'][$count]['passenger_fare'][$pk]['discount'] = 0;
                    $data['selectFlights'][$count]['passenger_fare'][$pk]['taxBreakUpDetails'] =  $_AtaxDescription;
					if(empty($feeDetails)) {
						$totalTax += ($data['userRequest']['paxInfo'][$pv['passengerType']] * $pv['totalTax']);
						$totalBF += ($data['userRequest']['paxInfo'][$pv['passengerType']] * $pv['baseFare']);
					}
                    //penalty for the reschedule
                    $penaltyAmount += ($data['userRequest']['paxInfo'][$pv['passengerType']] * $pv['penaltyAmount']);
                    //amount to be paid for reschedule - difference between old and new amount
                    $differentAmount += ($data['userRequest']['paxInfo'][$pv['passengerType']] * $pv['differentAmount']);
                }

				# Apply Commission and ADMIN MARKUP FEE
                if(empty($data['selectFlights'][$count]['totalAmount'])){
					$_OldTotalAmount = $data['selectFlights'][$count]['base_fare'] + $data['selectFlights'][$count]['tax'];     
                } else {
                    $_OldTotalAmount = $data['selectFlights'][$count]['totalAmount'];
                }
				if(!empty($feeDetails)) {
					$data['selectFlights'][$count]['totalAmount'] = $data['selectFlights'][$count]['base_fare'] + $data['selectFlights'][$count]['tax'];     
					fileWrite("new com 2: ".print_r(COMMISSION,true),"farecheckCommission","a+");        
					
					$data['selectFlights'][$count] = ($data['selectFlights'][$count]['commissionFLow'] == 'new') ? $this->_Ocommission->_applyCommissions($feeDetails, $data['selectFlights'][$count], 'ITINERARY') : $this->_Ocommission->_doCommissionCalculation($feeDetails, $data['selectFlights'][$count],'ITINERARY');               

					$data['selectFlights'][$count]['increasedAdminMarkupFare']['ADMIN_MARKUP_FEE'] = $data['selectFlights'][$count]['ADMIN_MARKUP_FEE'];
					$totalBF += $data['selectFlights'][$count]['base_fare'];
					$totalTax += $data['selectFlights'][$count]['tax'];
				}

				#coupon details
                if(!empty($_AcouponDetails)){
                	$this->_OapplyCoupon = new \App\Application\Actions\Payment\ApplyCouponCodeAction;
                	$key = $count == 0 ? 'ONWARD' : 'RETURN';
					
					$this->_OapplyCoupon->_IsectorCount = $_IcouponViaFlightCount;
					$this->_OapplyCoupon->_StravelType = $data['userRequest']['travelType'];

                	$data['selectFlights'][$count]['totalAmount'] = $data['selectFlights'][$count]['base_fare'] + $data['selectFlights'][$count]['tax'];
                	fileWrite('totalamount : '.print_r($data['selectFlights'][$count]['totalAmount'],true),'fareincrease','a+');
                	if(!empty($data['userRequest']['couponCode'])){
                		// $_AcouponDetails['coupon_details'] = $_AcouponDetails['coupon_details']['couponDetails']
                		unset($_AcouponDetails[0]['coupon_details']['totalDiscount']);
                		unset($_AcouponDetails[0]['coupon_details']['couponIds']);
         				fileWrite('req_AcouponDetails : '.print_r($_AcouponDetails,true),'fareincrease','a+');
         				// foreach ($_AcouponDetails[0]['coupon_details']  as $value) {
         					$_AcouponResponse= $this->_OapplyCoupon->_couponCodeCalculations($_AcouponDetails[0]['coupon_details'][$key]['couponDetails'], $data['selectFlights'][$count], $data['userRequest']['couponCode'], $key, $_AcouponDiscount, $_AcouponDetails[0]['ws_request_id']);
         					
         					if(isset($_AcouponResponse['status']) && !$_AcouponResponse['status']){
         						continue;
         					}
         					else{
         						$_AcouponDiscount = $_AcouponResponse;
         					}
         				// }
                		
         				fileWrite('_AcouponDiscount : '.print_r($_AcouponDiscount,true),'fareincrease','a+');
		         		#check update the status
		         		if(empty($_AcouponDiscount)){
		         			fileWrite('update : error','fareincrease','a+');
		         			QB::table('coupon_apply_details')
		         				->update(array('status'=>'E','update_date'=>gmdate("Y-m-d H:i:s")))
		         				->where('r_order_id','=',':roi')
		         					->setParameters(['roi'=>$response['order_id']])
		         				->getResult();
		         		}
		         		else{
		         			fileWrite('update : new','fareincrease','a+');
		         			QB::table('coupon_apply_details')
		         				->update(array(
		         					"coupon_details" => json_encode($_AcouponDiscount),
		         					"discount_amount" => $_AcouponDiscount['totalDiscount'],
		         					"update_date" => gmdate('Y-m-d H:i:s'),
		         				))
		         				->where('r_order_id','=',':roi')
		         					->setParameters(['roi'=>$response['order_id']])
		         				->getResult();		         			
		         		}
		         	}
                }

				/**
				* Author: Logesh.NR
				* Description:Subagency markup validation code
				* Date: 04-11-2022
				*/
				# subagency markup calculation by agency markup table values

				if(!empty($subagencyMarkup)){
					$output = $this->_OagencyMarkupManagement->_agencyMarupCalculation($data['selectFlights'][$count],$subagencyMarkup,$data['userRequest'],true);
					if(!empty($output['markUp'])){
						$response['markUp']=$output['markUp'];
						$response['markUpApplied']=$output['markUpApplied'];
					}
					if(!empty($output['markUpmissing'])){
						$response['markUpMissing']=$output['markUpmissing'];
					}
				}

				
                $data['selectFlights'][$count]['base_fare'] = $totalBF;
                $data['selectFlights'][$count]['tax'] = $totalTax;
                if(isset($data['selectFlights'][$count]['ancillaries'])) {
                	/**
                	 * Iterating the Ancillaries and getting the total ssr amount fare
                	 */
                	foreach($data['selectFlights'][$count]['ancillaries'] as $_SancilliariesKey => $_IancilliaryValue) {
                		$_IssrAmount += $_IancilliaryValue;
                	}
                }
                fileWrite("UC3".$_OldTotalAmount.print_r($data['selectFlights'],1),"databf","a+");
                if(!isset($data['selectFlights'][$count]['totalAmount'])) {
                	$data['selectFlights'][$count]['totalAmount'] = $_IoldBf+$_IoldTax;
                }
            fileWrite( $totalTax .'+'. $totalBF .'+'. $_IssrAmount.print_r('xs',1),"lk","a+");
                $data['selectFlights'][$count]['newTotalAmount'] = $totalTax + $totalBF + $_IssrAmount;
                $data['selectFlights'][$count]['totalAmount'] = $_OldTotalAmount + $_IssrAmount;
	            $count++;
	        }
			// check the fare increase amount and existing amount are same or not
			if($data['selectFlights'][0]['newTotalAmount'] == $data['selectFlights'][0]['totalAmount']){
				$response['show_alert'] = false;
				$response['status'] = true;
				$response['status_message'] = [
					"packageId" => $this->_AcontrollerOutput['serviceResponse']['package_id'],
				];
				return $response;
			}
        fileWrite(print_r($data['selectFlights'],1),"databf","a+");
           if($this->_IS_RT_FARE == 'Y') {
                foreach ($data['selectFlights'][1]['passenger_fare'] as $key => $value) {
                    $data['selectFlights'][1]['passenger_fare'][$key]['base_fare'] = 0;
                    $data['selectFlights'][1]['passenger_fare'][$key]['tax'] = 0;
                    $data['selectFlights'][1]['passenger_fare'][$key]['taxBreakUpDetails'] = [];
                    $data['selectFlights'][1]['passenger_fare'][$key]['totalAmount'] = 0;
                }
                $data['selectFlights'][1]['totalAmount'] = 0;
                if(isset($data['selectFlights'][1]['ancillaries'])) {
                	/**
                	 * Iterating the Ancillaries and getting the total ssr amount fare
                	 */
                	$_IssrAmount = 0;
                	foreach($data['selectFlights'][1]['ancillaries'] as $_SancilliariesKey => $_IancilliaryValue) {
                		$_IssrAmount += $_IancilliaryValue;
                	}
                	$data['selectFlights'][0]['newTotalAmount'] += $_IssrAmount;
                	$data['selectFlights'][0]['totalAmount'] += $_IssrAmount;
                }
            }

            if($data['userRequest']['tripType'] == "M" && count($data['selectFlights']) >= 2) {
                foreach ($data['selectFlights'] as $key => $value) {
                    if($key != 0){
                        foreach ($value['passenger_fare'] as $paxkey => $paxvalue) {
                            $data['selectFlights'][$key]['passenger_fare'][$paxkey]['base_fare'] = 0;
                            $data['selectFlights'][$key]['passenger_fare'][$paxkey]['tax'] = 0;
                            $data['selectFlights'][$key]['passenger_fare'][$paxkey]['taxBreakUpDetails'] = [];
                            $data['selectFlights'][$key]['passenger_fare'][$paxkey]['totalAmount'] = 0;
                        }      
                    }
                }
            }

            $response['selectFlights'] = $data['selectFlights'];
            $response['fareRule'] = (isset($data['fareRule']) && !empty($data['fareRule'])) ? $data['fareRule']: array();

            $response['paxInfo'] = $this->_AcontrollerOutput['serviceResponse']['fareCheckResponse']['paxResult'];
            
            $response['packageInfo'] = [
            	"package_id" => $this->_AcontrollerOutput['serviceResponse']['package_id'],
                "packageId" => $this->_AcontrollerOutput['serviceResponse']['package_id'],
				"tripType" => $data['userRequest']['tripType']
            ];
            $response['packageInfo']['orderInfo'][] = [
                "grandTotal" => $data['selectFlights'][0]['totalAmount'],
                "currency_type" => $data['selectFlights'][0]['currency_type'],
                "orderId" => $this->_AcontrollerOutput['serviceResponse']['fareCheckResponse']['order_id']
            ];
            $response['RTfare'] = $this->_IS_RT_FARE ;

            // if($request['userRequest']['reschedule'] == 'Y') {
            //     $request['packageInfo']['penaltyAmount'] = $penaltyAmount;
            //     $request['packageInfo']['amountPaid'] = $differentAmount;
            //     $request['packageInfo']['grandTotal'] = $request['selectFlights'][0]['totalAmount'];
            // }

            $response['status'] = true;

            $response['order_id'] = APPLICATION_METHOD == 'B2C' ? BOOKING_REFERENCE_CODE.$this->_AcontrollerOutput['serviceResponse']['package_id'] : $response['order_id'];

            $response['status_message'] = $response;

		}   
		 else {
			$response['show_alert'] = false;
			$response['status'] = true;
			$response['status_message'] = [
				"packageId" => $this->_AcontrollerOutput['serviceResponse']['package_id'],
			];
		}
		fileWrite(print_r($response,true),"acresponse","a+");
        return $response;
    }

	/**
	 * @author seemon.G
	 * @date 2024-06-20 23:22:40
	 * @description Function to check the payment is already initiated
	*/
	private function _checkDuplicatePayment($_IrequestId){
		#temporary hardcoded for checking purpose

		$_SuserTimeZone = '';

		#Current Time of the user
		$currentTime = $this->_OretailCommon->_getUTCtimeZone();

		#convert the current time of user
		$convertTime = strtotime($currentTime);
		                 
		#Get payment requested time.
		$bookingTime = $this->_getLastPaymentRequestedTime($_IrequestId)['updated_date'];

		if (!empty($bookingTime)) {

			$paymentRequestTime = strtotime($bookingTime);
			
			if(HOLD_RETRY_PAYMENT_TIME['Type'] == "H")
			{
				#Calculate Hours difference
				$_ShoursDiffCheck = intval(($convertTime - $paymentRequestTime) / 3600);
			
				if ($_ShoursDiffCheck < HOLD_RETRY_PAYMENT_TIME['Time']) {
					return false;
				}
			}
			else{
				#Calculate Minutes difference
				$_SminutesDiffCheck = intval(($convertTime - $paymentRequestTime) / 60);
			
				if ($_SminutesDiffCheck < HOLD_RETRY_PAYMENT_TIME['Time']) {
					return false;
				}
			}
		}
		return true;
	}



	/**
	 * @author Sudha.S
	 * @date 2024-07-11 13:08:19
	 * @description Function to check the payment is already initiated for the particular package ID While Release is happening for Same package ID.
	*/

	public function _confirmAndReleaseSimultaneousFlow($_IpackageId) {
		
		# Response assign.
		$_Aresponse = array();

		$_SpaymentDetails = QB::table('pg_payment_details pgpd')
						    ->select(['pgpd.response_data', 'pgpd.status', 'pd.payment_status as finalStatus','pd.payment_request_time'])
						    ->join('payment_details pd', 'pd.payment_id', '=', 'pgpd.r_request_id', 'INNER JOIN')
						    ->where('pd.r_package_id', '=', $_IpackageId)
							->getResult();
	
		if (!empty($_SpaymentDetails)) {

			$finalStatuses = array_column($_SpaymentDetails, 'finalStatus');
			$statuses = array_column($_SpaymentDetails, 'status');
			$responseDatas = array_column($_SpaymentDetails, 'response_data');
	
			if (in_array('Y', $finalStatuses)) {
				# Payment Already Succeed. So Not allowed to release
				$_Aresponse['allowedToRelease'] = false;
				$_Aresponse['message'] = "Sorry, you can't release the booking as it has already been confirmed.";
			} else {
				# Failed Payments - Status is in in N status But reponse data is not empty.because its failured response.
				$_AfailedPayments = array_filter($_SpaymentDetails, function($payment) {
					return $payment['status'] == 'N' && !empty($payment['response_data']);
				});
	
				# Pending Payments - Status is in in N status But reponse data is empty. (Waiting for Payment response OR No response from Gateway)
				$_ApendingPayments = array_filter($_SpaymentDetails, function($payment) {
					return $payment['status'] == 'N' && empty($payment['response_data']);
				});
	
				if (!empty($_ApendingPayments)) {

					# If there are pending payments, check for duplicates
					$_Aresponse['allowedToRelease'] = $this->_checkDuplicatePayment($_IpackageId);
					$_Aresponse['message'] = "Last Payment tried 5 hours ago.So You can Release.";
					if(!$_Aresponse['allowedToRelease']){
                        $_Aresponse['message'] = "You can try to release this booking after " . HOLD_RETRY_PAYMENT_TIME . " hours.";
					}
				}
				elseif (!empty($_AfailedPayments)) {
					# If there are failed payments, allow to release
					$_Aresponse['allowedToRelease'] = true;
				} 
				else {
					# If all payments are either successful or pending without response, allow to release
					$_Aresponse['allowedToRelease'] = true;
				}
			}
		} else {
			# If no payment process attempt for this order, allow to release
			$_Aresponse['allowedToRelease'] = true;
		}
		return $_Aresponse;
	}

	/**
	 * @author Sudha.S
	 * @date 2024-08-09 13:46:45
	 * @description Function to check the payment date and time which is last tried by user.
	 * **/

	public function _getLastPaymentRequestedTime($_IpackageId){

		$_ApaymentDetails = QB::table('pg_payment_details pgpd')
							->select(['pgpd.updated_date','pgpd.pg_payment_details_id'])
							->join('payment_details pd', 'pd.payment_id', '=', 'pgpd.r_request_id', 'INNER JOIN')
							->where('pd.r_package_id', '=', $_IpackageId)
							->andWhere('pd.payment_status', '=', 'N')
							->andWhere('pgpd.status', '=', 'N')
							->orderBy('pgpd.pg_payment_details_id','DESC')
							->getResult()[0];

		fileWrite("_ApaymentDetails--->".$_IpackageId.print_r($_ApaymentDetails,true),'RELEASECHECK','a+');
			
		return $_ApaymentDetails;
	}
}
?>
