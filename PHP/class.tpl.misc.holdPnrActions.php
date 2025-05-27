<?php

use \QB\queryBulider as QB;
use \api\webServiceCall as WS;

fileRequire('classes/class.sync.php');
fileRequire('classes/class.retailMail.php');
fileRequire("/classes/class.webServiceCall.php");



class holdPnrActions
{
    public function __construct(){
      $this->_Osync = new sync();
      $this->_OretailMail = new retailMail();
    }
	
	/**
	 * get Display info
     * @description  release the PNR
	 * @method      _getDisplayInfo
	 * @Author_name sudha.s <sudha.s@infinitisoftware.net>
	 * @datetime    2024-04-26 16:54:28
	 * @return      void
	 */
	
    public function _getDisplayInfo() {
        # Assing the Input
        $_Ainput = array();
        $_Ainput = $this->_IinputData;
        #Return the Response
        $this->_AserviceResponse['data'] = $this->_holdedPnrActions($_Ainput);     
    }


    /**
      * @description  Action based functionality
	    * @method      _holdedPnrActions
	    * @Author_name sudha.s <sudha.s@infinitisoftware.net>
	    * @datetime    2024-04-26 16:54:29
	    * @return      void
	    */

    public function _holdedPnrActions($_Ainput){

      # Variable Decalarations
      $_Arequest =array();
      $_Adetails = array();
      $_AserviceResponse = array();

      # set the release by details
      $_SreleaseMail = !empty($_SESSION['loginEmail']) ?  $_SESSION['loginEmail']  :  $_SESSION['employeeEmailId'];
      $_Ainput['releasedBy'] = !empty($_SreleaseMail) ? $_SreleaseMail : $_Ainput['releasedBy'];

      $_Ainput['orderId'] = $this->getOrderIdFromOad($_Ainput['reference_id']);

      #get the hold status id form the master table
      $_IholdStatusId = PNR_HOLD;
       
      #get the travel mode code and their id
      $_QtravelModeCode = QB::table('dm_travel_mode dtm')->select(['dtm.travel_mode_id','dtm.travel_mode_code as travelTypeCode'])->getResult();
      $_AtravelModeCode = array_column($_QtravelModeCode,'travelTypeCode','travel_mode_id');
      
      #Release Request Status check and Updation.
      $_AserviceResponse['data'] = $this->releaseRequestedStatusInsert($_Ainput); 

      if ($_AserviceResponse['data']['status'] == 'FAILURE'){
        return $_AserviceResponse;
      } 

      # Get the booking details against package ID.
      $_Adetails = $this->getOrderAdditionalDetails($_Ainput['reference_id']);
      if(!empty($_Adetails)){
          # Input Prepare.
          $_Adetails['releasedReason'] = $_Ainput['releasedReason'];
          $_Adetails['releasedBy'] = $_Ainput['releasedBy'];
          $_Adetails['travelTypeCode'] = $_AtravelModeCode[$_Adetails['r_travel_mode_id']];
            
          # Sync service to backend.
          // $_AserviceResponse['data'] = $this->_Osync->_syncPnrReleaseDetails($_Adetails,'holdedPnrActions');
          $_AserviceResponse['data'] = $this->_Osync->_getdata($_Adetails,'holdedPnrActions');
          $_Adetails['onward_release_status'] = $_AserviceResponse['data']['responseData']['onward_release_status'];
          $_Adetails['return_release_status'] = $_AserviceResponse['data']['responseData']['return_release_status'];
          
          if(isset($_AserviceResponse['data']['responseData']['status_code']) && ($_AserviceResponse['data']['responseData']['status_code'] == 0)){
            #Updated date for identify released date
            $_Adetails['updated_date'] = $_AserviceResponse['data']['responseData']['updated_date'];
            $_AserviceResponse['data'] = $this->_holdedPnrReleaseFromFe($_Adetails);
            #Mail Functionality
            if($_AserviceResponse['data']['status_code'] == 0){
              $_AserviceResponse['data'] = array('status_code' => 0,'status' => 'SUCCESS' ,'message' => 'Your PNR has been successfully released, and an email notification has been sent');
              $_SmailFor = 'ReleaseConfirmationMail';
              $this->holdedPnrReleaseMailInputPrepare($_Adetails['packageId'],$_SmailFor,'Manual');
            }
          }else{
            $_AserviceResponse['data'] = $this->releaseFailureUpdate($_Ainput['reference_id'],$_Adetails);
            unset($_AserviceResponse['updated_date']);
          }
      }

      return $_AserviceResponse;
    }

    /*Purpose :  Get the details about 
    Author  :  Sudha.s <sudha.s@infinitisoftware.net> 
    Date    :  2024-04-26 14:39:16
   */

   public function getOrderAdditionalDetails($_IpackageId){

     $_Adetails = QB::table('order_additional_details oad')
                    ->select(["oad.r_order_id as orderId",
                              "oad.r_package_id as packageId",
                              "oad.r_travel_mode_id",
                              "JSON_UNQUOTE(JSON_EXTRACT(oad.other_info,'$.tripType')) as tripType",
                              "JSON_UNQUOTE(JSON_EXTRACT(oad.other_info,'$.gdsPnr')) as GDSPNR",
                              "JSON_UNQUOTE(JSON_EXTRACT(oad.other_info,'$.orderRequestId')) as orderRequestId",
                              "dmp.reservation_code as reservationType"
                              ])
                    ->join('dm_package dmp','dmp.package_id','=','oad.r_package_id','INNER JOIN')
                    ->where('oad.r_package_id','=',$_IpackageId)
                    ->andWhere('oad.process_type','=','HOLD')
                    ->andWhere('oad.r_status_id','=',RELEASE_REQUESTED)
                    ->getResult()[0];
     return $_Adetails;
   }


    /*
     Purpose :  Update the released status of holded  PNR orders.
     Author  :  Sudha.s <sudha.s@infinitisoftware.net> 
     Date    :  2024-04-26 14:39:16
    */

