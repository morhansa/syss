// Fixed progress.js file
define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';
    
    return function (config) {
        var progressContainer = $('#sync-progress-container');
        var progressBar = $('#sync-progress-bar');
        var statusText = $('#sync-status-text');
        var syncButton = $('#sync-button');
        var stopSyncButton = $('#stop-sync-button');
        var isInProgress = config.isInProgress;
        var checkInterval = config.checkInterval || 3000;
        var intervalId;
        var lastData = null;
        var startTime = new Date();
        
        function checkStatus() {
            $.ajax({
                url: config.statusUrl,
                type: 'GET',
                dataType: 'json',
                cache: false,
                success: function (response) {
                    if (response.in_progress !== undefined) {
                        updateProgressUI(response);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error checking sync status:', error);
                }
            });
        }
        
        function updateProgressUI(data) {
            // Save data for comparison
            lastData = data;
            
            // Update UI elements
            progressBar.css('width', data.percent + '%');
            $('#sync-total').text(data.total);
            $('#sync-processed').text(data.processed);
            $('#sync-updated').text(data.updated);
            $('#sync-created').text(data.created);
            $('#sync-errors').text(data.errors);
            
            // Calculate and display estimated time remaining
            if (data.in_progress && data.processed > 0 && data.total > 0) {
                var currentTime = new Date();
                var elapsedTime = (currentTime - startTime) / 1000; // in seconds
                var itemsPerSecond = data.processed / elapsedTime;
                
                if (itemsPerSecond > 0) {
                    var remainingItems = data.total - data.processed;
                    var remainingTimeSeconds = remainingItems / itemsPerSecond;
                    
                    var remainingTimeText = '';
                    if (remainingTimeSeconds > 3600) {
                        remainingTimeText = Math.ceil(remainingTimeSeconds / 3600) + ' ' + $t('hours');
                    } else if (remainingTimeSeconds > 60) {
                        remainingTimeText = Math.ceil(remainingTimeSeconds / 60) + ' ' + $t('minutes');
                    } else {
                        remainingTimeText = Math.ceil(remainingTimeSeconds) + ' ' + $t('seconds');
                    }
                    
                    var speedText = Math.round(itemsPerSecond * 60) + ' ' + $t('products/minute');
                    
                    // Update status text with detailed information
                    var statusMessage = $t('Sync in progress... Processing %1 of %2 products (%3%) • %4 remaining • %5');
                    statusText.html(statusMessage
                        .replace('%1', '<strong>' + data.processed + '</strong>')
                        .replace('%2', '<strong>' + data.total + '</strong>')
                        .replace('%3', '<strong>' + data.percent + '</strong>')
                        .replace('%4', '<strong>' + remainingTimeText + '</strong>')
                        .replace('%5', '<strong>' + speedText + '</strong>')
                    );
                } else {
                    // Simple status if we can't calculate speed yet
                    var simpleStatusMessage = $t('Sync in progress... Processing %1 of %2 products (%3%)');
                    statusText.html(simpleStatusMessage
                        .replace('%1', '<strong>' + data.processed + '</strong>')
                        .replace('%2', '<strong>' + data.total + '</strong>')
                        .replace('%3', '<strong>' + data.percent + '</strong>')
                    );
                }
            }
            
            // Update UI based on sync status
            if (data.in_progress) {
                if (!isInProgress) {
                    progressContainer.addClass('in-progress');
                    isInProgress = true;
                    syncButton.hide();
                    stopSyncButton.show();
                    startTime = new Date(); // Reset start time
                }
            } else {
                if (isInProgress) {
                    progressContainer.removeClass('in-progress');
                    isInProgress = false;
                    syncButton.show();
                    stopSyncButton.hide();
                    
                    if (data.percent >= 100) {
                        statusText.text($t('Sync completed successfully'));
                    } else {
                        statusText.text($t('Sync stopped or failed'));
                    }
                }
            }
        }
        
        // Start status check when page loads if sync is in progress
        if (isInProgress) {
            checkStatus();
        }
        
        // Set up periodic status check
        intervalId = setInterval(checkStatus, checkInterval);
        
        // Clean up when leaving page
        $(window).on('beforeunload', function () {
            if (intervalId) {
                clearInterval(intervalId);
            }
        });
    };
});