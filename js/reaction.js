// 使用 jQuery 为点赞按钮添加点击事件
jQuery(document).ready(function($) {
    $('.bslb-like-button').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var post_id = $btn.data('postid');
        $.post(bslb_vars.ajax_url, {
            action: 'bslb_like',
            post_id: post_id
        }, function(response) {
            if(response.success) {
                $btn.find('.bslb-like-count').text(response.data.likes);
                var $icon = $btn.find('.bslb-like-icon');
                $icon.addClass('pulse');
                setTimeout(function(){
                    $icon.removeClass('pulse');
                }, 500);
            }
        });
    });
});
