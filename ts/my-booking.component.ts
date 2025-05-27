import { Component, OnInit, Input, EventEmitter, Output, SimpleChanges } from '@angular/core';
import { Router } from '@angular/router';
import { urlConfig } from 'projects/cockpit/src/app/core-module/config/url';
import { FormBuilder, FormGroup } from '@angular/forms';
import { CommonService } from '../../../../core-module/services/common.service';
import { config } from 'projects/cockpit/src/app/core-module/config/app';
import { environment } from 'projects/cockpit/src/environments/environment';
declare var $: any;
import { IDataTypeConfig } from 'projects/cockpit/src/app/core-module/interfaces/dataType.interface';
import { MyBookingModuleService } from '../../my-booking-module.service';
/**
 * Des:component info
 * Author:Abarna,Venkat,Ajay
 */
@Component({
  selector: 'app-my-booking',
  templateUrl: './my-booking.component.html',
  styleUrls: ['./my-booking.component.scss']
})
export class MyBookingComponent implements OnInit {
  public filterRequestData: Array<any>;
  /**
   * @referenceId reference Id For my booking list Global search Value.
   */
  @Input() public referenceId:any='';
  @Input() public searchData: string = ''
  /**
   * input of Tab index
   */
  @Input() public tabIndexStatus: number = 0;
  @Input() public tabData:any;
   /**
   * input of Tab index
   */
   @Input() public resetStatus: boolean = true;
  /**
   * input of mybookingist
   */
  @Input() public bookingData: Array<any>;
  @Input() public tableHeader: Array<any>;
  /**
   * Desc : invalid input alert
   */
  public alertMessage: any = config;
  /**
   * Status Loader
   * */
  public statusloader: boolean = false;
  /**
   * Desc:Expanding the list
   */
  @Input() public expandAll: boolean = false;
  /**
   * Desc: Hold release emit
   */
  @Output() public holdRelease: EventEmitter<object> = new EventEmitter();

  constructor(private router: Router, private fb: FormBuilder, private commonService: CommonService, private bookingService: MyBookingModuleService) { }
  /***
   * des: Suucess Information
   */
  public successinfo: string = 'initail';
  /**
   * Rating REsponse 
   */
  public ratingResponse: any;
  /**
   * Des: download Voucher status
   */
  public hitstatus: boolean = false;
  /**
   * Des:User Rating Array
  **/

  public userRating: FormGroup = new FormGroup({});
  /**
   * Des:Corporate Type
   *   * @param commonService 
   */
  public corporate: string = '';
  /****************Hotel Category Array *********/
  public packageCategory: Array<any> = [
    { id: 1, class: '' },
    { id: 2, class: '' },
    { id: 3, class: '' },
    { id: 4, class: '' },
    { id: 5, class: '' }
  ];

  public loadData: any;

  /**
   * package category
   */
  public hotelStar: number = 1;
  /**
   * Request Id
   */
  public requestId: number = 0;
  /**
   * Coupon applied content
   */
  public couponShow: boolean;
  /**
* Validation Status
*/
  public itineraryData: IDataTypeConfig = {};
  public packageInfo: IDataTypeConfig = {};
  public validate: number = 0;
  travelData: object
  /**
   * Des: Common Alert input
   * @param 
   */
  public alert: IDataTypeConfig = {};
  /**
   * Desc : Hold option alert popup content
   */
  public holdData : Array<any> = [
    {
      "id": "Confirm",
      "title": "Confirmation",
      "content": "Are you sure you want to confirm the booking?",
      "cancel": "Close",
      "success": "Yes, confirm"
    },
    {
      "id": "Release",
      "title": "Release",
      "content": "Are you sure you want to release the booking?",
      "cancel": "Close",
      "success": "Yes, proceed",
      "holdAlertContent": {
        remarks: {
          "placeholder": 'Enter remarks',
          "release": true,
          "maxlength": 250,
          "errorMsg": 'Enter valid remarks'
        }
      }
    }
  ];
    /**
   * Desc : Show toast flag
   */
    public toastInfo: IDataTypeConfig = {};
  /**
   * 
   * function for category
   */
  public fncategory(i: number): void {
    var element = document.getElementById('cls-star' + i);
    if (document.getElementById('cls-star' + i).classList.contains('active')) {
      this.userRating.controls.user_rating.setValue(i);
      // $(element).removeClass('active')
      $(element).nextAll().removeClass('active');
    }
    else {
      $(element).addClass('active');
      $(element).prevAll().addClass('active');
      this.userRating.controls.user_rating.setValue(i);
    }
  }
  /**
   * Desc : rating stars hover functions
   * @param i ,action
   */
  public hotelStartRating(i: number, action: string): void {
    action === 'hover' ? $('#cls-star' + i).prevAll().addClass('cls-selected') : $('#cls-star' + i).siblings().removeClass('cls-selected');
  }
  /** 
    *corporateName 
    */
  public productName = config.productConfig.productName;
  /**
   * component initialization
   */

