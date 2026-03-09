<?php

class Bulk_Content_Cleaner_Admin {

    private $plugin_name;

    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles( $hook ) {
        if ( 'toplevel_page_bulk-content-cleaner' !== $hook ) {
            return;
        }
        wp_enqueue_style(
            $this->plugin_name,
            BCC_PLUGIN_URL . 'admin/assets/css/bulk-content-cleaner-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts( $hook ) {
        if ( 'toplevel_page_bulk-content-cleaner' !== $hook ) {
            return;
        }
        wp_enqueue_script(
            $this->plugin_name,
            BCC_PLUGIN_URL . 'admin/assets/js/bulk-content-cleaner-admin.js',
            array( 'jquery' ),
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name,
            'bcc_ajax',
            array(
                'ajax_url'    => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'bcc_delete_nonce' ),
                'i18n'        => array(
                    'confirm_posts'  => __( 'Sei sicuro di voler eliminare TUTTI i post? Questa operazione è irreversibile.', 'bulk-content-cleaner' ),
                    'confirm_media'  => __( 'Sei sicuro di voler eliminare TUTTI i media? Questa operazione è irreversibile.', 'bulk-content-cleaner' ),
                    'confirm_both'   => __( 'Sei sicuro di voler eliminare TUTTI i post e i media associati? Questa operazione è irreversibile.', 'bulk-content-cleaner' ),
                    'confirm_generic' => __( 'Sei sicuro di voler eliminare gli elementi selezionati? Questa operazione è irreversibile.', 'bulk-content-cleaner' ),
                    'running'        => __( 'Operazione in corso...', 'bulk-content-cleaner' ),
                    'completed'      => __( 'Operazione completata.', 'bulk-content-cleaner' ),
                    'error'          => __( 'Si è verificato un errore. Controlla il log per i dettagli.', 'bulk-content-cleaner' ),
                    'nothing_to_do'  => __( 'Nessun elemento da eliminare trovato.', 'bulk-content-cleaner' ),
                    'aborted'        => __( 'Operazione annullata dall\'utente.', 'bulk-content-cleaner' ),
                    'select_type'    => __( 'Seleziona almeno un tipo di contenuto da eliminare.', 'bulk-content-cleaner' ),
                ),
            )
        );
    }

    /**
     * Register the admin menu page.
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            __( 'Bulk Content Cleaner', 'bulk-content-cleaner' ),
            __( 'Bulk Cleaner', 'bulk-content-cleaner' ),
            'manage_options',
            'bulk-content-cleaner',
            array( $this, 'display_plugin_admin_page' ),
            'dashicons-trash',
            80
        );
    }

    /**
     * Render the admin page.
     */
    public function display_plugin_admin_page() {
        require_once BCC_PLUGIN_DIR . 'admin/views/admin-display.php';
    }

    /**
     * AJAX handler for bulk deletion.
     * This handler is idempotent: if a post has already been deleted, it is simply skipped.
     */
    public function ajax_delete_posts() {
        // 1. Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'bcc_delete_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Errore di sicurezza: nonce non valido.', 'bulk-content-cleaner' ) ), 403 );
        }

