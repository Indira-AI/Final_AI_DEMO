// auto complete for current approver.
app.directive('autoCompletePassengerEmail', function ($timeout,serviceCall) {
    return {
        restrict: 'A',
        require: '?ngModel',
        link: function (scope, elem, attr, ctrl) { 
            var passEml = [];
            $timeout(function () {
                elem.autocomplete({ 
                    source: function (request, response) {
                        var data2 = {reportName : 'passengerEmails',text:$('#passEmail').val()};
                        finalresponse = serviceCall.ajaxPost('misc.reports', data2, 'harinim');
                        finalresponse.then(function (res) {
                            
                            if(res !== null && res.length !== 0) {
                                passEml = res;
                                response(passEml);
                            }else{
                                passEml = [];
                                response(passEml);
                            }
                        });
                    }, //from your service
                    minLength: 3,
                    select: function (event, ui) {
                        
                        angular.forEach(passEml, function(value, key) 
                        {
                            if(ui.item.email_id === value.email_id)
                            {
                                $timeout(function () {
                                    $('#passEmail').val(value.email_id);
                                }, 300);
                            }
                        }); 
                    },
                    response: function (event, ui) {
                        // ui.content is the array that's about to be sent to the response callback.
                        if (ui.content.length === 0) {
                            ui.content[0] = {};
                            ui.content[0].email_id = 'No search found';
                            ui.content[0].value = '';
                            elem.text("No results found");
                        }
                    }
                }).autocomplete('instance')._renderItem = function (ul, item) {
                    $(ul).addClass('custom-autocomplete').css({'height': '200px','overflow-y':'scroll'});

                    return $("<li>")
                        .append("<li>" + item.email_id +"</li>")
                        .appendTo(ul);
                };
            }, 500);
        }
    };
});
