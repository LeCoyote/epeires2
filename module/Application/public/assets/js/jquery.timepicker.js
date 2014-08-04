/**
 * JQuery Plugin to transform a simple datetime input into a functional datetime picker
 * Requires jquery-ui-datepicker
 * Licence : AGPL
 * @author Bruno Spyckerelle
 */

(function($) {

    $.fn.timepickerform = function(options) {

        var dayplusone = function(input, delta){
            if(input.val()){
                var daystring = input.val().split('-');
                var day = new Date(daystring[2], daystring[1]-1, daystring[0], 12);
                var newday = new Date();
                newday.setDate(day.getDate() + delta);
                var newdaystring = newday.getUTCDate()+'-'+(newday.getUTCMonth()+1)+'-'+newday.getUTCFullYear();
            } else {
                var d = new Date();
                newdaystring = d.getUTCDate()+'-'+(d.getUTCMonth()+1)+'-'+d.getUTCFullYear();
            }
            return newdaystring;
        };

        var hourplusone = function(input, delta) {
            if (input.val()) {
                var hour = parseInt(input.val()) + delta;
                if (hour > 0 && hour <= 9)
                    hour = "0" + hour;
                if (hour <= 0)
                    hour = 23;
                if (hour > 23)
                    hour = "00";
            } else {
                var d = new Date();
                hour = d.getUTCHours();
                if (hour >= 0 && hour <= 9) {
                    hour = "0" + hour;
                }
            }
            return hour;
        };

        var minuteplusone = function(input, delta) {
            if (input.val()) {
                var minutes = parseInt(input.val()) + delta;
                if (minutes > 0 && minutes <= 9)
                    minutes = "0" + minutes;
                if (minutes <= 0)
                    minutes = 59;
                if (minutes > 59)
                    minutes = "00";
            } else {
                var d = new Date();
                minutes = d.getUTCMinutes();
                if (minutes >= 0 && minutes <= 9) {
                    minutes = "0" + minutes;
                }
            }
            return minutes;
        };

        var updateFakeForm = function(fakediv) {
            var day = "";
            if (!fakediv.find('.day input').val()) {
                var d = new Date();
                day = d.getUTCDate() + "-" + (d.getUTCMonth() + 1) + "-" + d.getUTCFullYear();
                fakediv.find('.day input').val(day);
            }
            if (!fakediv.find('.hour input').val()) {
                var hour = "00";
                if ($(this).attr('end')) {
                    hour = $("#start .hour input").val();
                } else {
                    var d = new Date();
                    hour = d.getUTCHours();
                    if (hour >= 0 && hour <= 9) {
                        hour = "0" + hour;
                    }
                }
                fakediv.find('.hour input').val(hour);
            }
            if (!fakediv.find('.minute input').val()) {
                var minutes = "00";
                if ($(this).attr('end')) {
                    minutes = $("#start .minute input").val();
                } else {
                    var d = new Date();
                    minutes = d.getUTCMinutes();
                    if (minutes >= 0 && minutes <= 9) {
                        minutes = "0" + minutes;
                    }
                }
                fakediv.find('.minute input').val(minutes);
            }
        };

        var createFakeForm = function() {
            var div = $("<div class=\"timepicker-form\" id=" + parameters.id + "></div>");
            var table = $("<table></table>");
            table.append('<tbody>' +
                    '<tr>' +
                    '<td class="day">' +
                    "<div class=\"input-prepend\">" +
                    "<span class=\"add-on\"><i class=\"icon-calendar\"></i></span>" +
                    '<input type="text" class="date" ' + (parameters.required ? 'required="required"' : '') + '></input>' +
                    "</div>" +
                    '</td>' +
                    '<td class="hour">' +
                    '<a class="next" href="#"><i class="icon-chevron-up"></i></a><br>' +
                    '<input type="text" class="input-mini"><br>' +
                    '<a class="previous" href="#"><i class="icon-chevron-down"></i></a>' +
                    '</td>' +
                    '<td class="separator">:</td>' +
                    '<td class="minute">' +
                    '<a class="next" href="#"><i class="icon-chevron-up"></i></a><br>' +
                    '<input type="text" class="input-mini"><br>' +
                    '<a class="previous" href="#"><i class="icon-chevron-down"></i></a>' +
                    '</td>' +
                    '<td>'+
                    (parameters.clearable ? '<a href="#" class="clear-time"><i class="icon-remove"></i></a>' : '') +
                    '</td>' +
                    '</tr>' +
                    '</tbody>');
            div.append(table);
            return div;
        };

        var defaults = {
            'id': '',
            'required': false,
            'clearable': false,
            'init': false
        };

        var parameters = $.extend(defaults, options);

        return this.each(function() {
            var element = $(this);
            if (element.is('input[type=datetime]')) {
                //change the type of the field to hidden
                element.prop('type', 'hidden');
                //add the div containing timepicker fake form
                var div = createFakeForm();
                element.parent().append(div);

                //add datepicker
                div.on('focus', 'input[type=text].date', function() {
                    $(this).datepicker({
                        dateFormat: "dd-mm-yy",
                    });
                });

                //init fields if original field contains a date
                // else init with current date
                var value = element.val();
                if(value){
                    var daysplit = value.split(' ');
                    var hoursplit = daysplit[1].split(':');
                    div.find('.day input').val(daysplit[0]);
                    div.find('.hour input').val(hoursplit[0]);
                    div.find('.minute input').val(hoursplit[1]);
                } else if(parameters.init){
                    var d = new Date();
                    $(".day input").val(d.getUTCDate()+"-"+(d.getUTCMonth()+1)+"-"+d.getUTCFullYear());
                    var hour = ""+d.getUTCHours();
                    if(d.getUTCHours() >= 0 && d.getUTCHours() <= 9){
			hour = "0"+d.getUTCHours();
                    }
                    $(".hour input").val(hour);
                    var minute = ""+d.getUTCMinutes();
                    if(d.getUTCMinutes()>=0 && d.getUTCMinutes()<=9){
			minute = "0"+d.getUTCMinutes();
                    }
                    $(".minute input").val(minute);
                    element.val(div.find('.day input').val() + " " + div.find('.hour input').val() + ":" + div.find('.minute input').val());
                }

                //subscribe to events

                //Change on a fake input -> update other inputs then update hidden input
                div.on('change', 'input', function() {
                    //mise à jour du champ caché
                    // 1 : remplissage des autres champs si besoin
                    updateFakeForm(div);
                    //2: mise à jour du champ caché en fonction
                    element.val(div.find('.day input').val() + " " + div.find('.hour input').val() + ":" + div.find('.minute input').val());
                    //3: on prévient les autres qu'il y a eu un changement
                    element.trigger('change');
                });

                div.on('click', '.hour .next', function(event) {
                    event.preventDefault();
                    var input = $(this).closest('td').find('input');
                    input.val(hourplusone(input, 1));
                    input.trigger('change');
                });

                div.on('click', '.minute .next', function(event) {
                    event.preventDefault();
                    var input = $(this).closest('td').find('input');
                    input.val(minuteplusone(input, 1));
                    input.trigger('change');
                });

                div.on('click', '.hour .previous', function(event) {
                    event.preventDefault();
                    var input = $(this).closest('td').find('input');
                    input.val(hourplusone(input, -1));
                    input.trigger('change');
                });

                div.on('click', '.minute .previous', function(event) {
                    event.preventDefault();
                    var input = $(this).closest('td').find('input');
                    input.val(minuteplusone(input, -1));
                    input.trigger('change');
                });

                div.on('mousewheel', 'td.hour input', function(event, delta) {
                    event.preventDefault();
                    $(this).val(hourplusone($(this), delta));
                    $(this).trigger('change');
                });

                div.on('mousewheel', 'td.minute input', function(event, delta) {
                    event.preventDefault();
                    $(this).val(minuteplusone($(this), delta));
                    $(this).trigger('change');
                });
                
                div.on('mousewheel', 'td.day input', function(event, delta){
                   event.preventDefault(); 
                   $(this).val(dayplusone($(this), delta));
                   $(this).trigger('change');
                });
                
                div.on('click', 'a.clear-time', function(e){
                    e.preventDefault();
                    element.val('');
                    var form = $(this).closest('.timepicker-form');
                    form.find('td input').val('');
                    element.trigger('change');
                });
                
                //change input if more than 2 digits are entered in the hour input
                var charCount = 0;
                
                div.on('focus', 'td.hour input', function(event){
                    event.preventDefault();
                    this.charCount = $(this).val().length;
                });
                
                div.on('keyup', 'td.hour input', function(event){
                    var currentCharCount = $(this).val().length;
                    if(currentCharCount === 2 && this.charCount === 1){
                        $(this).closest('.timepicker-form').find('td.minute input').focus().select();
                    }
                    this.charCount = currentCharCount;
                });
            }
        });
    };
})(jQuery);




