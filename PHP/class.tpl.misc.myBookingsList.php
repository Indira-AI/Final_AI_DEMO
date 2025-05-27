<?php
use \QB\queryBulider as QB;

/**
 * myBookings Module File
 * @author Kevin Peter J <kevinpeter.j@infinitisoftware.net>
 * @datetime    2020-01-21T12:37:50+05:30
 */

fileRequire("classes/class.retailCommon.php");
fileRequire("/plugins/misc/personal/harinim/classes/class.holdPnrCommon.php");

class myBookingsList
{
    /**
     * @var Object
     */
    private $_Ocommon;
    private $_AticketStatus;

    public function __construct()
    {
        global $CFG;
        $_SdataPath = LOAD_BALANCE_SERVER_MOUNT_PATH != '' ? DATA_SETTINGS_PATH : $CFG['path']['basePath'].'data/';
        $this->_AjsonSetting = json_decode(file_get_contents($_SdataPath.'retailMyBookings.json'), true)['data'];
        $this->_Ocommon = new retailCommon();
        $this->_OcommonDBO = new commonDBO();
        $this->_Oroute = new route();
        # Getting Ticket Status
        $this->_AticketStatus = $this->_getTicketStatus();
        // $this->_OholdPnrCommon = new \holdPnrCommon();
    }
	
	/**
	 * get Display info
     * @description fetch Bookings
	 * @method      _getDisplayInfo
	 * @Author_name Kevin Peter J <kevinpeter.j@infinitisoftware.net>
	 * @datetime    2020-01-21T12:37:33+05:30
	 * @return      void
	 */
	
