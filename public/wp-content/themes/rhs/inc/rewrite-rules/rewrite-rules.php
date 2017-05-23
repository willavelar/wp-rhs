<?php

class RHSRewriteRules {

    private static $instance;
    const LOGIN_URL = 'logar';
    const LOST_PASSWORD_URL = 'esqueceu-a-senha';
    const RETRIEVE_PASSWORD_URL = 'recuperar-senha';
    const RESET_PASS_URL = 'resetar-senha';
    const REGISTER_URL = 'registrar';
    const RP_URL = 'rp';
    const VOTING_QUEUE_URL = 'fila-de-votacao';
    const PROFILE_URL = 'perfil';
    const POST_URL = 'publicar-postagem';

    function __construct() {

        if(is_user_logged_in())
            return home_url();

        if ( empty ( self::$instance ) ) {
            add_action( 'generate_rewrite_rules', array( &$this, 'rewrite_rules' ), 10, 1 );
            add_filter( 'query_vars', array( &$this, 'rewrite_rules_query_vars' ) );
            add_filter( 'template_include', array( &$this, 'rewrite_rule_template_include' ) );
        }

        self::$instance = true;
    }

    function rewrite_rules( &$wp_rewrite ) {
        $new_rules         = array(
            self::LOGIN_URL . "/?$"             => "index.php?rhs_custom_login=1&rhs_login_tpl=" . self::LOGIN_URL,
            self::REGISTER_URL . "/?$"          => "index.php?rhs_custom_login=1&rhs_login_tpl=" . self::REGISTER_URL,
            self::LOST_PASSWORD_URL . "/?$"     => "index.php?rhs_custom_login=1&rhs_login_tpl=" . self::LOST_PASSWORD_URL,
            self::RETRIEVE_PASSWORD_URL . "/?$" => "index.php?rhs_custom_login=1&rhs_login_tpl=" . self::RETRIEVE_PASSWORD_URL,
            self::RESET_PASS_URL . "/?$"            => "index.php?rhs_custom_login=1&rhs_login_tpl=" . self::RESET_PASS_URL,
            self::RP_URL . "/?$"            => "index.php?rhs_custom_login=1&rhs_login_tpl=" . self::RP_URL,
            self::VOTING_QUEUE_URL . "/?$"            => "index.php?rhs_custom_login=1&rhs_login_tpl=" . self::VOTING_QUEUE_URL,
            self::PROFILE_URL . "/?$"            => "index.php?rhs_custom_login=1&rhs_login_tpl=" . self::PROFILE_URL,
            self::POST_URL . "/?$"            => "index.php?rhs_custom_login=1&rhs_login_tpl=" . self::POST_URL

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

}

global $RHSRewriteRules;
$RHSRewriteRules = new RHSRewriteRules();