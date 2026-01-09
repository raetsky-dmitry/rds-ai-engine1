(function ($) {
  "use strict";

  // Хранение истории сообщений
  let chatHistory = [];
  let currentSessionId = "";
  let debugData = {
    lastRequest: null,
    lastResponse: null,
    lastHistory: null,
  };

  // Инициализация
  $(document).ready(function () {
    // Генерируем session_id для чата
    currentSessionId =
      "chat_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9);

    // Активация кнопки отправки при вводе текста
    $("#chatInput").on("input", function () {
      const hasText = $(this).val().trim().length > 0;
      $("#sendMessage").prop("disabled", !hasText);
    });

    // Отправка сообщения по клику
    $("#sendMessage").on("click", sendMessage);

    // Отправка сообщения по Enter (без Shift)
    $("#chatInput").on("keydown", function (e) {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        if (!$("#sendMessage").prop("disabled")) {
          sendMessage();
        }
      }
    });

    // Очистка чата
    $("#clearChat").on("click", clearChat);

    // Переключение отладки
    $("#toggleDebug").on("click", toggleDebug);

    // Переключение вкладок отладки
    $(".debug-tab").on("click", function () {
      const tab = $(this).data("tab");
      switchDebugTab(tab);
    });

    // Автофокус на поле ввода
    $("#chatInput").focus();
  });

  /**
   * Отправка сообщения
   */
  function sendMessage() {
    const message = $("#chatInput").val().trim();
    const modelId = $("#chat_model").val();
    const assistantId = $("#chat_assistant").val();

    if (!message) {
      return;
    }

    // Проверка выбора модели или ассистента
    if (!modelId && !assistantId) {
      alert(
        rds_aie_ajax.select_model_or_assistant ||
          "Please select a model or assistant."
      );
      return;
    }

    // Добавление сообщения пользователя в чат
    addMessage("user", message);

    // Очистка поля ввода
    $("#chatInput").val("");
    $("#sendMessage").prop("disabled", true);

    // Показ индикатора загрузки
    showLoading();

    // Сохраняем данные для отладки
    debugData.lastRequest = {
      message: message,
      model_id: modelId,
      assistant_id: assistantId,
      session_id: currentSessionId,
      timestamp: new Date().toISOString(),
    };

    // Показываем данные запроса в отладке
    updateDebugInfo();

    // Отправка запроса на сервер с session_id
    $.ajax({
      url: rds_aie_ajax.ajax_url,
      type: "POST",
      data: {
        action: "rds_aie_test_chat",
        nonce: rds_aie_ajax.nonce,
        message: message,
        model_id: modelId,
        assistant_id: assistantId,
        session_id: currentSessionId,
        debug: 1,
      },
      success: function (response) {
        hideLoading();

        if (response.success) {
          addMessage("assistant", response.data.response);

          // Сохраняем данные ответа для отладки
          debugData.lastResponse = {
            raw: response,
            content: response.data.response,
            timestamp: new Date().toISOString(),
          };

          // Если пришел отладочный ответ
          if (response.data.debug) {
            debugData.lastHistory = response.data.debug.database_history;
            debugData.lastRequest = response.data.debug.messages_to_ai;
            debugData.fullRequest = response.data.debug.full_request_json;
            debugData.lastResponse.raw_api = response.data.debug.api_response;
            // console.log("response.data.debug isset", response.data.debug);
          } else {
            // console.log("response.data.debug not found", response.data.debug);
          }
        } else {
          addMessage("error", response.data.message || rds_aie_ajax.error_text);

          debugData.lastResponse = {
            error: response.data.message || rds_aie_ajax.error_text,
            timestamp: new Date().toISOString(),
          };
        }

        // Обновляем информацию отладки
        updateDebugInfo();

        // Прокрутка к последнему сообщению
        scrollToBottom();
      },
      error: function (xhr, status, error) {
        hideLoading();
        addMessage("error", rds_aie_ajax.error_text);

        debugData.lastResponse = {
          error: error,
          status: status,
          xhr: xhr,
          timestamp: new Date().toISOString(),
        };

        updateDebugInfo();
        scrollToBottom();
      },
    });
  }

  /**
   * Обновление информации отладки
   */
  function updateDebugInfo() {
    if (!$("#debugContainer").is(":visible")) {
      return;
    }

    // console.log("lastRequest", debugData.lastRequest);
    // console.log("lastHistory", debugData.lastHistory);
    // console.log("lastResponse", debugData.lastResponse);

    // Обновляем вкладку запроса
    if (debugData.lastRequest) {
      $("#debugRequestContent").text(
        JSON.stringify(debugData.lastRequest, null, 2)
      );
    }

    // Обновляем вкладку истории
    if (debugData.lastHistory) {
      $("#debugHistoryContent").text(
        JSON.stringify(debugData.lastHistory, null, 2)
      );
    } else {
      $("#debugHistoryContent").text(
        rds_aie_ajax.no_history || "No conversation history retrieved."
      );
    }

    // Обновляем вкладку полного запроса
    if (debugData.lastRequest) {
      $("#debugFullrequestContent").text(
        JSON.stringify(debugData.fullRequest, null, 2)
      );
    }

    // Обновляем вкладку ответа
    if (debugData.lastResponse) {
      $("#debugResponseContent").text(
        JSON.stringify(debugData.lastResponse, null, 2)
      );
    }
  }

  /**
   * Переключение вкладок отладки
   */
  function switchDebugTab(tab) {
    // Убираем активный класс со всех вкладок
    $(".debug-tab").removeClass("active");
    $(".debug-tab-content").removeClass("active");

    // Добавляем активный класс выбранной вкладке и контенту
    $(`.debug-tab[data-tab="${tab}"]`).addClass("active");
    $(`#debug${tab.charAt(0).toUpperCase() + tab.slice(1)}`).addClass("active");
  }

  /**
   * Переключение отладки
   */
  function toggleDebug() {
    const debugContainer = $("#debugContainer");
    const isVisible = debugContainer.is(":visible");

    if (isVisible) {
      debugContainer.hide();
      $("#toggleDebug").text(rds_aie_ajax.show_debug || "Show Debug");
    } else {
      debugContainer.show();
      $("#toggleDebug").text(rds_aie_ajax.hide_debug || "Hide Debug");

      // Обновляем информацию, если есть данные
      updateDebugInfo();
    }
  }

  /**
   * Добавление сообщения в чат
   */
  function addMessage(type, content) {
    const messagesContainer = $("#chatMessages");
    const timestamp = new Date().toLocaleTimeString([], {
      hour: "2-digit",
      minute: "2-digit",
    });

    // Определение отправителя и класса
    let sender, cssClass;
    switch (type) {
      case "user":
        sender = "You";
        cssClass = "user";
        break;
      case "assistant":
        sender = "Assistant";
        cssClass = "assistant";
        break;
      case "error":
        sender = "Error";
        cssClass = "error";
        break;
      default:
        sender = "Unknown";
        cssClass = "assistant";
    }

    // Создание HTML сообщения
    const messageHtml = `
            <div class="message ${cssClass}">
                <div class="sender">${sender}</div>
                <div class="content">${escapeHtml(content)}</div>
                <div class="time">${timestamp}</div>
            </div>
        `;

    // Добавление сообщения
    messagesContainer.append(messageHtml);

    // Удаление приветственного сообщения, если оно есть
    $(".chat-welcome").remove();

    // Сохранение в историю
    chatHistory.push({ type, content, timestamp });

    // Прокрутка к последнему сообщению
    scrollToBottom();
  }

  /**
   * Показать индикатор загрузки
   */
  function showLoading() {
    const messagesContainer = $("#chatMessages");
    messagesContainer.append(
      '<div class="loading">' + rds_aie_ajax.loading_text + "</div>"
    );
    scrollToBottom();
  }

  /**
   * Скрыть индикатор загрузки
   */
  function hideLoading() {
    $(".loading").remove();
  }

  /**
   * Очистка чата
   */
  function clearChat() {
    if (
      confirm(
        rds_aie_ajax.confirm_clear || "Are you sure you want to clear the chat?"
      )
    ) {
      // Генерируем новый session_id
      currentSessionId =
        "chat_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9);

      $("#chatMessages").html(
        '<div class="chat-welcome"><p>' +
          (rds_aie_ajax.chat_welcome ||
            "Start a conversation by typing a message below.") +
          "</p></div>"
      );
      chatHistory = [];
      debugData = { lastRequest: null, lastResponse: null, lastHistory: null };

      // Очищаем отладку
      $("#debugRequestContent").text(
        rds_aie_ajax.no_request || "No request data yet..."
      );
      $("#debugHistoryContent").text(
        rds_aie_ajax.no_history || "No history data yet..."
      );
      $("#debugResponseContent").text(
        rds_aie_ajax.no_response || "No response data yet..."
      );

      scrollToBottom();
    }
  }

  /**
   * Прокрутка к последнему сообщению
   */
  function scrollToBottom() {
    const messagesContainer = $("#chatMessages");
    messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
  }

  /**
   * Экранирование HTML
   */
  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML.replace(/\n/g, "<br>");
  }
})(jQuery);