    public function _getDisplayInfo() {

        /**
         * Modules List
         * @module (Package Booking List) => (cGFja2FnZUI3Qkdlb29raW5nTGlzdDg=)
         * @module (Hotel Booking List) => (aG8zMnpBdGVsQm9va2luZ0xpc3Qy)
         * @module (Air Booking List) -> (dmlld1JlcXVlaW9ncXN0QWlyOQ==)
         */
        # Defined Request and Response Variables
        $_Aresponse = array();
        $_Ainput = $this->_IinputData;
        $_AjsonSetupFormatted = array_flip(array_column($this->_AjsonSetting, 'id'));

        $_BdateFilterSkip = (!empty($_Ainput['searchText']) || !empty($_Ainput['pnr']) || !empty($_Ainput['travelDate']) || !empty($_Ainput['requestId']) ) ? true : false;

        # Set Start and End Date
        if($_Ainput['timePeriod']['start_date'] == '' && $_Ainput['timePeriod']['end_date'] == '' && !$_BdateFilterSkip) {
            // First day of a specific month
            $d = new DateTime();
            $d->modify('-6 days');
            $_Ainput['timePeriod']['start_date'] = $d->format('Y-m-d').' 00:00:00';
            // Last Day of this month
            $_Ainput['timePeriod']['end_date'] = date("Y-m-d").' 23:59:59';
            
        }
        if(!$_BdateFilterSkip){
            # Apply Time Type Filter
            switch ($_Ainput['timePeriod']['type']) {
                case 'Today':
                    $_Sdate = date('Y-m-d');
                    $_Ainput['timePeriod']['start_date'] = $_Sdate.' 00:00:00';
                    $_Ainput['timePeriod']['end_date'] = $_Sdate.' 23:59:59';
                    break;
                case 'Yesterday':
                    $_Sdate = date('Y-m-d',strtotime('-1 days'));
                    $_Ainput['timePeriod']['start_date'] = $_Sdate.' 00:00:00';
                    $_Ainput['timePeriod']['end_date'] = $_Sdate.' 23:59:59';
                    break;
                case 'Last 7 Days':
                    $_Sdate = date('Y-m-d', strtotime('-6 days'));
                    $_SendDate = date('Y-m-d');
                    $_Ainput['timePeriod']['start_date'] = $_Sdate.' 00:00:00';
                    $_Ainput['timePeriod']['end_date'] = $_SendDate.' 23:59:59';
                    break;
                case 'This Month':
                    $month_ini = new DateTime("first day of this month");
                    $month_end = new DateTime("last day of this month");
                    $_Ainput['timePeriod']['start_date'] = $month_ini->format('Y-m-d').' 00:00:00';
                    $_Ainput['timePeriod']['end_date'] =$month_end->format('Y-m-d').' 23:59:59';
                    break;
                case 'Last Month':
                    $month_ini = new DateTime("first day of last month");
                    $month_end = new DateTime("last day of last month");
                    $_Ainput['timePeriod']['start_date'] = $month_ini->format('Y-m-d').' 00:00:00';
                    $_Ainput['timePeriod']['end_date'] =$month_end->format('Y-m-d').' 23:59:59';
                    break;
            }
        }
        else{
            $_Ainput['timePeriod']['type'] = '';
        }

        // check the current tab Name is Hold or not based on the list will be displayed
        if ($_Ainput['currentTabName'] == 'hold' || isset($_Ainput['searchText']) ) {
            // set the hold booking flag as true    
            $_BholdFlag =  true;
            $_AfetchHoldList['data'] =[['status' => 'FAILURE'],['status' => 'FAILURE'],['status' => 'FAILURE']];
            // check the searchtext is set or not its a over all  search
            if(isset($_Ainput['searchText'])){
                $_ApaidStatusId = QB::table('dm_status')->select('status_id')->where('status_code','=','P')->getResult()[0]['status_id'];
                // get the package id by passing pnr or package id
                $_QpackageId = QB::table('order_additional_details oad')->selectDistinct(['oad.r_package_id'])->where('oad.onward_pnr','=', $this->_IinputData['searchText'])->orWhere('oad.return_pnr','=',$this->_IinputData['searchText']);
                $_Itext = str_replace(BOOKING_REFERENCE_CODE,'',$this->_IinputData['searchText']);
                if(is_numeric($_Itext))
                {
                    $_QpackageId->orWhere('oad.r_package_id','=',$_Itext);
                }
                $_QpackageId->andWhere('oad.r_status_id','!=',$_ApaidStatusId)
                            ->andWhere('oad.process_type','=','HOLD');
                $_ApackageId = $_QpackageId->getResult();
                if(!empty($_ApackageId)){
                    $_Ainput['searchType'] = 'Y';
                    $_Ainput['requestId'] = implode(',', array_unique(array_column($_ApackageId, 'r_package_id')));
                }
                else{
                    $_BholdFlag =  false;
                }
            }
            //check the flag is true based on that the hold booking list will be displayed
            if($_BholdFlag){
                $_AfetchHoldList['data'][$_AjsonSetupFormatted['hold']] = $this->_getHoldBookingLists($_Ainput);
                
                # Set Booking Tabs and Filters
                $_AfetchHoldFilter = $this->_getBookingTabandFilter();
                $_AfetchHoldList['tabs'] = $_AfetchHoldFilter['tabs'];
                $_AfetchHoldList['filters'] = $_AfetchHoldFilter['filters'];

                # Set the Default values for the timeperiod
                if($_Ainput['timePeriod']['type'] != "") {
                    foreach($_AfetchHoldList['filters'] as $_IfilterKey => $_AfilterValue) {
                        $_AfetchHoldList['filters'][$_IfilterKey]['time_period']['default'] = $_Ainput['timePeriod']['type'];
                    }
                }

                // check the search text is isset or not based on that list data will be display for overall search
                if(isset($_Ainput['searchText'])){
                    // define an array for overall search result datas
                    $_AoverAllSearchRes['data'] = $_AfetchHoldList['data'];
                    $_AoverAllSearchRes['tabs'] = $_AfetchHoldFilter['tabs'];
                    $_AoverAllSearchRes['filters'] = $_AfetchHoldFilter['filters'];
                    // return the response for overall result data
                    return $this->_AserviceResponse['data'] = $_AoverAllSearchRes;
                }
                else{
                    return $this->_AserviceResponse['data'] = $_AfetchHoldList;
                }
            }
        }
        $_Afetch = array();
        # Fetch Booking List
        switch ($_Ainput['travel_type']) {
            case 14: # Holiday Package
                $_Afetch = $this->_fetchBookingListData('cGFja2FnZUI3Qkdlb29raW5nTGlzdDg=', $this->_getPackageRequestInput($_Ainput))['serviceResponse']['data'];
                $_Afetch = $this->_prepareHolidayPackageResponseData($_Afetch);
                break;

            case 2: # Hotel
                $_Afetch = $this->_fetchBookingListData('aG8zMnpBdGVsQm9va2luZ0xpc3Qy', $this->_getHotelRequestInput($_Ainput))['twigResponse']['hotelListInfo'];
                $_Afetch = $this->_prepareHotelResponseData($_Afetch);
                break;

            case 1: # Domestic Air
            case 9: # International Air
                $_Afetch = $this->_fetchBookingListData('dmlld1JlcXVlaW9ncXN0QWlyOQ==', $this->_getFlightRequestInput($_Ainput));
                $_Afetch = $this->_prepareFlightResponseData($_Afetch['serviceResponse']['viewRequestList'], $_Ainput['travel_type']);
                break;
            
            default:
                # Holiday Package
                // $_AholidayListData = $this->_fetchBookingListData('cGFja2FnZUI3Qkdlb29raW5nTGlzdDg=', $this->_getPackageRequestInput($_Ainput))['serviceResponse']['data'];
                // $_AholidayListData = $this->_prepareHolidayPackageResponseData($_AholidayListData);
                // $_Afetch = !empty($_AholidayListData) ? array_merge($_Afetch, $_AholidayListData) : $_Afetch;

                # Hotel Booking
                $_AhotelListData = $this->_fetchBookingListData('aG8zMnpBdGVsQm9va2luZ0xpc3Qy', $this->_getHotelRequestInput($_Ainput))['twigResponse']['hotelListInfo'];
                $_AhotelListData = $this->_prepareHotelResponseData($_AhotelListData);
                $_Afetch = !empty($_AhotelListData) ? array_merge($_Afetch, $_AhotelListData) : $_Afetch;

                # Flight Booking
                $_AflightListData = $this->_fetchBookingListData('dmlld1JlcXVlaW9ncXN0QWlyOQ==', $this->_getFlightRequestInput($_Ainput));
                $_AflightListData = $this->_prepareFlightResponseData($_AflightListData['serviceResponse']['viewRequestList']);
                $_Afetch = !empty($_AflightListData) ? array_merge($_Afetch, $_AflightListData) : $_Afetch;
                break;
        }
        
        if(!empty($_Afetch)) {
            # Sort an array based on travel
            $_AsortArrayInput = array( "inputArray"=>$_Afetch, "firstFieldName"=>"travel_start_date", "firstFieldOrder"=>"ASC");
            $_Afetch = $this->_Ocommon->_multipleSortFunction($_AsortArrayInput);
            # Fetch Datas
            $_Afetch['data'] = $_Afetch;
            $_AcancellationStatus = array_column($this->_AjsonSetting[$_AjsonSetupFormatted['cancelled']]['filter']['status']['data'], 'name');
            $_AupcomingStatus = array_column($this->_AjsonSetting[$_AjsonSetupFormatted['upcoming']]['filter']['status']['data'], 'name');
            $_AcompletedStatus = array_column($this->_AjsonSetting[$_AjsonSetupFormatted['completed']]['filter']['status']['data'], 'name');
            #get status
            $_AallStatus = QB::table('dm_status')
                ->select(['status_id','status_value'])
                ->getResult();

            $_AallStatus = array_combine(array_column($_AallStatus, 'status_id'),array_column($_AallStatus, 'status_value'));
            // if(!in_array($_Avalue['booking_status'],array(CANCEL_REQUEST,CANCEL_PROCESS,PARTIAL_CANCEL_PROCESS)) && in_array($_Avalue['r_payment_status_id'],array(BOOKING_REFUND_INITIATED,BOOKING_REFUNDED,BOOKING_REFUND_FAILED,BOOKING_PARTIAL_REFUND_INITIATED,BOOKING_PARTIAL_REFUNDED))){
            //     $_Aresponse[$_Ikey]['booking_status'] = $_AallStatus[$_Avalue['r_payment_status_id']];
            // }
            # Apply Tab Filter
            foreach ($_Afetch['data'] as $key => $value) {
                $value['bookingStatus'] = $value['booking_status'];
               
                if($this->_AjsonSetting[$_AjsonSetupFormatted['cancelled']]['status'] == 'Y' && in_array($value['bookingStatus'], $_AcancellationStatus)) {
                    # Cancelled
                    $_IindexKey = 'cancelled';
                    if(!in_array($value['bookingStatus'],array('Partial Cancel Process','Cancel Process','Cancel Request')) && in_array($value['r_payment_status_id'],array(BOOKING_REFUND_INITIATED,BOOKING_REFUNDED,BOOKING_REFUND_FAILED,BOOKING_PARTIAL_REFUND_INITIATED,BOOKING_PARTIAL_REFUNDED,BOOKING_PARTIAL_REFUND_FAILED))){
                        $value['booking_status'] = $_AallStatus[$value['r_payment_status_id']];
                        if($value['bookingStatus'] == 'Partially Cancelled'){
                            if($value['r_payment_status_id'] == BOOKING_REFUNDED) 
                               $value['booking_status'] = $_AallStatus[BOOKING_PARTIAL_REFUNDED];
                            if($value['r_payment_status_id'] == BOOKING_REFUND_INITIATED) 
                               $value['booking_status'] = $_AallStatus[BOOKING_PARTIAL_REFUND_INITIATED];
                            if($value['r_payment_status_id'] == BOOKING_REFUND_FAILED) 
                               $value['booking_status'] = $_AallStatus[BOOKING_PARTIAL_REFUND_FAILED];
                        }                        
                    }
                    $value['retry_payment_status'] = false;
                    $value['user_review_status'] = false;
                    $_Aresponse['data'][$this->_AjsonSetting[$_AjsonSetupFormatted[$_IindexKey]]['index_order']][$value['request_id']] = $value;
                }
                #  Show booking in upcoming tab until return departure starts or onward departure starts based on triptype
                $commonMethods = new \commonMethods();
                #get user current time
                $userCurrentTime = $commonMethods->_convertUTCtoUserTimezone($commonMethods->_getUTCTime());
                $travelStartDate = strtotime($value['travel_start_date']);
                if($value['travel_mode_code']=='D' || $value['travel_mode_code'] =='I'){
                    $travel_date_time = explode(',',$value['travel_date_time']);
                    $travelStartDate = null;
                    foreach($travel_date_time as $tsdt){
                        # ONWARD
                        if(strpos($tsdt,'-O') && is_null($travelStartDate)){
                            $travelStartDate = explode('-O',$tsdt)[0];
                        }
                        # RETURN
                        elseif(strpos($tsdt,'-R')){
                            $travelStartDate = explode('-R',$tsdt)[0];
                            break;
                        }
                    }
                    $travelStartDate = strtotime($travelStartDate.":00");
                }
                if( ($travelStartDate >= strtotime($userCurrentTime)) && $this->_AjsonSetting[$_AjsonSetupFormatted['upcoming']]['status'] == 'Y' && in_array($value['bookingStatus'], $_AupcomingStatus)) {
                    # Upcoming
                    //$value['retry_payment_status'] = $this->_retryPayment($value);
                    if($value['travel_mode_code'] == 'H'){
                        $value['retry_payment_status'] = false;
                    }else{
                        $value['retry_payment_status'] = $this->_retryPayment($value);
                    }
                    $value['user_review_status'] = false;
                    $_IindexKey = 'upcoming';
                    if(!in_array($value['bookingStatus'],array('Partial Cancel Process','Cancel Process','Cancel Request')) && in_array($value['r_payment_status_id'],array(BOOKING_REFUND_INITIATED,BOOKING_REFUNDED,BOOKING_REFUND_FAILED,BOOKING_PARTIAL_REFUND_INITIATED,BOOKING_PARTIAL_REFUNDED,BOOKING_PARTIAL_REFUND_FAILED))){
                        $value['booking_status'] = $_AallStatus[$value['r_payment_status_id']];
                        if($value['bookingStatus'] == 'Partially Cancelled'){
                            if($value['r_payment_status_id'] == BOOKING_REFUNDED) 
                               $value['booking_status'] = $_AallStatus[BOOKING_PARTIAL_REFUNDED];
                            if($value['r_payment_status_id'] == BOOKING_REFUND_INITIATED) 
                               $value['booking_status'] = $_AallStatus[BOOKING_PARTIAL_REFUND_INITIATED];
                            if($value['r_payment_status_id'] == BOOKING_REFUND_FAILED) 
                               $value['booking_status'] = $_AallStatus[BOOKING_PARTIAL_REFUND_FAILED];
                        }                        
                    }
                    $_Aresponse['data'][0][$value['request_id']] = $value;
                    $_Aresponse['data'][$this->_AjsonSetting[$_AjsonSetupFormatted[$_IindexKey]]['index_order']][$value['request_id']] = $value;
                } elseif($this->_AjsonSetting[$_AjsonSetupFormatted['completed']]['status'] == 'Y' && in_array($value['bookingStatus'], $_AcompletedStatus)) {
                    # Completed
                    $value['retry_payment_status'] = false;
                    //$value['user_review_status'] = $value['downloadVoucherStatus'];
                    $value['user_review_status'] = false;
                    $_IindexKey = 'completed';
                    if(!in_array($value['bookingStatus'],array('Partial Cancel Process','Cancel Process','Cancel Request')) &&   in_array($value['r_payment_status_id'],array(BOOKING_REFUND_INITIATED,BOOKING_REFUNDED,BOOKING_REFUND_FAILED,BOOKING_PARTIAL_REFUND_INITIATED,BOOKING_PARTIAL_REFUNDED,BOOKING_PARTIAL_REFUND_FAILED))){
                        $value['booking_status'] = $_AallStatus[$value['r_payment_status_id']];
                        if($value['booking_status'] == 'Partially Cancelled'){
                            if($value['r_payment_status_id'] == BOOKING_REFUNDED) 
                               $value['booking_status'] = $_AallStatus[BOOKING_PARTIAL_REFUNDED];
                            if($value['r_payment_status_id'] == BOOKING_REFUND_INITIATED) 
                               $value['booking_status'] = $_AallStatus[BOOKING_PARTIAL_REFUND_INITIATED];
                            if($value['r_payment_status_id'] == BOOKING_REFUND_FAILED) 
                               $value['booking_status'] = $_AallStatus[BOOKING_PARTIAL_REFUND_FAILED];
                        }                        
                    }
                    $_Aresponse['data'][$this->_AjsonSetting[$_AjsonSetupFormatted[$_IindexKey]]['index_order']][$value['request_id']] = $value;
                }
                unset($commonMethods);
                unset($userCurrentTime);
                unset($travelStartDate);
                unset($travel_date_time);
            }

            # Sorting the array
            krsort($_Aresponse['data'][0]);
            krsort($_Aresponse['data'][1]);
            krsort($_Aresponse['data'][2]);

            $_Aresponse['data'][0] = empty($_Aresponse['data'][0]) ? array('status' => 'FAILURE') : array_values($_Aresponse['data'][0]);
            $_Aresponse['data'][1] = empty($_Aresponse['data'][1]) ? array('status' => 'FAILURE') : array_values($_Aresponse['data'][1]);
            $_Aresponse['data'][2] = empty($_Aresponse['data'][2]) ? array('status' => 'FAILURE') : array_values($_Aresponse['data'][2]);
            $_Aresponse['data'][3] = array('status' => 'FAILURE');
        } else {
            $_Aresponse['data'][0] = array('status' => 'FAILURE');
            $_Aresponse['data'][1] = array('status' => 'FAILURE');
            $_Aresponse['data'][2] = array('status' => 'FAILURE');
            $_Aresponse['data'][3] = array('status' => 'FAILURE');
        }
        
       
        # Set Booking Tabs and Filters
        $_AtabandFilterResponse = $this->_getBookingTabandFilter();

        $_Aresponse['tabs'] = $_AtabandFilterResponse['tabs'];
        $_Aresponse['filters'] = $_AtabandFilterResponse['filters'];
        # Set the Default values for the timeperiod
        if($_Ainput['timePeriod']['type'] != "") {
            foreach($_Aresponse['filters'] as $_IfilterKey => $_AfilterValue) {
                $_Aresponse['filters'][$_IfilterKey]['time_period']['default'] = $_Ainput['timePeriod']['type'];
            }
        }
    	$this->_AserviceResponse['data'] = $_Aresponse;
    }

