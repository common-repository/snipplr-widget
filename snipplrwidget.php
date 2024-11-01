<?php
/*
Plugin Name: 	Snipplr Widget
Plugin URI: 	http://jonas-doebertin.de/projekte/snipplr-widget/
Description: 	Show your latest Snipplr.com Snippets in your sidebar.
Version: 		1.1.2
Author: 		Jonas Doebertin
Author URI: 	http://jonas-doebertin.de
License: 		GPL2
*/

require_once('includes/xmlrpc.inc.php');

class Snipplr_Widget extends WP_Widget{
	
	/* Snippet URL */
	const VIEW_URL = 'http://snipplr.com/view/';
	
	/* API Domain */
	const API_SITE = 'snipplr.com';
	
	/* API end point */
	const API_LOC = '/xml-rpc.php';
	
	/* API function to list users snippets */
	const API_FUNC = 'snippet.list';
	
	/* Constructor */
	function Snipplr_Widget(){
		
		/* Widget settings */
		$widget_ops = array( 'classname' => 'snipplrwidget', 'description' => __('Display your latest Snipplr snippets.', 'snipplrwidget') );
		
		/* Widget control settings */
		$control_ops = array('id_base' => 'snipplrwidget');
		
		/* Create the widget */
		$this->WP_Widget('snipplrwidget', 'Snipplr', $widget_ops, $control_ops);
	}
	
	/* Register the Widget and load its textdomain*/
	function register(){
		register_widget('Snipplr_Widget');
		load_plugin_textdomain('snipplrwidget', null, basename(dirname(__FILE__)).'/languages');
	}
	
	/* Load cache from database */
	function loadCache(){
		return unserialize(get_option('snipplrwidget_cache_'.$this->id));
	}
	
	/* Save cache to database */
	function saveCache($data){
		$cache = array('timestamp' => time(), 'data' => $data);
		update_option('snipplrwidget_cache_'.$this->id, serialize($cache));
	}
	
	/* Reset cache */
	function resetCache(){
		$cache = array('timestamp' => 0);
		update_option('snipplrwidget_cache_'.$this->id, serialize($cache));
	}
	
	/* Convert maximum cache age strings to seconds */
	function getMaxCacheAge($expire, $custom){
		switch($expire){
			case '0':
			case '1800':
			case '3600':
			case '43200':
			case '86400':
				return intval($expire);
			case '-1':
				return (intval($custom) * 60);
		}
	}
	
	/* Get Snippets from API or Cache */
	function getSnippets($api_key, $number, $sortby, $showPrivate, $showFavorite, $maxCacheAge){
		
		/* Check if cached data is still fresh */
		$cache = $this->loadCache();
		if (isset($cache['data']) && (time() - $cache['timestamp'] < $maxCacheAge)){
			
			/* Return data from cache */
			return $cache['data'];
		}
		else{
			
			/* Make API Request */
			$result = XMLRPC_request(Snipplr_Widget::API_SITE, Snipplr_Widget::API_LOC, Snipplr_Widget::API_FUNC, array(XMLRPC_prepare($api_key), XMLRPC_prepare(''), XMLRPC_prepare($sortby), XMLRPC_prepare(''/*$number*/)));
			if(!isset($result[1]['faultCode'])){
				$snippets = array();
				$count = 0;
				if ($sortby == 'random'){
					shuffle($result[1]);
				}
				foreach($result[1] as $snippet){
					if ($count < $number){						
						if (!(!$showFavorite and $snippet['favorite']) and
							!(!$showPrivate and $snippet['private'])){
							array_push($snippets, $snippet);
							$count++;
						}
					}
				}
				$this->saveCache($snippets);
				return $snippets;
			}
			else{
				return false;
			}
		}
	}
	
	/* Widget Frontend Output */
	function widget($args, $instance) {
		extract($args);
		
		/* User Settings */
		$title = apply_filters('snipplrwidget', $instance['title']);
		$api_key = apply_filters('snipplrwidget', $instance['api_key']);
		$number = apply_filters('snipplrwidget', $instance['number']);
		$sortby = apply_filters('snipplrwidget', $instance['sortby']);
		$expire = apply_filters('snipplrwidget', $instance['expire']);
		$showPrivate = $instance['showprivate'];
		$showFavorite = $instance['showfavorite'];
		$customexpire = apply_filters('snipplrwidget', $instance['customexpire']);
		
		/* Before Widget HTML */
		echo $before_widget;
		
		/* Widget Title */
		if($title){
			echo $before_title.$title.$after_title;
		}
		
		$snippets = $this->getSnippets($api_key, $number, $sortby, $showPrivate, $showFavorite, $this->getMaxCacheAge($expire, $customexpire));
		
		if($snippets !== false){
			
			/* Output Snippets */
			echo '<ul class="snipplr">';
			foreach($snippets as $snippet){
				echo '<li><a href="'.Snipplr_Widget::VIEW_URL.$snippet['id'].'" title="'.__('Show on Snipplr:', 'snipplrwidget').' '.$snippet['title'].'">'.$snippet['title'].'</a></li>';
			}

			echo '</ul>';
		}
		else{
			echo '<p>'.__('Error contacting api.', 'snipplrwidget').'</p>';
		}		
		
		/* After Widget HTML */
		echo $after_widget;
	}
	
