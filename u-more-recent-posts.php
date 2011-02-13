<?php
/* 
Plugin Name: U More Recent Posts
Plugin URI: http://urlless.com/u-more-recent-posts/
Description: Based on Wordpress core "Recent Posts" widget, this plugin is redesigned to make it possible to navigate more recent posts without refreshing screen.
Version: 1.0 
Author: Taehan Lee
Author URI: http://urlless.com
*/

global $wp_version;
if (version_compare($wp_version, "2.8", "<")) wp_die("This plugin requires WordPress version 2.8 or higher.");

class UMoreRecentPosts {
	
	// domain => umrp
	var $plugin_url;
	
	function UMoreRecentPosts(){
		$this->plugin_url = plugin_dir_url(__FILE__);
		add_action( 'init', array(&$this, 'init') ); 
		add_action( 'widgets_init', array(&$this, 'widgets_init') ); 
		add_action( 'wp_ajax_umrp-ajax', array(&$this, 'ajax') );
		add_action( 'wp_ajax_nopriv_umrp-ajax', array(&$this, 'ajax') );
	}
	
	function init() { 
		if ( ! is_admin() ) {
			wp_enqueue_script( 'jquery' ); 
			wp_enqueue_style( 'umrp_style', $this->plugin_url.'u-more-recent-posts.css');
			wp_enqueue_script( 'umrp_script', $this->plugin_url.'u-more-recent-posts.js', array('jquery'));
			wp_localize_script( 'umrp_script', 'umrp_settings', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ), 
				'nonce' => wp_create_nonce( 'umrp_nonce' )
			));
		}
	}
	
	function widgets_init() { 
		register_widget( 'UMoreRecentPostsWidget' ); 
	}
	
	function ajax() {
		check_ajax_referer( 'umrp_nonce' );
		
		$args = array(
			'posts_per_page' => intval( $_POST['posts_per_page'] ) ? $_POST['posts_per_page'] : 5,
			'paged' => $_POST['paged'], 
			'nopaging' => 0, 
			'post_status' => 'publish', 
			'caller_get_posts' => 1
		);
		if( ! empty($_POST['exclude']) ) $args['category__not_in'] = explode(',', $_POST['exclude']);
		if( ! empty($_POST['include']) ) $args['category__in'] = explode(',', $_POST['include']);
		
		$r = new WP_Query($args);
		if($r->have_posts()): 
			echo '<ul>';
			while($r->have_posts()): $r->the_post();
			?>
			<li><a href="<?php the_permalink() ?>" title="<?php echo esc_attr(get_the_title() ? get_the_title() : get_the_ID()); ?>"><?php if ( get_the_title() ) the_title(); else the_ID(); ?></a></li>
			<?php
			endwhile; 
			echo '</ul>';
			
			if( $pager = $this->pager( array(
				'posts_per_page' => $args['posts_per_page'],
				'paged' => $args['paged'],
				'found_posts' => $r->found_posts
			) ) ): 
			?>
			<div class="umrp-nav"><?php echo $pager; ?></div>
			<?php
			endif;
		endif;
		die();
	}
	
	function pager($args) {
		extract($args);
		$totalpages = ceil( intval($found_posts) / intval($posts_per_page) );
		if ($totalpages < 2) return;	
		$currentpage = intval($paged)>1 ? intval($paged) : 1;
		$block_range = 1;
		$dots = 1;
		$wing = 1;
		$block_min = min($currentpage - $block_range, $totalpages - ($block_range + 1) );
		$block_max = max($currentpage + $block_range, ($block_range + 1) );
		$has_left = (($block_min - $wing - $dots) > 0) ? true : false;
		$has_right = (($block_max + $wing + $dots) < $totalpages) ? true : false;
		$dot_html = "<span class='dots'>&hellip;</span>";
		$ret = '';
		
		if ($has_right AND !$has_left) {
			$ret .= $this->pager_links(1, $block_max, $currentpage);
			$ret .= $dot_html;
			$ret .= $this->pager_links(($totalpages - $wing + 1), $totalpages);
			
		} else if ($has_left AND !$has_right) {
			$ret .= $this->pager_links(1, $wing);
			$ret .= $dot_html;
			$ret .= $this->pager_links($block_min, $totalpages, $currentpage);
			
		} else if ($has_left AND $has_right) {
			$ret .= $this->pager_links(1, $wing);
			$ret .= $dot_html;
			$ret .= $this->pager_links($block_min, $block_max, $currentpage);
			$ret .= $dot_html;
			$ret .= $this->pager_links(($totalpages - $wing + 1), $totalpages);
			
		} else {
			$ret .= $this->pager_links(1, $totalpages, $currentpage);
		}
		return $ret;
	}
	
	function pager_links($start, $total, $currentpage=0) {
		$ret = '';
		for ( $i=$start; $i<=$total; ++$i )
		$ret .= $currentpage==$i ? "<em>$i</em>" : "<a href='#'>$i</a>";
		return $ret;
	}
}