    /**
     * Fetch Holiday Package Informations
     * @author Kevin Peter J <kevinpeter.j@infinitisoftware.net>
     * @date(2020-01-21T13:24:11+05:30)
     * @method _fetchHolidayPackageData (private)
     */
    
    private function _prepareHolidayPackageResponseData($_Ainput) {
        foreach ($_Ainput as $_Ikey => $_Avalue) {
            # Get booking contact information using order id
            $_AbookingContactInfo = $this->_Ocommon->_getBookingContactInformation($_Avalue['order_id']);

            # Prepare response array
            $_ApackageInfo = json_decode($_Avalue['package_information'], true);
            $_Aresponse[$_Ikey]['city_name'] = $_ApackageInfo['city_name'];
            $_Aresponse[$_Ikey]['package_days'] = $_ApackageInfo['package_info']['days'];
            $_Aresponse[$_Ikey]['travellers']['adult'] = $_Avalue['ADT'];
            $_Aresponse[$_Ikey]['travellers']['child'] = $_Avalue['CHD'];
            $_Aresponse[$_Ikey]['travellers']['infant'] = $_Avalue['INFT'];
            $_Aresponse[$_Ikey]['travel_start_date'] = explode(" ", $_Avalue['travel_start_date'])[0];
            $_Aresponse[$_Ikey]['travel_start_time'] = explode(" ", $_Avalue['travel_start_date'])[1];
            $_Aresponse[$_Ikey]['travel_end_date'] = explode(" ", $_Avalue['travel_end_date'])[0];
            $_Aresponse[$_Ikey]['travel_end_time'] = explode(" ", $_Avalue['travel_end_date'])[1];
            $_AdateTime = $this->_Ocommon->_timezoneConversion($_Avalue['booking_date']);
            $_Aresponse[$_Ikey]['booking_date'] = date("Y-m-d", strtotime($_AdateTime));
            $_Aresponse[$_Ikey]['booking_time'] = date("h:i:s A", strtotime($_AdateTime));
            $_Aresponse[$_Ikey]['request_id'] = (int) $_Avalue['request_id'];
            $_Aresponse[$_Ikey]['name'] = $_AbookingContactInfo['title'].'. '.$_AbookingContactInfo['first_name'].' '.$_AbookingContactInfo['last_name'];
            $_Semail = explode('@', $_Avalue['requested_by'])[1] == 'retailInfinitiSoftware2020.in' ? '' : $_Avalue['requested_by'];
            $_Aresponse[$_Ikey]['requested_by'] = $_Semail;
            $_Aresponse[$_Ikey]['order_id'] = $_Avalue['order_id'];
            $_Aresponse[$_Ikey]['booking_status'] = $_Avalue['booking_status'];
            $_Aresponse[$_Ikey]['currency_type'] = $_Avalue['currency_type'];
            $_Aresponse[$_Ikey]['total_fare'] = $_Avalue['total_fare'];
            $_Aresponse[$_Ikey]['travel_mode'] = $_Avalue['travel_mode'];
            $_Aresponse[$_Ikey]['travel_mode_code'] = $_Avalue['mode_type'];
            $_Aresponse[$_Ikey]['displayReferenceId'] = BOOKING_REFERENCE_CODE.$_Avalue['request_id'];
            $_Aresponse[$_Ikey]['downloadVoucherStatus'] = $this->_AticketStatus[$_Avalue['ticket_status']] == 'T' ? true : false;

        }
        return $_Aresponse;
    }

