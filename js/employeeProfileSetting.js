app.controller('employeeProfileSettingController', function($scope, $state, $timeout, serviceCall, commonValidationServices, custommessage){

    // load default values to load in employee profile setting 
    $scope.comboList = {};
    $scope.updateStatus = 'add';
    $scope.comboList.fieldId;
    $scope.objCommonValidationServices = new commonValidationServices();

    $scope.makeAjaxRequest = function(tplFileName, formValues) {
        
        var result = serviceCall.ajaxPost(tplFileName, formValues);
        
        return result.then(function(response) {
            console.log(response);
            //$state.go('/ZVhnbHhtcGxveWVlUHJvZmlsZVNldHRpbmcx');
            return response;
        });
    };
    
    var formValues = { action : 'getSettingFields' };

    var result = $scope.makeAjaxRequest('misc.employeeProfileSettingTpl', formValues);
    result.then(function(response){
        $scope.fieldList = response.result;
    });

    //toggle employee mapping add/delete values from database
    $scope.toggleEmployeeProfileMapping = function(fieldId) {

        var formValues = { action : 'checkValueExistsAndUpdate', empFieldId : fieldId };
        
        $scope.makeAjaxRequest('misc.employeeProfileSettingTpl', formValues);
    };
    
    $scope.toggleValidationType = function(fieldId, fieldLableId) {
        console.log(fieldId);
        console.log(fieldLableId);
    };
    
    $scope.toggleFieldType = function(fieldId, fieldLableId) {
        
        var currentValue = $('#'+fieldLableId).val();
        
        var formValue = {
            action       : 'updateFieldType',
            currentValue : $('#'+fieldLableId).val(),
            fildId       : fieldId
        };
        
        $scope.makeAjaxRequest();
        
    };
    
    $scope.addNewField = function()
    {
        $scope.updateStatus = 'add';
        $('#addNewField').modal('show');

        var formValues = { action : 'getMasterValues' };

        var result = $scope.makeAjaxRequest('misc.employeeProfileSettingTpl', formValues);
        result.then(function(response){
            $scope.categories = response.categories;
            $scope.fieldTypes = response.fieldTypes;
            $scope.validationTypes = response.validationTypes;
            $scope.userTypes = response.userTypes;
        });
    }
    $scope.fieldDelete = function(fieldId)
    {
        $scope.comboList.fieldId = fieldId;
        $('#showDeleteModel').modal('show');
    }
    $scope.deleteField = function(fieldId)
    {
        console.log(fieldId);
        var data = {
            action  : 'deleteFieldType',
            fieldId : fieldId
        };
        response = serviceCall.ajaxPost('misc.employeeProfileSettingTpl', data);
            response.then(function(response)
            {
                custommessage.show(1,response.success_message);
               $state.go('/ZVhnbHhtcGxveWVlUHJvZmlsZVNldHRpbmcx'); 
            });
    }
    $scope.insertNewField = function(){
        
        var selectedUser = new Array();
        $('input[name="user_type[]"]:checked').each(function() {
            selectedUser.push(this.value);
        });
        
        var data = {
            action              :  'insertNewField',
            field_name          :  $('#field_name').val(),
            field_type          :  $('#field_type').val(),
            validation_type     :  $('#validation_type').val(),
            validation_status   :  $('#validation_status').val(),
            category            :  $('#category').val(),
            user_type           :  selectedUser
        };
        
        if(!$scope.validateNewFieldValues())
        {
            return false;
        }
        
        response = serviceCall.ajaxPost('misc.employeeProfileSettingTpl', data);
        response.then(function (data) {
            if (data.status == 0)
            {
                $('#addNewField').modal('hide');
                custommessage.show(1,data.result.error_alert);
                $state.go('ZW1wRXhkN2xveWVlUHJvZmlsZVNldHRpbmcz');
            } 
            if (data.status == 1) 
            {
                custommessage.show(1,data.result.error_alert);
                $state.go('ZW1wRXhkN2xveWVlUHJvZmlsZVNldHRpbmcz');
            }
        });
        
    }
    $scope.getFieldDetails = function(fieldId)
    {
        $scope.updateStatus = 'update';
        $scope.comboList.fieldId = fieldId;
        var data = {
            action      :  'getFieldDetails',
            field_id    :  fieldId
        };        
        response = serviceCall.ajaxPost('misc.employeeProfileSettingTpl', data);
        response.then(function (response) {
            $scope.categories = response.masterData.categories;
            $scope.fieldTypes = response.masterData.fieldTypes;
            $scope.validationTypes = response.masterData.validationTypes;
            $scope.userTypes = response.masterData.userTypes;
            $scope.field_name = response.fieldData.field_label;
            $scope.previous_field_name = $scope.field_name
            $scope.field_type = response.fieldData.field_type;            
            $scope.previous_field_type = $scope.field_type;
            $scope.validation_status = response.fieldData.mandatory;
            $scope.previous_validation_status = $scope.validation_status;
            $scope.validation_type = response.fieldData.validation_type;
            $scope.previous_validation_type = $scope.validation_type;
            $scope.category = response.fieldData.category;
            $scope.previous_category = $scope.category;
            $scope.user_type = response.fieldData.r_group_id;
            $scope.previous_user_type = $scope.user_type;
            $timeout(function(){
                $("#user_type"+$scope.user_type).prop("checked", true);
                $('#field_type option[value="'+$scope.fieldTypes+'"]').attr('selected', 'selected');
                $('#validation_type option[value="'+$scope.validation_type+'"]').attr('selected', 'selected');
                $('#validation_status option[value="'+$scope.validation_status+'"]').attr('selected', 'selected');
                $('#category option[value="'+$scope.category+'"]').attr('selected', 'selected');
            },100);
            $('#addNewField').modal('show');
        });        
    }
    $scope.updateFieldData = function(fieldId)
    {console.log(fieldId);
        if(!$scope.validateNewFieldValues())
        {
            return false;
        }
        if(angular.equals($('#field_name').val().trim(), $scope.previous_field_name) &&
           angular.equals($('#field_type').val(), $scope.previous_field_type) &&
           angular.equals($('#category').val(), $scope.previous_category) &&
           angular.equals($('#validation_type').val(), $scope.previous_validation_type) &&
           angular.equals($('#validation_status').val(), $scope.previous_validation_status)){
            custommessage.show(1,'No data has been changed to update');
        }
        else
        {
            var selectedUser = new Array();
            $('input[name="user_type[]"]:checked').each(function() {
                selectedUser.push(this.value);
            });
            var data = {
                action              :  'updateFieldType',
                field_name          :  $('#field_name').val().trim(),
                field_type          :  $('#field_type').val(),
                validation_type     :  $('#validation_type').val(),
                validation_status   :  $('#validation_status').val(),
                category            :  $('#category').val(),
                user_type           :  selectedUser
            };           
            response = serviceCall.ajaxPost('misc.employeeProfileSettingTpl', data);
            response.then(function (response) {
               console.log(response); 
            });            
        }
        
    }
    $scope.toggleMandatoryType = function(fieldId, fieldLableId) {
        
    };
    
    $scope.saveEmployeeSettings = function () {

    };
    
    $scope.resetEmployeeSettings = function() {

    };
    
    $scope.validateNewFieldValues = function()
    {
        $scope.returnValue = true;

        if($('#field_name').val().trim() == "" || typeof $('#field_name').val() == 'undefined')
        {
            $scope.objCommonValidationServices.getFocus(count = '', 'field_name', 'Enter Field name');
            $scope.returnValue = false;
        }
        if($('#field_name').val().trim().length < 3)
        {
            $scope.objCommonValidationServices.getFocus(count = '', 'field_name', 'Field name above 3 characters only allowed ');
            $scope.returnValue = false;
        }
        else 
        {
            if(!$scope.getSpeCharFreeText('field_name'))
            {
                $scope.objCommonValidationServices.getFocus(count = '', 'field_name', 'Special character not allowed');
                $scope.returnValue = false;
            }
        }
        if($('#field_type').val() == '' || typeof $('#field_type').val() == 'undefined')
        {
            $scope.objCommonValidationServices.getFocus(count = '', 'field_type', 'Select field type name');
            $scope.returnValue = false;
        }
        if($('#validation_type').val() == '' || typeof $('#validation_type').val() == 'undefined')
        {
            $scope.objCommonValidationServices.getFocus(count = '', 'validation_type', 'Select validation type name');
            $scope.returnValue = false;
        }
        if($('#validation_status').val() == '' || typeof $('#validation_status').val() == 'undefined')
        {
            $scope.objCommonValidationServices.getFocus(count = '', 'validation_status', 'Select validation status');
            $scope.returnValue = false;
        }
        if($('#category').val() == '' || typeof $('#category').val() == 'undefined')
        {
            $scope.objCommonValidationServices.getFocus(count = '', 'category', 'Select field category');
            $scope.returnValue = false;
        }
        
        var selectedUser = new Array();
        $('input[name="user_type[]"]:checked').each(function() {
            selectedUser.push(this.value);
        });
        
        if(selectedUser.length<1)
        {
            $scope.objCommonValidationServices.getFocus(count = '', 'user_type', 'Select user type');
            $scope.returnValue = false;
        }
        
        return $scope.returnValue;
        
    }
    
    // not allow special characters
    $scope.getSpeCharFreeText = function(id) 
    {
        var txtString = $('#'+id).val().trim();
        length = txtString.length-1;
            if (!$scope.objCommonValidationServices.checkSpecialCharacterInInputFields(txtString,length,id,action=''))
            { 
                $scope.returnValue = false;
            }   
        return $scope.returnValue;
    }
    
});