/**
 * Admin JavaScript
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // Tab functionality
    function activateTab(tabId) {
      // Hide all tab contents
      $(".tab-content").hide();

      // Show the selected tab content
      $("#" + tabId).show();
    }

    // Edit Email Modal
    $(".edit-email").on("click", function () {
      var id = $(this).data("id");
      var email = $(this).data("email");
      var currentTab = $(this).data("tab");

      $("#email_id").val(id);
      $("#email_edit").val(email);
      $("#edit_form_tab").val(currentTab);
      $("#edit-email-modal").show();
    });

    // Close modal when clicking on X
    $(".close-modal").on("click", function () {
      $("#edit-email-modal").hide();
    });

    // Close modal when clicking outside
    $(window).on("click", function (event) {
      if ($(event.target).is("#edit-email-modal")) {
        $("#edit-email-modal").hide();
      }
    });

    // Form validation for search
    $("#search-form").on("submit", function (e) {
      var searchTerm = $("#search_term").val().trim();
      if (searchTerm === "") {
        alert("Please enter a search term.");
        e.preventDefault();
      }
    });
  });
})(jQuery);
