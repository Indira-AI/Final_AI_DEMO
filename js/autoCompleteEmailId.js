app.directive('autoCompleteEmailId', function ($timeout,serviceCall) {
    return {
        restrict: 'A',
        require: '?ngModel',
        link: function (scope, elem, attr, ctrl) {

            scope.getEmpEmailId = function () {
                var emailInfo = [];
                if(scope.approverEmailIdInfo.length !== 0)
                {
                    angular.forEach(scope.approverEmailIdInfo, function(value, key) 
                    {  
                        emailInfo.push(value.email_id);
                    });     
                }
                else
                {
                    emailInfo.push({email_id:'No search found'})
                }
                 return emailInfo; 
            }
            
            $timeout(function () {
                elem.autocomplete({
                    source: typeof scope.approverEmailIdInfo != 'undefined' ? scope.getEmpEmailId(): [], //from your service
                    minLength: 3,
                    select: function (event, ui) {  
                       
                        angular.forEach(scope.approverEmailIdInfo, function(value, key) 
                        {   
                            if(ui.item.value === value.email_id)
                            {
                                ui.item.id = value.employee_id;
                            }
                        }); 
                        if(scope.delegationApprover == 'CA')
                        {
                           scope.current_approver = ui.item.id;
                        }
                        else if(scope.delegationApprover == 'DA')
                        {
                           scope.delegation_approver = ui.item.id;
                        }
                        else
                        {
                            scope.approver_email_id = ui.item.id;
                        }
                    },
                    response: function (event, ui) {
                        // ui.content is the array that's about to be sent to the response callback.
                        if (ui.content.length === 0) {
                            ui.content[0] = {};
                            ui.content[0].label = 'No search found';
                            ui.content[0].value = '';
                            elem.text("No results found");
                        }
                    }
                }).autocomplete('widget').addClass('custom-autocomplete').hide();
            }, 500);
        }
    };
});