        // 2. Verify capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'bulk-content-cleaner' ) ), 403 );
        }

        // 3. Sanitize and validate input
        $delete_post_type_post = isset( $_POST['delete_post_type_post'] ) && rest_sanitize_boolean( $_POST['delete_post_type_post'] );
        $delete_post_type_page = isset( $_POST['delete_post_type_page'] ) && rest_sanitize_boolean( $_POST['delete_post_type_page'] );
        $delete_media          = isset( $_POST['delete_media'] ) && rest_sanitize_boolean( $_POST['delete_media'] );
        $delete_terms          = isset( $_POST['delete_terms'] ) && rest_sanitize_boolean( $_POST['delete_terms'] );
        $batch_size            = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 5;
        $offset                = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
        
        // Date filters
        $date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : '';
        $date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : '';

        if ( $batch_size < 1 ) {
            $batch_size = 5;
        }
        if ( $batch_size > 100 ) {
            $batch_size = 100;
        }

        if ( ! $delete_post_type_post && ! $delete_post_type_page && ! $delete_media && ! $delete_terms ) {
            wp_send_json_error( array( 'message' => __( 'Nessuna operazione selezionata.', 'bulk-content-cleaner' ) ) );
        }

        $log          = array();
        $deleted      = 0;
        $deleted_media = 0;
        $errors       = 0;
        $skipped      = 0;

        // ----------------------------------------------------------------
        // MODE A: Delete posts (and optionally their attached media)
        // ----------------------------------------------------------------
        if ( $delete_post_type_post || $delete_post_type_page ) {
            $post_types = array();
            if ( $delete_post_type_post ) {
                $post_types[] = 'post';
            }
            if ( $delete_post_type_page ) {
                $post_types[] = 'page';
            }

            $args = array(
                'post_type'      => $post_types,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
                'posts_per_page' => $batch_size,
                'offset'         => 0, // Force offset 0
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'no_found_rows'  => false,
            );

            // Add date query if dates are provided
            if ( ! empty( $date_from ) || ! empty( $date_to ) ) {
                $date_query = array( 'inclusive' => true );
                
                if ( ! empty( $date_from ) ) {
                    $date_query['after'] = $date_from;
                }
                
                if ( ! empty( $date_to ) ) {
                    $date_query['before'] = $date_to . ' 23:59:59';
                }
                
                $args['date_query'] = array( $date_query );
            }

            $query = new WP_Query( $args );
            $post_ids = $query->posts;

            if ( ! empty( $post_ids ) ) {
                foreach ( $post_ids as $post_id ) {
                    // Idempotency: check if post still exists
                    $post = get_post( $post_id );
                    if ( ! $post ) {
                        $skipped++;
                        continue;
                    }

                    // Delete attached media if requested
                    if ( $delete_media ) {
                        $attachments = get_posts( array(
                            'post_type'      => 'attachment',
                            'post_parent'    => $post_id,
                            'post_status'    => 'any',
                            'posts_per_page' => -1,
                            'fields'         => 'ids',
                        ) );

                        foreach ( $attachments as $attachment_id ) {
                            $result = wp_delete_attachment( $attachment_id, true );
                            if ( false === $result || null === $result ) {
                                $errors++;
                                $log[] = array(
                                    'type'    => 'error',
                                    'message' => sprintf( __( 'Errore nella cancellazione del media ID %d (allegato al post ID %d).', 'bulk-content-cleaner' ), $attachment_id, $post_id ),
                                );
                            } else {
                                $deleted_media++;
                                $log[] = array(
                                    'type'    => 'success',
                                    'message' => sprintf( __( 'Media ID %d eliminato (allegato al post ID %d).', 'bulk-content-cleaner' ), $attachment_id, $post_id ),
                                );
                            }
                        }
                    }

                    // Delete the post itself
                    $result = wp_delete_post( $post_id, true );
                    if ( false === $result || null === $result ) {
                        $errors++;
                        $log[] = array(
                            'type'    => 'error',
                            'message' => sprintf( __( 'Errore nella cancellazione del post ID %d ("%s").', 'bulk-content-cleaner' ), $post_id, esc_html( $post->post_title ) ),
                        );
                    } else {
                        $deleted++;
                        $log[] = array(
                            'type'    => 'success',
                            'message' => sprintf( __( 'Post ID %d ("%s") eliminato con successo.', 'bulk-content-cleaner' ), $post_id, esc_html( $post->post_title ) ),
                        );
                    }
                }

                $total_found = $query->found_posts;
                $has_more    = $total_found > count( $post_ids );

                wp_send_json_success( array(
                    'deleted'     => $deleted,
                    'errors'      => $errors,
                    'skipped'     => $skipped,
                    'has_more'    => true, // Force true to keep looping until empty or next phase
                    'next_offset' => 0,    // Always 0 for posts
                    'log'         => $log,
                ) );
            }
            // If no posts found, we fall through to the next check.
        }

        // ----------------------------------------------------------------
        // MODE B: Delete only media (not tied to posts)
        // ----------------------------------------------------------------
        // If we are deleting media BUT NOT as an attachment to selected posts
        // This runs if we didn't select posts OR if we selected posts but none were found (and thus we are done with posts)
        // However, if we selected posts, we might have deleted media attached to them.
        // The logic here is: "Media allegati" checkbox means "delete media attached to posts being deleted".
        // BUT if user selects ONLY "Media allegati", we treat it as "delete ALL media".
        // The previous logic was: if (!delete_posts && delete_media) -> delete all media.
        
        $deleting_posts_mode = ($delete_post_type_post || $delete_post_type_page);

        if ( ! $deleting_posts_mode && $delete_media ) {
            $args = array(
                'post_type'      => 'attachment',
                'post_status'    => 'any',
                'posts_per_page' => $batch_size,
                'offset'         => 0, // Force offset 0
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'no_found_rows'  => false,
            );

            $query       = new WP_Query( $args );
            $attach_ids  = $query->posts;

            if ( ! empty( $attach_ids ) ) {
                foreach ( $attach_ids as $attachment_id ) {
                    if ( ! get_post( $attachment_id ) ) {
                        $skipped++;
                        continue;
                    }

                    $result = wp_delete_attachment( $attachment_id, true );
                    if ( false === $result || null === $result ) {
                        $errors++;
                        $log[] = array(
                            'type'    => 'error',
                            'message' => sprintf( __( 'Errore nella cancellazione del media ID %d.', 'bulk-content-cleaner' ), $attachment_id ),
                        );
                    } else {
                        $deleted++;
                        $log[] = array(
                            'type'    => 'success',
                            'message' => sprintf( __( 'Media ID %d eliminato con successo.', 'bulk-content-cleaner' ), $attachment_id ),
                        );
                    }
                }

                $total_found = $query->found_posts;
                $has_more    = $total_found > count( $attach_ids );

                wp_send_json_success( array(
                    'deleted'     => $deleted,
                    'errors'      => $errors,
                    'skipped'     => $skipped,
                    'has_more'    => true,
                    'next_offset' => 0,
                    'log'         => $log,
                ) );
            }
            // If no media found, fall through.
        }

        // ----------------------------------------------------------------
        // MODE C: Delete empty terms (Categories & Tags)
        // ----------------------------------------------------------------
        if ( $delete_terms ) {
            $taxonomies = array( 'category', 'post_tag' );
            
            // Use provided offset since we might skip terms
            $term_args = array(
                'taxonomy'   => $taxonomies,
                'hide_empty' => false, // We need all to check emptiness manually if needed, or rely on count=0 check
                'number'     => $batch_size,
                'offset'     => $offset,
                'fields'     => 'all',
            );

            $terms = get_terms( $term_args );

            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $batch_kept = 0;

                foreach ( $terms as $term ) {
                    if ( $term->count == 0 ) {
                        // Delete empty term
                        $result = wp_delete_term( $term->term_id, $term->taxonomy );
                        if ( is_wp_error( $result ) ) {
                            $errors++;
                            $log[] = array(
                                'type'    => 'error',
                                'message' => sprintf( __( 'Errore nella cancellazione del termine ID %d ("%s").', 'bulk-content-cleaner' ), $term->term_id, $term->name ),
                            );
                            $batch_kept++; // Treat as kept to avoid infinite loop on same offset
                        } else {
                            $deleted++;
                            $log[] = array(
                                'type'    => 'success',
                                'message' => sprintf( __( 'Termine ID %d ("%s") eliminato con successo.', 'bulk-content-cleaner' ), $term->term_id, $term->name ),
                            );
                        }
                    } else {
                        // Keep non-empty term
                        $batch_kept++;
                        // Optional: we can log skipping, but it's verbose
                    }
                }

                $next_offset = $offset + $batch_kept;
                
                // Determine if there are more terms.
                // If we retrieved fewer than batch_size, we reached the end.
                $has_more = count( $terms ) >= $batch_size;

                wp_send_json_success( array(
                    'deleted'     => $deleted,
                    'errors'      => $errors,
                    'skipped'     => $skipped,
                    'has_more'    => $has_more,
                    'next_offset' => $next_offset,
                    'log'         => $log,
                ) );
            }
            
            if ( is_wp_error( $terms ) ) {
                 $log[] = array(
                    'type'    => 'error',
                    'message' => __( 'Errore nel recupero delle tassonomie.', 'bulk-content-cleaner' ),
                );
            }
        }

        // If we fall through here, it means no items were found in the current phase (and previous phases).
        // Since we return early on success, reaching here means "Nothing found".
        
        $log[] = array(
            'type'    => 'info',
            'message' => __( 'Nessun elemento da eliminare trovato.', 'bulk-content-cleaner' ),
        );

        wp_send_json_success( array(
            'deleted'     => 0,
            'errors'      => 0,
            'skipped'     => 0,
            'has_more'    => false,
            'next_offset' => 0,
            'log'         => $log,
        ) );
    }
}
