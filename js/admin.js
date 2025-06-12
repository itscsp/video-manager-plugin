jQuery(document).ready(function($) {
    // Handle API Key input
    $('#api_key').on('change', function() {
        const apiKey = $(this).val();
        if (!apiKey) return;

        // Disable library select while loading
        $('#library_id').prop('disabled', true);
        
        // Show loading indicator
        $('.libraries-loading').show();

        // Fetch libraries from Bunny.net
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'fetch_bunny_libraries',
                api_key: apiKey,
                nonce: bunnyVideoSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Clear and populate library dropdown
                    const select = $('#library_id');
                    select.empty();
                    select.append($('<option>', {
                        value: '',
                        text: bunnyVideoSettings.selectLibrary
                    }));
                    
                    response.data.libraries.forEach(function(library) {
                        select.append($('<option>', {
                            value: library.id,
                            text: library.name
                        }));
                    });
                    
                    select.prop('disabled', false);
                } else {
                    alert(response.data.message || bunnyVideoSettings.errorMessage);
                }
            },
            error: function() {
                alert(bunnyVideoSettings.errorMessage);
            },
            complete: function() {
                $('.libraries-loading').hide();
            }
        });
    });

    // Handle Library selection
    $('#library_id').on('change', function() {
        const libraryId = $(this).val();
        const apiKey = $('#api_key').val();
        if (!libraryId || !apiKey) return;

        // Show loading indicator
        $('.api-key-loading').show();

        // Fetch Stream API Key
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'fetch_stream_api_key',
                library_id: libraryId,
                api_key: apiKey,
                nonce: bunnyVideoSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#stream_api_key').val(response.data.streamApiKey);
                    $('#video_pull_zone').val(response.data.pullZone);
                } else {
                    alert(response.data.message || bunnyVideoSettings.errorMessage);
                }
            },
            error: function() {
                alert(bunnyVideoSettings.errorMessage);
            },
            complete: function() {
                $('.api-key-loading').hide();
            }
        });
    });
    console.log('Bunny.net: Admin script loaded');

    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('post_type') === 'video') {
        var $addPostBtn = $('.page-title-action');
        if ($addPostBtn.length) {
            $('<button id="sync-bunny-videos" class="page-title-action">Sync Videos from Bunny.net</button>').insertAfter($addPostBtn);
        }
    }

    // Handle library refresh
    $('#refresh-libraries').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $select = $('#bunny-library-select');
        var apiKey = $('#bunny-api-key').val();
        
        if (!apiKey) {
            alert('Please enter an API key first');
            return;
        }
        
        $button.prop('disabled', true).text('Refreshing...');
        $select.prop('disabled', true);
        
        $.ajax({
            url: bunny_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'bunny_get_libraries',
                nonce: bunny_ajax.nonce,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    $select.empty().append('<option value="">Select a library...</option>');
                    response.data.forEach(function(library) {
                        $select.append(
                            $('<option>', {
                                value: library.Id,
                                text: library.Name + ' (ID: ' + library.Id + ')'
                            })
                        );
                    });
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error connecting to server');
            },
            complete: function() {
                $button.prop('disabled', false).text('Refresh Libraries');
                $select.prop('disabled', false);
            }
        });
    });

    // Handle video sync button click
    $('#sync-bunny-videos').on('click', function(e) {
        debugger;
        e.preventDefault();
        console.log('Sync button clicked');
        var $button = $(this);
        var $status = $('#sync-status');
        
        // Disable button and show loading state
        $button.prop('disabled', true).text('Syncing...');
        $status.removeClass('notice-error notice-success').addClass('notice').show();
        $status.html('<p>Syncing videos from Bunny.net...</p>');
        
        console.log('Making AJAX call to sync videos');
        console.log('AJAX URL:', bunny_ajax.ajaxurl);
        console.log('Nonce:', bunny_ajax.nonce);
        
        // Make AJAX call to sync videos
        $.ajax({
            url: bunny_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'bunny_manual_sync',
                nonce: bunny_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.removeClass('notice-error').addClass('notice-success');
                    $status.html('<p>' + response.data + '</p>');
                    // Reload the page after 2 seconds to show new videos
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    $status.removeClass('notice-success').addClass('notice-error');
                    $status.html('<p>Error: ' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $status.removeClass('notice-success').addClass('notice-error');
                $status.html('<p>Error: Could not connect to the server</p>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Sync Videos from Bunny.net');
            }
        });
    });
    
    // Handle test connection button click
    $('#test-connection').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $status = $('#test-status');
        
        $button.prop('disabled', true).text('Testing...');
        $status.removeClass('notice-error notice-success').addClass('notice').show();
        $status.html('<p>Testing connection...</p>');
        
        $.ajax({
            url: bunny_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'bunny_test_connection',
                nonce: bunny_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.removeClass('notice-error').addClass('notice-success');
                } else {
                    $status.removeClass('notice-success').addClass('notice-error');
                }
                $status.html('<p>' + response.data + '</p>');
            },
            error: function() {
                $status.removeClass('notice-success').addClass('notice-error');
                $status.html('<p>Error: Could not connect to the server</p>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Connection');
            }
        });
    });
});
