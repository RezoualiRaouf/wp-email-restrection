/**
 * Admin JavaScript
 */
(function ($) {
  "use strict";
  $(document).ready(function () {
    // Edit User Modal
    $(document).on("click", ".edit-user", function () {
      var $this = $(this);
      $("#user_id").val($this.data("id"));
      $("#user_id_reset").val($this.data("id"));
      $("#name_edit").val($this.data("name"));
      $("#email_edit").val($this.data("email"));
      $("#edit_form_tab").val($this.data("tab"));
      // Clear password field for security
      $("#password_edit").val("");
      $("#edit-user-modal").show();
    });

    // Password Display Modal
    if (wpEmailRestriction.tempPassword) {
      $("#new-password-display").text(wpEmailRestriction.tempPassword);
      $("#password-display-modal").show();
    }

    // Copy password to clipboard
    $(document).on("click", "#copy-password", function () {
      var password = $("#new-password-display").text();
      var tempInput = $("<input>");
      $("body").append(tempInput);
      tempInput.val(password).select();
      document.execCommand("copy");
      tempInput.remove();

      $(this).text("Copied!").prop("disabled", true);
      setTimeout(() => {
        $(this).text("Copy Password").prop("disabled", false);
      }, 2000);
    });

    // Generate password button
    $("#generate-password").on("click", function () {
      var randomPassword = generateRandomPassword(12);
      $("#password").val(randomPassword);
    });

    // Close modals
    $(".close-modal").on("click", function () {
      $(this).closest(".modal").hide();
    });

    // Close modal when clicking overlay
    $(".modal").on("click", function (e) {
      if ($(e.target).is(".modal")) {
        $(this).hide();
      }
    });

    // Password strength meter
    $("#password, #password_edit").on("keyup", function () {
      validatePasswordStrength(
        $(this).val(),
        $(this).siblings(".password-strength")
      );
    });

    // Helper function to generate random password
    function generateRandomPassword(length) {
      const chars =
        "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()-_=+";
      let password = "";
      for (let i = 0; i < length; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
      }
      return password;
    }

    // Helper function to validate password strength
    function validatePasswordStrength(password, $indicator) {
      if (!$indicator.length) return;

      let strength = 0;

      if (password.length >= 8) strength += 1;
      if (password.match(/[A-Z]/)) strength += 1;
      if (password.match(/[a-z]/)) strength += 1;
      if (password.match(/[0-9]/)) strength += 1;
      if (password.match(/[^A-Za-z0-9]/)) strength += 1;

      // Update indicator
      $indicator.removeClass("poor weak medium strong");

      if (strength === 0) {
        $indicator.text("");
      } else if (strength <= 2) {
        $indicator.addClass("poor").text("Poor");
      } else if (strength === 3) {
        $indicator.addClass("weak").text("Weak");
      } else if (strength === 4) {
        $indicator.addClass("medium").text("Medium");
      } else {
        $indicator.addClass("strong").text("Strong");
      }
    }

    // Bulk action handling
    $("#doaction, #doaction2").on("click", function (e) {
      const action = $(this).prev("select").val();
      if (action === "delete") {
        if (!confirm("Are you sure you want to delete the selected users?")) {
          e.preventDefault();
        }
      }
    });
  });
})(jQuery);
