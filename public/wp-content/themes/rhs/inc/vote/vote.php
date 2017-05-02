<?php
/**
 * Essa classe implementa tudo relacionado ao esquema de votação
 * 
 * * Registra os post status novos
 * * Registra o novo role e capability e as regras para que os usuários ganhem essas permissões
 * * Todas as ações para registrar os votos
 * 
 */ 

Class RHSVote {

    var $tablename;
    
    var $post_status = [];
    
    function __construct() {
        
        global $wpdb;
        $this->tablename = $wpdb->prefix . 'votes';
        
        $this->post_status = $this->get_custom_post_status();
        
        // Hooks
        add_action('init', array(&$this, 'init'));
        add_action('admin_footer-post.php', array(&$this, 'add_status_dropdown'));

        
        add_action('wp_ajax_rhs_vote', array(&$this, 'ajax_vote'));
        add_action('rhs_votebox', array(&$this, 'get_vote_box'), 10, 1);
        add_action('wp_enqueue_scripts', array(&$this, 'addJS'));
        
        add_filter('map_meta_cap', array(&$this, 'vote_post_cap'), 10, 4);
        
        
        /**
         * ROLES
         */ 
        $option_name = 'roles_edited';
        if (!get_option($option_name)) {
            
            // só queremos que isso rode uma vez
            add_option($option_name, true);
            
            global $wp_roles;
            
            $contributor = $wp_roles->get_role('contributor');
            
            // Criamos o role voter copiando as capabilites de author
            $voter = $wp_roles->add_role('voter', 'Votante', $contributor->capabilities);
            
            // Adicionamos a capability de votar a todos os roles que devem
            $voter->add_cap('vote_posts');
            
            $editor = $wp_roles->get_role('editor');
            $editor->add_cap('vote_posts');
            
            $administrator = $wp_roles->get_role('administrator');
            $administrator->add_cap('vote_posts');
            
            
        }
        
        /**
         * DATABASE TABLE
         */
        $option_name = 'database';
        if (!get_option($option_name)) {
            
            // só queremos que isso rode uma vez
            add_option($option_name, true);
            
            $createQ = "
                CREATE TABLE IF NOT EXISTS `$this->tablename` (
                    ID bigint(20) unsigned NOT NULL auto_increment PRIMARY KEY,
                    post_id bigint(20) unsigned NOT NULL default '0',
                    user_id tinytext NOT NULL,
                    vote_date datetime NOT NULL default CURRENT_TIMESTAMP,
                    vote_source varchar(20) NOT NULL default '0.0.0.0'
                )
            ";
            
            $wpdb->query($createQ);
            
        }
        
    }
    
    function get_custom_post_status() {
        return array(
            
            'voting-queue' => array(
                'label'                     => 'Fila de votação',
                'public'                    => false,
                'exclude_from_search'       => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( 'Fila <span class="count">(%s)</span>', 'Fila <span class="count">(%s)</span>' ),
                ),
                
            'voting-expired' => array(
                'label'                     => 'Não promovidos',
                'public'                    => false,
                'exclude_from_search'       => true,
                'show_in_admin_all_list'    => false,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( 'Não promovido <span class="count">(%s)</span>', 'Não promovidos <span class="count">(%s)</span>' ),
                )
            
        );
    }
    
    function addJS() {
        wp_enqueue_script('rhs_vote', get_template_directory_uri() . '/inc/vote/vote.js', array('jquery'));
        wp_localize_script('rhs_vote', 'vote', array('ajaxurl' => admin_url('admin-ajax.php')));
    }
    
    function init() {
        
        // Registra post status
        foreach ($this->post_status as $post_status => $args)
            register_post_status( $post_status, $args );
        
    }
    
    
    function add_status_dropdown(){
        global $post;
        $complete = '';
        $label = '';
        if($post->post_type == 'post'){

            $js = '';
            $change_status_label = false;
            
            foreach ($this->post_status as $post_status => $args) {

                $selected = '';
                
                if($post->post_status == $post_status) {
                    $selected = 'selected';
                    $change_status_label = $args['label'];
                
                }
                
                $js .= '$("select#post_status").append("<option value=\''.$post_status.'\' '.$selected.'>'.$args['label'].'</option>");';

            }
            
            if ($change_status_label !== false)
                $js .= '$("#post-status-display").append("'.$change_status_label.'");';
            
            echo '
                <script>
                    jQuery(document).ready(function($){
                        ' . $js . '
                    });
                </script>
            ';
         }
    }
    
    function vote_post_cap($caps, $cap, $user_id, $args) {
        
        if ($cap == 'vote_post') {

            $caps = array();
            
            $post = get_post($args[0]);
            
            // TODO: Aplicar regras temporais. Só pode votar em post até duas semanas depois q ele foi criado
            
            if ($this->user_has_voted($post->ID, $user_id)) {
                $caps[] = 'vote_posts_again';
            } elseif ($post->post_author == $user_id) {
                $caps[] = 'vote_own_posts';
            } else {
                $caps[] = 'vote_posts';
            }
            
        }
        
        return $caps;
        
    }
    
    function ajax_vote() {
    
        if (current_user_can('vote_post', $post_id)) {
        
            if (isset($_POST['post_id']) && is_numeric($_POST['post_id'])) {
                
                $this->add_vote($_POST['post_id']);
                $this->get_vote_box($_POST['post_id']);
                
            }
        
        }
        
        die;
    
    }
    
    function add_vote($post_id, $user_id = null) {
        
        global $wpdb;
        
        if (is_null($user_id)) {
            $current_user = wp_get_current_user();
            $user_id = $current_user->ID;
        }
        
        // Adiciona voto na table se ainda não houver
        if (!$this->user_has_voted($post_id, $user_id)) {
            $wpdb->insert($this->tablename, array(
                'user_id' => $user_id,
                'post_id' => $post_id,
                'vote_source' => $_SERVER['REMOTE_ADDR']
            ));
        }
        
        $this->update_vote_count($post_id);
        
        // TODO: rotina de promover o post se atingir o numero que precisa
        // TODO: rotina de promover o usuário para votante, se for o primeiro post aprovado dele
        
    }
    
    function update_vote_count($post_id) {
    
        global $wpdb;
        
        $numVotes = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(ID) FROM $this->tablename WHERE post_id = %d", $post_id) );
        
        update_post_meta($post_id, '_total_votes', $numVotes);
    
    }
    
    function get_total_votes($post_id) {
    
        return get_post_meta($post_id, '_total_votes', true);
    
    }
    
    function user_has_voted($post_id, $user_id = null) {
        
        global $wpdb;
        
        if (is_null($user_id)) {
            $current_user = wp_get_current_user();
            $user_id = $current_user->ID;
        }
        
        // Verifica se este usuário já votou neste post
        $vote = $wpdb->get_results( $wpdb->prepare("SELECT ID FROM $this->tablename WHERE user_id = %d AND post_id = %d", $user_id, $post_id) );
        
        return sizeof($vote) > 0;
    
    }
    
    function get_vote_box($post_id, $echo = true) {
    
        $output = '<div id="votebox-'.$post_id.'">';
        $totalVotes = $this->get_total_votes($post_id);
        if (empty($totalVotes))
            $totalVotes = 0;
        
        $output .='<span class="vTexto">' . $totalVotes . '</span>';
        
        
        // TODO: vai haver uma meta capability vote_post,
        // Se o usuário ja votou neste post, não aparece o botão e aparece de alguma maneira que indique q ele já votou
        // Se ele não estiver logado, aparece só o texto "Votos"
        if (current_user_can('vote_post', $post_id)) {
            $output .= '<span class="vTexto"><a class="btn btn-danger js-vote-button" data-post_id="'.$post_id.'">VOTAR</a></span>';
        } else {
            if (is_user_logged_in() && $this->user_has_voted($post_id)) {

                $output .= 'javotou';

            } else {
                $output .= '<span class="vTexto">Votos</span>';
            }
            
        }
        
        $output .= '</div>';
        
        if ($echo)
            echo $output;
        
        return $output;
        
    
    }
    
    
    

}

$RHSVote = new RHSVote();
