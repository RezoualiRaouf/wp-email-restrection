/**
 * Frontend JavaScript for WP Email Restriction
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    initLoginForm();
    initLogoutLink();
  });

  function initLoginForm() {
    const $form = $("#restricted-login-form");
    const $submitButton = $form.find('button[type="submit"]');
    const $buttonText = $submitButton.find(".button-text");
    const $buttonSpinner = $submitButton.find(".button-spinner");
    const $messageDiv = $("#login-message");

    $form.on("submit", function (e) {
      e.preventDefault();

      // Clear previous messages
      hideMessage();

      // Get form data
      const formData = {
        action: "restricted_login",
        email: $("#email").val().trim(),
        password: $("#password").val(),
        nonce: $('input[name="nonce"]').val(),
        redirect_url: $('input[name="redirect_url"]').val(),
      };

      // Basic validation
      if (!formData.email || !formData.password) {
        showMessage("Please fill in all fields.", "error");
        return;
      }

      if (!isValidEmail(formData.email)) {
        showMessage("Please enter a valid email address.", "error");
        return;
      }

      // Show loading state
      setLoadingState(true);

      // Submit via AJAX
      $.ajax({
        url: wpEmailRestrictionFrontend.ajaxurl,
        type: "POST",
        data: formData,
        dataType: "json",
        success: function (response) {
          if (response.success) {
            showMessage(response.data.message, "success");

            // Redirect after successful login
            setTimeout(function () {
              window.location.href = response.data.redirect_url;
            }, 1000);
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

    function setLoadingState(loading) {
      if (loading) {
        $submitButton.prop("disabled", true);
        $buttonText.hide();
        $buttonSpinner.show();
        $form.addClass("loading");
      } else {
        $submitButton.prop("disabled", false);
        $buttonText.show();
        $buttonSpinner.hide();
        $form.removeClass("loading");
      }
    }

    function showMessage(message, type) {
      $messageDiv
        .removeClass("success error")
        .addClass(type)
        .text(message)
        .show()
        .addClass("show");

      // Auto-hide error messages after 5 seconds
      if (type === "error") {
        setTimeout(function () {
          hideMessage();
        }, 5000);
      }
    }

    function hideMessage() {
      $messageDiv.removeClass("show").fadeOut(300);
    }

    function isValidEmail(email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
    }

    // Auto-focus first input
    $("#email").focus();

    // Enter key handling
    $form.find("input").on("keypress", function (e) {
      if (e.which === 13) {
        // Enter key
        e.preventDefault();
        $form.submit();
      }
    });

    // Real-time email validation
    $("#email").on("blur", function () {
      const email = $(this).val().trim();
      if (email && !isValidEmail(email)) {
        $(this).addClass("error");
        showMessage("Please enter a valid email address.", "error");
      } else {
        $(this).removeClass("error");
        hideMessage();
      }
    });

    // Clear error styling on input
    $form.find("input").on("input", function () {
      $(this).removeClass("error");
    });
  }

  function initLogoutLink() {
    $(document).on("click", ".restricted-logout-link", function (e) {
      e.preventDefault();

      if (!confirm("Are you sure you want to logout?")) {
        return;
      }

      $.ajax({
        url: wpEmailRestrictionFrontend.ajaxurl,
        type: "POST",
        data: {
          action: "restricted_logout",
          nonce: wpEmailRestrictionFrontend.logout_nonce,
        },
        dataType: "json",
        success: function (response) {
          if (response.success) {
            // Redirect to home page or login page
            window.location.href = window.location.origin;
          }
        },
        error: function () {
          // Fallback - just reload the page
          window.location.reload();
        },
      });
    });
  }

  // Add some nice animations
  function addAnimations() {
    // Fade in the form
    $(".login-form-wrapper")
      .css({
        opacity: "0",
        transform: "translateY(20px)",
      })
      .animate(
        {
          opacity: "1",
        },
        500
      )
      .css("transform", "translateY(0)");

    // Add focus animations
    $('input[type="email"], input[type="password"]')
      .on("focus", function () {
        $(this).parent().addClass("focused");
      })
      .on("blur", function () {
        if (!$(this).val()) {
          $(this).parent().removeClass("focused");
        }
      });
  }

  // Initialize animations
  setTimeout(addAnimations, 100);

  // Add some security features
  function addSecurityFeatures() {
    // Disable right-click on login form (optional)
    $(".login-form").on("contextmenu", function (e) {
      e.preventDefault();
    });

    // Clear password field on page visibility change
    $(document).on("visibilitychange", function () {
      if (document.hidden) {
        $("#password").val("");
      }
    });

    // Add CSRF protection
    const csrfToken = $('meta[name="csrf-token"]').attr("content");
    if (csrfToken) {
      $.ajaxSetup({
        headers: {
          "X-CSRF-TOKEN": csrfToken,
        },
      });
    }
  }

  addSecurityFeatures();
})(jQuery);