    /**
     * Function to prepare an input for the package booking list
     * @author Kevin Peter J <kevinpeter.j@infinitisoftware.net>
     * @date(2020-03-06T12:15:26+05:30)
     * @method _getPackageRequestInput (private)
     */
    
    private function _getPackageRequestInput($_Ainput) {
        return array(
            'start_date' => $_Ainput['timePeriod']['start_date'],
            'end_date' => $_Ainput['timePeriod']['end_date'],
            'booking_status' => $_Ainput['status']
        );
    }

    /**
     * Function to fetch the response from the existing module files
     * @author Kevin Peter J kevinpeter.j@infinitisoftware.net
     * @date(2020-03-06T12:24:01+05:30)
     * @method _fetchBookingListData (private)
     */
    
    private function _fetchBookingListData($_SstateName, $_ArequestInput) {
        # Set an input for the list bookings
        $this->_Oroute->_IinputData = array(
            "name" => $_SstateName,
            "stateData" => $_ArequestInput
        );

        # Set an application type as AGENCY_DIRECT
        $this->_Oroute->_SappType = APPLICATION_TYPE;
        return json_decode($this->_Oroute->_handleRequest(), true);
    }

    /**
     * Function to prepare the hotel booking request data
     * @author Kevin Peter J <kevinpeter.j@infinitisoftware.net>
     * @date(2020-03-06T17:44:02+05:30)
     * @method _getHotelRequestInput (private)
     */
    
    private function _getHotelRequestInput($_Ainput) {
        return array(
            'start_date' => $_Ainput['timePeriod']['start_date'],
            'end_date' => $_Ainput['timePeriod']['end_date'],
            'booking_status' => $_Ainput['status'],
            'travel_date' => $_Ainput['travelDate'],
            'voucher'=>$_Ainput['pnr'],
            'searchText'=>$_Ainput['searchText']
        );
    }

    /**
     * Function to prepare the flight booking request data
     * @author Kevin Peter J <kevinpeter.j@infinitisoftware.net>
     * @date(2020-03-06T17:44:02+05:30)
     * @method _getFlightRequestInput (private)
     */

    private function _getFlightRequestInput($_Ainput) {
        return array(
            'from' => $_Ainput['timePeriod']['start_date'],
            'to' => $_Ainput['timePeriod']['end_date'],
            'range' => 7, # Date Range
            'sortDataFilter' => [
                'status' => $_Ainput['status']
            ],
            'pnr'=>$_Ainput['pnr'],
            'travelDate'=>$_Ainput['travelDate'],
            'searchText'=>$_Ainput['searchText']
        );   
    }

    /**
     * Function to prepare the hotel response data
     * @author Kevin Peter J <kevinpeter.j@infinitisoftware.net>
     * @date(2020-03-06T18:24:35+05:30)
     * @method  _prepareHotelResponseData (private)
     */
    
