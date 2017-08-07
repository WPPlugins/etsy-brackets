<?php
/*
Plugin Name: Etsy Brackets
Plugin URI: http://99bots.com/products/plugins/etsy-brackets/
Description: Inserts Etsy products in post or page using bracket/shortcode method. Enables Etsy users to share their products through blog posts.
Author: Aaron Trank (99bots)
Version: 1.0
Author URI: http://99bots.com
*/ 

define('ETSY_BRACKETS_PLUGIN_DIR', 'etsy-brackets');
define('ETSY_CACHE_LIFE',  21600);//6 hours in seconds

$etsy_stag = "[etsy-id=";
$etsy_etag = "]";

$etsy_api_key_default = "qgqnb597e2qhxugwztjsuqf6";

$etsy_listing_id = "";
$etsy_base_url = "http://beta-api.etsy.com/v1";
$etsy_command_uri = "/listings/";
$etsy_callback = "?callback=getData";
$etsy_api_key = "?api_key=";
$etsy_optional_params = "&detail_level=medium";

//Complements of YouTube Brackets
function etsy_brackets_post($the_content)
{
    GLOBAL $etsy_stag, $etsy_etag, $etsy_listing_id;

    $spos = strpos($the_content, $etsy_stag);
    if ($spos !== false)
    {
        $epos = strpos($the_content, $etsy_etag, $spos);
        $spose = $spos + strlen($etsy_stag);
        $slen = $epos - $spose;
        $tagargs = substr($the_content, $spose, $slen);
        
        $the_args = explode(" ", $tagargs);
        
        if (sizeof($the_args) == 1)
        {
            $etsy_listing_id = $tagargs;
			$width = 425;
			$height = 350;
            $tags = generate_etsy_tags($etsy_listing_id,$width,$height);
            $new_content = substr($the_content,0,$spos);
            $new_content .= $tags;
            $new_content .= substr($the_content,($epos+1));
        }
		
		else if (sizeof($the_args) == 3)
        {
            list($etsy_listing_id,$width,$height) = explode(" ", $tagargs);
            $tags = generate_etsy_tags($etsy_listing_id,$width,$height);
            $new_content = substr($the_content,0,$spos);
            $new_content .= $tags;
            $new_content .= substr($the_content,($epos+1));
        }
        if ($epos+1 < strlen($the_content))
        {
            $new_content = etsy_brackets_post($new_content);
        }
        return $new_content;
    }
    else
    {
        return $the_content;
    }
}


function generate_etsy_tags($etsy_listing_id, $width, $height, $poster = "", $autoplay = "false", $controller = ""){
	GLOBAL $etsy_base_url, $etsy_command_uri, $etsy_callback, $etsy_api_key, $etsy_optional_params;
	$listing = etsy_brackets_getEtsyListing($etsy_listing_id);
	$title = $listing->title;
	if (strlen($title) > 18) {
		$title = substr($title, 0, 17);
		$title .= "...";
	}

	$script_tags = 	'
		<div class="listing-card" id="' . $etsy_listing_id . '">
			<a title="' . $listing->title . '" href="' . $listing->url . '" class="listing-thumb">
				<img height="125" width="155" alt="' . $listing->title . '" src="' . $listing->image_url_170x135 . '">        	
			</a>
			<div class="listing-detail">
				<p class="listing-title">
					<a title="' . $listing->title . '" href="' . $listing->url . '">'.$title.'</a>
	        	</p>
	        	<p class="listing-maker">
	                <a title="Check out '.$listing->user_name.'\'s store" href="http://www.etsy.com/shop/'.$listing->user_name.'">'.$listing->user_name.'</a>
	        	</p>
			</div>
			<p class="listing-price">$'.$listing->price.' <span class="currency-code">'.$listing->currency_code.'</span></p>
		</div>';    
	return $script_tags;
}


function etsy_brackets_custom_css() {
  $link = get_bloginfo('wpurl') . '/wp-content/plugins/' . ETSY_BRACKETS_PLUGIN_DIR . '/etsy-brackets.css';
  wp_register_style('etsy_brackets_style', $link);
  wp_enqueue_style('etsy_brackets_style');
}


function etsy_brackets_queryEtsy( $etsy_listing_id, $cache_file ) {
	GLOBAL $etsy_base_url, $etsy_command_uri, $etsy_callback, $etsy_api_key, $etsy_optional_params;
	if(get_option("etsy_api_key_default")) {
		$etsy_api_key_default = get_option("etsy_api_key_default");
	} 
	$url = $etsy_base_url . $etsy_command_uri . "{$etsy_listing_id}" . $etsy_api_key . $etsy_api_key_default . $etsy_optional_params;
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response_body = curl_exec($ch);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if (intval($status) != 200) die("Error: $response_body");
	
	$tmp_file = $cache_file.rand().'.tmp';
	file_put_contents($tmp_file, $response_body);
	rename($tmp_file, $cache_file);
	return $response_body;
}

function etsy_brackets_getEtsyListing( $etsy_listing_id ){
	$parsed = parse_url(get_bloginfo('home'));
	$cache_file = sys_get_temp_dir().'/etsy'.$etsy_listing_id.'_cache_'.$parsed['host'].'.json';
	
	$data;

	if (!file_exists($cache_file) or (time() - filemtime($cache_file) >= ETSY_CACHE_LIFE)){
		$data = etsy_brackets_queryEtsy( $etsy_listing_id, $cache_file );
	}else{
		$data = file_get_contents($cache_file);
	}
	
	$items = json_decode($data);
	return $items->results[0];
}

// Title of page, Name of option in menu bar, Which function prints out the html
function etsy_brackets_options() {
	add_options_page(__('Etsy Brackets Options'), __('Etsy Brackets'), 5, basename(__FILE__), 'etsy_brackets_options_page');
}

// HTML Options Page
function etsy_brackets_options_page() {

	// Default username if none is specified
	global $etsy_api_key_default;

	// did the user enter a new/changed location?
	if (isset($_POST['etsy_api_key_default'])) {
		$etsy_api_key_default = $_POST['etsy_api_key_default'];
		update_option('etsy_api_key_default', $etsy_api_key_default);
		// and remember to note the update to user
		$updated = true;
	}

	// Grab the latest value for the users Etsy API key
	if(get_option('etsy_api_key_default')) {
		$etsy_api_key_default = get_option('etsy_api_key_default');
	} else {
		add_option('etsy_api_key_default', $etsy_api_key_default, "My Etsy Developer API Key", "yes");
	}

	if ($updated) {
		echo '<div class="updated"><p><strong>Options saved.</strong></p></div>';
	}

	// Print the Options Page w/ form
	?>
	<div class="wrap">
		<h2>Etsy Developer API Key</h2>
		<form name="form1" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
			<fieldset class="options">
                    <input id="etsy_api_key_default" name="etsy_api_key_default" value="<? echo get_option('etsy_api_key_default'); ?>" />
			</fieldset>
			<p class="submit">
				<input type="submit" name="etsy_api_key_default" value="Update Options &raquo;" />
			</p>
	  	</form>
  	</div>
<?php
}
// Options submenu
add_action('admin_menu', 'etsy_brackets_options');
add_action('wp_print_styles', 'etsy_brackets_custom_css');
add_filter('the_content', 'etsy_brackets_post');
add_filter('the_excerpt','etsy_brackets_post');
?>