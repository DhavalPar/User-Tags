jQuery(document).ready(function($) {
    $('#user_tag_filter').select2({
        placeholder: 'Filter by User Tag',
        allowClear: true,
    });

    $('#user_tag_filter').on('change', function() {
        var tagId = $(this).val();

        if (!tagId) {
            location.reload(); // Reload the page to reset the filter
            return;
        }

        console.log('Selected Tag ID:', tagId); // Debugging

        $.ajax({
            url: userTagsAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'filter_users_by_custom_field',
                tag_id: tagId,
                nonce: userTagsAjax.nonce,
            },
            success: function(response) {
                console.log('AJAX Response:', response); // Debugging

                if (response.success) {
                    var users = response.data;
                    var tableBody = $('#the-list');

                    // Clear the existing table rows
                    tableBody.empty();

                    // Add new rows for filtered users
                    users.forEach(function(user) {
                        var row = '<tr>' +
                            '<td class="username column-username">' +
                                '<strong>' + user.username + '</strong>' +
                            '</td>' +
                            '<td class="name column-name">' + user.name + '</td>' +
                            '<td class="email column-email">' + user.email + '</td>' +
                            '<td class="role column-role">' + user.role + '</td>' +
                            '<td class="posts column-posts">' + user.posts + '</td>' +
                            '</tr>';
                        tableBody.append(row);
                    });
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error); // Debugging
                alert('An error occurred while filtering users.');
            },
        });
    });
});
