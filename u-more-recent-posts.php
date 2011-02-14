<?php
/* 
Plugin Name: U More Recent Posts
Plugin URI: http://urlless.com/wordpress-plugin-u-more-recent-posts/
Description: Based on Wordpress core "Recent Posts" widget, this plugin is redesigned to make it possible to navigate more recent posts without refreshing screen.
Version: 1.1
Author: Taehan Lee
Author URI: http://urlless.com
*/ 

global $wp_version;
if (version_compare($wp_version, "2.8", "<")) wp_die("This plugin requires WordPress version 2.8 or higher.");

class UMoreRecentPosts {
	
	// domain => umrp
	var $plugin_url;
	var $options;
	
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
		
		switch( $_POST['scope'] ):
			
			case 'get_option':
				$opts = get_option('widget_umrp');
				$opt = $opts[intval($_POST['widget_id'])];
				die( json_encode($opt) );
				break;
				
			case 'get_list':
				$args = array(
					'posts_per_page' => intval( $_POST['number'] ) ? $_POST['number'] : 5,
					'paged' => $_POST['paged'], 
					'nopaging' => 0, 
					'post_status' => 'publish', 
					'caller_get_posts' => 1
				);
				if( ! empty($_POST['exclude']) ) $args['category__not_in'] = explode(',', $_POST['exclude']);
				if( ! empty($_POST['include']) ) $args['category__in'] = explode(',', $_POST['include']);
				
				$r = new WP_Query($args);
				if($r->have_posts()): 
					$pager = $this->pager( array(
						'posts_per_page' => $args['posts_per_page'],
						'paged' => $args['paged'],
						'found_posts' => $r->found_posts,
						'page_range' => $_POST['page_range']
					) );
					if( $pager ){
						$pager = $_POST['navi_label'] . ' ' . $pager;
					}
					if( $pager AND ($_POST['navi_pos']=='top' || $_POST['navi_pos']=='both') ) {
						echo '<div class="umrp-nav umrp-nav-top '.$_POST['navi_align'].'">'.$pager.'</div>';
					}
					
					echo '<ul>';
					while($r->have_posts()): $r->the_post();
					
					$title = get_the_title() ? get_the_title() : get_the_ID();
					$title = apply_filters('the_title', $title);
					$title_attr = esc_attr($title);
					
					$word_limit = isset($_POST['length']) ? intval($_POST['length']) : 0;
					if( $word_limit>0 ){
						$words = explode(' ',$title);
						if(count($words) > $word_limit) {
							array_splice($words, $word_limit);
		    				$title = implode(' ', $words) . '&hellip;';
		    			}
	    			}
					?>
					<li><a href="<?php the_permalink() ?>" title="<?php echo $title_attr; ?>"><?php echo $title; ?></a></li>
					<?php
					endwhile; 
					echo '</ul>';
					
					if( $pager AND $_POST['navi_pos']!='top' ) {
						echo '<div class="umrp-nav umrp-nav-bottom '.$_POST['navi_align'].'">'.$pager.'</div>';
					}
					
				endif;
				die();
				break;
				
