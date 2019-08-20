// card box jquery

jQuery(document).ready(function(){
    VestaCardBox();
});
jQuery(window).resize(function(){
    VestaCardBox();
});

function VestaCardBox(){
    var cardBox = jQuery('#Card_bound').width();
if(cardBox < 450){
jQuery('body').addClass('vesta_inputFull');
}else{
    jQuery('body').removeClass('vesta_inputFull');
}
}