// Fixed sync.js file
define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';
    
    return function (config) {
        var syncButton = $('#sync-button');
        var stopSyncButton = $('#stop-sync-button');
        var isProcessing = false;
        var currentBatch = 0;
        var totalBatches = 0;
        
        function startSync() {
            isProcessing = true;
            syncButton.prop('disabled', true);
            
            // Show stop button immediately
            syncButton.hide();
            stopSyncButton.show();
            
            $('#sync-status-text').text($t('Initializing sync process...'));
            $('#sync-progress-container').addClass('in-progress');
            
            // Start the first batch (initialization)
            processBatch(0);
        }
        
        function processBatch(batchNumber) {
            console.log('Processing batch: ' + batchNumber);
            
            $.ajax({
                url: config.syncUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: window.FORM_KEY,
                    batch: batchNumber
                },
                success: function (response) {
                    if (response.success) {
                        // Update progress information
                        if (response.progress) {
                            updateProgressUI(response.progress);
                        }
                        
                        // Store total batches info
                        if (response.total_batches) {
                            totalBatches = response.total_batches;
                        }
                        
                        // Continue with next batch if needed
                        if (response.continue && response.next_batch) {
                            currentBatch = response.next_batch;
                            
                            // Update status text with batch info
                            $('#sync-status-text').text(
                                $t('Processing batch %1 of %2...')
                                    .replace('%1', currentBatch)
                                    .replace('%2', totalBatches)
                            );
                            
                            // Process next batch with a small delay to prevent server overload
                            setTimeout(function() {
                                processBatch(response.next_batch);
                            }, 1000);
                        } else {
                            // Sync completed
                            syncComplete();
                        }
                    } else {
                        // Error occurred
                        syncError(response.message || $t('Unknown error occurred during sync.'));
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX error:', error);
                    syncError($t('Error communicating with server: ') + error);
                }
            });
        }
        
        function updateProgressUI(progress) {
            // Update progress bar
            var percent = progress.percent || 0;
            $('#sync-progress-bar').css('width', percent + '%');
            
            // Update stats
            $('#sync-total').text(progress.total || 0);
            $('#sync-processed').text(progress.processed || 0);
            $('#sync-updated').text(progress.updated || 0);
            $('#sync-created').text(progress.created || 0);
            $('#sync-errors').text(progress.errors || 0);
            
            // Calculate estimated time remaining if enough data
            if (currentBatch > 0 && totalBatches > 0) {
                var percentComplete = (currentBatch / totalBatches) * 100;
                $('#sync-progress-percent').text(Math.round(percentComplete) + '%');
            }
        }
        
        function syncComplete() {
            isProcessing = false;
            syncButton.prop('disabled', false);
            
            $('#sync-status-text').text($t('Sync completed successfully'));
            $('#sync-progress-container').removeClass('in-progress');
            
            syncButton.show();
            stopSyncButton.hide();
            
            // Refresh the page after a delay to show final results
            setTimeout(function() {
                window.location.reload();
            }, 3000);
        }
        
        function syncError(message) {
            isProcessing = false;
            syncButton.prop('disabled', false);
            
            $('#sync-status-text').text($t('Sync failed: ') + message);
            $('#sync-progress-container').removeClass('in-progress');
            
            syncButton.show();
            stopSyncButton.hide();
            
            alert(message);
        }
        
        // Bind click event
        syncButton.on('click', function () {
            if (!isProcessing) {
                startSync();
            }
        });
    };
});