		endswitch;
	}
	
	function pager($args) {
		extract($args);
		$totalpages = ceil( intval($found_posts) / intval($posts_per_page) );
		if ($totalpages < 2) return;	
		$currentpage = intval($paged)>1 ? intval($paged) : 1;
		$block_range = intval($page_range)>1 ? intval($page_range) : 1;
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
		$ret .= $currentpage==$i ? " <em>$i</em> " : " <a href='#'>$i</a> ";
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
	
	function update($new_instance, $old_instance) { 
		$instance = $old_instance; 
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = intval($new_instance['number']);
		$instance['exclude'] = $new_instance['exclude'];
		$instance['include'] = $new_instance['include'];
		$instance['length'] = intval($new_instance['length']) ? $new_instance['length'] : '';
		$instance['effect'] = $new_instance['effect'];
		$instance['loader_label'] = strip_tags(trim($new_instance['loader_label']));
		$instance['loader_symbol'] = strip_tags(trim($new_instance['loader_symbol']));
		$instance['loader_direction'] = $new_instance['loader_direction'];
		$instance['page_range'] = intval($new_instance['page_range']);
		$instance['navi_label'] = strip_tags(trim($new_instance['navi_label']));
		$instance['navi_pos'] = $new_instance['navi_pos'];
		$instance['navi_align'] = $new_instance['navi_align'];
		return $instance; 
	} 
	
	function form($instance) {
		$defaults = array( 
			'title'			=> __('Recent Posts', 'umrp'), 
			'number' 		=> '5', 
			'exclude'		=> '', 
			'include'		=> '', 
			'length'		=> '', 
			'effect'		=> '',
			'loader_label'	=> 'Loading',
			'loader_symbol'	=> '.',
			'loader_direction'	=> '',
			'page_range'	=> 1,
			'navi_label'	=> 'Pages:',
			'navi_pos' 		=> 'bottom',
			'navi_align' 	=> '',
		); 
		$instance = wp_parse_args( (array) $instance, $defaults ); 
		$title = esc_attr( $instance['title'] ); 
		$number = esc_attr( $instance['number'] ); 
		$exclude = esc_attr( $instance['exclude'] ); 
		$include = esc_attr( $instance['include'] ); 
		$length = esc_attr( $instance['length'] ); 
		$effect = esc_attr( $instance['effect'] ); 
		$loader_label = esc_attr( $instance['loader_label'] );
		$loader_symbol = esc_attr( $instance['loader_symbol'] );
		$loader_direction = $instance['loader_direction'];
		$page_range = intval( $instance['page_range'] );
		$navi_label = esc_attr( $instance['navi_label'] );
		$navi_pos = $instance['navi_pos'];
		$navi_align = $instance['navi_align'];
		?>
		<p>
			<label><?php _e('Title', 'umrp'); ?>:</label>
			<input name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $title; ?>" type="text" class="widefat" />
		</p> 
		<style type="text/css">
		.umrp-widget-div {background:#f5f5f5; border: 1px dashed #ccc; padding: 5px; margin-bottom:10px;}
		</style>
		<div class="umrp-widget-div">
			<p><strong>Posts List</strong></p>
			<p>
				<label><?php _e('Number of posts to show', 'umrp'); ?>: </label>
				<input name="<?php echo $this->get_field_name('number'); ?>" value="<?php echo $number; ?>" type="text" size="2" />
			</p>
			
			<p>
				<label><?php _e('Post title length', 'umrp'); ?>: </label>
				<input name="<?php echo $this->get_field_name('length'); ?>" value="<?php echo $length; ?>" type="text" size="3" /> 
				<?php _e('words', 'umrp'); ?>
			</p>
			
			<p>
				<label><?php _e('Show Effect', 'umrp'); ?>: </label>
				<select name="<?php echo $this->get_field_name('effect'); ?>">
					<?php 
					$effects = array('none'=>'none', 'fadein'=>'Fade In', 'slidedown'=>'Slide Down');
					foreach($effects as $key=>$val){
						$selected = $effect==$key ? 'selected="selected"' : '';
						?>
						<option value="<?php echo $key; ?>" <?php echo $selected; ?>><?php echo $val; ?></option>
						<?php
					} ?>
				</select>
			</p>
			
			<p>
				<label><?php _e('Exclude category IDs', 'umrp'); ?>: </label>
				<input name="<?php echo $this->get_field_name('exclude'); ?>" value="<?php echo $exclude; ?>" type="text" class="widefat" />
				<br><small><?php _e('Separated by commas', 'umrp'); ?>.</small>
			</p>
			<p>
				<label><?php _e('Include category IDs', 'umrp'); ?>: </label>
				<input name="<?php echo $this->get_field_name('include'); ?>" value="<?php echo $include; ?>" type="text" class="widefat" />
				<br><small><?php _e('Separated by commas', 'umrp'); ?>.</small>
			</p>
		</div>
		
		<div class="umrp-widget-div">
			<p><strong>Ajax Loader</strong></p>
			<p>
				<label><?php _e('Label', 'umrp'); ?>:</label>
				<input name="<?php echo $this->get_field_name('loader_label'); ?>" value="<?php echo $loader_label; ?>" type="text" /></p> 
			<p>
			
			<p>
				<label><?php _e('Progress symbol', 'umrp'); ?>:</label>
				<input name="<?php echo $this->get_field_name('loader_symbol'); ?>" value="<?php echo $loader_symbol; ?>" type="text" size="1" /></p> 
			<p>
			
			<p>
				<label><?php _e('Progress direction', 'umrp'); ?>:</label>
				<label><input type="radio" name="<?php echo $this->get_field_name('loader_direction'); ?>" value="" <?php echo $loader_direction!='left' ? 'checked="checked"' : '' ?> /> Right</label>
				<label><input type="radio" name="<?php echo $this->get_field_name('loader_direction'); ?>" value="left" <?php echo $loader_direction=='left' ? 'checked="checked"' : '' ?> /> Left</label>
			</p>
		</div>
		
		<div class="umrp-widget-div">
			<p><strong>Navigation</strong></p>
			<p>
				<label><?php _e('Label', 'umrp'); ?>:</label>
				<input name="<?php echo $this->get_field_name('navi_label'); ?>" value="<?php echo $navi_label; ?>" type="text" /></p> 
			<p>
			<p>
				<label><?php _e('Page range', 'umrp'); ?>:</label>
				<select name="<?php echo $this->get_field_name('page_range'); ?>">
					<?php for ($i=1; $i<=10; $i++) : ?>
					<option value="<?php echo $i; ?>" <?php echo ($i == $page_range) ? "selected='selected'" : ""; ?>><?php echo $i; ?></option>
					<?php endfor; ?>
				</select>
				<br><small><?php _e('The number of page links to show before and after the current page.<br>Recommended value: 1', 'umrp'); ?></small>
			</p>
			<p>
				<label><?php _e('Position', 'umrp'); ?>: </label>
				<select name="<?php echo $this->get_field_name('navi_pos'); ?>">
					<?php 
					$navi_pos_arr = array('bottom'=>'Bottom', 'top'=>'Top', 'both'=>'Both');
					foreach($navi_pos_arr as $key=>$val){
						$selected = $navi_pos==$key ? 'selected="selected"' : '';
						?>
						<option value="<?php echo $key; ?>" <?php echo $selected; ?>><?php echo $val; ?></option>
						<?php
					} ?>
				</select>
			</p>
			<p>
				<label><?php _e('Text align', 'umrp'); ?>:</label>
				<select name="<?php echo $this->get_field_name('navi_align'); ?>">
					<?php 
					$navi_align_arr = array(''=>'Normal (inherit)', 'left'=>'Left', 'center'=>'Center', 'right'=>'Right');
					foreach ($navi_align_arr as $key=>$val) : ?>
					<option value="<?php echo $key; ?>" <?php echo ($key==$navi_align) ? "selected='selected'" : ""; ?>><?php echo $val; ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		</div>
		<?php 
	}
	
	
	function widget($args, $instance) {
		extract($args);
		$title = apply_filters('widget_title', $instance['title']);
		echo $before_widget;
		if( $title ) echo $before_title . $title . $after_title;
		?>
		<div class="umrp-container">
			<div class="umrp-loader">Loading</div>
			<div class="umrp-content"></div>
		</div>
		<?php
        echo $after_widget; 
	}
}


new UMoreRecentPosts();

