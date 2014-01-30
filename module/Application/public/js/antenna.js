/**
 * @author Bruno Spyckerelle
 */

var antenna = function(url){
	   
		//if true, switch the button to its previous state
		var back = true;
	
		//if true, show modal on switch change
		var modal = true;
		
	   $('.antenna-switch').on('switch-change', function(e, data){
		   $('a#end-antenna-href').attr('href', $(this).data('href')+"&state="+data.value);
		   $('#antenna_name').html($(this).data('antenna'));
		   $("#cancel-antenna").data('antenna', $(this).data('antennaid')) ;
		   if(!data.value){
			   $("#confirm-end-event .modal-body").html("<p>Voulez-vous vraiment créer un nouvel évènement antenne ?</p>"+
						"<p>L'heure actuelle sera utilisée comme heure de début.</p>");
		   } else {
			   $("#confirm-end-event .modal-body").html( "<p>Voulez-vous vraiment terminer l'évènement antenne en cours ?</p>"+
				"<p>L'heure actuelle sera utilisée comme heure de fin.</p>");
		   }
		   if(modal){
			   $("#confirm-end-event").modal('show');
		   }
	   });
	
	   $("#confirm-end-event").on('hide', function(){
		 if(back){
			 modal = false;
			 $('#switch_'+$("#cancel-antenna").data('antenna')).bootstrapSwitch('toggleState');
			 modal = true;
		 }
	   });
	   
	   $("#end-antenna-href").on('click', function(event){
		   event.preventDefault();
		   back = false;
		   $("#confirm-end-event").modal('hide');
		   $.post($("#end-antenna-href").attr('href'), function(data){
			   displayMessages(data);
			   back = true;
			   var switchbtn = $('#switch_'+$("#cancel-antenna").data('antenna'));
			   if(data['error']){
				   //dans le doute, on remet le bouton à son état antérieur
				   modal = false;
				   switchbtn.bootstrapSwitch('toggleState');
				   modal = true;
			   } else {
				   //mise à jour des fréquences
				   if(switchbtn.bootstrapSwitch('status')){
					   $('.antenna-color.antenna-'+$('#cancel-antenna').data('antenna')).removeClass('background-status-fail');
					   $('.antenna-color.antenna-'+$('#cancel-antenna').data('antenna')).addClass('background-status-ok');
				   } else {
					   $('.antenna-color.antenna-'+$('#cancel-antenna').data('antenna')).removeClass('background-status-ok');
					   $('.antenna-color.antenna-'+$('#cancel-antenna').data('antenna')).addClass('background-status-fail');
				   }
			   }
		   }, 'json');
	   });
	   
	   $("#antennas tr").hover(
			   //in
			   function(){
				   $('.antenna-'+$(this).data('id')).closest('.sector').addClass('background-status-test');
			   },
			   //out
			   function(){
				   $('.antenna-'+$(this).data('id')).closest('.sector').removeClass('background-status-test'); 
			   });
	   
	   //refresh page every 30s
	   (function doPoll(){
		   $.post(url+'frequencies/getantennastate')
	   			.done(function(data) {
	   				$.each(data, function(key, value){
	   					$('#switch_'+key).bootstrapSwitch('setState', value, true);
	   					if(value){
	   						$('.antenna-color.antenna-'+key).removeClass('background-status-fail');
	   						$('.antenna-color.antenna-'+key).addClass('background-status-ok');
	   					} else {
	   						$('.antenna-color.antenna-'+key).removeClass('background-status-ok');
	   						$('.antenna-color.antenna-'+key).addClass('background-status-fail');
	   					}
	   				});
	   			})
	   			.always(function() { setTimeout(doPoll, 30000);});
		   
		   $.post(url+'frequencies/getfrequenciesstate')
  			.done(function(data) {
  				$.each(data, function(key, value){
  					if(value){
  						$('.sector-color.frequency-'+key).removeClass('background-status-fail');
  						$('.sector-color.frequency-'+key).addClass('background-status-ok');
  					} else {
  						$('.sector-color.frequency-'+key).removeClass('background-status-ok');
  						$('.sector-color.frequency-'+key).addClass('background-status-fail');
  					}
  				});
  			})
  			.always(function() { setTimeout(doPoll, 30000);});
	   })();
};
