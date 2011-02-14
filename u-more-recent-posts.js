;(function($) {	

$.fn.UMoreRecentPostsWidget = function(){ 
	var matches = this.attr('id').match(/^umrp-(\d*)$/);
	if( ! matches ) return;
	
	var widget_id = matches[1];
	var $local = this;
	var $container = this.find('.umrp-container');
	var $content = this.find('.umrp-content');
	var $status = this.find('.umrp-loader');
	var options = {};
	
	function get_option(){
		var data = { 
			action: 'umrp-ajax', 
			_ajax_nonce: umrp_settings.nonce,
			scope: 'get_option',
			widget_id: widget_id
		}
		$.post(umrp_settings.ajax_url, data, init, 'json');
	}
	
	function get_list( paged ) {
		$content.empty();
		$status.play();
		
		var data = { 
			action: 'umrp-ajax', 
			_ajax_nonce: umrp_settings.nonce,
			scope: 'get_list',
			paged: paged ? paged : ''
		}
		data = $.extend(options, data);
		
		$.post(umrp_settings.ajax_url, data, display);
	}
	
	function init(r){
		if(typeof r != 'object') return;
		options = r;
		
		if( r.loader_label ) $status.text(r.loader_label);
		var status_opts = {};
		if( r.loader_symbol ) status_opts.char = r.loader_symbol;
		if( r.loader_direction=='left' ) status_opts.char_direction = r.loader_direction;
		$status.ajax_status( status_opts );
		
		get_list();
	}
	
	function display(r){
		$status.stop();
		$content
		.html(r)
		.find('.umrp-nav a').click( function(){
			get_list( $(this).text() );
			return false;
		});
		
		$container
		.css({height: 'auto'})
		.css({height: $container.height()});
		
		switch(options.effect){
			case 'fadein':
			$content.find('li').hide().fadeIn('fast');
			break;
			case 'slidedown':
			$content.find('li').hide().slideDown('fast');
			break;
		}
	}
	
	get_option();
}

$.fn.ajax_status = function(opts){
	var defaults = {interval:150, char:'.', char_len:3, char_direction:'right'};
	var opts = $.extend(defaults, opts);
	
	var $local = this;
	var default_str;
	var max_len;
	var id;
	
	this.play = function(){
		if( opts.char_len>0 && opts.char.length>0 ){
			id = window.setInterval(function(){
				var str = $local.text();
				var txt = str.length < max_len ? opts.char_direction=='left' ? opts.char+str : str+opts.char : default_str;
				$local.text( txt );
			}, opts.interval);
		}
		return $local.show();
	}
	
	this.stop = function(){
		if(id) window.clearInterval(id);
		return $local.text(default_str).hide();
	}
	
	this.reset_text = function(str){
		default_str = str;
		max_len = default_str.length + opts.char.length*opts.char_len;
	}
	
	this.reset_text(this.text());
	this.hide();
	return this;
}

$(function(){
	$('.widget_umrp').each(function(){ 
		$(this).UMoreRecentPostsWidget();
	});
});

})(jQuery);





