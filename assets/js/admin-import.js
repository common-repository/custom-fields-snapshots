jQuery(function($) {
    'use strict';

    var $uploadArea = $('.custom-fields-snapshots .upload-area');
    var $fileInput = $('.custom-fields-snapshots .import-file');
    var $fileInfo = $('.custom-fields-snapshots .file-info');
    var $fileName = $('.custom-fields-snapshots .file-name');
    var $removeFile = $('.custom-fields-snapshots .remove-file');
    var $importForm = $('.custom-fields-snapshots .import-form');
    var $importValidationMessage = $('.custom-fields-snapshots .import-validation-message');
    var $importResult = $('.custom-fields-snapshots .import-result');
    var $eventLog = $('.custom-fields-snapshots .event-log');

    function handleFiles(files) {
        if (files && files.length > 0) {
            var file = files[0];
            if (file.type === 'application/json') {
                $fileName.text(file.name);
                $fileInfo.show();
                $uploadArea.hide();
                $fileInput[0].files = files;
            } else {
                alert(customFieldsSnapshots.l10n.invalidFileType);
            }
        }
    }

    function displayValidationErrors(errors) {
        var errorHtml = errors.length ? '<div class="notice notice-error"><p>' + errors.join('</p><p>') + '</p></div>' : '';
        $importValidationMessage.html(errorHtml).show();
    }

    function attachCopyLogHandler() {
        $('.custom-fields-snapshots .copy-log').off('click').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $pre = $button.closest('.event-log').find('pre');
            var logText = $pre.text();

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(logText).then(function() {
                    updateButtonText($button);
                }).catch(function(err) {
                    console.error('Failed to copy text: ', err);
                });
            } else {
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(logText).select();
        
                try {
                    var success = document.execCommand('copy');
                    if (success) {
                        updateButtonText($button);
                    } else {
                        console.error('execCommand returned false');
                    }
                } catch (err) {
                    console.error('Failed to copy text: ', err);
                } finally {
                    $temp.remove();
                }
            }
        });
    
        function updateButtonText($button) {
            var originalText = $button.data('original-text') || $button.text();
            $button.data('original-text', originalText);
            
            $button.text(customFieldsSnapshots.l10n.copiedText);
            
            clearTimeout($button.data('reset-timeout'));
            
            var resetTimeout = setTimeout(function() {
                $button.text(originalText);
            }, 2000);
            
            $button.data('reset-timeout', resetTimeout);
        }
    }

    $uploadArea.on({
        'click': function(e) {
            e.preventDefault();
            e.stopPropagation();
            $fileInput.click();
        },
        'dragover dragenter': function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css('border-color', '#0073aa');
        },
        'dragleave dragend drop': function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css('border-color', '#b4b9be');
        },
        'drop': function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleFiles(e.originalEvent.dataTransfer.files);
        }
    });

    $fileInput.on({
        'click': function(e) {
            e.stopPropagation();
        },
        'change': function(e) {
            e.stopPropagation();
            handleFiles(this.files);
        }
    });

    $removeFile.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $fileInput.val('');
        $fileInfo.hide();
        $uploadArea.show();
        $importValidationMessage.add($importResult).add($eventLog).html('').hide();
    });

    $importForm.on('submit', function(e) {
        e.preventDefault();

        if (!$('.custom-fields-snapshots #rollback-changes-input').is(':checked') && !confirm(customFieldsSnapshots.l10n.rollbackDisabledConfirmation)) {
            return false;
        }

        var formData = new FormData(this);
        var file = $fileInput[0].files[0];
        var errors = [];

        if (!file) {
            errors.push(customFieldsSnapshots.l10n.noFileSelected);
        } else if (file.type !== 'application/json') {
            errors.push(customFieldsSnapshots.l10n.invalidFileType);
        }

        if (errors.length) {
            displayValidationErrors(errors);
            $importResult.add($eventLog).hide();
            return;
        }

        var $submitButton = $(this).find('input[type="submit"]');
        $submitButton.prop('disabled', true).val(customFieldsSnapshots.l10n.importingText);

        formData.append('action', 'custom_fields_snapshots_import');
        formData.append('nonce', customFieldsSnapshots.nonce);

        $.ajax({
            url: customFieldsSnapshots.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $importValidationMessage.html('');
                if (response.success) {
                    $importResult.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
                    $fileInput.val('');
                    $fileInfo.hide();
                    $uploadArea.show();
                } else {
                    $importResult.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
                }
                
                if (response.data.log) {
                    $eventLog.html(
                        '<div class="header">' +
                            '<h4>' + customFieldsSnapshots.l10n.eventLogText + '</h4>' +
                            '<a class="button button-secondary copy-log">' + customFieldsSnapshots.l10n.copyText + '</a>' +
                        '</div>' +
                        '<pre>' + response.data.log + '</pre>'
                    ).show();
                    
                    attachCopyLogHandler();
                } else {
                    $eventLog.hide();
                }
            },
            error: function() {
                $importValidationMessage.html('');
                $importResult.html('<div class="notice notice-error"><p>' + customFieldsSnapshots.l10n.ajaxError + '</p></div>').show();
                $eventLog.hide();
            },
            complete: function() {
                $submitButton.prop('disabled', false).val(customFieldsSnapshots.l10n.importText);
            }
        });
    });
});