;(function($) {	

$.fn.UMoreRecentPostsWidget = function(){ 
	var widget_id = this.attr('id');
	var $container = this.find('.umrp-container');
	var $content = this.find('.umrp-content');
	var $nav = this.find('.umrp-nav');
	var $status = this.find('.umrp-loader');
	var options = {};
	
	function init(){
		$container.css({height: $container.height()});
		$nav.find('a').live('click', function(){
			get_list( $(this).text() );
			return false;
		});
		
		var data = { 
			action: 'umrp-ajax', 
			_ajax_nonce: umrp_settings.nonce,
			scope: 'get_option',
			widget_id: widget_id
		}
		$.post(umrp_settings.ajax_url, data, function(r){
			if(typeof r != 'object') return;
			options = r;
			if( options.loader_label ) $status.text(options.loader_label);
			var status_opts = {};
			if( options.loader_symbol ) status_opts.char = options.loader_symbol;
			if( options.loader_direction=='left' ) status_opts.char_direction = options.loader_direction;
			$status.umrp_ajax_status( status_opts );
		}, 'json');
	}
	
	function get_list( paged ) {
		$content.empty();
		if( $status.enabled ) $status.play();
		var data = { 
			action: 'umrp-ajax', 
			_ajax_nonce: umrp_settings.nonce,
			scope: 'get_list',
			widget_id: widget_id,
			paged: paged ? paged : ''
		}
		$.post(umrp_settings.ajax_url, data, function(r){
			if( $status.enabled ) $status.stop();
			$content.html(r);
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
		});
	}
	
	init();
	return this;
}

$.fn.umrp_ajax_status = function(opts){
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
	this.enabled = true;
	return this;
}

$(function(){
	$('.widget_umrp').each(function(){ 
		$(this).UMoreRecentPostsWidget();
	});
});

})(jQuery);