    private function _prepareHotelResponseData($_Ainput) {
        $_Aresponse = array();

        $_AorderIds = array_column($_Ainput, 'orderid');

        $_AagencyMarkupDetails = QB::table('fare_details')->select(['tax_breakup','r_order_id'])
                        ->whereIn('r_order_id', $_AorderIds)
                        ->andWhere('pax_type', '=', 'ALL')
                        ->getResult();
        $_AagencyMarkupDetails = array_column($_AagencyMarkupDetails, 'tax_breakup','r_order_id');

        $likeEmulate = "'%"."Emulate_"."%'";

        $emulateQuery = "SELECT de.email_id,order_id FROM order_history oh LEFT JOIN dm_employee de ON de.employee_id = oh.employee_id WHERE activity LIKE '%Emulate_%' AND order_id IN (".implode(',', $_AorderIds).") order by id DESC limit 1 ";
       
        $emulateUser = QB::query($emulateQuery)->getResult();
        
        
        if(!empty($emulateUser)){
            $emulateUser = array_combine(array_column($emulateUser,'order_id') ,array_column($emulateUser, 'email_id'));
        }

        foreach ($_Ainput as $_Ikey => $_Avalue) {

            # Get booking contact information using order id
            // $_AbookingContactInfo = $this->_Ocommon->_getBookingContactInformation($_Avalue['orderid']);

            # Prepare response array
            $checkIn = date_create($_Avalue['checkin_date']);
            $checkOut = date_create($_Avalue['checkout_date']);
            # Set Mode Type for Hotel
            try {
                $_AhotelAdditionalInfo = json_decode($_Avalue['additional_info'], true);
            } catch (Exception $e) {}
            $_SaccomodationType = $_AhotelAdditionalInfo['accomodationType'] != '' ? $_AhotelAdditionalInfo['accomodationType'] : $_Avalue['mode_value'];
            $_Aresponse[$_Ikey]['travellers']['adult'] = $_Avalue['adult_count'];
            $_Aresponse[$_Ikey]['travellers']['child'] = $_Avalue['child_count'];
            $_Aresponse[$_Ikey]['travellers']['infant'] = 0;
            $_Aresponse[$_Ikey]['travel_start_date'] = $_Avalue['checkin_date'];
            $_Aresponse[$_Ikey]['request_id'] = (int) $_Avalue['package_id'];
            $_Semail = explode('@', $_Avalue['requested_by'])[1] == 'retailInfinitiSoftware2020.in' ? '' : $_Avalue['requested_by'];
            $_Aresponse[$_Ikey]['requested_by'] = $_Semail;
            $_Aresponse[$_Ikey]['emulated_by'] = (isset($emulateUser[$_Avalue['orderid']]) && !empty($emulateUser[$_Avalue['orderid']])) ? $emulateUser[$_Avalue['orderid']] : '';
            $_Aresponse[$_Ikey]['name'] = $_Avalue['title'].'. '.$_Avalue['first_name'].' '.$_Avalue['last_name'];
            $_Aresponse[$_Ikey]['order_id'] = $_Avalue['orderid'];
            $_Aresponse[$_Ikey]['r_order_id'] = (int) $_Avalue['sync_order_id'];
            $_Aresponse[$_Ikey]['booking_status'] = $_Avalue['status_value'];
            $_Aresponse[$_Ikey]['voucher'] = $_Avalue['voucher_number'];
            $_Aresponse[$_Ikey]['nights'] = $_AhotelAdditionalInfo['totalNights'];
            $_Aresponse[$_Ikey]['days'] = date_diff($checkIn, $checkOut)->days;
            $_Aresponse[$_Ikey]['currency_type'] = $_Avalue['currency_code'];
            $_Aresponse[$_Ikey]['total_fare'] = $_Avalue['Amount'] + $_Avalue['extra_charge']+ $_Avalue['extra_charge_gst'];
            $_Aresponse[$_Ikey]['extra_charge'] = $_Avalue['extra_charge'];
            $_Aresponse[$_Ikey]['extra_charge_gst'] = $_Avalue['extra_charge_gst'];
            $_Aresponse[$_Ikey]['total_amount'] = $_Avalue['Amount'];
            $_Aresponse[$_Ikey]['travel_mode'] = $_SaccomodationType;
            $_Aresponse[$_Ikey]['travel_mode_code'] = $_Avalue['mode_type'];
            $_Aresponse[$_Ikey]['travel_end_date'] = $_Avalue['checkout_date'];
            $_Aresponse[$_Ikey]['city_name'] = $_Avalue['city_name'];
            $_AdateTime = $this->_Ocommon->_timezoneConversion($_Avalue['booking_date']);
            $_Aresponse[$_Ikey]['booking_date'] = date("Y-m-d", strtotime($_AdateTime));
            $_Aresponse[$_Ikey]['booking_time'] = date("h:i:s A", strtotime($_AdateTime));
            $_Aresponse[$_Ikey]['displayReferenceId'] = BOOKING_REFERENCE_CODE.$_Avalue['package_id'];
            $_Aresponse[$_Ikey]['r_payment_status_id'] = $_Avalue['r_payment_status_id'];
            $_Aresponse[$_Ikey]['hotelType'] = $_Avalue['hotel_provide_type'];
            $_Aresponse[$_Ikey]['downloadInvoiceStatus'] = $this->_AticketStatus[$_Avalue['ticket_status']] == 'T' ? true : false;
            $_Aresponse[$_Ikey]['downloadVoucherStatus'] = $this->_AticketStatus[$_Avalue['ticket_status']] == 'T' ? true : false;
            
            $_AagencyMarkup = $_AagencyMarkupDetails[$_Avalue['orderid']];
            if(!empty($_AagencyMarkup)) {
                $_AagencyMarkup = json_decode($_AagencyMarkup, true);
                $_Aresponse[$_Ikey]['total_fare'] += $_AagencyMarkup['AGENCY_MARKUP_FEE'];
            }
        }
        return $_Aresponse;
    }
     /**
     * @description To retry an payment failure
     * @method      _retryPayment
     * @Author_name Kasi raja pandian C <kasirajanpandian@infinitisoftware.net>
     * @datetime    2020-02-28T11:24:51+05:30
     */
     private function _retryPayment($_Ainput) {

        # Check Booking Status
        if($_Ainput['booking_status'] != PAYMENT_NOT_DONE_STATUS_VALUE) {
            return false;
        } 
        #Current Time of the user
         $currentTime = $this->_Ocommon->_getUTCtimeZone();

         #convert the current time of user
         $convertTime = date("Y-m-d H:i:s", strtotime($currentTime));

         #To convert the currenttime with timezone
         $timezoneConvTime = $this->_Ocommon->_timezoneConversion($convertTime);

        #check Payment Id
        $checkPaymentId = QB::table('payment_details')->select(['payment_id'])->where('r_order_id', '=', $_Ainput['order_id'])->getResult()[0]['payment_id'];
        if($checkPaymentId != '') {
        #Fetch the details from Payment Details that contains payment failure id's
        $bookingTime = QB::table('payment_details pmd')
            ->join('fact_booking_details fbd', 'fbd.r_payment_id', '=', 'pmd.payment_id', 'INNER JOIN')
            ->select(['pmd.payment_request_time', 'pmd.payment_status', 'pmd.r_package_id', 'fbd.r_order_id', 'pmd.payment_id'])
            ->where('pmd.payment_status', '=', 'N')
            ->andWhere('fbd.r_package_id', '=', $_Ainput['request_id'])
            ->getResult();

        #check the Payment status of the user
        if (empty($bookingTime)) {
            return false;
        } else {

        #Check the payment time of user is greater than 5hrs
        $paymentRequestTime = $bookingTime[0]['payment_request_time'];

        #To convert the BooingTime with timezone
        $bookingConvTime = $this->_Ocommon->_timezoneConversion($paymentRequestTime);

        #dateCreate of BookingTime
        $fetchBookingTime = date_create($bookingConvTime);
        #dateCreate of timezoneConvTime
        $fetchtimeZoneTime = date_create($timezoneConvTime);
        #To find Date Time differences
        $dateDifferTime = date_diff($fetchBookingTime , $fetchtimeZoneTime);

        #If the Booking time is greater than 5 hrs
        if (($dateDifferTime->h > RETRY_PAYMENT_TIME || $dateDifferTime->days != 0) && RETRY_PAYMENT_TIME_CHECK_STATUS)  {
            return false;
        } else {
                #Check the Count for Payment count
                $countPayment = QB::table('pg_payment_details ppd')
                    ->select(["count('pg_payment_details_id')as count "])
                    ->where('ppd.r_request_id', '=', $bookingTime[0]['payment_id'])
                    ->getResult();

                #check if the Payment count is greater than Redirect Count
                if ($countPayment[0]['count'] > RETRY_PAYMENT_COUNT) {
                    return false;
                } else {
                    filewrite(print_r("retrypayment",1),"orderidd","a+");
                        return true;
                    }
                }
            }
        } elseif ($checkPaymentId == "" && $_Ainput['booking_status'] == PAYMENT_NOT_DONE_STATUS_VALUE) {
            return true;
        }
    }

    /**
     * Function to get the ticket status
     * @author  Kevin Peter J <kevinpeter.j@infinitisoftware.net>
     * @date 2020-09-07T16:14:25+05:30
     */
    private function _getTicketStatus() {
        $_AticketStatus = QB::table('dm_status')->select(['status_code', 'status_id'])->getResult();
        return array_column($_AticketStatus, 'status_code', 'status_id');
    }

