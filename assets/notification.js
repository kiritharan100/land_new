  'use strict';
 

$(document).ready(function() {
   
    /*--------------------------------------
         Notifications & Dialogs
     ---------------------------------------*/
    /*
     * Notifications
     */
    function notify(type, message1){
        $.growl({
            icon: '',
            title: ' Bootstrap Growl ',
            message: message1,
            url: ''
			 
        },{
			 
			
            element: 'body',
            type: type,
            allow_dismiss: true,
            placement: {
                from: 'top',
                align: "right"
            },
            offset: {
                x: 30,
                y: 30
            },
            spacing: 10,
            z_index: 999999,
            delay: 8500,
            timer: 5000,
            url_target: '_blank',
            mouse_over: false,
            animate: {
                enter: 'animated fadeInDown',
                exit: 'animated fadeOutUp'
            },
            icon_type: 'class',
            template: '<div data-growl="container" class="alert" role="alert">' +
            '<button type="button" class="close" data-growl="dismiss">' +
            '<span aria-hidden="true">&times;</span>' +
            '<span class="sr-only">Close</span>' +
            '</button>' +
            '<span data-growl="icon"></span>' +
            '<span data-growl="title"></span>' +
            '<span data-growl="message"></span>' +
            '<a href="#" data-growl="url"></a>' +
            '</div>'
        });
    };

    //$('.notifications > .btn').on('click',function(e){
      //  e.preventDefault();
      //  var nType = "info";
	//	var message1 = "asasa";
       // notify(nType,message1);
    //});

});

