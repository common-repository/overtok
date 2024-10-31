<?php 
/*
Plugin Name: Overtok
Plugin URI: https://overtok.com
Description: This plugin will allow you to put your Overtok button on your website
Author: overtok
Version: 0.5
Author URI: https://overtok.com/
*/

class overtok_embed_js {
	const OPTION_PREFIX = 'overtok_';
	const A_SLUG_PREFIX = 'o5k_';
	const WEB_SDK_SRC 	= ['https://assets.overtok.com/web-sdk/overtok.js','https://assets.overtok-qa.com/web-sdk/overtok.js'];
	
	private static $instance = null;
	
	private $web_sdk_src = '';
	
	static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	
	private function __construct() {
		$this->web_sdk_src = (!empty(SELF::WEB_SDK_SRC[0])) ? SELF::WEB_SDK_SRC[0] : '';
		
		add_action( 'admin_menu', [$this,'add_options_page'] );
		add_action( 'admin_init', [$this,'save_options_page'] );
		add_action( 'init', [$this,'embed_code_block'] );
	}
	
	
	public function get_overtok_code(){
		
		return get_option(self::OPTION_PREFIX .'global_code');
	}
	
	public function get_overtok_code_sdk(){
		
		$sdk_i = (int)get_option(self::OPTION_PREFIX .'global_code_sdk_i');
		
		if (array_key_exists($sdk_i,SELF::WEB_SDK_SRC))
			$this->web_sdk_src = SELF::WEB_SDK_SRC[$sdk_i];
		
		
		return $this->web_sdk_src;
	}
	

	public function build_overtok_script(){
		
		$code = $this->get_overtok_code();
		
		if ($code){
			$js_src = (defined('OVERTOK_WEB_SDK_SRC')) ? OVERTOK_WEB_SDK_SRC : $this->get_overtok_code_sdk();
			return "<script>!function(){var e=function(){{$code}},t=document.createElement('script');t.type='text/javascript',t.src='{$js_src}',t.onreadystatechange=e,t.onload=e,document.getElementsByTagName('head')[0].appendChild(t)}();</script>";
		}
		return '';
	}
	
	public function the_overtok_script(){
		
		echo $this->build_overtok_script();
	}
	
	public function content_filter($content){
		
		return $content . "\r\n" . $this->build_overtok_script() . "\r\n";
	}
	
	public function embed_code_block(){
		$overtok_method = get_option(self::OPTION_PREFIX .'global_method');
		
		
		if ($overtok_method == 'footer'){
			add_action('wp_footer',[$this,'the_overtok_script']);
		
		}elseif($overtok_method == 'header'){
			add_action('wp_head',[$this,'the_overtok_script']);
		
		}elseif($overtok_method == 'content'){
			add_filter( 'the_content', [$this,'content_filter'] );
			
		}else{
			
			
		}
		
	}
	
	
	
	
	
	public function sanitize_overtok_code($code){
		
		if (!$code) 
			return false;
		
		//stripslashes_deep
		$code = stripslashes_deep($code);
		
		//take core
		if ( preg_match('/\{(overtokCreate\([^)]+?\))\}/', $code, $matches) ){
			
			//sanitize
			return (!empty($matches[1])) ? sanitize_text_field($matches[1]) : '';
		}
		
		return false;
	}
	
	
	public function save_options_page(){
		//$screen = get_current_screen();
		//if ( $screen->id != settings_page_o5k_overtok )
	}

	public function add_options_page(){
		
		add_options_page( 'Overtok setting page', 'Overtok', 'manage_options', self::A_SLUG_PREFIX.'overtok', [$this,'setting_page']);
	}
	
	public function setting_page(){
		$errormsg = '';
		
		if (!empty($_POST['overtok_nonce']) && wp_verify_nonce( $_POST['overtok_nonce'], 'overtok_save' )){
			
			$overtok_method = (!empty($_POST['overtok_method'])) ? sanitize_key($_POST['overtok_method']) : '';
			update_option( self::OPTION_PREFIX .'global_method', $overtok_method );
			
			
			$overtok_code  = (!empty($_POST['overtok_code'])) ? $this->sanitize_overtok_code($_POST['overtok_code']) : '';
			if ($overtok_code === false){
				$errormsg = 'Please enter valid overtok code';
			}else{
				update_option( self::OPTION_PREFIX .'global_code', $overtok_code );
				
				foreach ((array)SELF::WEB_SDK_SRC as $k=>$v){
					
					if( strpos( $_POST['overtok_code'], $v ) !== false )
						update_option( self::OPTION_PREFIX .'global_code_sdk_i', $k );
					
					
				}
			}
			
				
			//menu_page_url(self::A_SLUG_PREFIX.'overtok',false), 302 and exit;
		}
		
		$overtok_script = $this->build_overtok_script();
		$overtok_method = get_option(self::OPTION_PREFIX .'global_method');
		
		
?>
<div class="wrap">
	<h1>Overtok - Setting</h1>
	
	<form method="post" action="<?php menu_page_url(self::A_SLUG_PREFIX.'overtok'); ?>">
		<?php wp_nonce_field( 'overtok_save', 'overtok_nonce' , false ); ?>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">Overtok code bar</th>
					<td>
						<label for="overtok_code">Please type or paste your <a target="_blank" href="https://overtok.com">Overtok</a> block code here</label>
						<textarea name="overtok_code" rows="5" cols="50" id="overtok_code" class="large-text code"><?php echo esc_textarea($overtok_script); ?></textarea>
						<?php if($errormsg) echo '<p><strong style="color: #a00;">'.$errormsg.'</strong></p>' ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="blogname">Block code method</label></th>
					<td>
						<select class="regular-text" name="overtok_method">
							<option value="">Disable</option>
							<option value="footer" <?php selected( $overtok_method, 'footer' ); ?>>Footer (recommended)</option>
							<option value="content" <?php selected( $overtok_method, 'content' ); ?>>Content</option>
							<option value="header" <?php selected( $overtok_method, 'header' ); ?>>Header</option>
						</select>
					</td>
				</tr>				
				
			</tbody>
		</table>
		<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
		
	</form>	
	
	<hr>
	<h3>Convert inbound calls into additional actions</h3>
	<p>
		Connect business calls from any digital asset with an outstanding
		on-site visual journey that converts more customers and calls
		into valuable actions and new revenue stream.	
	</p>
	
	<h3>Description</h3>
	<p>
	Using <a href="https://overtok.com/">overtok</a> on your website Increases conversion and deliver more leads from your website users.
	</p>
	
	<h3>How It Works</h3>
	<ul>
		<li>After activating Overtok on your Wordpress site or landing page, your visitors can call your business while they stay on-site instead of being sent out to the phone's dialer.</li>
		<li>With Overtok, the call takes place on top of a visual branded dialer. Before the call is connected and after it ends, the caller is exposed to relevant content, offers, and visual call-to-action activities, all of which happens in realtime as part of the native process of the call.</li>
		<li>You'll receive regular calls to the same phone number that you already have.</li>
	</ul>
</div>
<?php
		
	}//END-FN setting_page
	
}//END-CLASS overtok_embed_js

overtok_embed_js::init();