    /**
     * Function to prepare the flight booking list format
     * @author Kevin Peter J
     * @date 2020-10-07T16:22:35+05:30
     */
    private function _prepareFlightResponseData($_Ainput, $_ItravelType = 0) {
        $_Aresponse = array();
        if(!empty($_Ainput)) {
            # Get Travel Mode
            $_AtravelMode = array_count_values(array_column($_Ainput, 'r_travel_mode_id'));
            foreach ($_AtravelMode as $_ItravelModeId => $_ItravelModeValue) {
                $_AtravelMode[$_ItravelModeId] = QB::table('dm_travel_mode')->select(['travel_mode', 'travel_mode_code'])->where('travel_mode_id', '=', $_ItravelModeId)->getResult()[0];
            }
            #get statusid of holdpnr processs
            $_IholPnrStatusId = QB::table('dm_status')
                ->select(['status_id'])
                ->whereIn('status_code',['PNRH','PNRHRQ','PNRHR','PNRHF','PNRRRQ','PNRRRF'])
                ->getResult();
            $_IholPnrStatus = array_column($_IholPnrStatusId,'status_id');

            $_AorderIds = array_column($_Ainput, 'order_id');
            #get orderid based on holdpnr process status code to unset the hold orderid
            $_AholdOrderCheck= QB::table('order_additional_details')
                ->select(['r_order_id'])
                ->where('process_type','=','HOLD')
                ->andWhereIn('r_order_id', $_AorderIds)
                ->andWhereIn('r_status_id',$_IholPnrStatus)
                ->getResult();
            $_AholdOrderId= array_column($_AholdOrderCheck,'r_order_id');

            $_AagencyMarkupDetails = QB::table('fare_details')->select(['tax_breakup','r_order_id'])
                        ->whereIn('r_order_id', $_AorderIds)
                        ->andWhere('pax_type', '=', 'ALL')
                        ->getResult();
            $_AagencyMarkupDetails = array_column($_AagencyMarkupDetails, 'tax_breakup','r_order_id');
            $likeEmulate = "'%"."Emulate_"."%'";

            $emulateQuery = "SELECT de.email_id,order_id FROM order_history oh LEFT JOIN dm_employee de ON de.employee_id = oh.employee_id WHERE activity LIKE '%Emulate_%' AND order_id IN (".implode(',', $_AorderIds).")";
           
            $emulateUser = QB::query($emulateQuery)->getResult();
           
            if(!empty($emulateUser)){
                $emulateUser = array_combine(array_column($emulateUser,'order_id') ,array_column($emulateUser, 'email_id'));
            }
            foreach ($_Ainput as $_Ikey => $_Avalue) {

                # Booking Assign Status
                $_IbookingAssignStatus = true;

                if($_ItravelType) {
                    $_IbookingAssignStatus = $_Avalue['r_travel_mode_id'] == $_ItravelType ? true : false;
                } 
                if($_IbookingAssignStatus) {
                    #unset the order id if the orderid is against the hold status id.
                    # If it does, the continue statement is executed, which skips the rest of the current iteration of a loop and moves to the next iteration
                    if(in_array($_Avalue['order_id'],$_AholdOrderId)){
                        continue;
                    }
                    # Get Travellers Count
                    $_Atravellers = array_count_values(array_column($_Avalue['paxInfo'], 'passenger_type'));
                    $_Aresponse[$_Ikey]['travellers']['adult'] = isset($_Atravellers['ADT']) ? $_Atravellers['ADT'] : 0;
                    $_Aresponse[$_Ikey]['travellers']['child'] = isset($_Atravellers['CNN']) ? $_Atravellers['CNN'] : 0;
                    $_Aresponse[$_Ikey]['travellers']['infant'] = isset($_Atravellers['INF']) ? $_Atravellers['INF'] : 0;

                    $_Aresponse[$_Ikey]['travel_start_date'] = $_Avalue['travelDate'];
                    $_Aresponse[$_Ikey]['request_id'] = (int) $_Avalue['package_id'];
                    $_Aresponse[$_Ikey]['r_order_id'] = (int) $_Avalue['sync_order_id'];
                    $_Semail = explode('@', $_Avalue['requestedPersonInfo']['email_id'])[1] == 'retailInfinitiSoftware2020.in' ? '' : $_Avalue['requestedPersonInfo']['email_id'];
                    $_Aresponse[$_Ikey]['requested_by'] = $_Semail;
                    $_Aresponse[$_Ikey]['emulated_by'] = $emulateUser[$_Avalue['order_id']];
                    $_Aresponse[$_Ikey]['name'] = $_Avalue['requestedPersonInfo']['employee_name'];
                    $_Aresponse[$_Ikey]['order_id'] = $_Avalue['order_id'];
                    $_Aresponse[$_Ikey]['booking_status'] = $_Avalue['status_value'];
                    $_Aresponse[$_Ikey]['r_payment_status_id'] = $_Avalue['r_payment_status_id'];
                    $_Aresponse[$_Ikey]['currency_type'] = $_Avalue['currency_symbol'];
                    $_Aresponse[$_Ikey]['total_fare'] = $_Avalue['total_amount']+$_Avalue['extra_charge']+$_Avalue['extra_charge_gst'];
                    $_Aresponse[$_Ikey]['extra_charge'] = $_Avalue['extra_charge'];
                    $_Aresponse[$_Ikey]['extra_charge_gst'] = $_Avalue['extra_charge_gst'];
                    $_Aresponse[$_Ikey]['total_amount'] = $_Avalue['total_amount'];
                    $_Aresponse[$_Ikey]['travel_mode'] = $_AtravelMode[$_Avalue['r_travel_mode_id']]['travel_mode'];
                    $_Aresponse[$_Ikey]['travel_mode_code'] = $_AtravelMode[$_Avalue['r_travel_mode_id']]['travel_mode_code'];
                    $_Aresponse[$_Ikey]['travel_end_date'] = $_Avalue['arrival_date'];
                    $_Aresponse[$_Ikey]['city_name'] = $_Avalue['sector_from'].'-'.$_Avalue['sector_to'];
                    $_Aresponse[$_Ikey]['travel_class'] = $_Avalue['travel_class'];
                    $_Aresponse[$_Ikey]['fare_type'] = $_Avalue['fare_type'];
                    $_Aresponse[$_Ikey]['departure_datetime'] = $_Avalue['departure_date']." ".$_Avalue['time_departure'].":00";
                    $_Aresponse[$_Ikey]['arrival_datetime'] = $_Avalue['arrival_date']." ".$_Avalue['time_arrival'].":00";
                    $_Aresponse[$_Ikey]['travel_date_time'] = $_Avalue['travel_date_time'];

                    $_Aresponse[$_Ikey]['trip_type'] = $this->_getTripType($_Avalue['trip_type']);

                    // $_SgetBookDate = QB::table('dm_package')->select(['created_date'])->where('package_id', '=', $_Avalue['package_id'])->getResult()[0]['created_date'];
                    $_SgetBookDate = $_Avalue['booking_date'];
                    $_AagencyMarkup = $_AagencyMarkupDetails[$_Avalue['order_id']];
                    if(!empty($_AagencyMarkup)) {
                        $_AagencyMarkup = json_decode($_AagencyMarkup, true);
                        $_Aresponse[$_Ikey]['total_fare'] += $_AagencyMarkup['AGENCY_MARKUP_FEE'];
                    }
                    
                    $_AdateTime = $this->_Ocommon->_timezoneConversion($_Avalue['booking_date']);
                    $_Aresponse[$_Ikey]['booking_date'] = date("Y-m-d", strtotime($_AdateTime));
                    $_Aresponse[$_Ikey]['booking_time'] = date("h:i:s A", strtotime($_AdateTime));
                    $_Aresponse[$_Ikey]['displayReferenceId'] = BOOKING_REFERENCE_CODE.$_Avalue['package_id'];
                    // $_Aresponse[$_Ikey]['downloadVoucherStatus'] = $this->_AticketStatus[$_Avalue['ticket_status']] == 'T' ? true : false;
                    //$_Aresponse[$_Ikey]['downloadInvoiceStatus'] = in_array($_Avalue['booking_status'], [3,16,48])?false : true;

                    #PNR Display for List
                    $explodePnr = explode(',',$_Avalue['pnr_list']);
                    if($_Avalue['travel_type'] == 'I' && $_Avalue['trip_type'] == '1'){
                        // $uniquePnr = array($explodePnr[0],$explodePnr[1]);
                        $_Aresponse[$_Ikey]['pnr'] = (!empty($explodePnr[0])) ? $explodePnr[0] .'/'. $explodePnr[0]  : "";
                    } else {
                        $uniquePnr = array_unique($explodePnr);
                        $_Aresponse[$_Ikey]['pnr'] = !empty($uniquePnr) ? implode(' / ',$uniquePnr) : "";
                    }
                }
            }
        }
        return array_values($_Aresponse);
    }
    