    public function _holdedPnrReleaseFromFe($_Ainput){
      
      $_Ainput['releasedThrough'] = isset($_Ainput['releasedThrough']) &&  !empty($_Ainput['releasedThrough']) ? $_Ainput['releasedThrough'] : 'ManualRelease';
      
      # Get status ID for PNR Release
      $_IstatusId = $this->getStatusIdbasedStatusCode('PNRHR');

      #Emulated email id
      $_SreleaseEmailId = isset($_SESSION['emulate']['emulateEmailId']) && !empty($_SESSION['emulate']['emulateEmailId']) ?  $_SESSION['emulate']['emulateEmailId'] : $_Ainput['releasedBy'];
      $_BisEmulated = isset($_SESSION['emulate']['emulateEmailId']) && !empty($_SESSION['emulate']['emulateEmailId']) ? 'YES' : 'NO';

      # If already the booking is in released status because of simultaneous release - have to check booking status.
      $_AcheckIsAlreadyInRelease = QB::table('order_additional_details oad')->select(['oad.r_status_id'])->where('oad.r_status_id','=',$_IstatusId)->andWhere('oad.r_package_id','=',$_Ainput['packageId'])->getResult()[0]['r_status_id'];
                   
      if(empty($_AcheckIsAlreadyInRelease)){

        # Current UTC Time - if release from  Backend , UTC time get in FE using gmdate() , if release from  Frontend , UTC time get from BE service response Input
        $_ScurrentUTCtime = (isset($_Ainput['updated_date']) && !empty($_Ainput['updated_date'])) ? $_Ainput['updated_date'] : gmdate('Y-m-d H:i:s');
          
        $_JreleasedReason= json_encode($_Ainput['releasedReason']); 
        # Update array.
        $_Aupdate = array(
          'other_info' => QB::raw("JSON_SET(other_info, '$.releasedReason',".$_JreleasedReason.",'$.releasedBy','".$_Ainput['releasedBy']."')"),
          'r_status_id' => $_IstatusId,
          'updated_date' => $_ScurrentUTCtime,
          'onward_release_status' => $_Ainput['onward_release_status'],
          'return_release_status' => $_Ainput['return_release_status']
        );
          
        $_QupdatePnrStatus = $this->updateOrderAdditionalDetails($_Ainput['packageId'],$_Ainput['orderId'],$_Aupdate);
        # While releasing PNR,we have to reclaim the applied coupon for this Order. 
        if($_QupdatePnrStatus == 'SUCCESS'){
          $this->_appliedCouponReclaim($_Ainput['orderId']);
          # Insert the Release action in Order History
          $this->_insertOrderReleaseHistory($_Ainput['orderId'],$_SreleaseEmailId,$_BisEmulated,$_Ainput['releasedThrough']);
        }
        $_Aresponse = $_QupdatePnrStatus == 'SUCCESS' ? array('status_code'=>0, 'message' => 'successfully Released!','updated_date' => $_ScurrentUTCtime,'onward_release_status' => $_Ainput['onward_release_status'],'return_release_status' => $_Ainput['return_release_status']) : array('status_code'=>1, 'status' => 'FAILURE','message' => 'Sorry, the release process has failed. Please try again.');
      }else{
        $_Aresponse = array('status' => 'FAILURE' ,'status_code' => 1 , 'message' => 'You already Released this booking!');
      }
      return $_Aresponse;
    }

    /*
     Purpose :  Prepare Input for Release Mail trigger for use the  HOLD confirmation mail fucntion
     Author  :  Sudha.s <sudha.s@infinitisoftware.net> 
     Date    :  2024-04-26 14:39:16
    */

    public function holdedPnrReleaseMailInputPrepare($_IpackageId,$_SmailFor,$_SreleasedThrough){

      #get the hold status id form the master table
      $_SstatusCode = (strtoupper($_SmailFor) == 'HOLDPNRNOTIFYCRONMAIL') ? 'PNRH' : ((strtoupper($_SmailFor) == 'HOLDEDPAYMENTMAIL') ? 'P' : 'PNRHR');
      $_IreleaseStatusId = $this->getStatusIdbasedStatusCode($_SstatusCode);

      # Get the booking details against package ID.
      $_Adetails = QB::table('order_additional_details oad')
                   ->select(["
                             oad.r_order_id",
                             "oad.onward_pnr",
                             "oad.return_pnr",
                             "oad.updated_date",
                             "oad.created_date",
                             "oad.r_package_id",
                             "oad.expiry_ttl_time",
                             "oad.r_travel_mode_id",
                             "oad.other_info as other_info"
                             ])
                   ->where('oad.r_package_id','=',$_IpackageId)
                   ->andWhere('oad.process_type','=','HOLD')
                   ->andWhere('oad.r_status_id','=',$_IreleaseStatusId)
                   ->getResult()[0];

        $_AmailInput =array();
        $_AmailInput['orderId'] = $_Adetails['r_order_id'];
        $_AmailInput['mailFor'] = !empty($_SmailFor) ? $_SmailFor : 'ReleaseConfirmationMail';
        $_AmailInput['mailContentTopic'] = $_SmailFor;
        $_AmailInput['additionalDetails']['sync_package_id'] = $_Adetails['r_package_id'];
        $_AmailInput['additionalDetails']['onward_pnr'] = $_Adetails['onward_pnr'];
        $_AmailInput['additionalDetails']['return_pnr'] = $_Adetails['return_pnr'];
        $_AmailInput['additionalDetails']['created_date'] = $_Adetails['created_date'];
        $_AmailInput['additionalDetails']['expiry_ttl_time'] = $_Adetails['expiry_ttl_time'];
        $_AmailInput['additionalDetails']['r_travel_mode_id'] = $_Adetails['r_travel_mode_id'];
        $_AmailInput['additionalDetails']['other_info'] = $_Adetails['other_info'];
        $_AmailInput['releasedThrough'] = $_SreleasedThrough;

        $_SmailSent = $this->_OretailMail->_holdAndReleaseConfirmationMail($_AmailInput);

        return $_SmailSent;
    }


    /*
 	* created By      :   Mohamed Ahamed V K
 	* FunctionName    :   releaseFailureUpdate()
 	* Description     :   This function is used to update the release Failure status .
	* Date            :   2024-06-05 13:06:15
	*/

