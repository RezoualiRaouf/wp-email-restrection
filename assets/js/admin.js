/**
 * Admin JavaScript
 */
(function ($) {
  "use strict";

  // Variables
  let currentPage = 1;
  let totalPages = 1;
  let loading = false;
  let perPage = 25; // Updated to 25 per page
  let searchTerm = "";
  let searchField = "all";
  let orderBy = "id";
  let order = "DESC";

  // Initialize on document ready
  $(document).ready(function () {
    // Initialize modals
    initModals();

    // Initialize edit user modal
    initEditUserModal();

    // Initialize pagination
    initPagination();

    // Initialize search
    initSearch();

    // Initialize bulk actions
    initBulkActions();

    // Show password if reset
    if (wpEmailRestriction.tempPassword) {
      $("#new-password-display").text(wpEmailRestriction.tempPassword);
      $("#password-display-modal").show();
    }
  });

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

  // Initialize edit user modal
  function initEditUserModal() {
    // Show edit modal when edit button is clicked
    $(document).on("click", ".edit-user", function (e) {
      e.preventDefault();

      const id = $(this).data("id");
      const name = $(this).data("name");
      const email = $(this).data("email");
      const tab = $(this).data("tab") || getCurrentTab();

      console.log("Edit user clicked:", { id, name, email, tab }); // Debug

      // Populate modal fields
      $("#user_id").val(id);
      $("#user_id_reset").val(id);
      $("#name_edit").val(name);
      $("#email_edit").val(email);
      $("#password_edit").val("");
      $("#edit_form_tab").val(tab);

      // Show the modal
      $("#edit-user-modal").show();
    });

    // Ensure modal is properly hidden on page load
    $("#edit-user-modal").hide();
    $("#password-display-modal").hide();
  }

  // Initialize pagination
  function initPagination() {
    // Load more users on scroll
    $(window).on("scroll", function () {
      if (
        $(window).scrollTop() + $(window).height() >
        $(document).height() - 200
      ) {
        if (!loading && currentPage < totalPages) {
          loadMoreUsers();
        }
      }
    });

    // Handle pagination clicks
    $(document).on("click", ".tablenav-pages a", function (e) {
      e.preventDefault();
      const href = $(this).attr("href");
      if (href) {
        const pageMatch = href.match(/page=(\d+)/);
        if (pageMatch) {
          const page = parseInt(pageMatch[1]);
          if (page) {
            loadUsersPage(page);
          }
        }
      }
    });
  }

  // Initialize search
  function initSearch() {
    // Debounce search input
    const searchInput = $("input[name='search_term']");
    let searchTimer;

    searchInput.on("input", function () {
      clearTimeout(searchTimer);

      searchTimer = setTimeout(function () {
        searchTerm = searchInput.val();
        currentPage = 1;
        loadUsersPage(1);
      }, 500);
    });

    // Handle search field change
    $("select[name='search_field']").on("change", function () {
      searchField = $(this).val();
      if (searchTerm) {
        currentPage = 1;
        loadUsersPage(1);
      }
    });
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

    // Handle delete button click
    $(document).on("click", ".delete-user", function (e) {
      e.preventDefault();
      const id = $(this).data("id");

      if (confirm("Are you sure you want to delete this user?")) {
        deleteUser(id, $(this).closest("tr"));
      }
    });
  }

  // Load users page
  function loadUsersPage(page) {
    loading = true;
    currentPage = page;

    // Show loading indicator
    $("#users-loading").show();
    $(".tablenav").addClass("loading");

    // Load users via AJAX
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "get_restricted_users",
        security: wpEmailRestriction.nonce,
        page: page,
        per_page: perPage,
        search_term: searchTerm,
        search_field: searchField,
        orderby: orderBy,
        order: order,
      },
      success: function (response) {
        if (response.success) {
          // Update table with users
          updateUsersTable(response.data);

          // Update pagination info
          updatePaginationInfo(response.data);

          // Update total pages
          totalPages = response.data.total_pages;
        } else {
          console.error("Error loading users:", response);
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX error:", error);
        showNotice("Error loading users. Please try again.", "error");
      },
      complete: function () {
        // Hide loading indicator
        $("#users-loading").hide();
        $(".tablenav").removeClass("loading");
        loading = false;
      },
    });
  }

  // Load more users (append to existing)
  function loadMoreUsers() {
    loading = true;
    currentPage++;

    // Show loading indicator
    $("#load-more-spinner").show();

    // Load users via AJAX
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "get_restricted_users",
        security: wpEmailRestriction.nonce,
        page: currentPage,
        per_page: perPage,
        search_term: searchTerm,
        search_field: searchField,
        orderby: orderBy,
        order: order,
      },
      success: function (response) {
        if (response.success) {
          // Append users to table
          appendUsersToTable(response.data);

          // Update pagination info
          updatePaginationInfo(response.data);

          // Update total pages
          totalPages = response.data.total_pages;
        }
      },
      complete: function () {
        // Hide loading indicator
        $("#load-more-spinner").hide();
        loading = false;
      },
    });
  }

  // Update users table with fresh data
  function updateUsersTable(data) {
    const users = data.users;
    let html = "";

    // Generate table rows
    for (let i = 0; i < users.length; i++) {
      html += generateUserRow(users[i]);
    }

    // Update table body
    $(".wp-list-table tbody").html(html);
  }

  // Append users to existing table
  function appendUsersToTable(data) {
    const users = data.users;
    let html = "";

    // Generate table rows
    for (let i = 0; i < users.length; i++) {
      html += generateUserRow(users[i]);
    }

    // Append to table body
    $(".wp-list-table tbody").append(html);
  }

  // Generate single user row HTML
  function generateUserRow(user) {
    // Format date
    const date = new Date(user.created_at);
    const formattedDate =
      date.toLocaleDateString() + " " + date.toLocaleTimeString();

    // Generate row HTML
    return `
          <tr>
              <th class="check-column">
                  <input type="checkbox" name="bulk-delete[]" value="${
                    user.id
                  }">
              </th>
              <td>${user.id}</td>
              <td>${escapeHtml(user.name)}</td>
              <td>${escapeHtml(user.email)}</td>
              <td>${formattedDate}</td>
              <td>
                  <button type="button" class="button edit-user"
                      data-id="${user.id}"
                      data-name="${escapeHtml(user.name)}"
                      data-email="${escapeHtml(user.email)}"
                      data-tab="${getCurrentTab()}">
                      Edit
                  </button>
                  <a href="#" class="button delete-user" data-id="${user.id}">
                      Delete
                  </a>
              </td>
          </tr>
      `;
  }

  // Update pagination information
  function updatePaginationInfo(data) {
    const showing = Math.min(currentPage * perPage, data.total);
    $(".displaying-num").text(`Showing ${showing} of ${data.total} users`);
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
      },
      success: function (response) {
        if (response.success) {
          rowElement.fadeOut(300, function () {
            $(this).remove();

            // If all rows are removed, refresh to show "no users found"
            if ($(".wp-list-table tbody tr").length === 0) {
              loadUsersPage(1);
            }
          });

          // Show success message
          showNotice(
            response.data.message || "User deleted successfully",
            "success"
          );
        } else {
          rowElement.removeClass("deleting");
          showNotice(response.data.message || "Error deleting user", "error");
        }
      },
      error: function () {
        rowElement.removeClass("deleting");
        showNotice("Server error while deleting user", "error");
      },
    });
  }

  // Bulk delete users
  function bulkDeleteUsers(ids) {
    // Show progress indicator
    $("#bulk-operation-progress").removeClass("hidden");
    $(".progress-bar-fill").css("width", "0%");
    $(".total-count").text(ids.length);
    $(".processed-count").text("0");

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "bulk_delete_restricted_users",
        security: wpEmailRestriction.nonce,
        ids: ids,
      },
      beforeSend: function () {
        // Mark selected rows
        ids.forEach(function (id) {
          $(`input[name='bulk-delete[]'][value='${id}']`)
            .closest("tr")
            .addClass("deleting");
        });
      },
      success: function (response) {
        if (response.success) {
          // Update progress bar to 100%
          $(".progress-bar-fill").css("width", "100%");
          $(".processed-count").text(ids.length);

          // Show success message
          showNotice(response.data.message, "success");

          // Reload users table after a brief delay
          setTimeout(function () {
            loadUsersPage(1);
            $("#bulk-operation-progress").addClass("hidden");
          }, 1000);
        } else {
          // Hide progress indicator
          $("#bulk-operation-progress").addClass("hidden");

          // Show error message
          showNotice("Error performing bulk delete", "error");

          // Remove deleting class
          $(".wp-list-table tbody tr.deleting").removeClass("deleting");
        }
      },
      error: function () {
        // Hide progress indicator
        $("#bulk-operation-progress").addClass("hidden");

        // Show error message
        showNotice("Server error while performing bulk delete", "error");

        // Remove deleting class
        $(".wp-list-table tbody tr.deleting").removeClass("deleting");
      },
    });
  }

  // Show notice message
  function showNotice(message, type) {
    const notice = $(
      `<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`
    );

    // Add dismiss button
    const dismissButton = $(
      '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>'
    );
    notice.append(dismissButton);

    // Add notice to the page
    $(".wrap > h1").after(notice);

    // Auto-dismiss after 5 seconds
    setTimeout(function () {
      notice.fadeOut(300, function () {
        $(this).remove();
      });
    }, 5000);

    // Handle dismiss button click
    dismissButton.on("click", function () {
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
    return $("<div>").text(text).html();
  }

  // Debug function to test modal
  window.testModal = function () {
    $("#edit-user-modal").show();
  };

  // Add some debug info
  if (window.console) {
    console.log("WP Email Restriction Admin JS loaded");
    console.log("Current tab:", getCurrentTab());
  }
})(jQuery);
