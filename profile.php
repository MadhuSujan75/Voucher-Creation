<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Voucher Dashboard</title>
  <style>
    body {
      margin: 0;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background: #f8f9fa;
    }

    /* Navbar */
    .navbar {
      background: #ffffff;
      border-bottom: 1px solid #ddd;
      padding: 12px 40px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      position: sticky;
      top: 0;
      z-index: 1000;
    }

    .navbar .logo {
      font-size: 20px;
      font-weight: bold;
      color: #2c3e50;
      letter-spacing: 0.5px;
    }

    .nav-links {
      display: flex;
      gap: 12px;
    }

    /* Beautiful Buttons */
    .btn {
      padding: 8px 18px;
      border-radius: 25px;
      font-size: 14px;
      font-weight: 500;
      border: none;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .btn-primary {
      background: #007bff;
      color: #fff;
    }
    .btn-primary:hover {
      background: #0056b3;
    }

    .btn-success {
      background: #28a745;
      color: #fff;
    }
    .btn-success:hover {
      background: #1e7e34;
    }

    .btn-warning {
      background: #fd7e14;
      color: #fff;
    }
    .btn-warning:hover {
      background: #e96a00;
    }

    .btn-danger {
      background: #dc3545;
      color: #fff;
    }
    .btn-danger:hover {
      background: #b02a37;
    }
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="logo">Voucher Dashboard</div>
    <div class="nav-links">
      <a href="voucher_create.php"><button class="btn btn-primary">Create Voucher</button></a>
      <a href="vouchers_list.php"><button class="btn btn-success">Voucher List</button></a>
      <a href="voucher_redeem.php"><button class="btn btn-warning">Redeem Voucher</button></a>
      <a href="logout.php"><button class="btn btn-danger">Logout</button></a>
    </div>
  </nav>
</body>
</html>