  public ngOnInit(): void {
    /*
    * Des: Corporate Info
    */
    //  console.log(this.bookingData,'bgd')
    this.corporate = environment.corporateName;
    this.filterRequestData = this.bookingData;
    this.userRating = this.fb.group({
      user_rating: ['0'],
      user_description: ['']
    });
  }
  // public ngDoCheck(): void {
  //   (this.userRating.value.user_description == '') ? $('#submit').prop('disabled','true') : $("#submit"). removeAttr("disabled");
  // }

  /****
   *Des: User ratting Booking id Getting
   */
  public requestedId(requestId: number) {
    this.requestId = requestId;
    this.successinfo = 'initail';
    this.validate = 1;
  }
  /*****
   * Value Send throw service file
   */
  public rattingProceed() {
    if (this.userRating.controls.user_rating.value != '0') {
      this.ratingResponse = [];
      const Formdata: object = {
        data: {
          "booking_id": this.requestId,
          "user_rating": this.userRating.value.user_rating,
          "user_description": this.userRating.value.user_description
        },
        actionName: 'userRating'
      };
      this.statusloader = true;
      this.commonService.callWebService(urlConfig.BACKEND_ROUTES.COMMONSERVICE, Formdata).subscribe(getBackendData => {
        this.ratingResponse = getBackendData.response.data;
        this.statusloader = false;
        if (getBackendData.response.data.status == true)
          this.successinfo = 'success';
        this.userRating.reset();
      });
    }
    else {
      this.validate = 1;
    }
  }
  public myBookingLodaer:any=[1,2,3,4,5,6,7,8,9];
  /**
* Function for Error message
*/
  public get contact(): any {
    return this.userRating.controls;
  }
  /**
    * Des:pagination data for list view
    */
  // tslint:disable-next-line: no-any
  public loadDataValue(listData: any): void {
    if(listData) this.loadData = listData;
    console.log('rxlk loaddata',this.loadData);
  }
  /**
   * Des:view detailed view action
   */
  public viewBookingData(bookingId: number, travelModeCode: string, paymentStatus: boolean): void {
    console.log(bookingId, travelModeCode, paymentStatus);
    this.couponShow = true;
    $('#try' + bookingId).addClass('cls-retry');
    const searchVal: any = {
      data: bookingId, travelModeCode, paymentStatus
    };
    if (travelModeCode != 'I' && travelModeCode != 'D') {
      const data = {

        "data": {
          "reference_id": bookingId,
          "type": "cart",
          "status": false,
          "fromPage": 'myBooking'
        },
        "actionName": "MyBookingsDisplay"
      }
      this.router.navigate(['./' + urlConfig.FRONTEND_ROUTES.noAuth + '/' + urlConfig.FRONTEND_ROUTES.cartItinerary], { state: { CartItinearyReqData: data, tabIndex: this.tabIndexStatus } });
    }
    else {
      searchVal.viewType = "itineraryDisplays";
      this.router.navigate(['./' + urlConfig.FRONTEND_ROUTES.noAuth + '/' + urlConfig.FRONTEND_ROUTES.viewItinerary], {
        state: {
          orderId: bookingId,
          actionName: 'viewItinerary',
          couponShow: this.couponShow,
          // actionName: 'cancelticket',
          prevFilter: '',
          prevLabel: '',
          tabVal: 0,
          appType: { appName: '' },
          referenceId:this.referenceId
        }
      });
    }

  }
  public expandAllData(): void {
    this.expandAll = !this.expandAll
  }
  public ngOnChanges(changes:SimpleChanges) {
    console.log('zv bookingData rh',this.bookingData,'tableHeader',this.tableHeader);
    if (this.bookingData && changes['bookingData']) {
      this.loadDataValue(this.bookingData);
      // this.expandAll = true;
      // console.log(this.bookingData)
    }
  }