    public function releaseFailureUpdate($_IpackageId,$_Arequest){
      # Get status ID for PNR Release Failure
      $_IstatusId = QB::table('dm_status ds')->select(['ds.status_id'])->where('ds.status_code','=','PNRRRF')->getResult()[0]['status_id'];

      $_ScurrentUTCtime = isset($_Arequest['updated_date']) && !empty($_Arequest['updated_date']) ? $_Arequest['updated_date'] :  gmdate('Y-m-d H:i:s');

      # Update array.
      $_JreleasedReason= json_encode($_Arequest['releasedReason']);
      $_AreleaseFailupdate = array(
          'other_info' => QB::raw("JSON_SET(other_info, '$.releasedReason',".$_JreleasedReason.",'$.releasedBy','".$_Arequest['releasedBy']."')"),
          'r_status_id' => $_IstatusId,
          'updated_date' => $_ScurrentUTCtime,
          'onward_release_status' => $_Arequest['onward_release_status'],
				  'return_release_status'=>$_Arequest['return_release_status']
      );
      # Status ID Update Query
      $_QreleaseRequest= QB::table('order_additional_details')
                          ->update($_AreleaseFailupdate)
                          ->where('r_package_id','=',$_IpackageId)
                          ->andWhere('process_type','=','HOLD')
                          ->getResult();
                         
      $_Aresponse = array('status_code' => 1 , 'status' => 'FAILURE' , 'message'=> 'Sorry, the release process has failed. Please try again.','updated_date' => $_ScurrentUTCtime);
      return $_Aresponse;
    }


     /*
     Purpose :  Get the details about 
     Author  :  Sudha.s <sudha.s@infinitisoftware.net> 
     Date    :  2024-04-26 14:39:16
    */

    public function releaseRequestedStatusInsert($_Ainput){

      # Check the Hold Pnr is already release initate or not based
      $_ApackageStatus = QB::table('order_additional_details')->select(['r_package_id','r_status_id'])->where('r_package_id','=',$_Ainput['reference_id'])->andWhere('process_type','=','HOLD')->getResult()[0];
      $_IstatusCode = $this->getStatusCodeBasedOnId($_ApackageStatus['r_status_id']);

      if($_IstatusCode == 'PNRH'){
        
        fileRequire("plugins/misc/personal/harinim/classesTpl/class.tpl.misc.confirmHoldedPnrActions.php");
        $_OconfirmHoldedPnrActions = new confirmHoldedPnrActions();
        $_SpaymentIsInProgress = $_OconfirmHoldedPnrActions->_confirmAndReleaseSimultaneousFlow($_Ainput['reference_id']);
        
        if($_SpaymentIsInProgress['allowedToRelease']){

          # Update array.
          $_JreleasedReason= json_encode($_Ainput['releasedReason']);

          $_AreleaseRequpdate = array(
            'other_info' => QB::raw("JSON_SET(other_info, '$.releasedReason',".$_JreleasedReason.",'$.releasedBy','".$_Ainput['releasedBy']."', '$.releasedRetry',0)"),
            'r_status_id' => RELEASE_REQUESTED,
            'updated_date' => gmdate('Y-m-d H:i:s')
          );
          $_AupdateReleaseRequest = $this->updateOrderAdditionalDetails($_Ainput['reference_id'],$_Ainput['orderId'],$_AreleaseRequpdate);
          
          $_Aresponse = $_AupdateReleaseRequest == 'SUCCESS' ? array('status_code' => 0, 'status' => 'SUCCESS' ,'message' => 'Release Requested Inserted!') : array('status_code' => 1, 'status' => 'FAILURE' ,'message' => 'Release Requested Not Inserted!');
          
        }else{
          $_Aresponse = array('status' => 'FAILURE' ,'status_code' => 1 , 'message' => $_SpaymentIsInProgress['message']);
        }
      }else{
        $_SreleasingBy = $_Ainput['releasedBy'];
        $_Aresponse = $this->responseMessage($_Ainput['reference_id'],$_SreleasingBy); 
      }
      
      return $_Aresponse;
  }

    /*
    Function : _appliedCouponReclaim()
     Purpose :  Reclaim the Applied coupons for Holded PNR
     Author  :  Sudha.s <sudha.s@infinitisoftware.net> 
     Date    :  2024-04-26 14:39:16
    */

    public function _appliedCouponReclaim($_IorderId){

      # Check, is there any Coupon for this Order ID
      $_AcheckIsAnyCoupon = QB::table('coupon_apply_details cad')
                            ->select(['cad.coupon_code'])
                            ->where('r_order_id','=',$_IorderId)
                            ->getResult()[0]['coupon_code'];
      
      if(!empty($_AcheckIsAnyCoupon)){
        $_AreclaimCoupon = QB::table('coupon_apply_details')
                           ->update(['status'=>'NA'])
                           ->where('r_order_id','=',$_IorderId)
                           ->getResult();
      }
    }



    /*
     Function : _insertOrderReleaseHistory()
     Purpose :  Insert the release action in order History 
     Author  :  Sudha.s <sudha.s@infinitisoftware.net> 
     Date    :  2024-04-26 14:39:16
    */

    public function _insertOrderReleaseHistory($_IorderId,$_SreleaseEmailId,$_BisEmulated,$_SreleasedThrough){

      #set the variable to insert into history details for hold pnr details
      $insertArray = array();

      $_AemployeeId = QB::table('dm_employee dme')
                      ->select(['dme.employee_id'])
                      ->where('dme.email_id','=',$_SreleaseEmailId)
                      //->andWhere('dme.status','=','Y') #Some users are in N status,eventhough allowed to Hold. So commented
                      ->getResult()[0]['employee_id'];
                     
      $insertArray['employee_id'] = $_AemployeeId;
      $insertArray['order_id'] = $_IorderId;
      $insertArray['created_date'] = gmdate('Y-m-d H:i:s');
      $insertArray['action'] = $_SreleasedThrough == 'CRON' ? 'Booking is Auto Released on <b>###time###</b>.' : 'Booking is Released on <b>###time###</b> by <b>###user###</b>';
      $insertArray['activity'] = $_SreleasedThrough == 'CRON' ? 'Holded_Auto_Release' : ($_BisEmulated == 'YES' ? 'Emulate_Released_booking' : 'Hold_release');

      $_QinsertReleaseData = QB::table('order_history')
                             ->insert($insertArray)
                             ->getResult();
    }


