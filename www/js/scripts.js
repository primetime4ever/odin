/*
   Odin - IP plan management and tracker
   Copyright (C) 2015-2016  Tobias Eliasson <arnestig@gmail.com>
                            Jonas Berglund <jonas.jberglund@gmail.com>
                            Martin Rydin <martin.rydin@gmail.com>

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License along
   with this program; if not, write to the Free Software Foundation, Inc.,
   51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

*/

$(document).ready(function() {

    $('.history').popover({
        trigger: 'click',
        placement: 'right',
        title: 'Log',
        container: 'body',
        html: true,
        content: function() {
            var theLog = '<p>No history for this ip yet</p>';
            var host = this.id.substr(3);
            var heyhey = 'alhd';
            $.ajax({
                url: 'overview_handler.php',
                type: 'GET',
                dataType: 'text',
                data: 'host=' + host,
                //very bad with async false....
                // TODO: rewrite with callback
                async: false,
                success: function( response ) {
                    theLog = response;
                    //console.log(theLog);
                }
            });
            //console.log(theLog);
            return theLog;
        }   
    });


    $('td.check-lease-opt').on('click', 'input:checkbox', function() {
        var ip = this.id.substr(8);
        if ($(this).is(':checked')) {
            $('p[id="ciEmpty"]').remove();
            $('#leaseBasket').append('<p id="ci' + ip + '"">' + ip + '</p>');
            $('input[id="leasesActionBtn"]').show(300);
        } else {
            $('p[id="ci' + ip + '"]').remove();
        }
        if ( $('#leaseBasket').children().length < 1 ) {
            $('#leaseBasket').append('<p id="ciEmpty">Nothing selected</p>');
            $('input[id="leasesActionBtn"]').hide(300);
        }
    });

    // overview.php
    $('td.check-reserve').on('click', 'input:checkbox', function() {
        var $element = $(this);
        var ip = this.value;
        var action = $(this).is(':checked');
        $.ajax({
            type: 'POST',
            dataType: 'json',
            data: {
                ip: ip,
                action: action
            },
            url: 'overview_handler.php',
            success : function(data){
                var ips = data.ipList;
                if (action && !data.opStatus) {
                    alert("Another user reserved this host. The host might be available in a few minutes again if the user don't book the address.");
                    location.reload(true);
                    $element.prop('checked', false);
                } else if (action && data.opStatus) {
                    var basketHtml = '';
                    for (i = 0; i < ips.length; i++) {
                        $('a.bookAddrBtn').show();
                        basketHtml += '<p id="bi' + ips[i] + '" class="cart-item">' + ips[i] + '<span id="rm' + ips[i] + '" class="glyphicon glyphicon-remove cart-remove pull-right"></span><p>';
                    }
                    $('div#choosenAddr').html(basketHtml);
                } else if (!action && data.opStatus) {
                    $('p[id="bi' + ip + '"]').remove();
                    if (ips.length < 1) {
                        $('a.bookAddrBtn').hide();
                        $('div#choosenAddr').html('<p class="text-center">EMPTY</p>');
                    }
                }
            },
            error : function(XMLHttpRequest, textStatus, errorThrown) {
                alert('Your request was not handled properly. Please try again.');
            }
        });
    });

    $('div#choosenAddr').on('click', '.cart-remove', function() {
        var ip = $(this).prop('id').substr(2);
        var action = 'false';
        $.ajax({
            type: 'POST',
            dataType: 'json',
            data: {
                ip: ip,
                action: action
            },
            url: 'overview_handler.php',
            success : function(data){
                console.log(data);
                var ips = data.ipList;
                // TODO: compare recieved data to value sent to ensure proper removal
                for (j = 0; j < ips.length; j++) {
                    console.log('ipList[' + j + ']: ' + ips[j]);
                }
                var checkbox = 'input[id="cb' + ip + '"]';
                var basketItem = 'p[id="bi' + ip + '"]';
                $(checkbox).prop('checked', false);
                $(basketItem).remove();
                if (ips.length < 1) {
                    $('a.bookAddrBtn').hide();
                    $('div#choosenAddr').html('<p class="text-center">EMPTY</p>');
                }
            },
            error : function(XMLHttpRequest, textStatus, errorThrown) {
                alert('Your request was not handled properly. Please try again.');
            }
        });
        console.log('#bi' +ip);
    });

    
    $(".rm-lease").click(function(event) {
        if( !confirm('Are you sure that you want to terminate the lease?') ) {
            event.preventDefault();
        }
    });

    $(".book-address-container").on('click', '.book-address-remove', function() {
        var ip = this.id.substr(5);
        $.ajax({
            type: 'POST',
            dataType: 'json',
            data: {
                ip: ip,
                action: 'false'
            },
            url: 'overview_handler.php',
            success : function(data){
                var target = 'div[id="book' + ip + '"]';
                $(target).hide('slow', function(){ 
                    $(target).remove(); 
                });
                if ( $(document).find('.book-address-container').length == 1 ) {
                    var getUrl = window.location;
                    var baseUrl = getUrl .protocol + "//" + getUrl.host + "/";
                    console.log(baseUrl);
                    window.location.replace(baseUrl + 'overview.php');
                }
            },
            error : function(XMLHttpRequest, textStatus, errorThrown) {
                alert('Your request was not handled properly. Please try again.');
            }
        });
        
    });

    // Fix for autofocus in modals
    $('.modal').on('shown.bs.modal', function() {
        $(this).find('[autofocus]').focus();
    });

    // UserIPS
    $(document).on("click", ".open-EditHostDialog", function () {

        var hostIp = $(this).data('hostip');
        var hostName = $(this).data('hostname');
        var hostDescription = $(this).data('hostdescription');

        $(".form-group #userHostIp").val( hostIp );
        $(".form-group #userHostIp2").val( hostIp );
        $(".form-group #userHostName").val( hostName );
        $(".form-group #userHostDescription").val( hostDescription );

    });

    // Manage Users
    $(document).on("click", ".open-EditUserDialog", function () {

        var userId = $(this).data('userid');
        var userName = $(this).data('username');
        var firstName = $(this).data('firstname');
        var lastName = $(this).data('lastname');
        var email = $(this).data('email');
        var privileges = $(this).data('privileges');

        $(".form-group #userId").val( userId );
        $(".form-group #userName").val( userName );
        $(".form-group #firstName").val( firstName );
        $(".form-group #lastName").val( lastName );
        $(".form-group #email").val( email );
        $(".form-group #privileges").val( privileges ).change();

    });    

    $(document).on("click", ".open-RemoveUserDialog", function () {
        
        var userId = $(this).data('userid');
        var userName = $(this).data('username');
        var firstName = $(this).data('firstname');
        var lastName = $(this).data('lastname');
        var email = $(this).data('email');

        $(".form-group #userId").val( userId );
        $(".form-group #userName").val( userName );
        $(".form-group #firstName").val( firstName );
        $(".form-group #lastName").val( lastName );
        $(".form-group #email").val( email );
    });

    // Manage Networks
    $(document).on("click", ".open-EditNetworkDialog", function () {
        
        var networkId = $(this).data('networkid');
        var networkBase = $(this).data('networkbase');
        var networkCidr = $(this).data('networkcidr');
        var networkDescription = $(this).data('networkdescription');

        $(".form-group #networkId").val( networkId );
        $(".form-group #networkBase").val( networkBase );
        $(".form-group #networkBase2").val( networkBase );
        $(".form-group #networkCidr").val( networkCidr );
        $(".form-group #networkCidr2").val( networkCidr );
        $(".form-group #networkDescription").val( networkDescription );
    });

    $(document).on("click", ".open-RemoveNetworkDialog", function () {
        
        var networkId = $(this).data('networkid');
        var networkBase = $(this).data('networkbase');
        var networkCidr = $(this).data('networkcidr');
        var networkDescription = $(this).data('networkdescription');

        $(".form-group #networkId").val( networkId );
        $(".form-group #networkBase").val( networkBase );
        $(".form-group #networkCidr").val( networkCidr );
        $(".form-group #networkDescription").val( networkDescription );
    });
    

    //Submit page number when hitting enter
    $('.result-page-field').keydown(function(event) {
        if (event.keyCode == 13) {
            this.form.submit();
            return false;
         }
    });

    $('.accordion-toggle').click(function() {

        console.log($('td#' + this.id + ' i'));
        console.log($('td#' + this.id + '>i').hasClass('glyphicon-triangle-right'));

        if ($(this.id + ' i').hasClass('glyphicon-triangle-right')) {
            return console.log('yis - glyphicon-triangle-right');
        }

    });
});