    /**
     * Function to get the hold booking list
     * @author  Mohamed Ahamed VK <ahamed.vk@infinitisoftware.net>
     * @date 2024-04-18T16:14:25+05:30
     * @input : $input | array
     * @output : $response | array
     */
    private function _getHoldBookingLists($_Ainput = array()) {

        // define an array for action datas
        $_AactionDatas = [
            [
                'name' => 'View',
                'className' => 'cls-view',
                'actionName' => 'viewHoldBooking',
                "iconName" => "icon-15-view"
            ],
            [
                'name' => 'Confirm',
                'className' => 'cls-confirm',
                'actionName' => 'confirmHoldBooking',
                "iconName" => "icon-195-tick"
            ],
            [
                'name' => 'Release',
                'className' => 'cls-release',
                'actionName' => 'holdPnrActions',
                "iconName" => "icon-46-delete"
            ]
        ];

        // query to get the list for hold pnr booking from database
        $_QholdListQuery =  QB::table('order_additional_details as oad')
                            ->select(["oad.r_order_id",
                                    "expiry_ttl_time as expiry_time",
                                    "oad.r_package_id as request_id",
                                    "oad.r_package_id",
                                    "oad.r_status_id",
                                    "dmp.requested_by",
                                    "ard.r_travel_class_id as cabin_class",
                                    "ard.travel_type",
                                    "ard.trip_type",
                                    "da1.airport_code as sector_from",
                                    "da1.city_name as origin_airport",
                                    "da2.airport_code as sector_to",
                                    "da2.city_name as destination_airport",
                                    "ard.onward_date as travel_start_date",
                                    "oad.onward_pnr",
                                    "oad.return_pnr",
                                    "ard.adult_count",
                                    "ard.child_count",
                                    "ard.infant_count",
                                    "oad.created_date as blocked_date"
                                    ])
                        ->join('dm_package dmp','dmp.package_id','=','oad.r_package_id','INNER JOIN')
                        ->join('fact_booking_details fbd','fbd.r_package_id','=','oad.r_package_id','INNER JOIN')
                        ->join('air_request_details ard','ard.air_request_id','=','fbd.r_request_id','INNER JOIN')
                        ->join('dm_airport da1','ard.r_origin_airport_id','=','da1.airport_id','INNER JOIN')
                        ->join('dm_airport da2','ard.r_destination_airport_id','=','da2.airport_id','INNER JOIN')
                        ->join('passenger_via_details pvd','pvd.r_order_id','=','oad.r_order_id','INNER JOIN')
                        ->where('oad.process_type','=','HOLD');
                //Added the condition to get the hold booking based on the filter condition
                $this->_holdBookingFilter($_Ainput,$_QholdListQuery);
                $_QholdListQuery->groupby('oad.r_order_id')->orderby('oad.r_order_id','DESC');
                $_AholdListResult = $_QholdListQuery->getResult();

        //get the travel mode from the database
        $_AtravelModeData = QB::table('dm_travel_mode')->select(['travel_mode','travel_mode_code'])->getResult();
        $_AtravelMode = array_column($_AtravelModeData,'travel_mode','travel_mode_code');

        //get the travel class from the database
        $_AtravelClassData = QB::table('dm_travel_class')->select(['class_name','travel_class_id'])->Where('status','=','Y')->andWhere('r_travel_mode_id','=',1)->getResult();
        $_AtravelClass = array_column($_AtravelClassData,'class_name','travel_class_id');
        
        //get the booking status value 
        $_AstatusData = QB::table('dm_status')->select(['status_value','status_id'])->getResult();
        $_AstatusValue = array_column($_AstatusData,'status_value','status_id');

        // check the query result data is not empty based on that below loop will be executed
        if(!empty($_AholdListResult)){
           //get the hold orderid
           $_IholdListOrderid = array_column($_AholdListResult,'r_order_id');
           $_IviaflightOrderid = QB::table('passenger_via_details')
                                   ->select(["GROUP_CONCAT(r_via_flight_id) as viaFlightId",
                                             "r_order_id"])
                                   ->WhereIn('r_order_id', $_IholdListOrderid)
                                   ->groupBy('r_order_id')
                                   ->getResult();
            $_IviaflightId = array_column($_IviaflightOrderid,'viaFlightId','r_order_id');
            #get emulate email
            $whereValue = "%Emulate_%";
            $_Aemulate = QB::table('order_history oh')
                          ->select(['de.email_id','oh.order_id'])
                          ->join('dm_employee de','de.employee_id', '=', 'oh.employee_id','INNER JOIN')
                          ->where('oh.activity','LIKE',$whereValue)
                          ->andWhereIn('oh.order_id', $_IholdListOrderid) 
                          ->getResult();
            $_AemulateEmail = array_column($_Aemulate,'email_id','order_id');          
            // loop to form the data for hold pnr list
            foreach ($_AholdListResult as $key => $value) {
                // get the airline code the flight details
                $_AairlinesData = QB::table('via_flight_details')->select(['GROUP_CONCAT(airline_code) as airlineCode','GROUP_CONCAT(airline_name) as airlineName'])->join('dm_airline','airline_id','=','r_airline_id','INNER JOIN')->WhereIn('via_flight_id',explode(',',$_IviaflightId[$value['r_order_id']]))->groupBy('trip_type')->getResult();
                $_AairlineCode = array_column($_AairlinesData,'airlineCode');
                $_AairlineName = array_column($_AairlinesData,'airlineName');
                $_AholdListResult[$key]['travel_mode'] = $_AtravelMode[$value['travel_type']];
                $_AholdListResult[$key]['travel_mode_code'] = $value['travel_type'];
                $_AholdListResult[$key]['travel_class'] = $_AtravelClass[$value['cabin_class']];
                $_AholdListResult[$key]['booking_status'] = $_AstatusValue[$value['r_status_id']];
                $_AholdListResult[$key]['airline_code'] = count($_AairlineCode) > 1 ? $_AairlineCode[0]. ' / '.$_AairlineCode[1] : $_AairlineCode[0];
                $_AholdListResult[$key]['airline_name'] = count($_AairlineName) > 1 ? $_AairlineName[0]. ' / '.$_AairlineName[1] : $_AairlineName[0];
                $_AholdListResult[$key]['displayReferenceId'] = BOOKING_REFERENCE_CODE.$value['r_package_id'];
                $_AholdListResult[$key]['city_name'] = $value['sector_from'].'-'.$value['sector_to'];
                $_AholdListResult[$key]['trip_type'] = $this->_getTripType($value['trip_type']);
                $_AholdListResult[$key]['emulated_by'] = $_AemulateEmail[$value['r_order_id']];
                $_DexpiryTime = "0000-00-00 00:00:00";
                #convert utc to User timezone  based on User EmailID.
                if($value['expiry_time'] != "0000-00-00 00:00:00"){
                    $_DexpiryTime = $this->_Ocommon ->_timezoneConversion($value['expiry_time']);
                    $_DexpiryTime = date("jS F, Y | H:i",strtotime($_DexpiryTime));
                }
                $_DblockedDate = $this->_Ocommon ->_timezoneConversion($value['blocked_date']);
                $_AholdListResult[$key]['blocked_date'] = date("jS F, Y | H:i",strtotime($_DblockedDate));
                $_AholdListResult[$key]['expiry_time'] = $_DexpiryTime;
                // set the pnr for list 
                $_AholdListResult[$key]['pnr_list'] = $value['onward_pnr'];
                // check the trip type is roundtrip then we concat the onward and return pnr to list
                if($value['trip_type'] == 1 && $value['travel_type'] == 'D'){
                    $_AholdListResult[$key]['pnr_list'] = $value['onward_pnr'].' / '.$value['return_pnr'];
                }
                $_AholdListResult[$key]['holdList'] = true;
                $_AholdListResult[$key]['travellers'] = array('adult' => 0,'child' => 0, 'infant' => 0);
                if($value['adult_count'] != 0){
                    $_AholdListResult[$key]['travellers']['adult'] = (int)$value['adult_count'];
                }
                if($value['child_count'] != 0){
                    $_AholdListResult[$key]['travellers']['child'] = (int)$value['child_count'];
                }
                if($value['infant_count'] != 0){
                    $_AholdListResult[$key]['travellers']['infant'] = (int)$value['infant_count'];
                }
                if ($value['r_status_id'] == PNR_HOLD_RELEASE || $value['r_status_id'] == RELEASE_FAILURE) {
                    $_AholdListResult[$key]['actionData'] = array(array('name' => 'View','className' => 'cls-view','actionName' => 'viewHoldBooking',"iconName" => "icon-15-view"));
                }
                else{
                    $_AholdListResult[$key]['actionData'] = $_AactionDatas;
                }
            }
        }
        else{
            // if no data received from the query then we send a failure as response to display no record available
            $_AholdListResult['status'] = 'FAILURE';
        }

        return $_AholdListResult;
    }

