/**
 * Enhanced Frontend JavaScript for WP Email Restriction
 *
 * @package WP_Email_Restriction
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    initLoginForm();
    initLogoutLink();
    initAnimations();
    initSecurityFeatures();
  });

  function initLoginForm() {
    const $form = $("#restricted-login-form");
    if (!$form.length) return;

    const $submitButton = $form.find('button[type="submit"]');
    const $buttonText = $submitButton.find(".button-text");
    const $buttonSpinner = $submitButton.find(".button-spinner");
    const $messageDiv = $("#login-message");
    const $email = $("#email");
    const $password = $("#password");

    function setLoadingState(loading) {
      $submitButton.prop("disabled", loading);
      $buttonText.toggle(!loading);
      $buttonSpinner.toggle(loading);
      $form.toggleClass("loading", loading);
    }

    function showMessage(msg, type) {
      $messageDiv
        .removeClass("success error")
        .addClass(type)
        .text(msg)
        .fadeIn()
        .addClass("show");

      if (type === "error") {
        setTimeout(hideMessage, 5000);
      }
    }

    function hideMessage() {
      $messageDiv.removeClass("show").fadeOut(300);
    }

    function isValidEmail(email) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    $form.on("submit", function (e) {
      e.preventDefault();
      hideMessage();

      const formData = {
        action: "restricted_login",
        email: $email.val().trim(),
        password: $password.val(),
        nonce: $form.find('[name="nonce"]').val(),
        redirect_url: $form.find('[name="redirect_url"]').val(),
        is_preview: $form.find('[name="is_preview"]').val(),
      };

      if (!formData.email || !formData.password) {
        return showMessage("Please fill in all fields.", "error");
      }

      if (!isValidEmail(formData.email)) {
        return showMessage("Please enter a valid email address.", "error");
      }

      setLoadingState(true);

      $.post({
        url: wpEmailRestrictionFrontend.ajaxurl,
        data: formData,
        dataType: "json",
        success: function (response) {
          if (response.success) {
            showMessage(response.data.message, "success");
            const delay = wpEmailRestrictionFrontend.is_preview ? 1500 : 1000;
            setTimeout(function () {
              window.location.href = response.data.redirect_url;
            }, delay);
          } else {
            showMessage(response.data.message, "error");
            setLoadingState(false);
          }
        },
        error: function (xhr, status, error) {
          console.error("Login error:", error);
          showMessage("An error occurred. Please try again.", "error");
          setLoadingState(false);
        },
      });
    });

    $form.find("input").on("keypress", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        $form.submit();
      }
    });

    $email.on("blur", function () {
      const emailVal = $(this).val().trim();
      if (emailVal && !isValidEmail(emailVal)) {
        $(this).addClass("error");
        showMessage("Please enter a valid email address.", "error");
      } else {
        $(this).removeClass("error");
        hideMessage();
      }
    });

    $form.find("input").on("input", function () {
      $(this).removeClass("error");
    });

    $email.focus();
  }

  function initLogoutLink() {
    $(document).on("click", ".restricted-logout-link", function (e) {
      e.preventDefault();

      if (!confirm("Are you sure you want to logout?")) return;

      $.post({
        url: wpEmailRestrictionFrontend.ajaxurl,
        data: {
          action: "restricted_logout",
          nonce: wpEmailRestrictionFrontend.logout_nonce,
        },
        dataType: "json",
        success: function (response) {
          if (response.success) {
            window.location.href = window.location.origin;
          }
        },
        error: function () {
          window.location.reload();
        },
      });
    });
  }

  function initAnimations() {
    const $wrapper = $(".login-form-wrapper");
    $wrapper
      .css({ opacity: "0", transform: "translateY(20px)" })
      .animate({ opacity: "1" }, 500)
      .css("transform", "translateY(0)");

    $('input[type="email"], input[type="password"]').on({
      focus: function () {
        $(this).parent().addClass("focused");
      },
      blur: function () {
        if (!$(this).val()) {
          $(this).parent().removeClass("focused");
        }
      },
    });
  }

  function initSecurityFeatures() {
    $(".login-form").on("contextmenu", function (e) {
      e.preventDefault();
    });

    $(document).on("visibilitychange", function () {
      if (document.hidden) {
        $("#password").val("");
      }
    });

    const csrfToken = $('meta[name="csrf-token"]').attr("content");
    if (csrfToken) {
      $.ajaxSetup({
        headers: { "X-CSRF-TOKEN": csrfToken },
      });
    }
  }

  setTimeout(initAnimations, 100);
})(jQuery);
