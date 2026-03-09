<?php
// Prevent direct access
if ( ! defined( 'WPINC' ) ) {
    die;
}
?>
<div class="wrap bcc-wrap">

    <div class="bcc-header">
        <span class="dashicons dashicons-trash bcc-header-icon"></span>
        <div>
            <h1><?php esc_html_e( 'Bulk Content Cleaner', 'bulk-content-cleaner' ); ?></h1>
            <p class="bcc-subtitle"><?php esc_html_e( 'Elimina in modo massivo post e/o media dalla tua installazione WordPress.', 'bulk-content-cleaner' ); ?></p>
        </div>
    </div>

    <div class="bcc-container">

        <!-- Settings Panel -->
        <div class="bcc-card bcc-settings-card">
            <h2 class="bcc-card-title">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php esc_html_e( 'Configurazione Operazione', 'bulk-content-cleaner' ); ?>
            </h2>

            <div class="bcc-form">

                <!-- Content Type -->
                <div class="bcc-form-group">
                    <label class="bcc-label"><?php esc_html_e( 'Tipo di contenuto da eliminare', 'bulk-content-cleaner' ); ?></label>
                    <div class="bcc-checkbox-group">
                        <label class="bcc-checkbox-label">
                            <input type="checkbox" id="bcc-delete-posts" name="delete_posts" value="1" checked />
                            <span class="bcc-checkbox-custom"></span>
                            <span class="bcc-checkbox-text">
                                <span class="dashicons dashicons-admin-post"></span>
                                <?php esc_html_e( 'Post e Pagine', 'bulk-content-cleaner' ); ?>
                            </span>
                        </label>
                        <label class="bcc-checkbox-label">
                            <input type="checkbox" id="bcc-delete-media" name="delete_media" value="1" checked />
                            <span class="bcc-checkbox-custom"></span>
                            <span class="bcc-checkbox-text">
                                <span class="dashicons dashicons-format-image"></span>
                                <?php esc_html_e( 'Media allegati', 'bulk-content-cleaner' ); ?>
                            </span>
                        </label>
                    </div>
                    <p class="bcc-description">
                        <?php esc_html_e( 'Se selezioni solo "Media allegati", verranno eliminati tutti i media dalla libreria. Se selezioni "Post e Pagine" insieme a "Media allegati", verranno eliminati i post con i loro media associati.', 'bulk-content-cleaner' ); ?>
                    </p>
                </div>

                <!-- Batch Size -->
                <div class="bcc-form-group">
                    <label class="bcc-label" for="bcc-batch-size">
                        <?php esc_html_e( 'Elementi per chiamata (batch size)', 'bulk-content-cleaner' ); ?>
                    </label>
                    <div class="bcc-input-wrapper">
                        <input
                            type="number"
                            id="bcc-batch-size"
                            name="batch_size"
                            value="5"
                            min="1"
                            max="100"
                            class="bcc-input"
                        />
                    </div>
                    <p class="bcc-description">
                        <?php esc_html_e( 'Numero di elementi eliminati per ogni singola chiamata AJAX. Valori più bassi riducono il rischio di timeout. Intervallo consentito: 1–100.', 'bulk-content-cleaner' ); ?>
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="bcc-actions">
                    <button id="bcc-start-btn" class="bcc-btn bcc-btn-danger">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e( 'Avvia Eliminazione', 'bulk-content-cleaner' ); ?>
                    </button>
                    <button id="bcc-stop-btn" class="bcc-btn bcc-btn-secondary" disabled>
                        <span class="dashicons dashicons-controls-pause"></span>
                        <?php esc_html_e( 'Interrompi', 'bulk-content-cleaner' ); ?>
                    </button>
                </div>

            </div>
        </div>

        <!-- Progress Panel -->
        <div class="bcc-card bcc-progress-card" id="bcc-progress-panel" style="display:none;">
            <h2 class="bcc-card-title">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php esc_html_e( 'Stato Operazione', 'bulk-content-cleaner' ); ?>
            </h2>

            <div class="bcc-status-bar-wrapper">
                <div class="bcc-status-bar">
                    <div class="bcc-status-bar-fill" id="bcc-progress-bar" style="width: 0%;">
                        <span class="bcc-progress-label" id="bcc-progress-label">0%</span>
                    </div>
                </div>
            </div>

            <div class="bcc-stats">
                <div class="bcc-stat bcc-stat-success">
                    <span class="bcc-stat-icon dashicons dashicons-yes-alt"></span>
                    <div>
                        <span class="bcc-stat-value" id="bcc-stat-deleted">0</span>
                        <span class="bcc-stat-label"><?php esc_html_e( 'Eliminati', 'bulk-content-cleaner' ); ?></span>
                    </div>
                </div>
                <div class="bcc-stat bcc-stat-error">
                    <span class="bcc-stat-icon dashicons dashicons-dismiss"></span>
                    <div>
                        <span class="bcc-stat-value" id="bcc-stat-errors">0</span>
                        <span class="bcc-stat-label"><?php esc_html_e( 'Errori', 'bulk-content-cleaner' ); ?></span>
                    </div>
                </div>
                <div class="bcc-stat bcc-stat-skip">
                    <span class="bcc-stat-icon dashicons dashicons-minus"></span>
                    <div>
                        <span class="bcc-stat-value" id="bcc-stat-skipped">0</span>
                        <span class="bcc-stat-label"><?php esc_html_e( 'Saltati', 'bulk-content-cleaner' ); ?></span>
                    </div>
                </div>
                <div class="bcc-stat bcc-stat-batch">
                    <span class="bcc-stat-icon dashicons dashicons-update"></span>
                    <div>
                        <span class="bcc-stat-value" id="bcc-stat-batches">0</span>
                        <span class="bcc-stat-label"><?php esc_html_e( 'Chiamate AJAX', 'bulk-content-cleaner' ); ?></span>
                    </div>
                </div>
            </div>

            <div class="bcc-status-message" id="bcc-status-message"></div>

        </div>

        <!-- Log Panel -->
        <div class="bcc-card bcc-log-card" id="bcc-log-panel" style="display:none;">
            <div class="bcc-log-header">
                <h2 class="bcc-card-title">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Log Operazione', 'bulk-content-cleaner' ); ?>
                </h2>
                <button id="bcc-clear-log-btn" class="bcc-btn bcc-btn-ghost bcc-btn-sm">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e( 'Pulisci Log', 'bulk-content-cleaner' ); ?>
                </button>
            </div>
            <div class="bcc-log" id="bcc-log">
                <div class="bcc-log-empty"><?php esc_html_e( 'Il log è vuoto.', 'bulk-content-cleaner' ); ?></div>
            </div>
        </div>

    </div><!-- /.bcc-container -->

    <div class="bcc-footer">
        <p>
            <?php
            printf(
                /* translators: %s: plugin version */
                esc_html__( 'Bulk Content Cleaner v%s — Sviluppato da Matteo Morreale', 'bulk-content-cleaner' ),
                esc_html( BCC_VERSION )
            );
            ?>
        </p>
    </div>

</div><!-- /.wrap.bcc-wrap -->
