<?php
/**
 * Tema para exibir Comments.
 *
 * A área da página que contém os comentários atuais
 * E o formulário de comentário. A exibição real dos comentários é
 * Manipulado por um callback em RHS_comment () que é
 * Localizado no arquivo functions.php.
 *
 * @package WordPress
 * @subpackage RHS
 */

if (post_password_required()) {
    return;
} ?>
<div class="row">
	<!-- Container -->
	<div class="col-xs-12 col-md-12" id="comments">
		<!--show the form-->
		<h2 class="titulo-quantidade text-uppercase"><i class="fa fa-commenting-o" aria-hidden="true"></i> <?php comments_number(__('não há Comentários', 'rhs'), __('1 Comentário','rhs'), __('% Comentários','rhs') );?></h2>
		<?php if('open' == $post->comment_status) : ?>
			<div id="respond" class="clearfix">        
			    <?php if(get_option('comment_registration') && !$user_ID) : ?>
					<p>
					<?php printf( __( 'Você precisa está %sloggedin%s para postar um comentário.', 'rhs'), "<a href='" . get_option('siteurl') . "/wp-login.php?redirect_to=" . urlencode(get_permalink()) ."'>", "</a>" ); ?>
					</p>        
			    <?php else : ?>
			    <form action="<?php echo get_option('siteurl'); ?>/wp-comments-post.php" method="post" id="form-comentario" class="clearfix">
			        <?php comment_id_fields(); ?>             
			        <div class="form-group">         
						<?php
							wp_editor( 'Escreva seu Comentário.', 'comment', array(
						        'media_buttons' => true, // show insert/upload button(s) to users with permission
						        'textarea_rows' => '7', // re-size text area
						        'dfw' => false, // replace the default full screen with DFW (WordPress 3.4+)
						        'tinymce' => array(
						            'theme_advanced_buttons1' => 'bold,italic,underline,strikethrough,bullist,numlist,code,blockquote,link,unlink,outdent,indent,|,undo,redo,fullscreen'
						        ),
						        'quicktags' => array(
						           'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close'
						        )
						    ) );
						?>
					</div>         
					<button id="submit" class="btn btn-info btn-comentar" type="submit" name="submit">Comentar</button>
					 <?php cancel_comment_reply_link('Cancelar'); ?>
			        <?php if(get_option("comment_moderation") == "1") : ?>
			        <?php _e('Todos os comentarios precisam ser aprovados.', 'rhs'); ?>
			        <?php endif; ?>
			        <?php do_action('comment_form', $post->ID); ?>
			    </form>
			    <?php endif; ?>
			</div>
		<?php endif; ?>

	    <?php if (have_comments()) : ?>

            <?php wp_list_comments(array('callback' => 'RHS_Comentarios')); ?>

	        <?php if (get_comment_pages_count() > 1 && get_option('page_comments')) : ?>
	            <nav id="comment-nav-below" class="navigation" role="navigation">
	                <div class="nav-previous">
	                    <?php previous_comments_link( _e('&larr; Anterior', 'rhs')); ?>
	                </div>
	                <div class="nav-next">
	                    <?php next_comments_link(_e('Próximo &rarr;', 'rhs')); ?>
	                </div>
	            </nav>
	        <?php endif; // check for comment navigation ?>

	        <?php elseif (!comments_open() && '0' != get_comments_number() && post_type_supports(get_post_type(), 'comments')) : ?>
	            <p class="nocomments"><?php _e('Comentarios está fechado.', 'rhs'); ?></p>
	    <?php endif; ?>
	</div>
</div>

<script type="text/javascript">
	jQuery(function($){
		$('.comment-reply-link').click(function(e){
			e.preventDefault();
			var args = $(this).data('onclick');
			args = args.replace(/.*(|)/gi, '').replace(/"|s+/g, '');
			args = args.split(',');
			tinymce.EditorManager.execCommand('mceRemoveControl', true, 'comment');
			addComment.moveForm.apply( addComment, args );
			tinymce.EditorManager.execCommand('mceAddControl', true, 'comment');
		});
	});
</script>