class UMoreRecentPostsWidget extends WP_Widget { 
	var $plugin_url;
	
	function UMoreRecentPostsWidget() {
		$this->plugin_url = plugin_dir_url(__FILE__);
		$opts = array( 'classname' => 'widget_umrp' ); 
		$this->WP_Widget( 'umrp', __('More Recent Posts', 'umrp'), $opts ); 
	}
	
	function form($instance) { 
		$defaults = array( 'title'=>__('Recent Posts', 'umrp'), 'number'=>'5', 'exclude'=>'', 'include'=>'' ); 
		$instance = wp_parse_args( (array) $instance, $defaults ); 
		$title = esc_attr( $instance['title'] ); 
		$number = esc_attr( $instance['number'] ); 
		$exclude = esc_attr( $instance['exclude'] ); 
		$include = esc_attr( $instance['include'] ); 
		?>
		<p>
			<label><?php _e('Title', 'umrp'); ?>:</label>
			<input name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $title; ?>" type="text" class="widefat" /></p> 
		<p>
			<label><?php _e('Number of list to show per page', 'umrp'); ?>: </label>
			<input name="<?php echo $this->get_field_name('number'); ?>" value="<?php echo $number; ?>" type="text" size="2" />
		</p>
		<p>
			<label><?php _e('Exclude Category IDs', 'umrp'); ?>: </label>
			<input name="<?php echo $this->get_field_name('exclude'); ?>" value="<?php echo $exclude; ?>" type="text" class="widefat" />
			<br><small><?php _e('Separated by commas', 'umrp'); ?>.</small>
		</p>
		<p>
			<label><?php _e('Include Category IDs', 'umrp'); ?>: </label>
			<input name="<?php echo $this->get_field_name('include'); ?>" value="<?php echo $include; ?>" type="text" class="widefat" />
			<br><small><?php _e('Separated by commas', 'umrp'); ?>.</small>
		</p>
		<?php 
	} 
	
	function update($new_instance, $old_instance) { 
		$instance = $old_instance; 
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = strip_tags($new_instance['number']);
		$instance['exclude'] = strip_tags($new_instance['exclude']);
		$instance['include'] = strip_tags($new_instance['include']);
		return $instance; 
	} 
	
	function widget($args, $instance) {
		extract($args);
		$title = apply_filters('widget_title', $instance['title']);
		echo $before_widget;
		if( $title ) echo $before_title . $title . $after_title;
		?>
		<img class="umrp-status" src="<?php echo $this->plugin_url; ?>images/loading.gif" />
		<div class="umrp-container"></div>
		<script>
			jQuery("#<?php echo $widget_id; ?>").UMoreRecentPostsWidget({
				number:"<?php echo esc_js($instance['number']); ?>", 
				exclude:"<?php echo esc_js($instance['exclude']); ?>", 
				include:"<?php echo esc_js($instance['include']); ?>"
			});
		</script>
		<?php
        echo $after_widget; 
	}
}


new UMoreRecentPosts();