    /*
     Fcuntion :  updateOrderAdditionalDetails
     Purpose : Update the data in order_additional_details
     Author  :  Sudha.s <sudha.s@infinitisoftware.net> 
     Date    :  2024-04-26 14:39:16
    */

    public function  updateOrderAdditionalDetails($_IpackageId,$_IorderId,$_Aupdate){
      # Status ID Update Query
      $_QupdatePnrStatus = QB::table('order_additional_details')
                           ->update($_Aupdate)
                           ->where('r_package_id','=',$_IpackageId)
                           ->andWhere('r_order_id','=',$_IorderId)
                           ->andWhere('process_type','=','HOLD')
                           ->getResult();
      return $_QupdatePnrStatus > 0  ? 'SUCCESS' : 'FAILURE';
    }


    /*
     Function :  getOrderIdFromOad 
     Purpose  : get Order ID from order_additional_details
     Author  :  Sudha.s <sudha.s@infinitisoftware.net> 
     Date    :  2024-04-26 14:39:16
    */

    public function getOrderIdFromOad($_IpackageId){
      return QB::table('order_additional_details oad')->select(['oad.r_order_id'])->where('oad.r_package_id','=',$_IpackageId)->andWhere('oad.process_type','=','HOLD')->getResult()[0]['r_order_id'];
    }


    /*
     Function :  getStatusIdbasedStatusCode()
     Purpose : Get Status Id Based On status code
     Author  :  Sudha.s <sudha.s@infinitisoftware.net> 
     Date    :  2024-04-26 14:39:16
    */

    public function getStatusIdbasedStatusCode($_SstatusCode){
      return QB::table('dm_status dm')->select(['dm.status_id'])->where('dm.status_code','=',$_SstatusCode)->getResult()[0]['status_id'];
    }

