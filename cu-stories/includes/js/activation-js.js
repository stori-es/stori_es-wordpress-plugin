var IntervalID=setInterval(function(){cuTimer()},1000);
var seconds = 1;

function cuTimer() {
	document.getElementById("div-timer").innerHTML = seconds;
	if(seconds == 5){
		document.getElementById("div-timer").innerHTML = " redirecting ... ";
		window.clearInterval(IntervalID);
		jQuery(window).attr("location",php_vars.custory_activation_link);
	}
    seconds += 1;
}
