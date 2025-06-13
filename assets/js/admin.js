/**
 * Simple Admin JavaScript - Working Edit & Delete with Load More Pagination
 */
(function ($) {
  "use strict";

  // Pagination variables
  let currentPage = 1;
  let totalUsers = 0;
  let loadedUsers = 0;
  let loading = false;
  const initialLoad = 100; // First load
  const loadMoreSize = 50; // Subsequent loads

  $(document).ready(function () {
    initModals();
    initEditUserModal();
    initBulkActions();
    initLoadMore();
    initializePaginationCounters();

    // Force re-initialize for initially loaded table
    initTableButtons();

    // Show password if reset
    if (wpEmailRestriction.tempPassword) {
      $("#new-password-display").text(wpEmailRestriction.tempPassword);
      $("#password-display-modal").show();
    }
  });

  // Initialize pagination counters based on loaded table
  function initializePaginationCounters() {
    // Use data passed from PHP if available
    if (window.wpEmailRestrictionPagination) {
      loadedUsers = window.wpEmailRestrictionPagination.loadedUsers;
      totalUsers = window.wpEmailRestrictionPagination.totalUsers;
      currentPage = window.wpEmailRestrictionPagination.currentPage;
    } else {
      // Fallback: Count currently loaded users
      loadedUsers = $(".wp-list-table tbody tr").length;

      // Extract total from display text
      const displayText = $(".displaying-num").text();
      const match = displayText.match(/of (\d+) users/);
      if (match) {
        totalUsers = parseInt(match[1]);
      }

      // Set current page (we start with page 1 loaded)
      currentPage = 1;
    }
  }

  // Initialize Load More functionality
  function initLoadMore() {
    $(document).on("click", "#load-more-users", function (e) {
      e.preventDefault();
      if (!loading) {
        loadMoreUsers();
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

    // Update button state
    $button.prop("disabled", true).text("Loading...");

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "get_restricted_users_paginated", // Make sure this matches the PHP action
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
          // Append new users to table
          appendUsersToTable(response.data.users);

          // Update counters
          loadedUsers += response.data.users.length;
          totalUsers = response.data.total;

          // Update display counter
          updateUserCount();

          // Re-initialize buttons for new rows
          initTableButtons();

          // Update button text or hide it
          const remaining = totalUsers - loadedUsers;
          if (remaining > 0) {
            $button
              .prop("disabled", false)
              .text(`Load More Users (${remaining} remaining)`);
          } else {
            $button.hide();
          }
        } else {
          // No more users or error - hide button
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

  // Initialize table buttons - Force attach events to existing elements
  function initTableButtons() {
    // Ensure event handlers are attached (remove first to avoid duplicates)
    $(document).off("click.table-buttons");

    // Re-attach with namespace to avoid conflicts
    $(document).on("click.table-buttons", ".edit-user", handleEditClick);
    $(document).on("click.table-buttons", ".delete-user", handleDeleteClick);
  }

  // Initialize modals
  function initModals() {
    // Close modal on X click
    $(document).on("click", ".close-modal", function () {
      $(this).closest(".modal").hide();
    });

    // Close modal on click outside
    $(window).on("click", function (event) {
      if ($(event.target).hasClass("modal")) {
        $(".modal").hide();
      }
    });

    // Copy password button
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
        // Fallback for older browsers
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

  // Handle edit button clicks - Separate function for reliable attachment
  function handleEditClick(e) {
    e.preventDefault();

    const $button = $(this);
    const id = $button.data("id");
    const name = $button.data("name");
    const email = $button.data("email");
    const tab = $button.data("tab") || getCurrentTab();

    // Validate data
    if (!id || !name || !email) {
      alert("Error: Missing user data for user ID: " + id);
      return;
    }

    // Populate modal fields
    $("#user_id").val(id);
    $("#user_id_reset").val(id);
    $("#name_edit").val(name);
    $("#email_edit").val(email);
    $("#password_edit").val("");
    $("#edit_form_tab").val(tab);

    // Show the modal
    $("#edit-user-modal").show();
  }

  // Handle delete button clicks - Separate function for reliable attachment
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

  // Initialize edit user modal - SIMPLIFIED
  function initEditUserModal() {
    // Modal is initialized via initTableButtons() function
    // Hide modal on page load
    $("#edit-user-modal").hide();
    $("#password-display-modal").hide();
  }

  // Initialize bulk actions
  function initBulkActions() {
    // Bulk select all checkbox
    $(document).on(
      "change",
      ".check-column input[type='checkbox']",
      function () {
        const isChecked = $(this).prop("checked");
        if ($(this).closest("thead").length > 0) {
          // This is the "select all" checkbox in the header
          $("input[name='bulk-delete[]']").prop("checked", isChecked);
        }
      }
    );

    // Handle bulk delete submit
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

    // Note: Individual delete handled in initTableButtons()
  }

  // Delete a single user - SIMPLIFIED
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
            // Update counters
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

  // Bulk delete users - SIMPLIFIED
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

          // Remove deleted rows and update counters
          let deletedCount = 0;
          ids.forEach(function (id) {
            $(`input[name='bulk-delete[]'][value='${id}']`)
              .closest("tr")
              .fadeOut(300, function () {
                $(this).remove();
                deletedCount++;
                if (deletedCount === ids.length) {
                  // Update counters after all rows are removed
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

  // Show notice message - SIMPLIFIED
  function showNotice(message, type) {
    // Remove existing notices
    $(".notice.temp-notice").remove();

    const notice = $(`
      <div class="notice notice-${type} is-dismissible temp-notice">
        <p>${message}</p>
        <button type="button" class="notice-dismiss">
          <span class="screen-reader-text">Dismiss this notice.</span>
        </button>
      </div>
    `);

    // Add notice
    $(".wrap > h1").after(notice);

    // Auto-dismiss after 5 seconds
    setTimeout(function () {
      notice.fadeOut(300, function () {
        $(this).remove();
      });
    }, 5000);

    // Handle dismiss button click
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
