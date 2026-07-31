jQuery(function($) {
    const userId = niz_wa_ajax.user_id;
    const botUserId = parseInt(niz_wa_ajax.bot_user_id);
    let autoRefresh = null;
    const sender = msg.sender_name;

    function formatSender(msg) {
        if (msg.direction === 'inbound') {
            return 'User';
        }

        if (parseInt(msg.agent_id) === botUserId) {
            return 'Sofia';
        }

        return 'Agent';
    }

    function formatTime(dt) {
        if (!dt) return '';
        return new Date(dt.replace(' ', 'T')).toLocaleString();
    }

    function scrollBottom() {
        const box = $('#niz-wa-messages');
        box.scrollTop(box[0].scrollHeight);
    }

    function loadWindowStatus() {
        $.post(niz_wa_ajax.ajax_url, {
            action: 'niz_wa_get_window_status',
            nonce: niz_wa_ajax.nonce,
            user_id: userId
        }, function(res) {
            if (!res.success) return;

            const active = res.data.active;

            if (active) {
                $('#niz-wa-window-status')
                    .removeClass('expired')
                    .addClass('active')
                    .text('24-hour WhatsApp window ACTIVE');

                $('#niz-wa-reply-text').prop('disabled', false);
                $('#niz-wa-send-reply').prop('disabled', false);
            } else {
                $('#niz-wa-window-status')
                    .removeClass('active')
                    .addClass('expired')
                    .text('24-hour WhatsApp window EXPIRED. Use template messages.');

                $('#niz-wa-reply-text').prop('disabled', true);
                $('#niz-wa-send-reply').prop('disabled', true);
            }
        });
    }

    function loadMessages() {
        $.post(niz_wa_ajax.ajax_url, {
            action: 'niz_wa_get_messages_by_user',
            nonce: niz_wa_ajax.nonce,
            user_id: userId
        }, function(res) {
            if (!res.success) return;

            let html = '';

            res.data.forEach(msg => {
                const sender = formatSender(msg);
                const cls = msg.direction === 'inbound'
                    ? 'niz-wa-inbound'
                    : 'niz-wa-outbound';

                let content = msg.content;

                if (msg.message_type === 'template') {
                    try {
                        const obj = JSON.parse(msg.content);
                        content = '[Template] ' + obj.template;
                    } catch (e) {}
                }

                html += `
                    <div class="niz-wa-message ${cls}">
                        <div class="sender">${sender}</div>
                        <div class="content">${content}</div>
                        <div class="time">${formatTime(msg.created_at)}</div>
                    </div>
                `;
            });

            $('#niz-wa-messages').html(html);
            scrollBottom();
        });
    }

    $('#niz-wa-send-reply').on('click', function() {
        const message = $('#niz-wa-reply-text').val().trim();

        if (!message) return;

        $.post(niz_wa_ajax.ajax_url, {
            action: 'niz_wa_send_reply',
            nonce: niz_wa_ajax.nonce,
            user_id: userId,
            message: message
        }, function(res) {
            if (res.success) {
                $('#niz-wa-reply-text').val('');
                loadMessages();
                loadWindowStatus();
            } else {
                alert(res.data || 'Failed');
            }
        });
    });

    $('.niz-wa-template-buttons button').on('click', function() {
        const template = $(this).data('template');

        $.post(niz_wa_ajax.ajax_url, {
            action: 'niz_wa_send_template',
            nonce: niz_wa_ajax.nonce,
            user_id: userId,
            template_key: template
        }, function(res) {
            if (res.success) {
                loadMessages();
            } else {
                alert(res.data || 'Template failed');
            }
        });
    });

    loadWindowStatus();
    loadMessages();

    if (autoRefresh) clearInterval(autoRefresh);

    autoRefresh = setInterval(function() {
        loadWindowStatus();
        loadMessages();
    }, 15000);
});