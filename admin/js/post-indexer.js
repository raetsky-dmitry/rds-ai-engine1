(function($) {
    'use strict';
    
    $(document).ready(function() {
        $('#rds-aie-index-btn').on('click', function() {
            var $btn = $(this);
            var $msg = $('#rds-aie-index-message');
            var postId = $btn.data('post-id');
            
            // Блокируем кнопку
            $btn.prop('disabled', true).text(rdsAieIndexer.indexing);
            $msg.hide().removeClass('notice-success notice-error');
            
            $.ajax({
                url: rdsAieIndexer.ajax_url,
                type: 'POST',
                data: {
                    action: 'rds_aie_index_post',
                    nonce: rdsAieIndexer.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        $msg.addClass('notice-success').html('<p>' + response.data.message + '</p>').show();
                        $('.rds-aie-index-status')
                            .removeClass('not-indexed')
                            .addClass('indexed')
                            .text('✓ Indexed for AI Search');
                        $btn.text('Re-index Document');
                    } else {
                        $msg.addClass('notice-error').html('<p>' + response.data.message + '</p>').show();
                    }
                },
                error: function() {
                    $msg.addClass('notice-error').html('<p>' + rdsAieIndexer.error + '</p>').show();
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });
    });
})(jQuery);