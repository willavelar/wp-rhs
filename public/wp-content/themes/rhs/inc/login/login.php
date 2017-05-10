<?php

/*
*
* Esta Class implementa as funções necessárias para o uso das reCaptcha.
* Pega a key setada no Painel do Admin (Wordpress).
* Com a Função display_recuperar_captcha() mostra na tela o reCaptcha.
*
*/
class RHSLogin{

    const SITE_KEY = 'captcha_site_key';
    const SECRET_KEY = 'captcha_secret_key';
    const LOGIN_URL = 'login';
    const LOST_PASSWORD_URL = 'lostpassword';
    const REGISTER_URL = 'register';

    static $instance;

    function __construct(){

        if( empty (self::$instance)){
            add_action('wp_enqueue_scripts', array(&$this, 'API_reCAPTCHA'));
            add_action("admin_menu", array(&$this, "no_captcha_recaptcha_menu"));
            add_action("admin_init", array(&$this, "display_recaptcha_options"));
            add_action( "recuperar-senha_form", array(&$this, "display_recuperar_captcha" ));
            add_filter("lostpassword_url", array(&$this, "verify_recuperar_captcha"), 10, 2);
            add_filter("login_url", array(&$this, "login_url"), 10, 3);
            add_filter("lostpassword_url", array(&$this, "lostpassword_url"), 10, 2);
            add_filter("login_redirect", array(&$this, "login_redirect"), 10, 3);
            add_filter("register_url", array(&$this, "register_url"));
            add_filter( 'wp_login_errors', array(&$this, 'login_errors'), 10, 2 );
            add_action('init', array(&$this, 'check_session'), 1);
            
            add_action( 'generate_rewrite_rules', array( &$this, 'rewrite_rules' ), 10, 1 );
			add_filter( 'query_vars', array( &$this, 'rewrite_rules_query_vars' ) );
			add_filter( 'template_include', array( &$this, 'rewrite_rule_template_include' ) );
            
        }
        self::$instance = true;
    }
    
    function login_url($login_url, $redirect, $force_reauth) {
        $login_page = home_url( self::LOGIN_URL );
        $login_url = add_query_arg( 'redirect_to', $redirect, $login_page );
        return $login_url;
    }
    
    function register_url($url) {
        return home_url( self::REGISTER_URL );
    }
    
    function lostpassword_url($login_url, $redirect, $force_reauth = '') {
        $lost_page = home_url( self::LOST_PASSWORD_URL );
        $login_url = add_query_arg( 'redirect_to', $redirect, $lost_page );
        return $login_url;
    }
    
    function login_redirect($redirect_to, $requested_redirect_to, $user) {
        if (empty($redirect_to)) {
            //TODO verificar role do usuário para enviar para a página apropriada
            $redirect_to = admin_url();
        }
        return $redirect_to;
    }
    
    function rewrite_rules( &$wp_rewrite ) {
		$new_rules         = array(
			self::LOGIN_URL . "/?$" => "index.php?rhs_custom_login=1&rhs_login_tpl=" . self::LOGIN_URL,
			self::REGISTER_URL . "/?$"     => "index.php?rhs_custom_login=1&rhs_login_tpl=" . self::REGISTER_URL,
			self::LOST_PASSWORD_URL . "/?$"     => "index.php?rhs_custom_login=1&rhs_login_tpl=" . self::LOST_PASSWORD_URL,
		);
		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;

	}

	function rewrite_rules_query_vars( $public_query_vars ) {

		$public_query_vars[] = "rhs_custom_login";
		$public_query_vars[] = "rhs_login_tpl";

		return $public_query_vars;

	}

	function rewrite_rule_template_include( $template ) {
		global $wp_query;

		if ( $wp_query->get( 'rhs_login_tpl' ) ) {

			if ( file_exists( STYLESHEETPATH . '/' . $wp_query->get( 'rhs_login_tpl' ) . '.php' ) ) {
				return STYLESHEETPATH . '/' . $wp_query->get( 'rhs_login_tpl' ) . '.php';
			}

		}

		return $template;


	}

    function API_reCAPTCHA(){
        wp_enqueue_script( 'reCAPTCHA_API', 'https://www.google.com/recaptcha/api.js', true );
    }

    function no_captcha_recaptcha_menu() {
        add_menu_page("Opções reCaptcha", "Opções reCaptcha", "manage_options", "recaptcha-options", array(&$this, "recaptcha_options_page"), "", 100);
    }
    function recaptcha_options_page() { ?>
        <div class="wrap">
            <h1>reCaptcha Options</h1>
            <form method="post" action="options.php">
            <?php 
                settings_fields("header_section");
                do_settings_sections("recaptcha-options");
                submit_button(); 
            ?>
            </form>
        </div>
    <?php }
    
    function display_recaptcha_options() {
        add_settings_section("header_section", "Keys", array(&$this, "display_recaptcha_content"), "recaptcha-options");
        add_settings_field('captcha_site_key', __("Site Key"), array(&$this, "display_captcha_site_key_element"), "recaptcha-options", "header_section");
        add_settings_field("captcha_secret_key", __("Secret Key"), array(&$this, "display_captcha_secret_key_element"), "recaptcha-options", "header_section");
        register_setting("header_section", self::SITE_KEY);
        register_setting("header_section", self::SECRET_KEY);
    }

