<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User List</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>User List</h2>
        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-primary" id="addUserButton">Add New User</button>
        </div>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Material</th>
                    <th>Production</th>
                    <th>Store</th>
                    <th>Admin</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <!-- Assume we fetch these rows dynamically from the server -->
                <tr data-user-id="1">
                    <td>1</td>
                    <td>K.Kiritharan</td>
                    <td>kiritharan100@gmail.com</td>
                    <td><input type="checkbox" name="material"></td>
                    <td><input type="checkbox" name="production"></td>
                    <td><input type="checkbox" name="store"></td>
                    <td><input type="checkbox" name="admin"></td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle" type="button" id="actionMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                Action
                            </button>
                            <div class="dropdown-menu" aria-labelledby="actionMenuButton">
                                <a class="dropdown-item edit-button" href="#" data-user-id="1">Edit</a>
                                <a class="dropdown-item inactive-button" href="#" data-user-id="1">Inactive</a>
                            </div>
                        </div>
                    </td>
                </tr>
                <!-- Add more rows as needed -->
            </tbody>
        </table>
    </div>

    <!-- Add/Edit User Modal -->
    <div class="modal fade" id="userModalNew" tabindex="-1" role="dialog" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="userForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="userModalLabel">Add  User</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="userId" id="userId">
                        <div class="form-group">
                            <label for="username">Name </label>
                            <input type="text" class="form-control" id="username" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email (user name)</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <!-- Add other form fields as needed -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <div class="modal fade" id="userModal" tabindex="-1" role="dialog" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="userForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="userModalLabel">Add/Edit User</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="userId" id="userId">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <!-- Add other form fields as needed -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // JavaScript code for handling actions

        // Add/Edit User Modal
        document.getElementById('userForm').addEventListener('submit', function(e) {
            e.preventDefault();

            let formData = new FormData(this);

            fetch('save_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('User saved successfully');
                    location.reload(); // Reload the page to show the updated user list
                } else {
                    alert('Failed to save user');
                }
            });
        });

        document.querySelectorAll('.edit-button').forEach(button => {
            button.addEventListener('click', function() {
                let userId = this.dataset.userId;
                fetch(`get_user.php?userId=${userId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('userId').value = data.userId;
                    document.getElementById('username').value = data.username;
                    document.getElementById('email').value = data.email;
                    // Populate other fields as needed
                    $('#userModal').modal('show');
                });
            });
        });

        document.getElementById('addUserButton').addEventListener('click', function() {
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            $('#userModalNew').modal('show');
        });

        // Handle checkbox changes
        document.querySelectorAll('input[type=checkbox]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                let userId = this.closest('tr').dataset.userId;
                let module = this.name;
                let isChecked = this.checked ? 1 : 0;

                fetch('update_permissions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        userId: userId,
                        module: module,
                        isChecked: isChecked
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Permission updated successfully');
                    } else {
                        alert('Failed to update permission');
                    }
                });
            });
        });

        // Inactive User
        document.querySelectorAll('.inactive-button').forEach(button => {
            button.addEventListener('click', function() {
                let userId = this.dataset.userId;
                if (confirm('Are you sure you want to deactivate this user?')) {
                    fetch('deactivate_user.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ userId: userId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('User deactivated successfully');
                            location.reload(); // Reload the page to show the updated user list
                        } else {
                            alert('Failed to deactivate user');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
