jQuery(document).ready(function ($) {
    function loadChatHistory() {
        $.post(wpAiChat.ajax_url, { action: "wp_ai_chat_history" }, function (response) {
            var history = JSON.parse(response);
            if (history.error) {
                $("#chat-box").html("<p class='error'>" + history.error + "</p>");
                return;
            }
            history.forEach(function (chat) {
                $("#chat-box").append("<div class='user-msg'><strong>You:</strong> " + chat.user_message + "</div>");
                $("#chat-box").append("<div class='ai-msg'><strong>AI:</strong> " + chat.ai_response + "</div>");
            });
        });
    }

    loadChatHistory();

    $("#chat-submit").click(function () {
        var message = $("#chat-input").val();
        if (message.trim() === "") return;
        
        $("#chat-box").append("<div class='user-msg'><strong>You:</strong> " + message + "</div>");
        $("#chat-input").val("");

        $.post(wpAiChat.ajax_url, { action: "wp_ai_chat", message: message }, function (response) {
            var data = JSON.parse(response);
            $("#chat-box").append("<div class='ai-msg'><strong>AI:</strong> " + data.message + "</div>");
        });
    });
});