    function display_recaptcha_content() {
        echo __('<p>You need to <a href="https://www.google.com/recaptcha/admin" rel="external">register you domain</a> and get keys to make this plugin work.</p>');
        echo __("Enter the key details below");
    }

    function display_captcha_site_key_element() { ?>
        <input type="text" name="captcha_site_key" id="captcha_site_key" value="<?php echo get_option(self::SITE_KEY); ?>" />
    <?php }

    function display_captcha_secret_key_element() { ?>
        <input type="text" name="captcha_secret_key" id="captcha_secret_key" value="<?php echo get_option(self::SECRET_KEY); ?>" />
    <?php }
    
    /*reCAPTCHA Recuperar Pass*/

    function display_recuperar_captcha() { ?>
        <div class="g-recaptcha" data-sitekey="<?php echo get_option(self::SITE_KEY); ?>"></div>
    <?php }
    
    function verify_recuperar_captcha($user, $password) {
        if (isset($_POST['g-recaptcha-response'])) :
            $recaptcha_secret = get_option(self::SECRET_KEY);
            $response = wp_remote_get("https://www.google.com/recaptcha/api/siteverify?secret=". $recaptcha_secret ."&response=". $_POST['g-recaptcha-response']);
            $response = json_decode($response["body"], true);
            if (true == $response["success"])
                return $user;
            else
                return new WP_Error("Captcha Invalid", __("<strong>ERROR</strong>: You are a bot"));
        else :
            return new WP_Error("Captcha Invalid", __("<strong>ERROR</strong>: You are a bot. If not then enable JavaScript"));
        endif;
    }

    function login_errors($errors, $redirect_to){

        $_SESSION['login_errors'] = '';

        if($errors instanceof WP_Error && !empty($errors->errors)){

            if($errors->errors){
                $_SESSION['login_errors'] = $errors->get_error_messages();
            }

            wp_redirect(esc_url( home_url( '/login' ) ));
            exit;
        }

        return $errors;
    }


    function check_session() {
        if(!session_id()) {
            session_start();
        }
    }

    function lostpassword(){

        $errors = array();

        if($_POST['user_login']){
            $errors = $this->retrieve_password();
            $errors = $errors->get_error_messages();


        }

        return $errors;

    }

    function retrieve_password() {
        $errors = new WP_Error();

        if ( empty( $_POST['user_login'] ) ) {
            $errors->add('empty_username', __('<strong>ERROR</strong>: Enter a username or email address.'));
        } elseif ( strpos( $_POST['user_login'], '@' ) ) {
            $user_data = get_user_by( 'email', trim( wp_unslash( $_POST['user_login'] ) ) );
            if ( empty( $user_data ) )
                $errors->add('invalid_email', __('<strong>ERROR</strong>: There is no user registered with that email address.'));
        } else {
            $login = trim($_POST['user_login']);
            $user_data = get_user_by('login', $login);
        }

        /**
         * Fires before errors are returned from a password reset request.
         *
         * @since 2.1.0
         * @since 4.4.0 Added the `$errors` parameter.
         *
         * @param WP_Error $errors A WP_Error object containing any errors generated
         *                         by using invalid credentials.
         */
        do_action( 'lostpassword_post', $errors );

        if ( $errors->get_error_code() )
            return $errors;

        if ( !$user_data ) {
            $errors->add('invalidcombo', __('<strong>ERROR</strong>: Invalid username or email.'));
            return $errors;
        }

        // Redefining user_login ensures we return the right case in the email.
        $user_login = $user_data->user_login;
        $user_email = $user_data->user_email;
        $key = get_password_reset_key( $user_data );

        if ( is_wp_error( $key ) ) {
            return $key;
        }

        $message = __('Someone has requested a password reset for the following account:') . "\r\n\r\n";
        $message .= network_home_url( '/' ) . "\r\n\r\n";
        $message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
        $message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
        $message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
        $message .= '<' . network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login') . ">\r\n";

        if ( is_multisite() ) {
            $blogname = get_network()->site_name;
        } else {
            /*
             * The blogname option is escaped with esc_html on the way into the database
             * in sanitize_option we want to reverse this for the plain text arena of emails.
             */
            $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        }

        /* translators: Password reset email subject. 1: Site name */
        $title = sprintf( __('[%s] Password Reset'), $blogname );

        /**
         * Filters the subject of the password reset email.
         *
         * @since 2.8.0
         * @since 4.4.0 Added the `$user_login` and `$user_data` parameters.
         *
         * @param string  $title      Default email title.
         * @param string  $user_login The username for the user.
         * @param WP_User $user_data  WP_User object.
         */
        $title = apply_filters( 'retrieve_password_title', $title, $user_login, $user_data );

        /**
         * Filters the message body of the password reset mail.
         *
         * @since 2.8.0
         * @since 4.1.0 Added `$user_login` and `$user_data` parameters.
         *
         * @param string  $message    Default mail message.
         * @param string  $key        The activation key.
         * @param string  $user_login The username for the user.
         * @param WP_User $user_data  WP_User object.
         */
        $message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );

        if ( $message && !wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) ) {
            $errors->add( 'hostoffiline', __( 'The email could not be sent.' ) . "<br />\n" . __( 'Possible reason: your host may have disabled the mail() function.' ) );
            return $errors;
        }
        return true;
    }
    
}

global $RHSLogin;
$RHSLogin = new RHSLogin();
