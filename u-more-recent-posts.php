<?php
/* 
Plugin Name: U More Recent Posts
Plugin URI: http://urlless.com/u-more-recent-posts/
Description: This plugin make it possible to navigate more recent posts without refreshing screen.
Version: 1.4.1
Author: Taehan Lee
Author URI: http://urlless.com
*/ 

class UMoreRecentPosts {

var $id = 'umrp';
var $ver = '1.4.1';
var $url;

function UMoreRecentPosts(){
	$this->url = plugin_dir_url(__FILE__);
	
	register_activation_hook( __FILE__, array(&$this, 'activation') );
	
	load_plugin_textdomain($this->id, false, dirname(plugin_basename(__FILE__)).'/languages/');
	
	add_action( 'init', array(&$this, 'init') ); 
	add_action( 'widgets_init', array(&$this, 'widgets_init') ); 
	add_action( 'wp_ajax_'.$this->id.'-ajax', array(&$this, 'ajax') );
	add_action( 'wp_ajax_nopriv_'.$this->id.'-ajax', array(&$this, 'ajax') );
	add_shortcode( 'u_more_recent_posts', array(&$this, 'shortcode_display'));
}

function activation() {
	global $wp_version;
	if (version_compare($wp_version, "3.1", "<")) 
		wp_die("This plugin requires WordPress version 3.1 or higher.");
}

function init() { 
	if ( ! is_admin() ) {
		wp_enqueue_script( 'jquery' ); 
		wp_enqueue_style( $this->id.'-style', $this->url.'inc/style.css', '', $this->ver);
		wp_enqueue_script( $this->id.'-script', $this->url.'inc/script.js', array('jquery'), $this->ver);
		wp_localize_script( $this->id.'-script', $this->id.'_vars', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ), 
			'nonce' => wp_create_nonce( $this->id.'_nonce' ),
		));
	}else{
		global $pagenow;
		if( $pagenow=='widgets.php' )
			wp_enqueue_style( $this->id.'-admin-style', $this->url.'inc/admin.css', '', $this->ver);
	}
}

function widgets_init() { 
	register_widget( 'UMoreRecentPostsWidget' ); 
}

function ajax() {
	check_ajax_referer( $this->id.'_nonce' );
	
	switch( $_POST['action_scope'] ):
		
		case 'get_widget_option':
		$opts = $this->get_widget_option( $_POST['widget_id'] );
		$opts['cookiepath'] = COOKIEPATH;
		echo json_encode( $opts );
		break;
		
		case 'the_list_for_widget':
		$args = array(
			'widget_id' => $_POST['widget_id'], 
			'paged' => $_POST['paged'],
			'current_postid' => $_POST['current_postid'], 
		);
		$this->the_list_for_widget( $args );
		break;
		
		case 'the_list_for_shortcode':
		$args = array(
			'widget_id' => $_POST['widget_id'], 
			'paged' => $_POST['paged'],
			'current_postid' => $_POST['current_postid'], 
			'options' => (array) $_POST['options'],
		);
		$this->the_list_for_shortcode( $args );
		break;
		
	endswitch;	
	die();
}

function get_widget_option($widget_id){
	$all_opts = get_option('widget_'.$this->id);
	$index = (int) preg_replace('/'.$this->id.'-/', '', $widget_id);
	$opts = $all_opts[$index];
	return $opts;
}

function the_list_for_widget( $args ){
	if( empty($args['widget_id']) )
		return false;
	$args['options'] = $this->get_widget_option( $args['widget_id'] );
	$args['cookie_key'] = 'wp-'.$args['widget_id'].'-paged';
	echo $this->get_the_list($args);
}

function the_list_for_shortcode( $args ){
	if( empty($args['widget_id']) )
		return false;
	$args['cookie_key'] = 'wp-'.$args['widget_id'].'-paged';
	echo $this->get_the_list( $args );
}

