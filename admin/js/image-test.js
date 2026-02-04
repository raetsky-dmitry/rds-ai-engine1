(function ($) {
  "use strict";

  $(document).ready(function () {
    // Обработчик для всех форм с POST методом, которые содержат поле model_id
    $('form[method="post"]').on("submit", function (e) {
      // Проверяем, что это форма тестирования изображений
      if ($(this).find('#model_id').length === 0) {
        return; // не форма для генерации изображений
      }
      
      e.preventDefault();

      var form = $(this);
      var submitBtn = form.find('button[type="submit"]');
      var originalText = submitBtn.text();

      // Получаем данные формы
      var formData = {
        action: "rds_aie_test_image_generation",
        nonce: rds_aie_image_test.nonce,
        model_id: $("#model_id").val(),
        prompt: $("#prompt").val(),
        width: $("#width").val(),
        height: $("#height").val(),
        n: $("#n").val(),
        quality: $("#quality").val(),
        style: $("#style").val(),
        seed: $("#seed").val()
      };

      // Валидация
      if (!formData.model_id) {
        alert(rds_aie_image_test.select_model || "Please select a model.");
        return;
      }

      if (!formData.prompt.trim()) {
        alert(rds_aie_image_test.enter_prompt || "Please enter a prompt.");
        return;
      }

      // Показываем индикатор загрузки
      submitBtn
        .prop("disabled", true)
        .text(rds_aie_image_test.generating || "Generating...");
      $("#imageResults").html(
        '<div class="loading">' + rds_aie_image_test.loading + "</div>",
      );

      // Отправка AJAX запроса
      $.ajax({
        url: rds_aie_image_test.ajax_url,
        type: "POST",
        data: formData,
        success: function (response) {
          submitBtn.prop("disabled", false).text(originalText);

          if (response.success) {
            displayImages(response.data.images);
          } else {
            $("#imageResults").html(
              '<div class="error-message">' +
                "<strong>" +
                (rds_aie_image_test.error || "Error") +
                ":</strong> " +
                response.data.message +
                "</div>",
            );
          }
        },
        error: function (xhr, status, error) {
          submitBtn.prop("disabled", false).text(originalText);
          $("#imageResults").html(
            '<div class="error-message">' +
              "<strong>" +
              (rds_aie_image_test.error || "Error") +
              ":</strong> " +
              (rds_aie_image_test.ajax_error || "AJAX request failed.") +
              "</div>",
          );
          console.error("Image generation error:", error);
        },
      });
    });

    // Удаляем дублирующийся обработчик формы из второго блока jQuery ready
    // Все функции объединены в один блок
    
    // Копирование base64 в буфер обмена
    $(document).on("click", ".copy-base64", function () {
      var base64 = $(this).data("base64");
      var tempInput = $("<textarea>");
      $("body").append(tempInput);
      tempInput.val(base64).select();
      document.execCommand("copy");
      tempInput.remove();

      // Визуальная обратная связь
      var originalText = $(this).text();
      $(this).text(rds_aie_image_test.copied || "Copied!");
      setTimeout(
        function () {
          $(this).text(originalText);
        }.bind(this),
        2000,
      );
    });

    // Функция отображения изображений
    function displayImages(images) {
      if (!images || images.length === 0) {
        $("#imageResults").html(
          '<div class="notice notice-warning">' +
            "<p>" +
            (rds_aie_image_test.no_images || "No images generated.") +
            "</p>" +
            "</div>",
        );
        return;
      }

      var html = '<div class="image-results">';
      html +=
        "<h3>" +
        (rds_aie_image_test.generated_images || "Generated Images") +
        " (" +
        images.length +
        ")</h3>";
      html += '<div class="image-grid">';

      $.each(images, function (index, imageData) {
        html += '<div class="image-item">';
        html += '<div class="image-container">';
        html +=
          '<img src="' +
          imageData +
          '" alt="' +
          (rds_aie_image_test.image_alt || "Generated image") +
          " " +
          (index + 1) +
          '" style="max-width: 100%; height: auto;">';
        html += "</div>";
        html += '<div class="image-actions">';
        html +=
          '<button type="button" class="button button-small copy-base64" data-base64="' +
          imageData +
          '">';
        html += rds_aie_image_test.copy_base64 || "Copy Base64";
        html += "</button>";
        html += "</div>";
        html += "</div>";
      });

      html += "</div></div>";
      $("#imageResults").html(html);
    }
  });
})(jQuery);
// Остальной код уже включен в первый блок

jQuery(document).ready(function($) {
    // Обработчик формы
    $('form[method="post"]').on('submit', function(e) {
        const modelId = $('#model_id').val();
        const prompt = $('#prompt').val();

        if (!modelId) {
            alert(rds_aie_image_test.select_model);
            e.preventDefault();
            return false;
        }

        if (!prompt.trim()) {
            alert(rds_aie_image_test.enter_prompt);
            e.preventDefault();
            return false;
        }

        // Показываем состояние загрузки
        const submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true).text(rds_aie_image_test.generating);
    });

    // Обработчик для кнопок копирования
    $(document).on('click', '.copy-base64', function() {
        const base64Data = $(this).data('base64');
        
        // Создаем временный элемент для копирования
        const $tempInput = $('<input>');
        $('body').append($tempInput);
        $tempInput.val(base64Data).select();

        try {
            document.execCommand('copy');
            $tempInput.remove();
            
            // Показываем временное сообщение
            const originalText = $(this).text();
            $(this).text(rds_aie_image_test.copied);
            setTimeout(() => {
                $(this).text(originalText);
            }, 2000);
        } catch (err) {
            console.error('Ошибка при копирования: ', err);
            $tempInput.remove();
        }
    });
});
