define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';
    
    return function (config) {
        var stopButton = $('#stop-sync-button');
        var syncButton = $('#sync-button');
        var isProcessing = false;
        
        stopButton.on('click', function () {
            if (isProcessing) {
                return;
            }
            
            isProcessing = true;
            stopButton.prop('disabled', true);
            
            $.ajax({
                url: config.stopUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: window.FORM_KEY
                },
                success: function (response) {
                    isProcessing = false;
                    stopButton.prop('disabled', false);
                    
                    if (response.success) {
                        $('#sync-status-text').text($t('Sync stopped by user'));
                        stopButton.hide();
                        syncButton.show();
                    } else {
                        alert(response.message || $t('Failed to stop synchronization.'));
                    }
                },
                error: function () {
                    isProcessing = false;
                    stopButton.prop('disabled', false);
                    alert($t('An error occurred while stopping the synchronization.'));
                }
            });
        });
    };
});