function get_the_list( $args ){
	$default_args = array(
		'options' => array(),
		'paged' => '', 
		'current_postid' => '', 
		'cookie_key' => '',
	);
	$args = wp_parse_args($args, $default_args);
	extract($args);
	
	$default_options = $this->get_default_options();
	$opts = wp_parse_args($options, $default_options);
	
	$paged = ( empty($paged) AND is_single() AND !empty($_COOKIE[$cookie_key]) ) ? $_COOKIE[$cookie_key] : $paged;
	$paged = max(1, absint($paged));
	
	$query_args = array(
		'posts_per_page' => $opts['number'],
		'paged' => $paged, 
		'post_status' => 'publish', 
		'ignore_sticky_posts' => true,
	);
	
	if( !empty($opts['post_type']) ) {
		$query_args['post_type'] = $opts['post_type'];
		
		if( !empty($opts['tax_query']) AND isset($opts['tax_query'][$opts['post_type']]) ) {
			$tax_query = $opts['tax_query'][$opts['post_type']];
			
			if( !empty($tax_query['taxonomy']) AND !empty($tax_query['terms']) ){
				$tax_query['terms'] = explode(',', preg_replace('/\s*/', '', $tax_query['terms']));
				$_tax_query = array(
					'taxonomy' => $tax_query['taxonomy'],
					'terms' => $tax_query['terms'],
					'field' => 'id',
				);
				$operator = '';
				switch($tax_query['operate']){
					case 'include': $operator = 'IN'; break;
					case 'exclude': $operator = 'NOT IN'; break;
					case 'and': $operator = 'AND'; break;
				}
				if( !empty($operator) )
					$_tax_query['operator'] = $operator;
				
				$query_args['tax_query'] = array($_tax_query);
			}
		}
	}
	
	$authors = preg_replace('/\s*/', '', $opts['authors']);
	$author_operate = $opts['author_operate'];
	$author_operate = ($author_operate=='include'||$author_operate =='exclude') ? $author_operate : '';
	if( !empty($authors) AND !empty($author_operate) ){
		if( $author_operate == 'exclude' ){
			$authors = explode(',', $authors);
			$ex_authors = array();
			foreach($authors as $author)
				$ex_authors[] = '-'.$author;
			$authors = join(',', $ex_authors);
		}
		$query_args['author'] = $authors;
	}
	
	if( absint($opts['time_limit'])>0 ){
		set_query_var('umrp_time_limit', $opts['time_limit']);
		add_filter( 'posts_where', array(&$this, 'filter_where') );
	}
	
	$query_args = apply_filters('umrp_query_parameters', $query_args);
	
	$q = new WP_Query($query_args);
	if ( !$q->have_posts() )
		return false;
		
	$ret = '<ul class="umrp-list">';
	while($q->have_posts()): $q->the_post();
		$post_id = get_the_ID();
		
		if( '' == $title = get_the_title() )
			$title = $post_id;
		
		$title_attr = esc_attr($title);
		
		if( $word_limit = absint($opts['length']) ){
			$words = explode(' ', $title);
			if(count($words) > $word_limit) {
				array_splice($words, $word_limit);
				$title = implode(' ', $words) . '&hellip;';
			}
		}
		
		// deprecated but support by next upgrade
		if( !empty($opts['show_comment_count']) AND $comment_count ) 
			$title .= ' ('.$comment_count.')';
		
		$comment_count = $q->post->comment_count;
		$date = date($opts['date_format'], strtotime($q->post->post_date));
		$author = get_the_author();
		
		$list_format = nl2br($opts['list_format']);
		$list_format = str_ireplace('%title%', $title, $list_format);
		$list_format = str_ireplace('%comment_count%', $comment_count, $list_format);
		$list_format = str_ireplace('%date%', $date, $list_format);
		$list_format = str_ireplace('%author%', $author, $list_format);
		if( preg_match('/%thumbnail%/', $list_format) ){
			$thumbnail = $this->get_post_thumbnail($post_id, $opts['thumbnail_w'], $opts['thumbnail_h']);
			$list_format = str_ireplace('%thumbnail%', $thumbnail, $list_format);
		}
		$title = $list_format;
		
		$li_class = $current_postid==$post_id ? 'current_post' : '';
		$ret .= '<li class="'.$li_class.'"><a href="'.get_permalink().'" title="'.$title_attr.'">'.$title.'</a></li>';
	endwhile; 
	$ret .= '</ul>';
	
	$max_page = absint($opts['max_page']);
	$total_page = $max_page>0 ? min($max_page, $q->max_num_pages) : $q->max_num_pages;
	$page_args = array(
		'base' => '#%#%',
		'format' => '',
		'total' => $total_page,
		'current' => $paged,
		'mid_size' => $opts['page_range'],
		'prev_next' => false,
	);
	$page_links = paginate_links( $page_args);
	
	if( $page_links ){
		$page_links_label = $opts['navi_label'] ? '<span class="umrp-nav-label">'.$opts['navi_label'].'</span> ' : '';
		$page_links = '<div class="umrp-nav %s '.$opts['navi_align'].'">'.$page_links_label.$page_links.'</div>';
		$page_links_top = sprintf($page_links, 'umrp-nav-top');
		$page_links_bottom = sprintf($page_links, 'umrp-nav-bottom');
		switch( $opts['navi_pos'] ) {
			case 'top': $ret = $page_links_top.$ret; break;
			case 'both': $ret = $page_links_top.$ret.$page_links_bottom; break;
			default: $ret = $ret.$page_links_bottom; break;
		}
	}
	
	$progress_img = '';
	switch( $opts['progress_img'] ){
		case 'white': $progress_img = 'ajax-loader-white.gif'; break;
		default: $progress_img = 'ajax-loader.gif'; break;
	}
	$progress_img = "<img src='{$this->url}i/{$progress_img}' class='umrp-progress' style='display:none;'>";
	$ret .= $progress_img;
	
	remove_filter( 'posts_where', array(&$this, 'filter_where') );
	wp_reset_query();
	return $ret;
}

