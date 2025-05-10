/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, alert, $t) {
    'use strict';
    
    return function (config) {
        var syncButton = $('#sync_button');
        var syncStatus = $('.sync-status');
        
        syncButton.on('click', function () {
            syncStatus.text($t('Initiating sync...'));
            syncButton.prop('disabled', true);
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        syncStatus.text($t('Sync initiated successfully.'));
                        
                        setTimeout(function() {
                            syncStatus.text($t('You can check progress in the Sync Dashboard.'));
                        }, 3000);
                    } else {
                        syncStatus.text('');
                        alert({
                            title: $t('Error'),
                            content: response.message
                        });
                    }
                },
                error: function () {
                    syncStatus.text('');
                    alert({
                        title: $t('Error'),
                        content: $t('An error occurred while syncing products.')
                    });
                },
                complete: function () {
                    syncButton.prop('disabled', false);
                }
            });
        });
    };
});