<?php

class RHSSearch {

    const BASE_URL = 'busca';
    const BASE_USERS_URL = 'busca/usuarios';

    function __construct() {
        add_action('pre_get_posts', array(&$this, 'pre_get_posts'), 2);
    }

    function pre_get_posts(&$wp_query) {

        if ( $wp_query->is_main_query() && $wp_query->get( 'rhs_busca' ) == 'posts' ) {

            $wp_query->is_home = false;

            $keyword =      $this->get_param('keyword');
            $uf =           $this->get_param('uf');
            $municipio =    $this->get_param('municipio');
            $date_from =    $this->get_param('date_from');
            $date_to =      $this->get_param('date_to');
            $order =        $this->get_param('rhs_order');

            /**
            * Tags e categorias são buscadas automaticamente passando os parametros padrão do WP
            * Ex: &cat=3&tag=2
            *
            * A informação de paginação também já é magicamente tratada pelo WP, pq jogamos ela pra query_var 'paged' lá na rewrite rule
            */

            if (!empty($keyword)) {
                $wp_query->set('s', $keyword);
            }

            // DATAS
            if (!empty($date_from) || !empty($date_to)) {
                $date_query = [];
                $has_date_query = false;

                if (!empty($date_from) && $_datefrom = $this->parse_date($date_from)) {
                    $date_query['after'] = $_datefrom;
                    $has_date_query = true;
                }

                if (!empty($date_to) && $_dateto = $this->parse_date($date_to)) {
                    $date_query['before'] = $_dateto;
                    $has_date_query = true;
                }

                if ($has_date_query) {
                    $date_query['inclusive'] = true;
                    $wp_query->set('date_query', [$date_query]);
                }

            }

            $meta_query = [];
            $has_meta_query = false;

            // ESTADOS E MUNICIPIOS
            if (!empty($uf) || !empty($municipio)) {

                if (!empty($municipio)) {

                    // podemos passar só o ID do município, ex: 2900702
                    // ou o formato da URL, com {id}-{slug}, ex: 2900702-alagoinhas
                    preg_match('/^([0-9]{7})(-.+)?$/', $municipio, $cod_municipio);

                    if (is_numeric($cod_municipio[1])) {
                        $meta_query['municipio_clause'] = [
                            'key' => UFMunicipio::MUN_META,
                            'value' => $cod_municipio[1],
                            'compare' => '='
                        ];
                        $has_meta_query = true;
                    }


                }

                if (!empty($uf) && !isset($meta_query['municipio']) /* se já tem municipio não precisa filtrar por estado tb */ ) {

                    $cod_uf = is_numeric($uf) ? $uf : UFMunicipio::get_uf_id_from_sigla($uf);
                    
                    if (is_numeric($cod_uf)) {
                        $meta_query['uf_clause'] = [
                            'key' => UFMunicipio::UF_META,
                            'value' => $cod_uf,
                            'compare' => '='
                        ];
                        $has_meta_query = true;
                    }

                }

            }

            // ORDER
            switch ($order) {
                case 'comments':
                    $q_order = 'DESC';
                    $q_order_by = 'comment_count';
                    break;

                // META KEYS
                case 'votes':
                    $q_order_meta = RHSVote::META_TOTAL_VOTES;
                    break;
                case 'shares':
                    $q_order_meta = RHSNetwork::META_KEY_TOTAL_SHARES;
                    break;
                case 'views':
                    $q_order_meta = RHSNetwork::META_KEY_VIEW;
                    break;

                case 'date':
                default:
                    $q_order = 'DESC';
                    $q_order_by = 'post_date';
                    break;
            }

            if (!empty($q_order_meta)) {
                $meta_query['rhs_meta_order'] = [
                    'key' => $q_order_meta,
                    'compare' => 'EXISTS',
                    'type' => 'numeric'
                ];
                $has_meta_query = true;
                $q_order_by = ['rhs_meta_order' => 'DESC'];
                $q_order = 'DESC';
            }

            if ($has_meta_query) {
                $meta_query['relation'] = 'AND';
                $wp_query->set('meta_query', [$meta_query]);
            }

            $wp_query->set('order', $q_order);
            $wp_query->set('orderby', $q_order_by);

        }

    }
    