  /**
   * List sorting
   * @param id value
   * @param index of object
   */
  public previousSort:string;
  public sort(id: string, index: string) {
    let value = $("#" + id).attr('data-sort');
    let sortVal = this.bookingData;
    if(this.previousSort && this.previousSort != id ){
      $("#" + this.previousSort).attr('data-sort', 'asc');
      $("#" + this.previousSort).removeClass('rotate');
    }
    if (value == 'asc') {
      $("#" + id).addClass('rotate');
      if (index == 'city_name' || 'travel_mode' || 'booking_status')
        sortVal.sort((a: any, b: any) => a[index] > b[index] ? -1 : b[index] > a[index] ? 1 : 0);
      if (index == 'travel_start_date' || 'expiry_time')
        sortVal.sort();
      if (index == 'displayReferenceId' || 'pnr_list')
        sortVal.sort((a: any, b: any) => b[index] - a[index]);
      if(index == "total_fare")
        sortVal.sort((a: any, b: any) => parseFloat(b[index].replace(/,/g, "")) - parseFloat(a[index].replace(/,/g, "")));
    }
    if (value == 'des') {
      $("#" + id).removeClass('rotate');
      if (index == 'city_name' || 'travel_mode' || 'booking_status')
        sortVal.sort((a: any, b: any) => b[index] > a[index] ? -1 : a[index] > b[index] ? 1 : 0);
      if (index == 'displayReferenceId' || 'pnr_list')
        sortVal.sort((a: any, b: any) => a[index] - b[index]);
      if (index == 'travel_start_date' || 'expiry_time')
        sortVal.sort();
      if(index == "total_fare")
        sortVal.sort((a: any, b: any) => parseFloat(a[index].replace(/,/g, "")) - parseFloat(b[index].replace(/,/g, "")));
    }
    sortVal = sortVal.slice(0, this.loadData.length);
    this.loadData = sortVal;
    if (value == 'asc')
      $("#" + id).attr('data-sort', 'des');
    if (value == 'des')
      $("#" + id).attr('data-sort', 'asc');
    // $('.page1').addClass('active');
    // $('.page1').siblings().removeClass('active');
    this.previousSort = id;
  }
  /**
   * Des:retry payment
   */
  public retryPayment(bookingId: any, travelModeData: any) {
    console.log(bookingId, travelModeData)
    $('#try' + bookingId).addClass('cls-retry');
    if (travelModeData.travel_mode_code !== 'D' && travelModeData.travel_mode_code !== 'I') {
      console.log('if');
      this.commonService.setLocalStorage(new Boolean(true), 'retryBooking');
      const data:any = {
        "data": {
          "reference_id": bookingId,
          "type": "cart", //travelModeData.travel_mode_code === 'H' ? "retryPayment" : "cart",
          "retryPayment": travelModeData.retry_payment_status ? true : false,
          "status": false
        },
        "actionName": "MyBookingsDisplay"
      }
      // this.commonService.setLocalStorage(data, 'retryCartViewData');
      this.commonService.saveDataToSsessiontorage(JSON.stringify(data),'retryCartViewData');
      // localStorage.setItem(data,'retryPaymentOption');
      this.commonService.saveDataToStorage(JSON.stringify(data),'retryCartViewData');
      this.router.navigate(['./' + urlConfig.FRONTEND_ROUTES.noAuth + '/' + urlConfig.FRONTEND_ROUTES.cartView], { state: { retryCartViewData: data } });
    }
    //Retry payment action
    else {
      console.log('else');
      const data = {
        "data": {
          "reference_id": bookingId,
          "type": "retryPayment",
          "status": false
        },
        "actionName": "MyBookingsDisplay"
      };
      console.log(JSON.stringify(data),"req")
      this.commonService.callWebService(urlConfig.BACKEND_ROUTES.COMMONSERVICE, data).subscribe(ResData => {
        console.log(ResData, "resp")
        this.itineraryData = ResData.response;
        this.itineraryData.retrypayment = true;
        this.itineraryData.packageId = bookingId;
        const data: IDataTypeConfig = {
          data: {
            userRequest: this.itineraryData.userRequest,
            selectFlights: this.commonService.btoaColorcode(JSON.parse(JSON.stringify(this.itineraryData.selectFlights))),
            currencyType: this.itineraryData.selectFlights[0].currency_type,
            retryFlow: this.itineraryData.retrypayment
          },
          actionName: 'getCurrentFare'
        };
        console.log(data);
        this.commonService.callWebService(urlConfig.BACKEND_ROUTES.getCurrentFare, data)
          .subscribe((currentFare: any) => {
            console.log(currentFare,'currentFare');
            $('#try' + bookingId).removeClass('cls-retry');
            if (currentFare.response.status === false && currentFare.response.show_alert === true) {
              this.alert.content = currentFare.response.status_message;
              this.alert.display = true;
              this.alert.title = '';
              this.alert.actions = [
                {
                  'label': 'Ok',
                  'action': false,
                  'data': 'getCurrentFare'
                }
              ];
            } else {
              // Single page optimization retry payment data binding starts
              currentFare.response.status_message.routingFlow = true;
              let retryFlightData = {
                searchData: currentFare?.response?.status_message,
                packageInfo: this.packageInfo,
                reqData: currentFare?.response?.status_message?.userRequest,
                routingFlow: true,
                paxInfo: this.itineraryData
              };
              let contactData = this.itineraryData?.paxInfo?.contactInfo[0];
              contactData.country_code_id = contactData?.country_code;
              let passengerInfo = {
                mainData : this.itineraryData?.paxInfo?.passengerInfo,
                DOB : currentFare?.response?.status_message?.userRequest?.DOBMandatory
              }
              let gstFormData = this.itineraryData?.paxInfo?.agencyGstInfo;
              console.log(passengerInfo,contactData,retryFlightData,'retryFlight confirm response data');
              this.commonService.saveDataToSsessiontorage(JSON.stringify(passengerInfo),'flgrtrvlrdettempstrg');
              this.commonService.saveDataToSsessiontorage(JSON.stringify(contactData),'flgrcontdettempstrg');
              this.commonService.saveDataToSsessiontorage(JSON.stringify(gstFormData),'gstFormData');
              this.commonService.saveDataToSsessiontorage(JSON.stringify(retryFlightData),'flightData');
              // Single page optimization retry payment data binding ends
              this.packageInfo = currentFare.response.status_message.packageInfo.incentive;
              console.log(this.packageInfo);
              // this.packageInfo = ResData.response.packageInfo.post_ssr_amnt;
              // this.commonService.setLocalStorage({ itineraryData: this.itineraryData, packageInfo: this.packageInfo }, 'FSSR');
              this.commonService.setLocalStorage(ResData?.response?.agentMarkupFee,'MARK');
              this.commonService.saveDataToSsessiontorage(JSON.stringify({ itineraryData: this.itineraryData, packageInfo: this.packageInfo }),'FSSR');
              // this.router.navigate(
              //   [
              //     './' +
              //     urlConfig.FRONTEND_ROUTES.noAuth +
              //     '/' +
              //     urlConfig.FRONTEND_ROUTES.makepayment
              //   ],
              //   {
              //     state: {
              //       itineraryData: this.itineraryData,
              //       packageInfo: this.packageInfo
              //     }
              //   }
              // );
              this.router.navigate(['./' + urlConfig.FRONTEND_ROUTES.noAuth +  '/' + urlConfig.FRONTEND_ROUTES.flightItineary],{state: { itineraryData: this.itineraryData,packageInfo: this.packageInfo}});
            }
          });


      });

    }
  }
  public downloadInvoice(id: number , element:string) {
    // console.log(id)
    $('#'+ element + id).removeClass('active');
    $('#dwn' + id).addClass('active');
    this.hitstatus = true;
    let voucherReqData = {
      data: {
        requestId: id
      },
      actionName: "retailVoucherGeneration"
    }

    this.commonService.callWebService(urlConfig.BACKEND_ROUTES.COMMONSERVICE, voucherReqData).subscribe(voucherResData => {
      // console.log(voucherResData,"voucherResData");
      // this.hitstatus = false;
      $('#'+ element + id).addClass('active');
      $('#dwn' + id).removeClass('active');
      window.open(
        voucherResData.response.fileDownloadPath
      );
    });
  }
  public ngDoCheck(): void {
    if (this.alertMessage.errorMessage != '') {
      this.statusloader = false;
    }
  }

