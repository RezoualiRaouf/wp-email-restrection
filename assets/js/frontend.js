/**
 * Enhanced Frontend JavaScript for WP Email Restriction with Preview Mode
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    initLoginForm();
    initLogoutLink();

    // 🆕 Initialize preview mode features
    if (wpEmailRestrictionFrontend.is_preview) {
      initPreviewMode();
    }
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
        is_preview: $('input[name="is_preview"]').val(), // 🆕 Include preview mode
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

            // 🆕 Add delay for preview mode to show success message
            const redirectDelay = wpEmailRestrictionFrontend.is_preview
              ? 1500
              : 1000;

            // Redirect after successful login
            setTimeout(function () {
              window.location.href = response.data.redirect_url;
            }, redirectDelay);
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

  // 🆕 Initialize preview mode specific features
  function initPreviewMode() {
    // Add preview mode indicator to console for debugging
    console.log("🔍 Preview Mode Active - Admin Testing Enabled");

    // Add preview mode class to body for CSS styling
    $("body").addClass("preview-mode");

    // Auto-focus password field if email is pre-filled
    if ($("#email").val().trim() !== "") {
      $("#password").focus();
    }

    // Add preview-specific styling
    addPreviewModeStyles();

    // Show helpful tooltips for admin testing
    showPreviewTooltips();
  }

  // 🆕 Add preview mode specific styles
  function addPreviewModeStyles() {
    const previewCSS = `
      <style id="preview-mode-styles">
        .test-user-hint {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: white;
          padding: 15px;
          margin: 15px 30px;
          border-radius: 8px;
          text-align: center;
          animation: pulseGlow 2s ease-in-out infinite alternate;
        }
        
        .test-user-hint code {
          background: rgba(255,255,255,0.2);
          padding: 2px 6px;
          border-radius: 3px;
          font-family: monospace;
          color: #fff;
        }
        
        @keyframes pulseGlow {
          from { box-shadow: 0 0 20px rgba(102, 126, 234, 0.4); }
          to { box-shadow: 0 0 30px rgba(118, 75, 162, 0.6); }
        }
        
        .preview-mode .login-button {
          position: relative;
          overflow: hidden;
        }
        
        .preview-mode .login-button::before {
          content: '';
          position: absolute;
          top: 0;
          left: -100%;
          width: 100%;
          height: 100%;
          background: linear-gradient(
            90deg,
            transparent,
            rgba(255,255,255,0.2),
            transparent
          );
          transition: left 0.5s;
        }
        
        .preview-mode .login-button:hover::before {
          left: 100%;
        }
      </style>
    `;

    $("head").append(previewCSS);
  }

  // 🆕 Show helpful tooltips for preview mode
  function showPreviewTooltips() {
    // Add tooltip to password field
    $("#password").attr("title", "Use your WordPress admin password");

    // Add visual feedback when admin email is detected
    const emailField = $("#email");
    if (emailField.val().includes("admin") || emailField.val().includes("@")) {
      emailField.css({
        "border-color": "#667eea",
        "box-shadow": "0 0 5px rgba(102, 126, 234, 0.3)",
      });
    }
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
