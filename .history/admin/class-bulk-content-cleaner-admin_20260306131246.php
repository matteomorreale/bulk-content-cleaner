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
        $delete_posts  = isset( $_POST['delete_posts'] ) && rest_sanitize_boolean( $_POST['delete_posts'] );
        $delete_media  = isset( $_POST['delete_media'] ) && rest_sanitize_boolean( $_POST['delete_media'] );
        $batch_size    = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 5;
        $offset        = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

        if ( $batch_size < 1 ) {
            $batch_size = 5;
        }
        if ( $batch_size > 100 ) {
            $batch_size = 100;
        }

        if ( ! $delete_posts && ! $delete_media ) {
            wp_send_json_error( array( 'message' => __( 'Nessuna operazione selezionata.', 'bulk-content-cleaner' ) ) );
        }

        $log          = array();
        $deleted      = 0;
        $errors       = 0;
        $skipped      = 0;
        $has_more     = false;
        $next_offset  = $offset;

        // ----------------------------------------------------------------
        // MODE A: Delete posts (and optionally their attached media)
        // ----------------------------------------------------------------
        if ( $delete_posts ) {
            $post_types = array( 'post', 'page' );

            $args = array(
                'post_type'      => $post_types,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
                'posts_per_page' => $batch_size,
                'offset'         => $offset,
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'no_found_rows'  => false,
            );

            $query = new WP_Query( $args );
            $post_ids = $query->posts;

            if ( ! empty( $post_ids ) ) {
                foreach ( $post_ids as $post_id ) {
                    // Idempotency: check if post still exists
                    $post = get_post( $post_id );
                    if ( ! $post ) {
                        $skipped++;
                        $log[] = array(
                            'type'    => 'info',
                            'message' => sprintf( __( 'Post ID %d non trovato (già eliminato, saltato).', 'bulk-content-cleaner' ), $post_id ),
                        );
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
                                $deleted++;
                                $log[] = array(
                                    'type'    => 'success',
                                    'message' => sprintf( __( 'Media ID %d eliminato (allegato al post ID %d).', 'bulk-content-cleaner' ), $attachment_id, $post_id ),
                                );
                            }
                        }
                    }

                    // Delete the post itself (force delete, bypass trash)
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

                // Check if there are more posts to process
                $total_found = $query->found_posts;
                $next_offset = $offset + count( $post_ids );
                $has_more    = $next_offset < $total_found;

            } else {
                $log[] = array(
                    'type'    => 'info',
                    'message' => __( 'Nessun post trovato da eliminare.', 'bulk-content-cleaner' ),
                );
            }
        }

        // ----------------------------------------------------------------
        // MODE B: Delete only media (not tied to posts) when delete_posts is false
        // ----------------------------------------------------------------
        if ( ! $delete_posts && $delete_media ) {
            $args = array(
                'post_type'      => 'attachment',
                'post_status'    => 'any',
                'posts_per_page' => $batch_size,
                'offset'         => $offset,
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'no_found_rows'  => false,
            );

            $query       = new WP_Query( $args );
            $attach_ids  = $query->posts;

            if ( ! empty( $attach_ids ) ) {
                foreach ( $attach_ids as $attachment_id ) {
                    // Idempotency check
                    if ( ! get_post( $attachment_id ) ) {
                        $skipped++;
                        $log[] = array(
                            'type'    => 'info',
                            'message' => sprintf( __( 'Media ID %d non trovato (già eliminato, saltato).', 'bulk-content-cleaner' ), $attachment_id ),
                        );
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
                $next_offset = $offset + count( $attach_ids );
                $has_more    = $next_offset < $total_found;

            } else {
                $log[] = array(
                    'type'    => 'info',
                    'message' => __( 'Nessun media trovato da eliminare.', 'bulk-content-cleaner' ),
                );
            }
        }

        wp_send_json_success( array(
            'deleted'     => $deleted,
            'errors'      => $errors,
            'skipped'     => $skipped,
            'has_more'    => $has_more,
            'next_offset' => $next_offset,
            'log'         => $log,
        ) );
    }
}