function shortcode_display($atts){
	global $post;
	
	$default_atts = array(
		'id' => 'umrp-shortcode',
	);
	extract( shortcode_atts( $default_atts, $atts ) );
	
	$opts = $this->get_default_options();
	foreach($opts as $k=>$v){
		if( isset($atts[$k]) )
			$opts[$k] = $atts[$k];
	}
	if( isset($atts['tax_query']) ){
		$opts['tax_query'] = array($opts['post_type'] => wp_parse_args(preg_replace('/&amp;/', '&', $atts['tax_query'])));
	}
	
	$container_class = 'shortcode-type';
	$current_postid = '';
	if( is_single() ){
		$container_class .= ' single postid-'.$post->ID;
		$current_postid = $post->ID;
	}
	
	$args = array(
		'widget_id' => $id, 
		'current_postid' => $current_postid,
		'options' => $opts,
	);
	?>
	<div class="umrp-shortcode" id="<?php echo $id?>">
		<?php if( $opts['title'] ){ ?>
		<h3 class="umrp-title"><?php echo $opts['title']?></h3>
		<?php } ?>
		<div id="<?php echo $id?>-container" class="umrp-container <?php echo $container_class?>">
			<?php echo $this->the_list_for_shortcode($args);?>
			<!--<?php echo json_encode($opts)?>-->
		</div>
	</div>
	<?php
}


function filter_where( $where = '' ) {
	$d = get_query_var('umrp_time_limit');
	$where .= " AND post_date > '" . date('Y-m-d', strtotime('-'.$d.' days')) . "'";
	return $where;
}


function get_post_thumbnail( $post_id, $w, $h ){
	$thumb_id = get_post_meta($post_id, '_thumbnail_id', true);
	$thumb = wp_get_attachment_image_src($thumb_id, 'thumbnail');
	if( !empty($thumb) ) {
		$thumb = $thumb[0];
	} else {
		$thumb = $this->url.'i/t.gif';
	}
	$r = "<div class='umrp-post-thumbnail' style='width:{$w}px; height:{$h}px'>";
	$r .= "<img src='$thumb' />";
	$r .= '</div>';
	return $r;
}