    /**
     * Function to get the hold booking filter 
     * @author  Mohamed Ahamed VK <ahamed.vk@infinitisoftware.net>
     * @date 2024-04-18T16:14:25+05:30
     * @input : $input | array
     * @output : $response | array
     */
    private function _holdBookingFilter($_Ainput = array(),$_QqueryObj) {

        // hold pnr status from config
        $_IholdStatus = PNR_HOLD;
        // session permission set
        $permission = $_SESSION['permissions'];
        // check the input search text is set or not based on that status condition will be added
        if(!isset($_Ainput['searchText'])){
            // check the input status based on that status id will be added in query
            if($_Ainput['status'] == PNR_HOLD_RELEASE){
                $_IholdReleaseStatus = [PNR_HOLD_RELEASE,RELEASE_FAILURE];
                $_QqueryObj->andWhereIn('oad.r_status_id',$_IholdReleaseStatus);
            }
            else{
                $_QqueryObj->andWhere('oad.r_status_id','=',$_IholdStatus);
            }
        }
        else{
            // added hold and hold release status condition in overall search 
            $_IholdStatus = [PNR_HOLD,PNR_HOLD_RELEASE];
            $_QqueryObj->andWhereIn('oad.r_status_id',$_IholdStatus);

        }
        // check the input timeperiod start date and end date based on that condition will be added in query
        if (!empty($_Ainput['timePeriod']['start_date']) && !empty($_Ainput['timePeriod']['end_date'])) {
            $_startDate = $_Ainput['timePeriod']['start_date'];
            #to check date or date&time 
            $_checkDateformat = $this ->checkDateOrDateTime($_startDate);
            $_Ainput['timePeriod']['start_date'] = !empty ($_checkDateformat) ? $_checkDateformat .= ' 00:00:00': $_Ainput['timePeriod']['start_date'];
            $_endDate = $_Ainput['timePeriod']['end_date'];
            #to check date or date&time 
            $_checkDateformat = $this ->checkDateOrDateTime($_endDate);
            $_Ainput['timePeriod']['end_date'] = !empty ($_checkDateformat) ? $_checkDateformat .= ' 23:59:59': $_Ainput['timePeriod']['end_date'];
            $_QqueryObj->andWhereBetween('oad.updated_date', $_Ainput['timePeriod']['start_date'], $_Ainput['timePeriod']['end_date']);
        }

        // check the input travel type based on that condition will be added in query
        if($_Ainput['travel_type'] != 0 ){
            $_QqueryObj->andWhere('oad.r_travel_mode_id','=',$_Ainput['travel_type']);
        }

        // check the input request Id based on that condition will be added in query
        if(isset($_Ainput['requestId']) && !empty($_Ainput['requestId'])){
            if(!is_numeric($_Ainput['requestId']))
            {
                $_IpackageId = str_replace(BOOKING_REFERENCE_CODE,'',$_Ainput['requestId']);
                $_QqueryObj->andWhere('oad.r_package_id','=',$_IpackageId);
            }
            else{
                $_QqueryObj->andWhere('oad.r_package_id','=',$_Ainput['requestId']);
            }
        }

        // check the input pnr based on that condition will be added in query
        if(!empty($_Ainput['pnr']) ){
            $_QqueryObj->andWhere('oad.onward_pnr','=',$_Ainput['pnr'])->orWhere('oad.return_pnr','=',$_Ainput['pnr']);
        }

        // check the input expiry time limit based on that condition will be added in query
        if(!empty($_Ainput['expiryLimit']['start_date']) &&  !empty($_Ainput['expiryLimit']['end_date']) ){
            $_QqueryObj->andWhereBetween("expiry_ttl_time",$_Ainput['expiryLimit']['start_date']. ' 00:00:00',$_Ainput['expiryLimit']['end_date'].' 23:59:59');
        }

        // check the input airline filter based on that condition will be added in query
        if(!empty($_Ainput['airline']) ){
            $_QqueryObj->join('via_flight_details vfd','vfd.via_flight_id','=','pvd.r_via_flight_id','INNER JOIN')->andWhere('vfd.r_airline_id','=',$_Ainput['airline']);
        }

        //Condition for Data  Permission for permission type self
        if ($permission['permissionName'] == 'Self') {
            $_QqueryObj->andWhere('fbd.r_employee_id','=',$permission['r_employee_id']);
        }

        //Condition for Data  Permission for permission type other corporate
        elseif ($permission['permissionName'] == 'Other Corporate') {
            $inCondition = '';
            $_QqueryObj->andWhereIn('fbd.r_corporate_id','=',$permission['r_corporate_id']);
        }

        //Condition for Data  Permission for permission type other employee
        elseif ($permission['permissionName'] == 'Other Employee') {
            $_QqueryObj->andWhereIn('fbd.r_employee_id',$permission['r_employee_id']);
        }

        //Condition for Data  Permission for permission type Self Corporate
        elseif ($permission['permissionName'] == 'Self Corporate') {
            $_QqueryObj->andWhereIn('fbd.r_corporate_id',$permission['r_corporate_id']);
        }
    }
    /**
     * Function to check date or date&time 
     * @author Mukesh M <mukeshmoorthi@infinitisoftware.net>
     * @date 2024-05-16 20:02:14
     * @output : $date | date
     */
    function checkDateOrDateTime($date) {
        // Define regex patterns for date and datetime
        $datePattern = '/^\d{4}-\d{2}-\d{2}$/'; // Matches YYYY-MM-DD
        $dateTimePattern = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/'; // Matches YYYY-MM-DD HH:MM:SS
        if (preg_match($datePattern, $date)) {
         return $date;
         } else  {
        return false;
       }
    }    

    /**
     * Function to get the tab and filter data 
     * @author  Mohamed Ahamed VK <ahamed.vk@infinitisoftware.net>
     * @date 2024-04-22T16:14:25+05:30
     * @output : $response | array
     */
    private function _getBookingTabandFilter(){

        // get the tab and filter json setting id
        $_AjsonSetupFormatted = array_flip(array_column($this->_AjsonSetting, 'id'));

        // get the tab name and its id
        $_AbookingTab = array_column($this->_AjsonSetting, 'status', 'name');
        $_AbookingTabId = array_column($this->_AjsonSetting, 'id', 'name');

        // get the airline data to display in airline drop down
        $_AdmAirline = QB::table('dm_airline')->select(['airline_id as value','IF(airline_code = "*", airline_name, CONCAT(airline_name, " - (", airline_code, ")")) as name'])->where('status', '=', 'Y')->orderBy('airline_name','ASC')->getResult();
        // set the airline filter data to dislay the drop down value
        // $_AallAirline = array(array('name'=> 'All','value'=> 0));
        // $_AairlineDropdown = array_merge($_AallAirline,$_AdmAirline);
        $this->_AjsonSetting[$_AjsonSetupFormatted['hold']]['filter']['airline']['data'] = $_AdmAirline;

        // set the tabs and filter based on the status
        foreach($_AbookingTab as $_StabName => $_StabStatus) {
            if($_StabStatus == 'Y') {
                $_AresData['tabs'][$this->_AjsonSetting[$_AjsonSetupFormatted[$_AbookingTabId[$_StabName]]]['index_order']]['name'] = $_StabName;
                $_AresData['filters'][$this->_AjsonSetting[$_AjsonSetupFormatted[$_AbookingTabId[$_StabName]]]['index_order']] = $this->_AjsonSetting[$_AjsonSetupFormatted[$_AbookingTabId[$_StabName]]]['filter'];
            }
        }

        // form the tabs and filter array in values
        $_AresData['tabs'] = array_values($_AresData['tabs']);
        $_AresData['filters'] = array_values($_AresData['filters']);

        // return the response data
        return $_AresData;
    }

    /**
     * Function to get the Trip Type data 
     * @author  Mohamed Ahamed VK <ahamed.vk@infinitisoftware.net>
     * @date 2024-04-24T16:14:25+05:30
     * @output : $response | array
     */
    private function _getTripType($_StripType){
        $_SresTripType = '';
        switch($_StripType) {
            case 2 :
                $_SresTripType = 'Multicity';
                break;
            case 1 :
                $_SresTripType = 'Round Trip';
                break;
            case 0 :
                $_SresTripType = 'One Way';
                break;
        }
        return $_SresTripType;
    }
}

