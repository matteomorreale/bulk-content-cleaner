/**
 * Bulk Content Cleaner - Admin JavaScript
 *
 * Handles the progressive AJAX deletion of posts and/or media.
 * All requests use a WordPress nonce for security (idempotent design).
 *
 * @author Matteo Morreale
 * @version 1.0.0
 */

(function ($) {
    'use strict';

    // -----------------------------------------------------------------------
    // State
    // -----------------------------------------------------------------------
    var bcc = {
        running:     false,
        aborted:     false,
        offset:      0,
        totalDeleted: 0,
        totalErrors:  0,
        totalSkipped: 0,
        batchCount:   0,
    };

    // -----------------------------------------------------------------------
    // DOM references (populated on document ready)
    // -----------------------------------------------------------------------
    var $startBtn, $stopBtn, $progressPanel, $logPanel,
        $progressBar, $progressLabel, $statusMessage,
        $statDeleted, $statErrors, $statSkipped, $statBatches,
        $log, $clearLogBtn;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Append a log entry to the log panel.
     *
     * @param {string} type    'success' | 'error' | 'info' | 'warning'
     * @param {string} message Human-readable message.
     */
    function appendLog(type, message) {
        var $empty = $log.find('.bcc-log-empty');
        if ($empty.length) {
            $empty.remove();
        }

        var time = new Date().toLocaleTimeString('it-IT');
        var iconMap = {
            success: 'dashicons-yes-alt',
            error:   'dashicons-dismiss',
            info:    'dashicons-info-outline',
            warning: 'dashicons-warning',
        };
        var icon = iconMap[type] || 'dashicons-info-outline';

        var $entry = $(
            '<div class="bcc-log-entry bcc-log-' + type + '">' +
            '  <span class="bcc-log-time">' + time + '</span>' +
            '  <span class="dashicons ' + icon + ' bcc-log-icon"></span>' +
            '  <span class="bcc-log-msg">' + $('<span>').text(message).html() + '</span>' +
            '</div>'
        );

        $log.append($entry);
        // Auto-scroll to bottom
        $log.scrollTop($log[0].scrollHeight);
    }

    /**
     * Update the status message banner.
     *
     * @param {string} message Text to display.
     * @param {string} type    'running' | 'success' | 'error' | 'warning'
     */
    function setStatus(message, type) {
        $statusMessage
            .text(message)
            .removeClass('bcc-status-running bcc-status-success bcc-status-error bcc-status-warning')
            .addClass('bcc-status-' + type)
            .show();
    }

    /**
     * Update the progress bar.
     * Since we don't know the total upfront, we use an indeterminate animation
     * while running and set to 100% on completion.
     *
     * @param {boolean} complete Whether the operation is complete.
     */
    function updateProgressBar(complete) {
        if (complete) {
            $progressBar.css('width', '100%').removeClass('bcc-progress-indeterminate');
            $progressLabel.text('100%');
        } else {
            $progressBar.addClass('bcc-progress-indeterminate');
            $progressLabel.text('...');
        }
    }

    /**
     * Update the statistics counters.
     */
    function updateStats() {
        $statDeleted.text(bcc.totalDeleted);
        $statErrors.text(bcc.totalErrors);
        $statSkipped.text(bcc.totalSkipped);
        $statBatches.text(bcc.batchCount);
    }

    /**
     * Set the UI to "running" state.
     */
    function setRunningUI() {
        $startBtn.prop('disabled', true);
        $stopBtn.prop('disabled', false);
        $progressPanel.show();
        $logPanel.show();
        updateProgressBar(false);
    }

    /**
     * Set the UI to "idle" state.
     */
    function setIdleUI() {
        $startBtn.prop('disabled', false);
        $stopBtn.prop('disabled', true);
    }

    // -----------------------------------------------------------------------
    // Core AJAX loop
    // -----------------------------------------------------------------------

    /**
     * Execute a single AJAX batch request.
     * On success, if there are more items, it schedules the next batch.
     * Idempotent: the server skips already-deleted items.
     */
    function runBatch() {
        if (bcc.aborted) {
            setStatus(bcc_ajax.i18n.aborted, 'warning');
            appendLog('warning', bcc_ajax.i18n.aborted);
            updateProgressBar(true);
            setIdleUI();
            return;
        }

        var deletePosts = $('#bcc-delete-posts').is(':checked') ? '1' : '0';
        var deleteMedia = $('#bcc-delete-media').is(':checked') ? '1' : '0';
        var batchSize   = parseInt($('#bcc-batch-size').val(), 10) || 5;

        $.ajax({
            url:    bcc_ajax.ajax_url,
            method: 'POST',
            data:   {
                action:       'bcc_delete_posts',
                nonce:        bcc_ajax.nonce,
                delete_posts: deletePosts,
                delete_media: deleteMedia,
                batch_size:   batchSize,
                offset:       bcc.offset,
            },
            success: function (response) {
                if (!response.success) {
                    var errMsg = (response.data && response.data.message)
                        ? response.data.message
                        : bcc_ajax.i18n.error;
                    setStatus(errMsg, 'error');
                    appendLog('error', errMsg);
                    updateProgressBar(true);
                    setIdleUI();
                    bcc.running = false;
                    return;
                }

                var data = response.data;

                // Accumulate stats
                bcc.totalDeleted += data.deleted;
                bcc.totalErrors  += data.errors;
                bcc.totalSkipped += data.skipped;
                bcc.batchCount++;
                updateStats();

                // Append log entries from server
                if (data.log && data.log.length > 0) {
                    $.each(data.log, function (i, entry) {
                        appendLog(entry.type, entry.message);
                    });
                }

                if (data.has_more && !bcc.aborted) {
                    // Advance offset and schedule next batch
                    bcc.offset = data.next_offset;
                    setStatus(bcc_ajax.i18n.running + ' (' + bcc.totalDeleted + ' eliminati)', 'running');
                    // Small delay to avoid overwhelming the server
                    setTimeout(runBatch, 300);
                } else {
                    // All done
                    bcc.running = false;
                    updateProgressBar(true);
                    setIdleUI();

                    if (bcc.totalDeleted === 0 && bcc.totalErrors === 0 && bcc.totalSkipped === 0) {
                        setStatus(bcc_ajax.i18n.nothing_to_do, 'warning');
                        appendLog('info', bcc_ajax.i18n.nothing_to_do);
                    } else {
                        var summary = bcc_ajax.i18n.completed +
                            ' — Eliminati: ' + bcc.totalDeleted +
                            ', Errori: ' + bcc.totalErrors +
                            ', Saltati: ' + bcc.totalSkipped;
                        setStatus(summary, bcc.totalErrors > 0 ? 'warning' : 'success');
                        appendLog(bcc.totalErrors > 0 ? 'warning' : 'success', summary);
                    }
                }
            },
            error: function (xhr, status, error) {
                bcc.totalErrors++;
                bcc.batchCount++;
                updateStats();

                var errMsg = bcc_ajax.i18n.error + ' (HTTP ' + xhr.status + ': ' + error + ')';
                appendLog('error', errMsg);

                // Retry logic: on network error, stop to avoid infinite loop
                setStatus(errMsg, 'error');
                updateProgressBar(true);
                setIdleUI();
                bcc.running = false;
            },
        });
    }

    // -----------------------------------------------------------------------
    // Event handlers
    // -----------------------------------------------------------------------

    function onStartClick() {
        var deletePosts = $('#bcc-delete-posts').is(':checked');
        var deleteMedia = $('#bcc-delete-media').is(':checked');

        if (!deletePosts && !deleteMedia) {
            alert(bcc_ajax.i18n.select_type);
            return;
        }

        // Confirmation dialog
        var confirmMsg;
        if (deletePosts && deleteMedia) {
            confirmMsg = bcc_ajax.i18n.confirm_both;
        } else if (deletePosts) {
            confirmMsg = bcc_ajax.i18n.confirm_posts;
        } else {
            confirmMsg = bcc_ajax.i18n.confirm_media;
        }

        if (!window.confirm(confirmMsg)) {
            return;
        }

        // Reset state
        bcc.running      = true;
        bcc.aborted      = false;
        bcc.offset       = 0;
        bcc.totalDeleted = 0;
        bcc.totalErrors  = 0;
        bcc.totalSkipped = 0;
        bcc.batchCount   = 0;

        // Reset UI
        $log.empty().append('<div class="bcc-log-empty">' + 'In attesa di risultati...' + '</div>');
        updateStats();
        setRunningUI();
        setStatus(bcc_ajax.i18n.running, 'running');

        runBatch();
    }

    function onStopClick() {
        bcc.aborted = true;
        $stopBtn.prop('disabled', true);
        appendLog('warning', bcc_ajax.i18n.aborted);
    }

    function onClearLogClick() {
        $log.empty().append('<div class="bcc-log-empty">Il log è vuoto.</div>');
    }

    // -----------------------------------------------------------------------
    // Init
    // -----------------------------------------------------------------------

    $(document).ready(function () {
        $startBtn      = $('#bcc-start-btn');
        $stopBtn       = $('#bcc-stop-btn');
        $progressPanel = $('#bcc-progress-panel');
        $logPanel      = $('#bcc-log-panel');
        $progressBar   = $('#bcc-progress-bar');
        $progressLabel = $('#bcc-progress-label');
        $statusMessage = $('#bcc-status-message');
        $statDeleted   = $('#bcc-stat-deleted');
        $statErrors    = $('#bcc-stat-errors');
        $statSkipped   = $('#bcc-stat-skipped');
        $statBatches   = $('#bcc-stat-batches');
        $log           = $('#bcc-log');
        $clearLogBtn   = $('#bcc-clear-log-btn');

        $startBtn.on('click', onStartClick);
        $stopBtn.on('click', onStopClick);
        $clearLogBtn.on('click', onClearLogClick);
    });

})(jQuery);
