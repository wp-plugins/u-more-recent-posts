
var UMoreRecentPostsWidget = function(t){
	var $ = jQuery;
	var t = $(t);
	var widget_id = t.attr('id');
	var container = t.find('.umrp-container');
	var content = t.find('.umrp-content');
	var list = t.find('.umrp-content ul:eq(0)');
	var nav = t.find('.umrp-nav');
	var loader = t.find('.umrp-loader').hide();
	var options = {};
	var current_postid = '';
	
	var init = function(){
		container.css({height: container.height()});
		
		if( container.hasClass('single') ){
			var current_postid_tmp = /postid-(\d+)/.exec( container.attr('class') );
			if( current_postid_tmp ) 
				current_postid = current_postid_tmp[1];
		}else{
			set_cookie('wp-'+widget_id+'-paged', null);
		}
		
		nav.find('a').live('click', function(){
			var paged = Number($(this).text());
			get_list( paged );
			set_cookie('wp-'+widget_id+'-paged', paged, {path:options.cookiepath ? options.cookiepath : '/'});
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
			if( options.loader_label ) 
				loader.text(options.loader_label);
			var loader_opts = {};
			if( options.loader_symbol ) 
				loader_opts.char = options.loader_symbol;
			if( options.loader_direction=='left' ) 
				loader_opts.char_direction = options.loader_direction;
			ajax_loader.init( loader_opts );
		}, 'json');
	}
	
	var get_list = function( paged ) {
		content.empty();
		if( ajax_loader.enabled ) ajax_loader.play();
		var data = { 
			action: 'umrp-ajax', 
			_ajax_nonce: umrp_settings.nonce,
			scope: 'get_list',
			widget_id: widget_id,
			paged: paged ? paged : '',
			current_postid: current_postid
		}
		$.post(umrp_settings.ajax_url, data, function(r){
			if( ajax_loader.enabled ) ajax_loader.stop();
			content.html(r);
			container
			.css({height: 'auto'})
			.css({height: container.height()});
			switch(options.effect){
				case 'fadein':
				content.find('li').hide().fadeIn('fast');
				break;
				case 'slidedown':
				content.find('li').hide().slideDown('fast');
				break;
			};
		});
	}
	
	var ajax_loader = {
		defaults: {
			interval: 150, 
			char: '.', 
			char_len: 3, 
			char_direction: 'right'
		},
		opts: {},
		default_str: '',
		max_len: 0,
		interval_id: null,
		enabled: false,
		
		init: function(opts){
			this.opts = $.extend(this.defaults, opts);
			this.default_str = loader.text();
			this.max_len = this.default_str.length + this.opts.char.length*this.opts.char_len;
			this.enabled = true;
		},
		
		play: function(){
			this.stop();
			if( this.opts.char_len>0 && this.opts.char.length>0 ){
				this.interval_id = window.setInterval(function(){
					var str = loader.text();
					loader.text( str.length < ajax_loader.max_len ? ajax_loader.opts.char_direction=='left' ? ajax_loader.opts.char+str : str+ajax_loader.opts.char : ajax_loader.default_str );
				}, this.opts.interval);
			}
			loader.show();
		},
		
		stop: function(){
			if(this.interval_id) 
				window.clearInterval(this.interval_id);
			loader.text(this.default_str).hide();
		}
	}
	
	var set_cookie = function(name, value, options) {
		// Copyright (c) 2006 Klaus Hartl (stilbuero.de)
		options = options || {};
		if (value === null) {
			value = '';
			options.expires = -1;
		}
		var expires = '';
		if (options.expires && (typeof options.expires == 'number' || options.expires.toUTCString)) {
			var date;
			if (typeof options.expires == 'number') {
				date = new Date();
				date.setTime(date.getTime() + (options.expires * 24 * 60 * 60 * 1000));
			} else {
				date = options.expires;
			}
			expires = '; expires=' + date.toUTCString(); 
		}
		var path = options.path ? '; path=' + (options.path) : '';
		var domain = options.domain ? '; domain=' + (options.domain) : '';
		var secure = options.secure ? '; secure' : '';
		document.cookie = [name, '=', encodeURIComponent(value), expires, path, domain, secure].join('');
	}
	
	init();
}


	
	

jQuery(function(){
	jQuery('.widget_umrp').each(function(){ 
		new UMoreRecentPostsWidget( this );
	});
});






