	/* Widget Configuration Output */
	function form($instance){
		$defaults = array(
			'title' 		=> __('Recent Snippets', 'snipplrwidget'),
			'api_key' 		=> '',
			'number' 		=> 5,
			'sortby' 		=> 'date',
			'expire' 		=> '21600',
			'showprivate'	=> false,
			'showfavorite' 	=> false,
			'customexpire' 	=> '10'
			);
		$instance = wp_parse_args((array) $instance, $defaults);
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'snipplrwidget'); ?></label>
			<input type="text" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $instance['title']; ?>" class="widefat"  />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('api_key'); ?>"><?php _e('Your API key:', 'snipplrwidget'); ?></label>
			<input type="text" id="<?php echo $this->get_field_id('api_key'); ?>" name="<?php echo $this->get_field_name('api_key'); ?>" value="<?php echo $instance['api_key']; ?>" class="widefat"  />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of Snippets to show:', 'snipplrwidget'); ?></label>
			<input type="text" id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" value="<?php echo $instance['number']; ?>" style="width: 20%; display: block;" />
		</p>
		<p>
			<!-- show private snippets -->
			<input type="checkbox" id="<?php echo $this->get_field_id('showprivate'); ?>" name="<?php echo $this->get_field_name('showprivate'); ?>" <?php if ($instance['showprivate']) echo 'checked="checked"'; ?> class="checkbox" />
			<label for="<?php echo $this->get_field_id('showprivate'); ?>"><?php _e('Show private Snippets', 'snipplrwidget'); ?></label>
		</p>
		<p>
			<!-- show favorite snippets -->
			<input type="checkbox" id="<?php echo $this->get_field_id('showfavorite'); ?>" name="<?php echo $this->get_field_name('showfavorite'); ?>" <?php if ($instance['showfavorite']) echo 'checked="checked"'; ?> class="checkbox" />
			<label for="<?php echo $this->get_field_id('showfavorite'); ?>"><?php _e('Show favorite Snippets', 'snipplrwidget'); ?></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('sortby'); ?>"><?php _e('Sort Snippets by:', 'snipplrwidget'); ?></label>
			<select id="<?php echo $this->get_field_id('sortby'); ?>" name="<?php echo $this->get_field_name('sortby'); ?>" class="widefat" style="width: 50%; display: block;">
				<option <?php if ( $instance['sortby'] == 'date' ) echo 'selected="selected"'; ?> value="date"><?php _e('Date', 'snipplrwidget'); ?></option>
				<option <?php if ( $instance['sortby'] == 'title' ) echo 'selected="selected"'; ?> value="title"><?php _e('Title', 'snipplrwidget'); ?></option>
				<option <?php if ( $instance['sortby'] == 'random' ) echo 'selected="selected"'; ?> value="random"><?php _e('Random', 'snipplrwidget'); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('expire'); ?>"><?php _e('Cache Snippets for:', 'snipplrwidget'); ?></label>
			<select id="<?php echo $this->get_field_id('expire'); ?>" name="<?php echo $this->get_field_name('expire'); ?>" class="widefat" style="width: 50%; display: block;">
				<option <?php if ( $instance['expire'] == '0' ) echo 'selected="selected"'; ?> value="0"><?php _e('Never cache', 'snipplrwidget'); ?></option>
				<option <?php if ( $instance['expire'] == '1800' ) echo 'selected="selected"'; ?> value="1800"><?php _e('30 minutes', 'snipplrwidget'); ?></option>
				<option <?php if ( $instance['expire'] == '3600' ) echo 'selected="selected"'; ?> value="3600"><?php _e('1 hour', 'snipplrwidget'); ?></option>
				<option <?php if ( $instance['expire'] == '21600' ) echo 'selected="selected"'; ?> value="21600"><?php _e('6 hours', 'snipplrwidget'); ?></option>
				<option <?php if ( $instance['expire'] == '43200' ) echo 'selected="selected"'; ?> value="43200"><?php _e('12 hours', 'snipplrwidget'); ?></option>
				<option <?php if ( $instance['expire'] == '86400' ) echo 'selected="selected"'; ?> value="86400"><?php _e('1 day', 'snipplrwidget'); ?></option>
				<option <?php if ( $instance['expire'] == '-1' ) echo 'selected="selected"'; ?> value="-1"><?php _e('Custom time', 'snipplrwidget'); ?></option>
			</select>
		</p>
		<?php if($instance['expire'] == '-1'): ?>
		<p>
			<label for="<?php echo $this->get_field_id('customexpire'); ?>"><?php _e('Custom time (minutes):', 'snipplrwidget'); ?></label>
			<input type="text" id="<?php echo $this->get_field_id('customexpire'); ?>" name="<?php echo $this->get_field_name('customexpire'); ?>" value="<?php echo $instance['customexpire']; ?>" style="width: 20%; display: block;" />
		</p>
		<?php else: ?>
			<input type="hidden" id="<?php echo $this->get_field_id('customexpire'); ?>" name="<?php echo $this->get_field_name('customexpire'); ?>" value="<?php echo $instance['customexpire']; ?>" />
		<?php endif; ?>
		<?php
	}
	
	/* Save Settings */
	function update($new_instance, $old_instance) {
		/* update settings */
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['api_key'] = strip_tags($new_instance['api_key']);
		$instance['number'] = strip_tags($new_instance['number']);
		$instance['sortby'] = strip_tags($new_instance['sortby']);
		$instance['expire'] = strip_tags($new_instance['expire']);
		$instance['showprivate'] = strip_tags($new_instance['showprivate']);
		$instance['showfavorite'] = strip_tags($new_instance['showfavorite']);
		$instance['customexpire'] = strip_tags($new_instance['customexpire']);
		
		/* reset cache */
		$this->resetCache();
		
		return $instance;
	}
}

/* Register Widget */
add_action('widgets_init', array('Snipplr_Widget', 'register'));

?>