
var UMoreRecentPostsWidget = function(t){
	var $ = jQuery;
	var t = $(t);
	var widget_id = t.attr('id');
	var container = t.find('.umrp-container');
	var content = t.find('.umrp-content');
	var nav = t.find('.umrp-nav');
	var loader = t.find('.umrp-loader').hide();
	var options = {};
	
	var init = function(){
		container.css({height: container.height()});
		
		nav.find('a').live('click', function(){
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
			paged: paged ? paged : ''
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
			}
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
	
	init();
}


	
	

jQuery(function(){
	jQuery('.widget_umrp').each(function(){ 
		new UMoreRecentPostsWidget( this );
	});
});






