  /**
   * Des: Download Invoice
  *
  */
  public downloadWalletInvoice(id: number,element:string) {
    $('#'+ element + id).removeClass('active');
    $('#dwn' + id).addClass('active');
    let invoiceReqData = {
      data: {
        serviceName: "GETWALLETINVOICE",
        wallet_id: id
      },
      actionName: "WalletServiceAction"
    }
    this.commonService.callWebService(urlConfig.BACKEND_ROUTES.walletService, invoiceReqData).subscribe(invoiceReqData => {
      $('#'+ element + id).addClass('active');
      $('#dwn' + id).removeClass('active');
      window.open(
        invoiceReqData.response.data.walletInvoice.response
      );
    });
  }

  /**
   * Des: Number and string validation
  *
  */
  keyPress(event: any, type: string) {
    this.commonService.inpVal(event, type);
  }
  
  /**
   * Desc alert
   */
  public alertInfo(data: IDataTypeConfig): object {
    console.log(data,'alertInfo data');
    this.alert = {
      title: 'Note',
      display: false,
      type: 'alert',
      actions: [
        {
          label: 'Ok',
          action: true
        }
      ]
    };
    if (data.data.data != undefined) {
      if (data.data.data == "getCurrentFare") {

      }
    }
    // Air Hold Pnr Confirm and Release Flow
    if(data?.method == 'hold' && data?.userAction == true){
      if(data?.holdStatus == 'Confirm'){
        let reqData: object = {
          data: {
            reference_id: data?.action?.r_package_id
          },
          actionName: 'confirmHoldedPnrActions',
        };
        console.log(reqData,'Confirm Hold Request data');
        this.commonService.callWebService(urlConfig.BACKEND_ROUTES.COMMONSERVICE, reqData).subscribe(confirmData => {
          console.log(confirmData,'Confirm Hold Response data');
          if(confirmData?.response?.show_alert){
            this.showToast(false, confirmData?.response?.status_message,data?.method);
          }
          else {
            this.itineraryData = confirmData?.response?.bookingDetails;
            this.itineraryData.payType = 'holdConfirm';
            this.itineraryData.routingFlow = true;
            this.itineraryData.packageId = confirmData?.response?.status_message?.packageId;
            this.packageInfo = confirmData?.response?.bookingDetails?.packageInfo?.incentive;
            this.commonService.setLocalStorage(confirmData?.response?.bookingDetails?.agentMarkupFee,'MARK');
            // Single page optimization hold confirm flow data binding starts
            let holdFlightData = {
              searchData: this.itineraryData,
              packageInfo: this.packageInfo,
              reqData: this.itineraryData?.userRequest,
              payType: this.itineraryData.payType,
              packageId: this.itineraryData.packageId,
              routingFlow: true
            };
            let contactData = this.itineraryData?.paxInfo?.contactInfo[0];
            contactData.country_code_id = contactData?.country_code;
            let passengerInfo = {
              mainData : this.itineraryData?.paxInfo?.passengerInfo,
              DOB : this.itineraryData?.userRequest?.DOBMandatory
            }
            let gstFormData = this.itineraryData?.paxInfo?.agencyGstInfo;
            console.log(passengerInfo,contactData,holdFlightData,'holdFlight confirm response data');
            this.commonService.saveDataToSsessiontorage(JSON.stringify(passengerInfo),'flgrtrvlrdettempstrg');
            this.commonService.saveDataToSsessiontorage(JSON.stringify(contactData),'flgrcontdettempstrg');
            this.commonService.saveDataToSsessiontorage(JSON.stringify(gstFormData),'gstFormData');
            this.commonService.saveDataToSsessiontorage(JSON.stringify(holdFlightData),'flightData');
            // Single page optimization hold confirm flow data binding ends
            // this.commonService.setLocalStorage({ itineraryData: this.itineraryData, packageInfo: this.packageInfo }, 'FSSR');
            this.commonService.saveDataToSsessiontorage(JSON.stringify({ itineraryData: this.itineraryData, packageInfo: this.packageInfo, fareRuleData: this.itineraryData?.fareRule }), 'FSSR');
            // this.router.navigate(['./' + urlConfig.FRONTEND_ROUTES.noAuth +  '/' + urlConfig.FRONTEND_ROUTES.makepayment],{state: { itineraryData: this.itineraryData,packageInfo: this.packageInfo}});
            this.router.navigate(['./' + urlConfig.FRONTEND_ROUTES.noAuth +  '/' + urlConfig.FRONTEND_ROUTES.flightItineary],{state: { itineraryData: this.itineraryData,packageInfo: this.packageInfo}});
          }
        });
      }
      else if(data?.holdStatus == 'Release'){
        let reqData: object = {
          data: {
            "reference_id": data?.action?.r_package_id,
            "releasedReason" : data?.holdRemarks
          },
          "actionName": "holdPnrActions"
        };
        console.log(reqData,'Release Hold Request',data);
        this.commonService.callWebService(urlConfig.BACKEND_ROUTES.COMMONSERVICE, reqData).subscribe(releaseData => {
          console.log(releaseData,'Release Hold Response');
          if(releaseData?.response?.data?.status === 'SUCCESS'){
            let holdRelease = {
              holdReleaseData: releaseData,
              tabIndexStatus: this.tabIndexStatus,
              holdReleasemsg: releaseData?.response?.data?.message
            }
            this.holdRelease.emit(holdRelease);
          }
          else {
            this.showToast(false, releaseData?.response?.data?.message,'hold');
          }
        })
      }
    }
    return data;
  }