    /*
    *Function :  _getHoldPNRTTLtime()
    *Purpose  :  Get the Hold PNR TTL Time not receieved booking to get form the service and update it in database
    *Author   :  Mohamed Ahamed VK 
    *Date     :  2024-06-14 14:39:16
    */
    public function _getHoldPNRTTLtime(){
      
      //get the Airline Data from the database
      $_AairlineData = QB::table('dm_airline')->select(['airline_code','airline_id'])->where('status','=','Y')->getResult();
      $this->_AairlineValue = array_column($_AairlineData,'airline_code','airline_id');

      // get the hold status id by passing the hold code
      $_IholdStatus = $this->getStatusIdbasedStatusCode('PNRH');
      // query to get the hold booking with no expiry ttl time
      $_AgetHoldBooking =  QB::table('order_additional_details oad')
                              ->select(["oad.r_order_id","oad.r_travel_mode_id","oad.r_package_id","JSON_UNQUOTE(JSON_EXTRACT(other_info,'$.gdsPnr')) as GDSPNR","service_subagency_code","service_provider_id","r_airline_id","JSON_UNQUOTE(JSON_EXTRACT(other_info,'$.expiry_timelimit_info.ttlTimeSettings')) as holdSetting","JSON_UNQUOTE(JSON_EXTRACT(other_info,'$.orderRequestId')) as orderRequestId","oad.created_date"])
                              ->join('passenger_via_details pvd','pvd.r_order_id','=','oad.r_order_id','INNER JOIN')
                              ->join('via_flight_details vfd','vfd.via_flight_id','=','pvd.r_via_flight_id','INNER JOIN')
                              ->join('via_fare_details vfrd','vfrd.r_via_flight_id','=','vfd.via_flight_id','INNER JOIN')
                              ->where('process_type','=','HOLD')
                              ->andWhere('expiry_ttl_time','=','0000-00-00 00:00:00')
                              ->andWhere('r_status_id','=',$_IholdStatus)
                              ->andWhere("JSON_UNQUOTE(JSON_EXTRACT(other_info,'$.expiry_timelimit_info.ttlTimeSettings.nottlTimeSettings.retryProcess'))","=","Y")
                              ->groupBy(['pvd.r_order_id','trip_type'])
                              ->orderBy('oad.r_order_id','ASC')
                              ->getResult();
      // get the hold booking and pnr with order id index 
      $_AorderedHoldBooking = array_reduce($_AgetHoldBooking, function ($result, $item) {
          $orderId = $item['r_order_id'];
          $gdsPnr = json_decode($item['GDSPNR'], true); // Assuming GDSPNR is a JSON string
            if(!empty($gdsPnr) && !isset($result['gdsPnr'][$orderId]))
            {
                $result['gdsPnr'][$orderId] =$gdsPnr;
            }
            $result['holdData'][$orderId][] = $item;
            $this->_AholdSetting[$item['service_subagency_code']][$this->_AairlineValue[$item['r_airline_id']]] = json_decode($item['holdSetting'], true);
            return $result;
      }, array());
      // check the ordered hold booking Pnr data is not empty then only below logic will be execute
      if(!empty($_AorderedHoldBooking['gdsPnr'])){
        //service Name to read pnr  
        $serviceName = 'BOOKINGRETRIEVE_SERVICES_AIR_RF_V2';
        //get the travel mode from the database
        $_AtravelModeData = QB::table('dm_travel_mode')->select(['travel_mode_id','travel_mode_code'])->getResult();
        $_AtravelMode = array_column($_AtravelModeData,'travel_mode_code','travel_mode_id');
        // loop to pass the pds pnr and get the ttl time 
        foreach ($_AorderedHoldBooking['gdsPnr'] as $OrderIdkey => $gdsPnrValue) {
          // loop to form the read pnr service request and call the service
          foreach ($gdsPnrValue as $gdskey => $gdsPnrData) {
            // set empty array to the request to avoid calling same request again and again
            $request = [];
            $data = [
              'travelType' => $_AtravelMode[$_AorderedHoldBooking['holdData'][$OrderIdkey][$gdskey]['r_travel_mode_id']],
              'airlinecode' => $this->_AairlineValue[$_AorderedHoldBooking['holdData'][$OrderIdkey][$gdskey]['r_airline_id']],
              'subAgencyCode' => $_AorderedHoldBooking['holdData'][$OrderIdkey][$gdskey]['service_subagency_code'],
              'reservation_type' => $_AorderedHoldBooking['holdData'][$OrderIdkey][$gdskey]['service_provider_id'],
              'pnr' => $gdsPnrData,
              'status' => 'Hold'
            ];
            $requestData = [
              'data' => json_encode($data),
              'agencyCode' => SERVICE_AGENCY_ID,
              'requestId' => $_AorderedHoldBooking['holdData'][$OrderIdkey][$gdskey]['orderRequestId'],
              'serviceName' => $serviceName,
              'devMode' => 'Y'
            ];
        
            $request[] = [
                  "endPoint"=> SERVICE_AGENCY_URL_PROFILE,
                  "data" => $requestData
            ];
            // webservice response
            $wsResponse = json_decode(WS::run($request)[0],true);
           
            // set the webservice response status code
            $response[$OrderIdkey][$gdskey]['status_code'] = 0;//$wsResponse['status_code'];
            $data = array_merge($data,array("order_id" => $OrderIdkey,"created_date" => $_AorderedHoldBooking['holdData'][$OrderIdkey][$gdskey]['created_date'],"package_id" => $_AorderedHoldBooking['holdData'][$OrderIdkey][$gdskey]['r_package_id']));
            // merge the supplier,provider and airline code data with webservice response data
            $response[$OrderIdkey][$gdskey]['data'] = array_merge($wsResponse['data'],$data);
          }        
        }
      }
      // loop to get the response and perform some logic 
      foreach ($response as $key => $resValue) {
        // get the status of the response
        $statusCodes = array_column($resValue, 'status_code');
        // check each index status code is 0 or not 
        if (count(array_unique($statusCodes)) === 1 && reset($statusCodes) === 0) {
          // condition to check the roundtrip booking
          if(count($resValue)> 1){
            // get the ttl time using array column
            $resTTLData = array_column(array_column($resValue, 'data'),'TTLTime');
            // check the condition both onward and return ttl time is not empty
            if(!empty($resTTLData[0]['dateAndTime']) && !empty($resTTLData[1]['dateAndTime'])){
              
              //set the onward sub agency code
              $_IonwSubAgencyCode = array_column(array_column($resValue, 'data'),'subAgencyCode')[0];
              // set the onward airline code
              $_SonwAirlineCode = array_column(array_column($resValue, 'data'),'airlinecode')[0];
              // set the onward ttl time received from service
              $_DonwardTTLTime = $resTTLData[0]['dateAndTime'];
              $_SonwardTimeZone = $resTTLData[0]['timeZone'];
              // call the function to manipulate the ttl time and calculate the logic based on the setting mapped
              $_AonwardExpiryTTLTime  = $this->_getTTLTime($_DonwardTTLTime,$_IonwSubAgencyCode,$_SonwAirlineCode,$_SonwardTimeZone);

              //set the return sub agency code
              $_IretSubAgencyCode = array_column(array_column($resValue, 'data'),'subAgencyCode')[1];
              // set the onward airline code
              $_SretAirlineCode = array_column(array_column($resValue, 'data'),'airlinecode')[1];
              // set the onward ttl time received from service
              $_DreturnTTLTime = $resTTLData[1]['dateAndTime'];
              $_SreturnTimeZone = $resTTLData[1]['timeZone'];
              // call the function to manipulate the ttl time and calculate the logic based on the setting mapped
              $_AreturnExpiryTTLTime  = $this->_getTTLTime($_DreturnTTLTime,$_IretSubAgencyCode,$_SretAirlineCode,$_SreturnTimeZone);
              // check the onward ttl time is less than the return ttl time  to set the ttl time
              $_DexpiryTtlTime = strtotime($_AonwardExpiryTTLTime['expiryTtlTime']) >= strtotime($_AreturnExpiryTTLTime['expiryTtlTime']) ? $_AreturnExpiryTTLTime['expiryTtlTime'] : $_AonwardExpiryTTLTime['expiryTtlTime'];
              // set the onward and return ttl in json
                $_Jonwardttl = json_encode($resTTLData[0]);
                $_Jreturnttl = json_encode($resTTLData[1]);
              // onward update array
              $_AonwUpdateArray =array(
                'other_info' =>  QB::raw("JSON_SET(other_info, '$.ttltime.onwordTTLTime', CAST(:newData AS JSON))"),
                'updated_date' => gmdate('Y-m-d H:i:s')
              );
              // calling function to update the data
              $this->updateHoldTTLTime($_AonwUpdateArray,$key,$_Jonwardttl);
              // return update array
              $_AretUpdateArray =array(
                'other_info' =>  QB::raw("JSON_SET(other_info, '$.ttltime.returnTTLTime', CAST(:newData AS JSON))"),
                'expiry_ttl_time' => $_DexpiryTtlTime,
                'updated_date' => gmdate('Y-m-d H:i:s')
              );
              // calling function to update the data
              $this->updateHoldTTLTime($_AretUpdateArray,$key,$_Jreturnttl);
              // form the array to trigger the mail process
              $_AairlinePnr = array_column(array_column($resValue, 'data'),'airlinePnr');
              $_AmailData[$key] =  array("noTTLTime"=> False, "pnr" => implode(' / ',$_AairlinePnr),"expiry_ttl_time" => $_DexpiryTtlTime,"order_id" => $resValue[0]['data']['order_id'],"created_date" => $resValue[0]['data']['created_date'],"reference_id" => $resValue[0]['data']['package_id']);
              $_AstatusSyncRequest[$key] = array('onwardttlTime' => $_Jonwardttl,'returnttlTime' => $_Jreturnttl, 'expiryttlTime' => $_DexpiryTtlTime);
            }
            else{
              $_AholdSettings = $this->_AholdSetting[$resValue[0]['data']['subAgencyCode']][$resValue[0]['data']['airlinecode']];
              $_SexpiryTTLTime = $this->_updateHoldExpiryTTLTime($resValue[0]['data']['created_date'],$_AholdSettings);
              // update array formation
              $_Aupdate = array('expiry_ttl_time' => $_SexpiryTTLTime,'updated_date' => gmdate("Y-m-d H:i:s"));
              // update the data in table
              QB::table('order_additional_details')->update($_Aupdate)->where('r_order_id', '=', $key)->andWhere('process_type','=','HOLD')->getResult();
              // sync the ttl time to Backend
              $_AstatusSyncRequest[$key] = array('onwardttlTime' => '','returnttlTime' => '', 'expiryttlTime' => $_SexpiryTTLTime);
              $_AairlinePnr = array_column(array_column($resValue, 'data'),'airlinePnr');
              $_AmailData[$key] =  array("noTTLTime"=> True, "pnr" => implode(' / ',$_AairlinePnr),"expiry_ttl_time" => $_SexpiryTTLTime, "order_id" => $resValue[0]['data']['order_id'],"created_date" => $resValue[0]['data']['created_date'],"reference_id" => $resValue[0]['data']['package_id']);
            }
          }
          elseif(!empty($resValue[0]['data']['TTLTime']['dateAndTime'])){
            // set the onward ttl time
            $_DexpiryTtlTime = $resValue[0]['data']['TTLTime']['dateAndTime']; 
            $_StimeZone = $resValue[0]['data']['TTLTime']['timeZone'];
            //set the onward sub agency code
            $_IsubAgencyCode = array_column(array_column($resValue, 'data'),'subAgencyCode')[0];
            // set the onward airline code
            $_SairlineCode = array_column(array_column($resValue, 'data'),'airlinecode')[0];
            // call the function to manipulate the ttl time and calculate the logic based on the setting mapped
            $_AexpiryTTLTime  = $this->_getTTLTime($_DexpiryTtlTime,$_IsubAgencyCode,$_SairlineCode,$_StimeZone);

            // set the onward ttl time data in json
            $_JttlTime = json_encode($resValue[0]['data']['TTLTime']);
            // onward update array
            $_AupdateData = array(
              'other_info' =>  QB::raw("JSON_SET(other_info, '$.ttltime.onwordTTLTime', CAST(:newData AS JSON))"),
              'expiry_ttl_time' => $_AexpiryTTLTime['expiryTtlTime'],
              'updated_date' => gmdate('Y-m-d H:i:s')
            );
            // calling function to update data
            $this->updateHoldTTLTime($_AupdateData,$key,$_JttlTime);
            $_AmailData[$key] =  array("noTTLTime"=> False, "pnr" => $resValue[0]['data']['airlinePnr'], "expiry_ttl_time" => $_AexpiryTTLTime['expiryTtlTime'], "order_id" => $resValue[0]['data']['order_id'],"created_date" => $resValue[0]['data']['created_date'],"reference_id" => $resValue[0]['data']['package_id']);
            $_AstatusSyncRequest[$key] = array('onwardttlTime' => $_JttlTime,'returnttlTime' => $_Jreturnttl, 'expiryttlTime' => $_AexpiryTTLTime['expiryTtlTime']);
          }
          else{
            $_AholdSettings = $this->_AholdSetting[$resValue[0]['data']['subAgencyCode']][$resValue[0]['data']['airlinecode']];
            $_SexpiryTTLTime = $this->_updateHoldExpiryTTLTime($resValue[0]['data']['created_date'],$_AholdSettings);
            // update array formation
            $_Aupdate = array('expiry_ttl_time' => $_SexpiryTTLTime,'updated_date' => gmdate("Y-m-d H:i:s"));
            // update the data in table
            QB::table('order_additional_details')->update($_Aupdate)->where('r_order_id', '=', $key)->andWhere('process_type','=','HOLD')->getResult();
            // sync the ttl time to Backend
            $_AstatusSyncRequest[$key] = array('onwardttlTime' => '','returnttlTime' => '', 'expiryttlTime' => $_SexpiryTTLTime);
            $_AmailData[$key] =  array("noTTLTime"=> True, "pnr" => $resValue[0]['data']['airlinePnr'], "expiry_ttl_time" => $_SexpiryTTLTime,"order_id" => $resValue[0]['data']['order_id'], "created_date" => $resValue[0]['data']['created_date'],"reference_id" => $resValue[0]['data']['package_id']);
          }
        }
      }

     if(!empty($_AstatusSyncRequest)){
        // service to sync and update the ttl in backend
        // $this->_Osync->_updateTTLtime($_AstatusSyncRequest);
        $this->_Osync->_getdata($_AstatusSyncRequest,'updateTTLTime');
     }
      // trigger the mail process for ttl received
      if(!empty($_AmailData)){
        $this->triggerMailProcess($_AmailData);
      }
    }
    /*
    *Function :  updateHoldTTLTime()
    *Purpose  :  update the details in order additional details
    *Author   :  Mohamed Ahamed VK
    *Date     :  2024-06-14 14:39:16
    */
    public function updateHoldTTLTime($_AupdateArray,$_IorderId,$_JttlTime){

      return QB::table('order_additional_details')
                      ->update($_AupdateArray)
                      ->where('r_order_id', '=', $_IorderId)
                      ->setParameters(['newData' => $_JttlTime])
                      ->getResult();
    }
    /*
    *Function :  _getTTLTime()
    *Purpose  :  get the hold time setting data
    *Author   :  Mohamed Ahamed VK
    *Date     :  2024-06-21 14:39:16
    */
    public function _getTTLTime($_DexpiryTtlTime,$_IsubAgencyCode,$_SairlineCode,$_StimeZone){
      // check is their any ALL setting enable for the provider and subagency
      $_AexpiryTTlTime = $this->_getHoldTimeSettingsData($_DexpiryTtlTime,$_IsubAgencyCode,$_SairlineCode,$_StimeZone);
      return $_AexpiryTTlTime;
    }