function get_default_options(){
	return array( 
		'title'					=> __('Recent Posts', $this->id), 
		'number' 				=> '5', 
		'length'				=> '', 
		'show_comment_count' 	=> '', //deprecated
		'list_format'			=> '%title%',
		'date_format'			=> 'F j, Y',
		'thumbnail_w'			=> get_option('thumbnail_size_w'),
		'thumbnail_h' 			=> get_option('thumbnail_size_h'),
		
		'post_type'				=> 'post',
		'tax_query'				=> array(),
		'authors'				=> '',
		'author_operate'		=> '',
		'time_limit'			=> '',
		
		'appear_effect'			=> '',
		'appear_effect_dur'		=> 0.3,
		'disappear_effect'		=> '',
		'disappear_effect_dur'	=> 0.3,
		'auto_paginate'			=> '',
		'auto_paginate_delay'	=> 5,
		
		'navi_label'			=> __('Pages:', $this->id),
		'navi_pos' 				=> 'bottom',
		'navi_align' 			=> '',
		'page_range'			=> 1,
		'max_page'				=> '',
		
		'progress_img'			=> '',
		'custom_css'			=> '',
	); 
}

}

$umrp = new UMoreRecentPosts();






class UMoreRecentPostsWidget extends WP_Widget { 

var $url;

function UMoreRecentPostsWidget() {
	$this->url = plugin_dir_url(__FILE__);
	$opts = array( 'classname' => 'widget_umrp' ); 
	$this->WP_Widget( 'umrp', 'U '.__('More Recent Posts', 'umrp'), $opts, array('width'=>420) );
}

function widget($args, $instance) {
	global $umrp, $post;
	
	extract($args);
	
	$title = apply_filters('widget_title', $instance['title']);
	$id = (int) str_replace('umrp-', '', $widget_id);
	
	$container_class = 'widget-type';
	$current_postid = '';
	if( is_single() ){
		$container_class .= ' single postid-'.$post->ID;
		$current_postid = $post->ID;
	}
	
	if( !empty($instance['custom_css']) ){
		$custom_css = preg_replace('/%widget_id%/', '#'.$widget_id.'-container', $instance['custom_css']);
		$custom_css = preg_replace('/(\r|\n)/', '', $custom_css);
		$custom_css = '<style>'.$custom_css.'</style>';
	}
	
	$args = array(
		'widget_id' => $widget_id, 
		'current_postid' => $current_postid,
	);
	
	echo $before_widget;
	if( $title ) 
		echo $before_title.$title.$after_title;
	?>
	
	<div id="umrp-<?php echo $id?>-container" class="umrp-container <?php echo $container_class?>">
		<?php $umrp->the_list_for_widget( $args );?>
	</div>
	
	<?php 
	echo $custom_css;
	echo $after_widget;
}

function update($new_instance, $old_instance) { 

	$instance = $old_instance; 
	$instance['title'] 				= strip_tags($new_instance['title']);
	$instance['number'] 			= absint($new_instance['number']);
	$instance['length'] 			= preg_replace('/[^0-9]/', '', $new_instance['length'] );
	$instance['list_format'] 		= trim($new_instance['list_format']);
	$instance['date_format'] 		= trim($new_instance['date_format']);
	$instance['thumbnail_w'] 		= absint($new_instance['thumbnail_w']);
	$instance['thumbnail_h'] 		= absint($new_instance['thumbnail_h']);
	
	$instance['post_type'] 			= $new_instance['post_type'];
	$instance['tax_query'] 			= array($instance['post_type'] => $new_instance['tax_query'][$instance['post_type']]);
	$instance['authors'] 			= trim($new_instance['authors']);
	$instance['author_operate'] 	= $new_instance['author_operate'];
	$instance['time_limit'] 		= preg_replace('/[^0-9]/', '', $new_instance['time_limit'] );
	
	$instance['appear_effect'] 		= $new_instance['appear_effect'];
	$instance['appear_effect_dur'] 	= floatval($new_instance['appear_effect_dur']);
	$instance['disappear_effect'] 	= $new_instance['disappear_effect'];
	$instance['disappear_effect_dur'] 	= floatval($new_instance['disappear_effect_dur']);
	$instance['auto_paginate'] 		= $new_instance['auto_paginate'];
	$instance['auto_paginate_delay']= floatval($new_instance['auto_paginate_delay']);
	
	$instance['navi_label'] 		= strip_tags($new_instance['navi_label']);
	$instance['navi_pos'] 			= $new_instance['navi_pos'];
	$instance['navi_align'] 		= $new_instance['navi_align'];
	$instance['page_range'] 		= $new_instance['page_range'];
	$instance['max_page'] 			= preg_replace('/[^0-9]/', '', $new_instance['max_page'] );
	
	$instance['progress_img'] 		= $new_instance['progress_img'];
	$instance['custom_css'] 		= trim($new_instance['custom_css']);
	
	//$instance['show_comment_count'] = $new_instance['show_comment_count']; //deprecated
	return $instance; 
} 

function form($instance) {
	
	global $umrp;
	$defaults = $umrp->get_default_options(); 
	$instance = wp_parse_args( $instance, $defaults ); 
	extract($instance);
	
	$title = esc_attr( $title );
	$number = absint( $number ); 
	$length = preg_replace('/[^0-9]/', '', $length );
	$navi_label = esc_attr( $navi_label );
	
	$appear_effects = array(
		'' => __('None', 'umrp'), 
		'fadein' => __('Fade In', 'umrp'), 
		'slidedown' => __('Slide Down', 'umrp'),
		'slidein' => __('Slide In', 'umrp'),
	);
	$disappear_effects = array(
		'' => __('None', 'umrp'), 
		'fadeout' => __('Fade Out', 'umrp'), 
		'slideup' => __('Slide Up', 'umrp'),
		'slideout' => __('Slide Out', 'umrp'),
	);
	
	$navi_pos_arr = array(
		'bottom' => __('Bottom', 'umrp'), 
		'top' => __('Top', 'umrp'), 
		'both' => __('Both', 'umrp'),
	);
	$navi_align_arr = array(
		'' => __('None', 'umrp'), 
		'left' => __('Left', 'umrp'), 
		'center' => __('Center', 'umrp'), 
		'right' => __('Right', 'umrp'),
	);
	?>
	
	<p>
		<a href="http://urlless.com/u-more-recent-posts-demos/" target="_blank" class="umrp-demos-link"><?php _e('View Demos', 'umrp')?></a>
		<br><span class="description"><?php _e('Demos, Shortcode usage, DOM Structure & Custom CSS', 'umrp')?></span>
	</p>
	
	<div class="umrp-group">
		<h5><?php _e('General settings', 'umrp')?></h5>
		<p>
			<?php _e('Title', 'umrp'); ?>:
			<input id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $title; ?>" type="text" />
		</p>
		
		<p>
			<?php _e('Number of posts to show', 'umrp'); ?>: 
			<input name="<?php echo $this->get_field_name('number'); ?>" value="<?php echo $number; ?>" type="text" size="1" />
		</p>
		
		<p>
			<?php _e('Post title length', 'umrp'); ?>:
			<input name="<?php echo $this->get_field_name('length'); ?>" value="<?php echo $length; ?>" type="text" size="1" /> 
			<?php _e('words', 'umrp'); ?>
		</p>
		<p>
			<?php _e('List format', 'umrp'); ?>:
			<span class="description"><small>(<?php _e('Enable HTML, Line break', 'umrp')?>)</small></span>
			<textarea name="<?php echo $this->get_field_name('list_format'); ?>" rows="3" class="widefat" style="margin:3px 0"><?php echo esc_textarea($list_format)?></textarea>
			<?php _e('Replace Keywords', 'umrp'); ?>:
			<code>%title%, %date%, $author%, %comment_count%, %thumbnail%</code>
		</p>
		
		<p>
			<?php _e('Date format', 'umrp'); ?>:
			<input name="<?php echo $this->get_field_name('date_format'); ?>" value="<?php echo $date_format; ?>" type="text" size="10"/>
		</p>
		
		<p>
			<?php _e('Thumbnail size', 'umrp'); ?>:
			<input name="<?php echo $this->get_field_name('thumbnail_w'); ?>" value="<?php echo $thumbnail_w; ?>" type="text" size="1"/>
			&nbsp; x &nbsp;
			<input name="<?php echo $this->get_field_name('thumbnail_h'); ?>" value="<?php echo $thumbnail_h; ?>" type="text" size="1"/>
		</p>
		
	</div>
	
	<div class="umrp-group">
		<h5><?php _e('Filtering', 'umrp')?></h5>
		
		<p><strong><?php _e('Post Type', 'umrp')?> & <?php _e('Taxonomy', 'umrp')?>:</strong></p>
		<ul class="umrp-types">
			<?php $this->get_post_type_chooser($post_type, $tax_query);?>
		</ul>
		
		<hr>
		
		<p><strong><?php _e('Author', 'umrp')?></strong>:
		<label><input name="<?php echo $this->get_field_name('author_operate'); ?>" value="" type="radio" <?php checked($author_operate=='')?> /> 
			<?php _e('None', 'umrp')?></label>
		<label><input name="<?php echo $this->get_field_name('author_operate'); ?>" value="include" type="radio" <?php checked($author_operate=='include')?> /> 
			<?php _e('Include', 'umrp')?></label>
		<label><input name="<?php echo $this->get_field_name('author_operate'); ?>" value="exclude" type="radio" <?php checked($author_operate, 'exclude')?> /> 
			<?php _e('Exclude', 'umrp')?></label><p>
		
		<p><?php _e('If you select Include or Exclude, input author IDs', 'umrp')?>:
		<input name="<?php echo $this->get_field_name('authors'); ?>" value="<?php echo $authors; ?>" type="text" size="6" /> 
		<br><span class="description"><?php _e('Separate IDs with commas', 'umrp'); ?>.</span></p>
		
		<hr>
		
		<p><strong><?php _e('Time limit', 'umrp')?></strong>:
		<?php printf(__('Last %s days' , 'umrp'), '<input name="'.$this->get_field_name('time_limit').'" value="'.$time_limit.'" type="text" size="2" />');?>
		
	</div>
	
	<div class="umrp-group">
		<h5><?php _e('Navigation', 'umrp')?></h5>
		<p>
			<?php _e('Label', 'umrp'); ?>:
			<input name="<?php echo $this->get_field_name('navi_label'); ?>" value="<?php echo $navi_label; ?>" type="text" />
		<p>
		<p>
			<?php _e('Position', 'umrp'); ?>: 
			<select name="<?php echo $this->get_field_name('navi_pos'); ?>">
				<?php foreach($navi_pos_arr as $key=>$val){ ?>
				<option value="<?php echo $key; ?>" <?php selected($navi_pos==$key)?>><?php echo $val?></option>
				<?php } ?>
			</select>
		</p>
		<p>
			<?php _e('Text align', 'umrp'); ?>:
			<select name="<?php echo $this->get_field_name('navi_align'); ?>">
				<?php foreach ($navi_align_arr as $key=>$val){ ?>
				<option value="<?php echo $key; ?>" <?php selected($key==$navi_align)?>><?php echo $val?></option>
				<?php } ?>
			</select>
		</p>
		<p>
			<?php _e('Page range', 'umrp'); ?>:
			<select name="<?php echo $this->get_field_name('page_range'); ?>">
				<?php for ($i=1; $i<=10; $i++){ ?>
				<option value="<?php echo $i; ?>" <?php selected($i==$page_range)?>><?php echo $i?></option>
				<?php } ?>
			</select>
			<br><span class="description"><?php _e('The number of page links to show before and after the current page.', 'umrp'); ?></span>
		</p>
		<p>
			<?php _e('Max page links', 'umrp'); ?>:
			<input name="<?php echo $this->get_field_name('max_page'); ?>" value="<?php echo $max_page; ?>" type="text" size="2" />
		</p>
		
	</div>
	
	<div class="umrp-group progress-image">
		<h5><?php _e('Progress image', 'umrp')?></h5>
		
		<p><label><input type="radio" name="<?php echo $this->get_field_name('progress_img'); ?>" value="" <?php checked($progress_img, '')?> />
		<img src="<?php echo $this->url?>i/ajax-loader.gif">
		<span class="description"><?php _e('For bright background color', 'umrp')?></span></label></p>
		
		<p style="background:#000;"><label><input type="radio" name="<?php echo $this->get_field_name('progress_img'); ?>" value="white" <?php checked($progress_img, 'white')?> />
		<img src="<?php echo $this->url?>i/ajax-loader-white.gif">
		<span class="description"><?php _e('For dark background color', 'umrp')?></span></label></p>
	</div>
	
	<div class="umrp-group">
		<h5><?php _e('Effect', 'umrp')?></h5>
		<p>
			<?php _e('Appear effect', 'umrp'); ?>: 
			<select name="<?php echo $this->get_field_name('appear_effect'); ?>">
				<?php foreach($appear_effects as $key=>$val){ ?>
				<option value="<?php echo $key?>" <?php selected($appear_effect==$key)?>><?php echo $val?></option>
				<?php } ?>
			</select>
		</p>
		<p>
			<?php _e('Appear effect duration', 'umrp'); ?>: 
			<input name="<?php echo $this->get_field_name('appear_effect_dur'); ?>" value="<?php echo $appear_effect_dur; ?>" type="text" size="2"/>
			<?php _e('sec', 'umrp')?>
		</p>
		<p>
			<?php _e('Disappear effect', 'umrp'); ?>: 
			<select name="<?php echo $this->get_field_name('disappear_effect'); ?>">
				<?php foreach($disappear_effects as $key=>$val){ ?>
				<option value="<?php echo $key?>" <?php selected($disappear_effect==$key)?>><?php echo $val?></option>
				<?php } ?>
			</select>
		</p>
		<p>
			<?php _e('Disappear effect duration', 'umrp'); ?>: 
			<input name="<?php echo $this->get_field_name('disappear_effect_dur'); ?>" value="<?php echo $disappear_effect_dur; ?>" type="text" size="2"/>
			<?php _e('sec', 'umrp')?>
		</p>
		<hr />
		<p>
			<?php _e('Auto paginate', 'umrp'); ?>: 
			<label><input type="checkbox" name="<?php echo $this->get_field_name('auto_paginate'); ?>" value="1" <?php checked($auto_paginate, '1')?>/>
			<?php _e('Yes', 'umrp')?></label>
		</p>
		<p>
			<?php _e('Auto paginate delay', 'umrp'); ?>: 
			<input name="<?php echo $this->get_field_name('auto_paginate_delay'); ?>" value="<?php echo $auto_paginate_delay; ?>" type="text" size="2"/>
			<?php _e('sec', 'umrp')?>
		</p>
	</div>
	
	<div class="umrp-group">
		<h5><?php _e('Custom CSS', 'umrp')?></h5>
		<p><textarea name="<?php echo $this->get_field_name('custom_css'); ?>" class="widefat" rows="10"><?php echo $custom_css?></textarea></p>
		<p><?php _e('Replace Keywords', 'umrp'); ?>: <code>%widget_id%</code></p>
		<a href="http://urlless.com/u-more-recent-posts-demos/#dom-structure" target="_blank"><?php _e('DOM Structure & Custom CSS', 'umrp')?></a>
	</div>
	
	<p>
		<a href="http://urlless.com/u-more-recent-posts-demos/" target="_blank" class="umrp-demos-link"><?php _e('View Demos', 'umrp')?></a>
		<br><span class="description"><?php _e('Demos, Shortcode usage, DOM Structure & Custom CSS', 'umrp')?></span>
	</p>
	
	<!--
	<div class="umrp-sprite"></div>
	<script>window.onload=function() { jQuery('.umrp-sprite').parents('.widget').find('a.widget-action').click();}</script>
	-->
	<?php 
}

function get_post_type_chooser($saved_type, $saved_taxs){
	$types = $this->posttypes_filter( get_post_types() );
	foreach( $types as $type ) {
		$type_object = get_post_type_object($type);
		$taxs = array();
		if( $_taxs = get_taxonomies() ){
			foreach($_taxs as $tax){
				$tax = get_taxonomy($tax);
				if( in_array($type, $tax->object_type) ) 
					$taxs[$tax->name] = $tax->label;
			}
		}
		?>
	<li>
		<label><input type="radio" name="<?php echo $this->get_field_name('post_type'); ?>" value="<?php echo $type?>" <?php checked($type==$saved_type)?> /> 
		<strong><?php echo $type_object->label?></strong></label>
		<?php 
		if( !empty($taxs) ){
			$saved_tax = $saved_terms = $saved_operate = $children_class = '';
			if( isset($saved_taxs[$type]) ){
				$_saved_taxs = $saved_taxs[$type];
				$saved_tax = isset($_saved_taxs['taxonomy']) ? $_saved_taxs['taxonomy'] : '';
				$saved_terms = isset($_saved_taxs['terms']) ? $_saved_taxs['terms'] : '';
				$saved_operate = isset($_saved_taxs['operate']) ? $_saved_taxs['operate'] : '';
			}
			$field_name = $this->get_field_name('tax_query').'['.$type.']';
			?>
		
		<div class="children">
			<p>
				<?php _e('Taxonomy', 'umrp')?>:
				<select name="<?php echo $field_name?>[taxonomy]">
					<option value=""></option>
					<?php foreach($taxs as $k=>$v){ ?>
					<option value="<?php echo $k?>" <?php selected($k==$saved_tax)?>><?php echo $v?></option>
					<?php } ?>
				</select> 
			</p>
			<p>
				<?php _e('Term', 'umrp')?> IDs:
				<input type="text" name="<?php echo $field_name?>[terms]" value="<?php echo $saved_terms?>" size="12" />
				<span class="description"><?php _e('Separate IDs with commas', 'umrp'); ?>.</span>
			</p>
			<p>
				<?php _e('Operate', 'umrp')?>:
				
				<label><input type="radio" name="<?php echo $field_name?>[operate]" value="" <?php checked($saved_operate=='')?>>
				<?php _e('None', 'umrp')?></label>
				
				<label><input type="radio" name="<?php echo $field_name?>[operate]" value="include" <?php checked($saved_operate=='include')?>>
				<?php _e('Include', 'umrp')?><small>(IN)</small></label>
				
				<label><input type="radio" name="<?php echo $field_name?>[operate]" value="exclude" <?php checked($saved_operate=='exclude')?>>
				<?php _e('Exclude', 'umrp')?><small>(NOT IN)</small></label>
				
				<label><input type="radio" name="<?php echo $field_name?>[operate]" value="and" <?php checked($saved_operate=='and')?>>
				<?php _e('Intersect', 'umrp')?><small>(AND)</small></label>
			</p>
		</div>
		<?php } ?>
	</li>
	<?php 
	}
}

function posttypes_filter($posttypes){
    foreach($posttypes as $key => $val) {
        if($val=='page'||$val=='attachment'||$val=='revision'||$val=='nav_menu_item'){
            unset($posttypes[$key]);
        }
    }
    return $posttypes;
}
}