  /**
   * Desc : Toast for Air Hold Pnr Release flow
   */
  public showToast(status: boolean, message: string, mode: string= ''): void {
    this.toastInfo.status = status;
    this.toastInfo.status_message = message;
    this.toastInfo.timer = mode == 'hold' ? true : false;
    this.toastInfo.mode = mode;
    setTimeout(() => {
      this.toastInfo.status_message = '';
    },mode === 'hold' ? 8000 : 6000);
  }
    /**
   * Desc : Hide toast for close
   */
  public hidetoast(event:boolean){
    event == true?this.toastInfo.status_message = '':'';
  }

  selectedPageNo(page_no: number): void {
    this.bookingService.setPageNumber(page_no);
  }

  // Booking list travellers details display
  public openPaxDetails(paxData: any){
    console.log(paxData,'open pax details data');
    if(paxData?.holdList){ 
      this.alert = {
        method: 'travellersPax',
        display: true,
        paxDetailsData: {
          "title": "Passengers detail(s)",
          "paxData": '',
          "paxId": paxData
        }
      };
    }
  }

  // Hold Option Function
  public holdOption(id: string,listData: any){
    let value = (id == 'Confirm') ? this.holdData[0] : (id == 'Release') ? this.holdData[1] : null;
    console.log(value,id,'hold option',listData);
    if(id !== 'View'){
      this.alert = {
        title: value?.title,
        method: 'hold',
        display: true,
        pnr: listData?.pnr_list,
        content: value?.content,
        holdStatus: id,
        data: listData,
        holdAlertContent: value?.holdAlertContent,
        actions: [
          {
            label: value?.cancel,
            action: false
          },
          {
            label: value?.success,
            action: true
          }
        ]
      };
    }
    else {
      this.viewBookingData(listData?.request_id,listData.travel_mode_code,false);
    }
  }

  /**
   * Add separator before capital letters and replace underscore
   */
  public replaceSeparator(str:string,separator:string) {
    return str
      .replace(/_/g, separator)                           
      .replace(/([a-z])([A-Z])/g, '$1'+ separator +'$2')  
      ?.toLowerCase()
  }
}