    /*
    *Function :  _getHoldTimeSettingsData()
    *Purpose  :  get the hold time setting data
    *Author   :  Mohamed Ahamed VK
    *Date     :  2024-06-21 14:39:16
    */
    public function _getHoldTimeSettingsData($_DexpiryTtlTime,$_IsubAgencyCode,$_SairlineCode,$_StimeZone){

      // check the hold setting and match the case based on the settings
      switch ($this->_AholdSetting) {
        // check the case if the supplier and airline code setting is mapped for the input supplier and airline code
        
        case isset($this->_AholdSetting[$_IsubAgencyCode][$_SairlineCode]):
            $_AholdSettingDetails = $this->_AholdSetting[$_IsubAgencyCode][$_SairlineCode];
            break;
        // check the case if the supplier and airline code setting is mapped for the input supplier and default airline code
        case isset($this->_AholdSetting[$_IsubAgencyCode]['ALL']):
            $_AholdSettingDetails = $this->_AholdSetting[$_IsubAgencyCode]['ALL'];
            break;
        // check the case if the supplier and airline code setting is mapped for the default supplier and input airline code
        case isset($this->_AholdSetting[0][$_SairlineCode]):
            $_AholdSettingDetails = $this->_AholdSetting['ALL'][$_SairlineCode];
            break;
        // check the case if the supplier and airline code setting is mapped for the default supplier and default airline code
        default:
            $_AholdSettingDetails = $this->_AholdSetting[0]['ALL'];
            break;
    }

    // check the hold setting detils is not empty 
    if(!empty($_AholdSettingDetails)){
      // get the expiry ttl time limit info
      $_AexpiryTimelimitInfo['ttlTimeSettings'] = $_AholdSettingDetails;
      // get the time setting data
      $_AtimeSettings = $_AexpiryTimelimitInfo['ttlTimeSettings']['expiry_timelimit_type'];
      // get the buffer time value
      $_IbufferTimeValue = $_AtimeSettings['expiry_buffer_limit_value'];    
      //check the time setting type based on that ttl time will be calculated 
      switch ($_AexpiryTimelimitInfo['ttlTimeSettings']['timeSettingsType']){
        case 'ACTUAL_TIME':   
            $_AtimeResponse = array();
            if(!empty($_DexpiryTtlTime)){
              $_BconvertUtcTime = true;                                 
              $_SttlTime = new DateTime($_DexpiryTtlTime);
                if($_AtimeSettings['expiry_buffer_limit_type'] === 'H'){       
                  $_AtimeResponse = $_SttlTime->modify("-$_IbufferTimeValue hours");  
                }elseif ($_AtimeSettings['expiry_buffer_limit_type'] === 'D') {
                  $_AtimeResponse = $_SttlTime->modify("-$_IbufferTimeValue days");
                }
            }
        break;
        case 'BLOCK_TIME':   
          #no need to convert utc time    
          $_BconvertUtcTime = false;                                 
          #Set ttlTime to current UTC time
          $_Ainput['ttlTime'] = gmdate("Y-m-d H:i:s");
          #Format defaultBlockTime from settings 
          $_AexpiryTimeSettings['ttlTimeSettings']['defaultBlockTime'] = date("H:i", strtotime($_AexpiryTimelimitInfo['ttlTimeSettings']['defaultBlockTime']));  
          $defaultBlockTime = DateTime::createFromFormat('H:i',$_AexpiryTimeSettings['ttlTimeSettings']['defaultBlockTime']); 
          #Create DateTime object from ttlTime
          $_SttlTime = new DateTime($_Ainput['ttlTime']);
            #If current time is greater than or equal to defaultBlockTime, increment buffer time value
            if ($_SttlTime->format('H:i') >= $_AexpiryTimeSettings['ttlTimeSettings']['defaultBlockTime']) {
              $_IbufferTimeValue++;
            }
            #Adjust ttlTime based on expiry buffer limit type
            if ($_AtimeSettings['expiry_buffer_limit_type'] === 'D') {
              $_AtimeResponse = $_SttlTime->modify("+$_IbufferTimeValue days")->setTime($defaultBlockTime->format('H'), $defaultBlockTime->format('i'));
            } else {                            
              $_AtimeResponse = array();
            }
        break;
      }
    }
      if(!empty($_AtimeResponse)){
        #push a ttl time to get a minimum time from $_AdateTime  echo("<pre>");
        if($_BconvertUtcTime){
            $_DdateTime = $this->dateConvertToUTC($_AtimeResponse->format('Y-m-d H:i'),$_StimeZone,'Y-m-d H:i');
        } else {
            $_DdateTime = $_AtimeResponse->format('Y-m-d H:i');
        }
        $_AdateTime[] = $_DdateTime;
      }
      return array("expiry_timelimit_info" => $_AexpiryTimeSettings,"expiryTtlTime" => min($_AdateTime));   
    }



