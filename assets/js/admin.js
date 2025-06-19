/**
 * Enhanced Admin JavaScript with Domain Validation
 *
 * @package WP_Email_Restriction
 */
(function ($) {
  "use strict";

  // Pagination variables
  let currentPage = 1;
  let totalUsers = 0;
  let loadedUsers = 0;
  let loading = false;
  const initialLoad = 100;
  const loadMoreSize = 50;

  // Domain validation variables
  let domainValidationTimeout;
  let lastValidatedDomain = "";

  $(document).ready(function () {
    initModals();
    initEditUserModal();
    initBulkActions();
    initLoadMore();
    initializePaginationCounters();
    initDomainValidation();
    initDomainExamples();
    initTableButtons();

    // Show password only if there's actually a temp password and we're on the right page
    if (
      wpEmailRestriction.tempPassword &&
      wpEmailRestriction.tempPassword.length > 0
    ) {
      // Check if we're on a password reset result page
      const urlParams = new URLSearchParams(window.location.search);
      if (
        urlParams.get("password_reset") === "1" ||
        document.referrer.includes("reset_password")
      ) {
        $("#new-password-display").text(wpEmailRestriction.tempPassword);
        $("#password-display-modal").show();
      }
    }

    // Check if domain is configured and show appropriate UI
    updateUIBasedOnDomainStatus();
  });

  /**
   * Initialize domain validation functionality
   */
  function initDomainValidation() {
    const $domainInput = $("#allowed_domain");
    const $domainGroup = $(".domain-input-group");
    const $validationMessage = $(".domain-validation-message");
    const $liveValidation = $(".domain-field-live-validation");

    if ($domainInput.length === 0) return;

    // Real-time validation as user types
    $domainInput.on("input", function () {
      const domain = $(this).val().trim();

      // Clear previous timeout
      clearTimeout(domainValidationTimeout);

      // Reset UI state
      $domainGroup.removeClass("loading error success");
      $validationMessage.removeClass("error success").hide();
      $liveValidation.removeClass("valid invalid");

      if (domain === "") {
        return;
      }

      // Show loading state
      $domainGroup.addClass("loading");

      // Debounce validation
      domainValidationTimeout = setTimeout(function () {
        validateDomainAjax(domain);
      }, 500);
    });

    // Handle form submission
    $domainInput.closest("form").on("submit", function (e) {
      const domain = $domainInput.val().trim();

      if (domain === "") {
        e.preventDefault();
        showDomainValidationError("Please enter a domain.");
        return false;
      }

      // If domain hasn't been validated yet, validate now
      if (domain !== lastValidatedDomain) {
        e.preventDefault();
        $domainGroup.addClass("loading");

        validateDomainAjax(domain, function (isValid) {
          if (isValid) {
            // Resubmit form if validation passes
            $domainInput.closest("form").off("submit").submit();
          }
        });
        return false;
      }
    });
  }

  /**
   * Validate domain via AJAX
   */
  function validateDomainAjax(domain, callback = null) {
    const $domainGroup = $(".domain-input-group");
    const $liveValidation = $(".domain-field-live-validation");

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "validate_domain",
        domain: domain,
        security: wpEmailRestriction.nonce,
      },
      success: function (response) {
        $domainGroup.removeClass("loading");
        lastValidatedDomain = domain;

        if (response.valid) {
          showDomainValidationSuccess(response.message);
          $liveValidation.addClass("valid").text("✓");
          if (callback) callback(true);
        } else {
          showDomainValidationError(response.message);
          $liveValidation.addClass("invalid").text("✗");
          if (callback) callback(false);
        }
      },
      error: function () {
        $domainGroup.removeClass("loading");
        showDomainValidationError(
          "Unable to validate domain. Please check your input."
        );
        $liveValidation.addClass("invalid").text("✗");
        if (callback) callback(false);
      },
    });
  }

  /**
   * Show domain validation success
   */
  function showDomainValidationSuccess(message) {
    const $domainGroup = $(".domain-input-group");
    const $validationMessage = $(".domain-validation-message");

    $domainGroup.removeClass("error loading").addClass("success");
    $validationMessage
      .removeClass("error")
      .addClass("success")
      .text(message)
      .show();
  }

  /**
   * Show domain validation error
   */
  function showDomainValidationError(message) {
    const $domainGroup = $(".domain-input-group");
    const $validationMessage = $(".domain-validation-message");

    $domainGroup.removeClass("success loading").addClass("error");
    $validationMessage
      .removeClass("success")
      .addClass("error")
      .text(message)
      .show();
  }

  /**
   * Initialize domain examples functionality
   */
  function initDomainExamples() {
    $(document).on("click", ".domain-example", function () {
      const domain = $(this).text().trim();
      const $domainInput = $("#allowed_domain");

      if ($domainInput.length) {
        $domainInput.val(domain).trigger("input").focus();

        // Add visual feedback
        $(this).css("background", "#0073aa").css("color", "white");
        setTimeout(() => {
          $(this).css("background", "").css("color", "");
        }, 300);
      }
    });
  }

  /**
   * Update UI based on domain configuration status
   */
  function updateUIBasedOnDomainStatus() {
    const isDomainConfigured = wpEmailRestriction.domainConfigured;

    if (!isDomainConfigured) {
      // Disable certain tabs and show setup notices
      $(".nav-tab.tab-disabled").on("click", function (e) {
        e.preventDefault();
        showNotice(
          "Please configure your domain first in the Settings tab.",
          "warning"
        );
        // Highlight settings tab
        $('.nav-tab[href*="tab=settings"]').addClass("pulse-highlight");
        setTimeout(() => {
          $('.nav-tab[href*="tab=settings"]').removeClass("pulse-highlight");
        }, 2000);
      });
    }
  }

  // Initialize pagination counters based on loaded table
  function initializePaginationCounters() {
    if (window.wpEmailRestrictionPagination) {
      loadedUsers = window.wpEmailRestrictionPagination.loadedUsers;
      totalUsers = window.wpEmailRestrictionPagination.totalUsers;
      currentPage = window.wpEmailRestrictionPagination.currentPage;
    } else {
      loadedUsers = $(".wp-list-table tbody tr").length;
      const displayText = $(".displaying-num").text();
      const match = displayText.match(/of (\d+) users/);
      if (match) {
        totalUsers = parseInt(match[1]);
      }
      currentPage = 1;
    }
  }

  // Initialize Load More functionality
  function initLoadMore() {
    $(document).on("click", "#load-more-users", function (e) {
      e.preventDefault();
      if (!loading && wpEmailRestriction.domainConfigured) {
        loadMoreUsers();
      } else if (!wpEmailRestriction.domainConfigured) {
        showNotice("Please configure your domain first.", "error");
      }
    });
  }

  // Load more users
  function loadMoreUsers() {
    if (loading) return;

    loading = true;
    currentPage++;

    const $button = $("#load-more-users");
    const originalText = $button.text();

    $button.prop("disabled", true).text("Loading...");

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "get_restricted_users_paginated",
        security: wpEmailRestriction.nonce,
        page: currentPage,
        per_page: loadMoreSize,
        search_term: getSearchTerm(),
        search_field: getSearchField(),
      },
      success: function (response) {
        if (
          response.success &&
          response.data &&
          response.data.users &&
          response.data.users.length > 0
        ) {
          appendUsersToTable(response.data.users);
          loadedUsers += response.data.users.length;
          totalUsers = response.data.total;
          updateUserCount();
          initTableButtons();

          const remaining = totalUsers - loadedUsers;
          if (remaining > 0) {
            $button
              .prop("disabled", false)
              .text(`Load More Users (${remaining} remaining)`);
          } else {
            $button.hide();
          }
        } else {
          $button.hide();
          if (response.data && response.data.message) {
            showNotice(response.data.message, "info");
          }
        }
      },
      error: function (xhr, status, error) {
        showNotice("Error loading more users: " + error, "error");
        $button.prop("disabled", false).text(originalText);
      },
      complete: function () {
        loading = false;
      },
    });
  }

  // Append users to existing table
  function appendUsersToTable(users) {
    const $tbody = $(".wp-list-table tbody");
    const currentTab = getCurrentTab();

    users.forEach(function (user) {
      const row = `
        <tr>
          <th class="check-column">
            <input type="checkbox" name="bulk-delete[]" value="${user.id}">
          </th>
          <td>${user.id}</td>
          <td><strong>${escapeHtml(user.name || "")}</strong></td>
          <td>${escapeHtml(user.email || "")}</td>
          <td>${formatDate(user.created_at)}</td>
          <td class="actions">
            <button type="button" class="button edit-user"
                data-id="${user.id}"
                data-name="${escapeHtml(user.name || "")}"
                data-email="${escapeHtml(user.email || "")}"
                data-tab="${currentTab}"
                title="Edit user: ${escapeHtml(user.name || "")}">
                Edit
            </button>
            <button type="button" class="button delete-user" 
                data-id="${user.id}"
                title="Delete user: ${escapeHtml(user.name || "")}">
                Delete
            </button>
          </td>
        </tr>
      `;
      $tbody.append(row);
    });
  }

  // Update user count display
  function updateUserCount() {
    $(".displaying-num").text(`Showing ${loadedUsers} of ${totalUsers} users`);
  }

  // Format date helper
  function formatDate(dateString) {
    if (!dateString) return "N/A";
    try {
      const date = new Date(dateString);
      return date.toLocaleDateString() + " " + date.toLocaleTimeString();
    } catch (e) {
      return "N/A";
    }
  }

  // Get current search term
  function getSearchTerm() {
    const searchInput = $("input[name='search_term']");
    return searchInput.length ? searchInput.val() || "" : "";
  }

  // Get current search field
  function getSearchField() {
    const searchField = $("select[name='search_field']");
    return searchField.length ? searchField.val() || "all" : "all";
  }

  // Initialize table buttons
  function initTableButtons() {
    $(document).off("click.table-buttons");
    $(document).on("click.table-buttons", ".edit-user", handleEditClick);
    $(document).on("click.table-buttons", ".delete-user", handleDeleteClick);
  }

  // Initialize modals
  function initModals() {
    $(document).on("click", ".close-modal", function () {
      $(this).closest(".modal").hide();
    });

    $(window).on("click", function (event) {
      if ($(event.target).hasClass("modal")) {
        $(".modal").hide();
      }
    });

    $(document).on("click", "#copy-password", function () {
      const password = $("#new-password-display").text();
      if (navigator.clipboard) {
        navigator.clipboard.writeText(password).then(() => {
          $(this).text("Copied!");
          setTimeout(() => {
            $(this).text("Copy Password");
          }, 2000);
        });
      } else {
        const tempInput = $("<input>");
        $("body").append(tempInput);
        tempInput.val(password).select();
        document.execCommand("copy");
        tempInput.remove();
        $(this).text("Copied!");
        setTimeout(() => {
          $(this).text("Copy Password");
        }, 2000);
      }
    });
  }

  // Handle edit button clicks
  function handleEditClick(e) {
    e.preventDefault();

    const $button = $(this);
    const id = $button.data("id");
    const name = $button.data("name");
    const email = $button.data("email");
    const tab = $button.data("tab") || getCurrentTab();

    if (!id || !name || !email) {
      alert("Error: Missing user data for user ID: " + id);
      return;
    }

    $("#user_id").val(id);
    $("#user_id_reset").val(id);
    $("#name_edit").val(name);
    $("#email_edit").val(email);
    $("#password_edit").val("");
    $("#edit_form_tab").val(tab);

    $("#edit-user-modal").show();
  }

  // Handle delete button clicks
  function handleDeleteClick(e) {
    e.preventDefault();

    const $button = $(this);
    const id = $button.data("id");
    const userName = $button
      .closest("tr")
      .find("td:nth-child(3)")
      .text()
      .trim();

    if (!id) {
      alert("Error: Missing user ID");
      return;
    }

    if (confirm(`Are you sure you want to delete user "${userName}"?`)) {
      deleteUser(id, $button.closest("tr"));
    }
  }

  // Initialize edit user modal
  function initEditUserModal() {
    $("#edit-user-modal").hide();
    $("#password-display-modal").hide();
  }

  // Initialize bulk actions
  function initBulkActions() {
    $(document).on(
      "change",
      ".check-column input[type='checkbox']",
      function () {
        const isChecked = $(this).prop("checked");
        if ($(this).closest("thead").length > 0) {
          $("input[name='bulk-delete[]']").prop("checked", isChecked);
        }
      }
    );

    $(document).on("click", "#doaction", function (e) {
      const action = $("#bulk-action-selector-top").val();

      if (action === "bulk_delete") {
        e.preventDefault();
        const selectedIds = [];

        $("input[name='bulk-delete[]']:checked").each(function () {
          selectedIds.push(parseInt($(this).val()));
        });

        if (selectedIds.length > 0) {
          if (
            confirm(
              `Are you sure you want to delete ${selectedIds.length} users?`
            )
          ) {
            bulkDeleteUsers(selectedIds);
          }
        } else {
          alert("Please select at least one user to delete.");
        }
      }
    });
  }

  // Delete a single user
  function deleteUser(id, rowElement) {
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "delete_restricted_user",
        security: wpEmailRestriction.nonce,
        id: id,
      },
      beforeSend: function () {
        rowElement.addClass("deleting");
        rowElement
          .find(".delete-user")
          .prop("disabled", true)
          .text("Deleting...");
      },
      success: function (response) {
        if (response.success) {
          rowElement.fadeOut(300, function () {
            $(this).remove();
            loadedUsers--;
            totalUsers--;
            updateUserCount();
          });
          showNotice(
            response.data.message || "User deleted successfully",
            "success"
          );
        } else {
          rowElement.removeClass("deleting");
          rowElement
            .find(".delete-user")
            .prop("disabled", false)
            .text("Delete");
          showNotice(response.data.message || "Error deleting user", "error");
        }
      },
      error: function (xhr, status, error) {
        rowElement.removeClass("deleting");
        rowElement.find(".delete-user").prop("disabled", false).text("Delete");
        showNotice("Server error while deleting user", "error");
      },
    });
  }

  // Bulk delete users
  function bulkDeleteUsers(ids) {
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "bulk_delete_restricted_users",
        security: wpEmailRestriction.nonce,
        ids: ids,
      },
      beforeSend: function () {
        ids.forEach(function (id) {
          $(`input[name='bulk-delete[]'][value='${id}']`)
            .closest("tr")
            .addClass("deleting");
        });
      },
      success: function (response) {
        if (response.success) {
          showNotice(response.data.message, "success");

          let deletedCount = 0;
          ids.forEach(function (id) {
            $(`input[name='bulk-delete[]'][value='${id}']`)
              .closest("tr")
              .fadeOut(300, function () {
                $(this).remove();
                deletedCount++;
                if (deletedCount === ids.length) {
                  loadedUsers -= ids.length;
                  totalUsers -= ids.length;
                  updateUserCount();
                }
              });
          });
        } else {
          showNotice("Error performing bulk delete", "error");
          $(".wp-list-table tbody tr.deleting").removeClass("deleting");
        }
      },
      error: function (xhr, status, error) {
        showNotice("Server error while performing bulk delete", "error");
        $(".wp-list-table tbody tr.deleting").removeClass("deleting");
      },
    });
  }

  // Show notice message
  function showNotice(message, type) {
    $(".notice.temp-notice").remove();

    const notice = $(`
      <div class="notice notice-${type} is-dismissible temp-notice domain-notice">
        <p>${message}</p>
        <button type="button" class="notice-dismiss">
          <span class="screen-reader-text">Dismiss this notice.</span>
        </button>
      </div>
    `);

    $(".wrap > h1").after(notice);

    setTimeout(function () {
      notice.fadeOut(300, function () {
        $(this).remove();
      });
    }, 5000);

    notice.find(".notice-dismiss").on("click", function () {
      notice.fadeOut(300, function () {
        $(this).remove();
      });
    });
  }

  // Get current tab
  function getCurrentTab() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get("tab") || "main";
  }

  // Escape HTML for security
  function escapeHtml(text) {
    if (!text) return "";
    return $("<div>").text(String(text)).html();
  }

  // Force initialization after a delay to ensure DOM is fully ready
  setTimeout(function () {
    initTableButtons();
  }, 500);
})(jQuery);
