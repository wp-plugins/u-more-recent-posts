;(function($) {	

$.fn.UMoreRecentPostsWidget = function(){ 
	var matches = this.attr('id').match(/^umrp-(\d*)$/);
	if( ! matches ) return;
	var $content = this.find('.umrp-container .umrp-content');
	var $status = this.find('.umrp-status');
	var ajax_url = umrp_settings.ajax_url;
	var defaults = { 
		action: 'umrp-ajax', 
		_ajax_nonce: umrp_settings.nonce,
		id: matches[1]
	}
	var options = {};
	
	function get_option(){
		var data = $.extend(defaults, { action_type:'get_option' });
		$.post(ajax_url, data, function(r) { 
			options = r;
			get_list();
		}, 'json');
	}
	
	function get_list( paged ) {
		$status.hide().fadeIn();
		$content.css({visibility: 'hidden'});
		var data = $.extend(defaults, { action_type:'get_list', paged: paged ? paged : ''});
		data = $.extend(options, data);
		$.post(ajax_url, data, function(r) { 
			$status.hide();
			$content
			.html(r)
			.css({height: 'auto'})
			.css({height: $content.height(), visibility: 'visible'})
			.find('.umrp-nav a').click( function(){
				get_list( $(this).text() );
				return false;
			});
			switch(options.effect){
				case 'fadein':
				$content.find('li').hide().fadeIn('fast');
				break;
				case 'slidedown':
				$content.find('li').hide().slideDown('fast');
				break;
			}
		});
	}
	
	get_option();
}

$(function(){
	$('.widget_umrp').each(function(){
		$(this).UMoreRecentPostsWidget();
	});
});

})(jQuery);