    /*
    *Function :  _checkOrderStatus()
    *Purpose  :  To confirm the order status before sent the mail to user.
    *Author   :  SUDHA S
    *Date     :  2024-07-08 13:05:02
    */

    public function _checkOrderStatus($_IholdStatusId,$_IpackageId){
      
      $_IholdStatusId = $this->getStatusIdbasedStatusCode($_IholdStatusId);

      $_AcheckForMail = QB::table('order_additional_details')
                      ->select(['r_package_id'])
                      ->where('r_status_id','=',$_IholdStatusId)
                      ->andWhere('r_package_id','=',$_IpackageId)
                      ->andWhere('process_type','=','HOLD')
                      ->getResult()[0]['r_package_id'];

      return $_AcheckForMail;
    }



    /**
     * Function to display the message in UI for hold,release and confirmation process 
     * @author SUDHA S
     * @datetime 2024-07-12 14:43:28
     * @input : array     
     * @method responseMessage()
     */

     public function responseMessage($_IpackageId,$_SreleasingBy){

      # Get status ID for PNR Release
      $_IstatusCode = QB::table('dm_status ds')->select(['ds.status_id','ds.status_code'])->getResult();
      $_IstatusCode = array_column($_IstatusCode,'status_id','status_code');
      $_AstatusId = array_flip($_IstatusCode);

      $_QreleaseInprogress = QB::table('order_additional_details')->select(['r_package_id',
                                      "JSON_UNQUOTE(JSON_EXTRACT(other_info,'$.releasedBy')) as releasedBy",
                                      "JSON_UNQUOTE(JSON_EXTRACT(other_info,'$.confirmedBy')) as confirmedBy",
                                      "updated_date",
                                      "r_status_id"])
                            ->where('r_package_id','=',$_IpackageId)
                            ->getResult()[0];

          switch ($_AstatusId[$_QreleaseInprogress['r_status_id']]) {
              case 'PNRHR':
                  $_AtimeZoneData = $this->_OretailCommon->getTimeZoneAndCodeBasedOnUser($_SreleasingBy, true, true, $_QreleaseInprogress["updated_date"]);
                  $_DreleasedDateandTimeFormat = (new DateTime($_AtimeZoneData['convertedTimeBasedOnUser'] ))->format('d M Y H:i');
                  $_Smessage = "Sorry, you can't release the booking as it was already released by " . $_QreleaseInprogress['releasedBy'] . " on " . $_DreleasedDateandTimeFormat . ".";
                  break;
              case 'PNRRRQ':
                  $_Smessage = "Your Required  booking  is Already released in queue";
                  break;
              case 'P':
                  $_AtimeZoneData = $this->_OretailCommon->getTimeZoneAndCodeBasedOnUser($_SreleasingBy, true, true, $_QreleaseInprogress["updated_date"]);
                  $_DreleasedDateandTimeFormat = (new DateTime($_AtimeZoneData['convertedTimeBasedOnUser'] ))->format('d M Y H:i');
                  $_Smessage = "Sorry, you can't release the booking as it was already confirmed by " . $_QreleaseInprogress['confirmedBy'] . " on " . $_DreleasedDateandTimeFormat . ".";
                  break;
              default:
                  $_Smessage = "PNR release is currently in progress. Please wait while we process your request.";
              break;
          }

          $_Aresponse = array('status_code' => 1 , 'status' => 'FAILURE' , 'message'=> $_Smessage);
          return $_Aresponse;
      }
    
    
    /*
     Function :  getStatusCodeBasedOnId()
     Purpose : Get Status Code Based On status Id
     Author  :  Sudha.s <sudha.s@infinitisoftware.net> 
     Date    :  2024-08-09 18:39:42
    */

