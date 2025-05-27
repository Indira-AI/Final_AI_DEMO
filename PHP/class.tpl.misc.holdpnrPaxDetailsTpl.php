<?php

use \QB\queryBulider as QB;

class holdpnrPaxDetailsTpl
{   
    public $_IinputData;
    public $_AserviceResponse;
    public function __construct(){
        
    }
	
	/**
	 * get Display info
     * @description  get pax details
	 * @method      _getDisplayInfo
	 * @Author_name MUKESH M 
	 * @datetime    2024-05-22 11:09:29
	 * @return      void
	 */
	
    public function _getDisplayInfo() {
        # Assing the Input
        $_Ainput = array();
        $_Ainput = $this->_IinputData;
        #Return the Response
        $this->_AserviceResponse['data'] = $this->_getPaxInfo($_Ainput);     
    }

    /*
    * @Description This method used to get the Pax Information
    * @param array|$input
    * @return string|$response
    * @date|2024-05-22 11:09:52
    * @author| MOHAMED AHAMED V.K
    * @modified| MUKESH M
    */
    public function _getPaxInfo($_Ainput) {

        $sql = QB::table('passenger_details pd') 
            ->select(["pd.title,pd.first_name,pd.last_name,concat(pd.phone_code ,' ', pd.mobile_no) as mobile_no,if(pd.passenger_type='CNN','CHD',pd.passenger_type) as passenger_type_name"])
            ->where('pd.r_order_id', '=', $_Ainput['id'])
            ->orderBy('pd.passenger_id')
            ->getResult();   
        // to assign table header in users list
        $tableHeaders = json_decode('
        [
        {
        "header": "Title",
        "sortField": "title",
        "customization_id": ""
        },
        {
        "header": "First Name",
        "sortField": "first_name",
        "customization_id": ""
        },
        {
        "header": "Last Name",
        "sortField": "last_name",
        "customization_id": ""
        },
        {
        "header": "Passenger Type",
        "sortField": "passenger_type_name",
        "customization_id": ""
        },
        {
        "header": "Mobile Number",
        "sortField": "mobile_no",
        "customization_id": ""
        }
        ]',true);
        
        $finalResponse['data'] =$sql;
        $finalResponse['tableHeader'] = $tableHeaders;
        
        
        $_Aresponse = array(
            'status' => true,
            'data' => $finalResponse
        );
        return $_Aresponse;
    }
}