    /**
     * Faz parse de uma string de data no formato YYYY-MM-DD e retorna 
     * um array no formato utilizado nas meta_queries do WP.
     *
     * Se a string não for no formato esperado, retorna False.
     * 
     * @param  string $str_date string no formato YYYY-MM-DD
     * @return array|false           array composto de de três elementos, com as chaves year, month e day e seus respectivos valores. Falso caso a string não seja no formato esperado
     */
    private function parse_date($str_date) {

        preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $str_date, $matches);

        if (isset($matches[1]) && isset($matches[2]) && isset($matches[3])) {
            return [
                'year' => $matches[1],
                'month' => $matches[2],
                'day' => $matches[3]
            ];
        }

        return false;

    }
    
    /**
     * Retorna o valor do parâmetro, que pode estar sendo passado via URL (rewrite rule)
     * ou diretamente via query string. 
     * 
     * Por exemplo, a função retorna o valor para 'uf' e para 'paged' nos dois formatos:
     * /busca/BA/page/2
     * /busca?uf=BA&paged=2
     * 
     * @param  string $param o nome do parâmetro
     * @return string        o valor do parâmetro
     */
    public function get_param($param) {
        if (isset($_GET[$param]))
            return $_GET[$param];
        return get_query_var($param);
    }
    

    /**
     * Busca usuários com filtros específicos da RHS
     *
     * Pode-se chamar o método sem nenhum parâmetro (como no template search-users.php). Neste Cadastro
     * serão usados os parâmetros disponíveis na URL de busca.
     * 
     * Opcionalmente pode-se chamar o método passando manualmente os filtros de busca em um array.
     *
     * Os parâmetros possíveis são:
     * $params = array(
     *     'uf' => 'BA', // pode ser a sigla ou id do estado
     *     'municipio' => '2922999' // pode ser o id ou url do municipio (ex: 2922999-nome-da-cidade)
     *     'keyword' => 'aless' // string de busca por nome do usuário
     *     'paged' => 2 // número da página para paginação de resultados,
     *     'rhs_order' => 'votes' // ordenação dos resultados. Valores possíveis são: name, register_date, posts, votes
     * )
     *
     * Exemplos de URs que funcionam:
     * /busca/usuarios/BA
     * /busca/usuarios/BA/page/2/?keyword=caetano
     * /busca/usuarios/?uf=BA&keyword=caetano&paged=2&rhs_order=votes
     * /busca/usuarios/?uf=29&keyword=caetano&paged=2&rhs_order=votes
     *
     * 
     * @param  array  $params opcional, os filtros de busca
     * @return Object WP_User_Query 
     */
    public function search_users($new_params = array(), $paged) {
        $users_per_page = '10';
        $meta_query = [];
        $has_meta_query = false;
        
        $keyword =      $this->get_param('keyword');
        $uf =           $this->get_param('uf');
        $municipio =    $this->get_param('municipio');
        $rhs_order =    $this->get_param('rhs_order');
        
      
        if (!empty($uf) || !empty($municipio)) {
            if (!empty($municipio)) {
                // podemos passar só o ID do município, ex: 2900702
                // ou o formato da URL, com {id}-{slug}, ex: 2900702-alagoinhas
                preg_match('/^([0-9]{7})(-.+)?$/', $municipio, $cod_municipio);
                if (is_numeric($cod_municipio[1])) {
                    $meta_query['_municipio'] = [
                        'key' => UFMunicipio::MUN_META,
                        'value' => $cod_municipio[1],
                        'compare' => '='
                    ];
                    $cod_municipio = $cod_municipio[1];
                    $has_meta_query = true;
                }

            }

            if (!empty($uf) && !isset($meta_query['municipio']) /* se já tem municipio não precisa filtrar por estado tb */ ) {           
                $cod_uf = is_numeric($uf) ? $uf : UFMunicipio::get_uf_id_from_sigla($uf);
                if (is_numeric($cod_uf)) {
                    $meta_query['_uf'] = [
                        'key' => UFMunicipio::UF_META,
                        'value' => $cod_uf,
                        'compare' => '='
                    ];
                    $has_meta_query = true;
                }
            }
        }
        
        $q_has_publish_posts = false;

        switch ($rhs_order) {
            case 'name':
                $q_order = 'ASC';
                $q_order_by = 'display_name';
                echo "entrou em name";
                    break;
            
            case 'register_date':
                $q_order = 'DESC';
                $q_order_by = 'registered';
                break;
            
            case 'posts':
                $q_order = 'DESC';
                $q_order_by = 'post_count';
                $q_has_publish_posts = true;
                break;
                
            // TODO
            case 'votes':
                $q_order_meta = RHSVote::META_TOTAL_VOTES;
                break;

            default:
                $q_order = 'ASC';
                $q_order_by = 'post_date';
                break;
        }

        if (!empty($q_order_meta)) {
            $meta_query['rhs_order'] = [
                'key' => $q_order_meta,
                'compare' => 'EXISTS',
                'type' => 'numeric'
            ];
            $has_meta_query = true;
            $q_order_by = ['rhs_order' => 'DESC'];
            $q_order = 'DESC';
        }

        $offset = $users_per_page * ($paged - 1);
        $cod_uf = ($uf) ? $uf : '' ;
        $cod_municipio = ($municipio) ? $municipio : '' ;
        
        $filters = array(
            'role'       => 'contributor',
            'order'      => $q_order,
            'orderby'    => $q_order_by,
            'search'     => '*' . esc_attr($this->get_param('keyword')) . '*',
            'paged'     => $paged,
            'number'    => $users_per_page,
            'offset'    => $offset,
            'has_published_posts' => $q_has_publish_posts,
            'meta_query' => array(
                    array(
                        'key'     => '_uf',
                        'value'   => $cod_uf,
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key'     => '_municipio',
                        'value'   => $cod_municipio,
                        'compare' => 'LIKE'
                    )
            )
         );     
        
        // Retorna o objeto com a lista de usuários encontrados
        return new WP_User_Query($filters);      
        
    }

    /**
     * Show pagination 
     * 
     * @return mixed Return html with paginate links
     */
    function show_users_pagination($paged) {
        // TODO
        $users_per_page = '10';
        $query_objects = $this->search_users([],$paged);
        $total_pages = 1;
        $total_pages = ceil($query_objects->total_users / $users_per_page);
        
        $big = 999999999;
        $content = paginate_links( array(
            'base'         => str_replace($big, '%#%', get_pagenum_link($big)),
            'format'       => '/page/%#%',
            'prev_text'    => __('&laquo; Anterior'),
            'next_text'    => __('Próxima &raquo;'), 
            'total'        => $total_pages,
            'current'      => $paged,
            'end_size'     => 1,
            'type'         => 'array',
            'mid_size'     => 8,
            'prev_next'    => true,
        ));
        
        if (is_array($content)) {
            $current_page = ($this->get_param('paged') == 0) ? 1 : $this->get_param('paged');
            echo '<ul class="pagination">';
            foreach ($content as $i => $page) {
                if ($current_page == 1 && $i == 0) {
                    echo "<li class='active'>$page</li>";
                } else {
                    if ($current_page != 1 && $current_page == $i) {
                        echo "<li class='active'>$page</li>";
                    } else {
                        echo "<li>$page</li>";
                    }
                }
            }
            echo '</ul>';
        }
    }
}

global $RHSSearch;
$RHSSearch = new RHSSearch();