    public function getStatusCodeBasedOnId($_SstatusId){
      return QB::table('dm_status dm')->select(['dm.status_code'])->where('dm.status_id','=',$_SstatusId)->getResult()[0]['status_code'];
    }


  /*
 	* created By      :   Mohamed Ahamed V K
 	* FunctionName    :   releaseRetryFailureMail()
 	* Description     :   This function is used to trigger the mail for release retry failure
	* Date            :   2024-09-25 13:06:15
	*/
  public function releaseRetryFailureMail($_ApackageId){
    // calling the mail trigger function 
    return $this->_OretailMail->releaseRetryFailureMailTrigger($_ApackageId);
  }



  /*
  *Function :  _updateHoldExpiryTTLTime()
  *Purpose  :  To update the expiry ttl time not receieved booking
  *Author   :  Mohamed Ahamed VK
  *Date     :  2024-09-13 14:05:02
  */

  public function _updateHoldExpiryTTLTime($_SholdBlockTime,$_AholdSettings){
    
    // set the hold block time and add buffer time for expiry ttl time
    $_SttlTime = new DateTime($_SholdBlockTime);
    $_SexpiryBufferType = $_AholdSettings['nottlTimeSettings']['expiry_timelimit_type']['expiry_buffer_limit_type'];
    $_SexpiryBufferValue = $_AholdSettings['nottlTimeSettings']['expiry_timelimit_type']['expiry_buffer_limit_value'];

    if ($_SexpiryBufferType == 'D') {
      $_AtimeResponse = $_SttlTime->modify("+$_SexpiryBufferValue days")->format('Y-m-d H:i:s');
    } 
    else{
      $_AtimeResponse = $_SttlTime->modify("+$_SexpiryBufferValue Hours")->format('Y-m-d H:i:s');
    }

    return $_AtimeResponse;
  }


  /**
  * @author MOHAMED AHAMED VK
  * @date 2024-09-16 14:31:06
  * description : fucntion to get a utc time by passing other time zone
  */
	public function dateConvertToUTC($_Ddate,$_SexistingTimeZone = 'Asia/Kolkata',$_Sformat = 'Y-m-d H:i')
	{
        $_DexistingDate = new DateTime($_Ddate, new DateTimeZone($_SexistingTimeZone));
        // Convert to UTC
        $_DexistingDate->setTimezone(new DateTimeZone('GMT'));
        // Format the UTC date
        $utc_datetime = $_DexistingDate->format($_Sformat);
		return $utc_datetime;
	}

  /**
  * @author MOHAMED AHAMED VK
  * @date 2024-09-16 14:31:06
  * description : fucntion to trigger the mail process for ttl time recieved and expired ttl time process
  */
	public function triggerMailProcess($_AmailData)
  {
    
    $_AorderIds = array_column($_AmailData,'order_id');
    $_ApassengerInfo =  QB::table('dm_package dp')->select(["'Agent' as userName", "dp.requested_by as email_id", "fbd.r_order_id"])->join('fact_booking_details fbd','fbd.r_package_id','=','dp.package_id','INNER JOIN')->whereIn('fbd.r_order_id',$_AorderIds)->getResult();
    $_APaxInfo = [];
    foreach ($_ApassengerInfo as $item) {
	    if (!isset($_APaxInfo[$item['r_order_id']])) {
            $_APaxInfo[$item['r_order_id']] = $item;
        }
    }
    $_APaxInfos = array_values($_APaxInfo);
    $_AorderPaxInfo = array_combine($_AorderIds, $_APaxInfos);  
    // Use array_map with array_keys to merge arrays
    $_AmailerInfo = array_merge(array_map(function($key) use ($_AmailData, $_AorderPaxInfo) {
      return array_merge($_AmailData[$key], $_AorderPaxInfo[$key]);
    }, array_keys($_AmailData)));

    $this->_OretailMail->retryTTLMailTrigger($_AmailerInfo);

  }
}
?>
