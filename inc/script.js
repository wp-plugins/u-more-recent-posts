
var UMRP_Widget = function(t, widget_type){
	var $ = jQuery;
	var widget_id = t.id;
	var container = $(t).find('.umrp-container');
	var list, progress, nav;
	var options = {}, current_postid, auto_paginate_t;
	
	var init = function(){
		resign_elements();
		
		if( container.hasClass('single') ){
			var match = /postid-(\d+)/.exec( container.attr('class') );
			current_postid = match ? match[1] : '';
		}else{
			UMRP_Cookie.remove_cookie(widget_id);
		}
		
		nav.find('a').live('click', function(){
			var match = /umrp-page=(\d+)/.exec(this.href);
			var paged = match ? match[1] : '';
			disappear(paged);
			UMRP_Cookie.add_cookie(widget_id, paged, options.cookiepath);
			return false;
		});
		
		container.hover(
			function(){
				$(this).addClass('pause');
			},
			function(){
				$(this).removeClass('pause');
			}
		);
			
		if( widget_type=='widget' ){
			var data = { 
				action: 'umrp-ajax', 
				_ajax_nonce: umrp_vars.nonce,
				action_scope: 'get_widget_option',
				widget_id: widget_id
			}
			$.post(umrp_vars.ajaxurl, data, function(r){
				if(typeof r != 'object') return;
				options = r;
				
				if( options.auto_paginate )
					next_page();
				
			}, 'json');
		
		}else if( widget_type=='shortcode'){
			options = /<!--(.*)-->/.exec(container.html());
			options = $.parseJSON(options[1]);
		}
		
		appear();
	}
	
	var resign_elements = function(){
		list = container.find('ul');
		progress = container.find('.umrp-progress');
		nav = container.find('.umrp-nav');
		
		var list_pos = list.position();
		progress.css({top: list_pos.top});
	}
	
	var get_list = function( paged ) {
		progress.show();
		
		var data = { 
			action: 'umrp-ajax', 
			_ajax_nonce: umrp_vars.nonce,
			widget_id: widget_id,
			paged: paged,
			current_postid: current_postid ? current_postid : ''
		}
		
		if( widget_type=='widget' ){
			data.action_scope = 'the_list_for_widget';
			
		}else if( widget_type=='shortcode'){
			data.action_scope = 'the_list_for_shortcode';
			data.options = options;
		}
		
		$.post(umrp_vars.ajaxurl, data, function(html){
			container.html(html);
			resign_elements();
			appear();
		});
	}
	
	var appear = function(){
		var w = $(t).width();
		var h = list.height();
		list.css({height: h});
		
		var dur = options.appear_effect_dur ? Number(options.appear_effect_dur)*1000 : 0;
		var effect = options.appear_effect ? options.appear_effect.toLowerCase() : '';
		switch(effect){
			case 'fadein':
			list.find('li').hide().fadeIn(dur);
			break;
			
			case 'slidedown':
			list.find('li').hide().slideDown(dur);
			break;
			
			case 'slidein':
			wrap_for_slide();
			list.css({left: w}).animate({left:0}, dur);
			break;
		};
		
		if( options.auto_paginate )
			next_page();
	}
	
	var disappear = function(paged){
		var w = $(t).width();
		var h = list.height();
		list.css({height: h});
			
		var dur = options.disappear_effect_dur ? Number(options.disappear_effect_dur)*1000 : 0;
		var effect = options.disappear_effect ? options.disappear_effect.toLowerCase() : '';
		
		switch(effect){
			case 'fadeout':
			list.find('li').fadeOut(dur, function(){
				get_list(paged);
			});
			break;
			
			case 'slideup':
			list.find('li').slideUp(dur, function(){
				get_list(paged);
			});
			break;
			
			case 'slideout':
			wrap_for_slide();
			list.animate({left:-w}, dur, '', function(){
				get_list(paged);
			});
			break;
			
			default:
			list.find('li').hide();
			get_list(paged);
			break;
		}
		
	}
	
	var wrap_for_slide = function(){
		var w = $(t).width();
		var h = list.height();
		var wrap = container.find('div.umrp-slide');
		if( !wrap.length ){
			wrap = $('<div class="umrp-slide"/>').css({width: w, height: h});
			list.wrap(wrap);
		}
		return wrap;
	}
	
	var next_page = function(){
		var _nav = nav.eq(0);
		_nav.find('a').removeClass('.next');
		
		var next_a = _nav.find('.current').next('a');
		if( next_a.length==0 )
			next_a = _nav.find('a:eq(0)');
		next_a.addClass('next');
		
		if( auto_paginate_t )
			clearTimeout(auto_paginate_t);
		auto_paginate_t = setTimeout(load_next_page, Number(options.auto_paginate_delay)*1000);
	}
	
	var load_next_page = function(){
		if( container.hasClass('pause') ){
			next_page();
		}else{
			var _nav = nav.eq(0);
			_nav.find('.next').click();
		}
	}
	
	
	init();
}

var UMRP_Cookie = {

	set_cookie : function(name, value, options) {
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
	},
	
	add_cookie: function(id, val, cookiepath){
		this.set_cookie('wp-'+id+'-paged', val, {path:cookiepath ? cookiepath : '/'});
	},
	
	remove_cookie: function(id){
		this.set_cookie('wp-'+id+'-paged', null);
	}
	
}


jQuery(function(){
	jQuery('.widget_umrp').each(function(){ 
		new UMRP_Widget( this, 'widget' );
	});
	jQuery('.umrp-shortcode').each(function(){ 
		new UMRP_Widget( this, 'shortcode' );
	});
});




