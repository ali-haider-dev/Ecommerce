<?php
include 'admin/db.php';
print_r($_SESSION);
// 1. SECURITY: Enforce Login for Checkout
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id <= 0 || !isset($_SESSION['LogedIn']) || $_SESSION['LogedIn'] != 1) {
  // If not logged in, redirect to login/sign-in page
  header("Location: sign-in.php?redirect=checkout.php");
  exit();
}

function format_price_rs($price)
{
  return number_format((float) $price, 2, '.', ',');
}
$cart_items = [];
$cart_subtotal = 0.00;
$shipping_cost = 10.00; // Hardcoded default shipping (can be made dynamic later)
$final_total = 0;

try {
  $user_data = [];
  $stmt_user = $conn->prepare("
    SELECT 
        u.first_name, 
        u.last_name, 
        u.email, 
        up.address, 
        up.city, 
        up.postcode
    FROM 
        tbl_users u
    LEFT JOIN 
        tbl_user_profiles up ON u.id = up.user_id
    WHERE 
        u.id = ?
");

  $stmt_user->bind_param("i", $user_id);
  $stmt_user->execute();
  $result_user = $stmt_user->get_result();
  if ($result_user->num_rows > 0) {
    $user_data = $result_user->fetch_assoc();
  }
  $stmt_user->close();

  // --- 3. Fetch Cart Items and Calculate Total ---

  $stmt_cart = $conn->prepare("SELECT c.product_id, c.quantity, p.product_name, p.price 
                                 FROM tbl_cart c
                                 JOIN tbl_products p ON c.product_id = p.id
                                 WHERE c.user_id = ?");
  $stmt_cart->bind_param("i", $user_id);
  $stmt_cart->execute();
  $result_cart = $stmt_cart->get_result();

  // The cart fetch logic is CORRECT in your provided code
  while ($row = $result_cart->fetch_assoc()) {
    $row['subtotal'] = $row['price'] * $row['quantity'];
    $cart_subtotal += $row['subtotal'];
    $cart_items[] = $row;
  }
  $stmt_cart->close();

  // CHECK: Redirect if cart is empty after fetching data
  if (empty($cart_items)) {
    header("Location: cart.php");
    exit(); // CRITICAL: Ensure execution stops after redirect
  }

  $final_total = $cart_subtotal + $shipping_cost;

  // Helper function to format price

} catch (Exception $e) {
  // Handle database error gracefully
  $error_message = "An error occurred while preparing your checkout: " . $e->getMessage();
  error_log($error_message);
  // For now, die with a friendly message
  echo $error_message;
} finally {
  // Ensure connection is closed only if it was successfully opened
  if (isset($conn) && $conn) {
    $conn->close();
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title>Molla - Bootstrap eCommerce Template</title>
  <meta name="keywords" content="HTML5 Template" />
  <meta name="description" content="Molla - Bootstrap eCommerce Template" />
  <meta name="author" content="p-themes" />
  <link rel="apple-touch-icon" sizes="180x180" href="assets/images/icons/apple-touch-icon.png" />
  <link rel="icon" type="image/png" sizes="32x32" href="assets/images/icons/favicon-32x32.png" />
  <link rel="icon" type="image/png" sizes="16x16" href="assets/images/icons/favicon-16x16.png" />
  <link rel="manifest" href="assets/images/icons/site.html" />
  <link rel="mask-icon" href="assets/images/icons/safari-pinned-tab.svg" color="#666666" />
  <link rel="shortcut icon" href="assets/images/icons/favicon.ico" />
  <meta name="apple-mobile-web-app-title" content="Molla" />
  <meta name="application-name" content="Molla" />
  <meta name="msapplication-TileColor" content="#cc9966" />
  <meta name="msapplication-config" content="assets/images/icons/browserconfig.xml" />
  <meta name="theme-color" content="#ffffff" />
  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/style.css" />
</head>

<body>
  <div class="page-wrapper">
    <?php include 'header.php'; ?>
    <main class="main">
      <div class="page-content">
        <div class="checkout">
          <div class="container">
            <div class="checkout-discount">
            </div>

            <form id="checkout-form" action="functions/place_order.php" method="POST">
              <div class="row">
                <div class="col-lg-9">
                  <h2 class="checkout-title">Billing Details</h2>
                  <div class="row">
                    <div class="col-sm-6">
                      <label>First Name *</label>
                      <input type="text" class="form-control" name="firstname"
                        value="<?= htmlspecialchars($user_data['first_name'] ?? '') ?>" required />
                    </div>
                    <div class="col-sm-6">
                      <label>Last Name *</label>
                      <input type="text" class="form-control" name="lastname"
                        value="<?= htmlspecialchars($user_data['lastname'] ?? '') ?>" required />
                    </div>
                  </div>

                  <label>Company Name (Optional)</label>
                  <input type="text" class="form-control" name="company" />

                  <label>Country *</label>
                  <input type="text" class="form-control" name="country" value="Pakistan" required />

                  <label>Street address *</label>
                  <input type="text" class="form-control" name="address1" placeholder="House number and Street name"
                    value="<?= htmlspecialchars($user_data['address'] ?? '') ?>" required />

                  <input type="text" class="form-control" name="address2"
                    placeholder="Appartments, suite, unit etc ..." />

                  <div class="row">
                    <div class="col-sm-6">
                      <label>Town / City *</label>
                      <input type="text" class="form-control" name="city"
                        value="<?= htmlspecialchars($user_data['city'] ?? '') ?>" required />
                    </div>
                    <div class="col-sm-6">
                      <label>State / County *</label>
                      <input type="text" class="form-control" name="state" required />
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-sm-6">
                      <label>Postcode / ZIP *</label>
                      <input type="text" class="form-control" name="postcode"
                        value="<?= htmlspecialchars($user_data['postcode'] ?? '') ?>" required />
                    </div>
                    <div class="col-sm-6">
                      <label>Phone *</label>
                      <input type="tel" class="form-control" name="phone_number"
                        value="<?= htmlspecialchars($user_data['phone_number'] ?? '') ?>" required />
                    </div>
                  </div>

                  <label>Email address *</label>
                  <input type="email" class="form-control" name="email"
                    value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required readonly />

                  <?php
                  // Prepare the shipping/billing address string from user data for DB insertion
                  $full_address_string = implode(', ', array_filter([
                    $user_data['address'] ?? '',
                    $user_data['city'] ?? '',
                    $user_data['postcode'] ?? '',
                    'Pakistan'
                  ]));
                  ?>
                  <input type="hidden" name="shipping_address_string" id="shipping_address_string"
                    value="<?= htmlspecialchars($full_address_string) ?>">
                  <input type="hidden" name="billing_address_string" id="billing_address_string"
                    value="<?= htmlspecialchars($full_address_string) ?>">

                  <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="checkout-diff-address" />
                    <label class="custom-control-label" for="checkout-diff-address">Ship to a different address?</label>
                  </div>
                  <label>Order notes (optional)</label>
                  <textarea class="form-control" cols="30" rows="4" name="order_notes"
                    placeholder="Notes about your order, e.g. special notes for delivery"></textarea>
                </div>

                <aside class="col-lg-3">
                  <div class="summary">
                    <h3 class="summary-title">Your Order</h3>
                    <table class="table table-summary">
                      <thead>
                        <tr>
                          <th>Product</th>
                          <th>Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($cart_items as $item): ?>
                          <tr>
                            <td>
                              <a href="product.php?id=<?= $item['product_id'] ?>"><?= htmlspecialchars($item['product_name']) ?>
                                (x<?= $item['quantity'] ?>)</a>
                            </td>
                            <td>Rs. <?= format_price_rs($item['price'] * $item['quantity']) ?></td>
                          </tr>
                        <?php endforeach; ?>
                        <tr class="summary-subtotal">
                          <td>Subtotal:</td>
                          <td>Rs. <?= format_price_rs($cart_subtotal) ?></td>
                        </tr>
                        <tr>
                          <td>Shipping:</td>
                          <td>Rs. <?= format_price_rs($shipping_cost) ?></td>
                        </tr>
                        <tr class="summary-total">
                          <td>Total:</td>
                          <td>Rs. <?= format_price_rs($final_total) ?></td>
                        </tr>
                      </tbody>
                    </table>

                    <div class="accordion-summary" id="accordion-payment">
                      <div class="card">
                        <div class="card-header">
                          <div class="custom-control custom-radio">
                            <input type="radio" id="cod_radio" name="payment_method" value="cod"
                              class="custom-control-input payment-method-radio" checked required>
                            <label class="custom-control-label" for="cod_radio">Cash on Delivery</label>
                          </div>
                        </div>
                      </div>

                      <div class="card">
                        <div class="card-header">
                          <div class="custom-control custom-radio">
                            <input type="radio" id="paypal_radio" name="payment_method" value="paypal"
                              class="custom-control-input payment-method-radio" required>
                            <label class="custom-control-label" for="paypal_radio">
                              PayPal
                              <img src="assets/images/payments-summary.png" alt="PayPal" class="float-right"
                                style="height: 20px;">
                            </label>
                          </div>
                        </div>
                      </div>

                    </div>
                    <input type="hidden" name="total_amount" value="<?= $final_total ?>">
                    <input type="hidden" name="shipping_cost" value="<?= $shipping_cost ?>">

                    <button type="submit" id="cod-submit-button" class="btn btn-outline-primary-2 btn-order btn-block">
                      <span class="btn-text">Place Order</span>
                      <span class="btn-hover-text">Proceed to Checkout</span>
                    </button>

                    <div id="paypal-button-container" style="margin-top: 15px; display: none;"></div>

                  </div>
                </aside>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>

    <?php include 'footer.php'; ?>
  </div>

  <script src="assets/js/jquery.min.js"></script>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/jquery.hoverIntent.min.js"></script>
  <script src="assets/js/jquery.waypoints.min.js"></script>
  <script src="assets/js/superfish.min.js"></script>
  <script src="assets/js/owl.carousel.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script
    src="https://www.paypal.com/sdk/js?client-id=AUOVyqA4VVr09Y0aGt6HFHb0VmLV-5sEcDqKOYI3VXN-U_B2zZ0AB2ZnGOJHfr3jTP_b5hNO1OAfxaJs&currency=USD"></script>
  <script>
    $(document).ready(function () {
      const FINAL_TOTAL = <?= number_format($final_total, 2, '.', '') ?>;
      const COD_BUTTON = $('#cod-submit-button');
      const PAYPAL_CONTAINER = $('#paypal-button-container');

      // 1. Payment Method Toggle Logic
      $('.payment-method-radio').on('change', function () {
        if ($(this).val() === 'paypal') {
          COD_BUTTON.hide();
          PAYPAL_CONTAINER.show();
        } else {
          COD_BUTTON.show();
          PAYPAL_CONTAINER.hide();
        }
      }).trigger('change'); // Initialize button visibility on load

      // 2. PayPal Button Setup
      paypal.Buttons({
        createOrder: function (data, actions) {
          // Ensure the address fields are valid before creating the PayPal order
          if (!document.getElementById('checkout-form').checkValidity()) {
            alert('Please fill out all required billing and shipping fields.');
            // Manually trigger the browser's validation UI
            document.getElementById('checkout-form').reportValidity();
            return false;
          }

          return actions.order.create({
            purchase_units: [{
              amount: {
                value: FINAL_TOTAL
              },
              description: 'Mello Order'
            }]
          });
        },
        onApprove: function (data, actions) {
          return actions.order.capture().then(function (details) {
            // Start processing on the server side
            processServerPayment(data.orderID, details);
          });
        },
        onCancel: function (data) {
          alert('PayPal payment was cancelled.');
        },
        onError: function (err) {
          console.error(err);
          alert('An error occurred during the PayPal transaction. Please check the console.');
        }
      }).render('#paypal-button-container');

      // 3. Server-Side Finalization Function
      function processServerPayment(paypalOrderID, paypalDetails) {
        // Collect all form data (including manually typed addresses)
        var formData = $('#checkout-form').serializeArray();

        // Overwrite the hidden address strings with the CURRENT form input values
        var currentShippingAddress = $('input[name="address1"]').val() + ', ' +
          $('input[name="address2"]').val() + ', ' +
          $('input[name="city"]').val() + ', ' +
          $('input[name="state"]').val() + ', ' +
          $('input[name="postcode"]').val() + ', ' +
          $('input[name="country"]').val();

        // Append/Update custom data fields
        formData.push({
          name: 'paypal_order_id',
          value: paypalOrderID
        });
        formData.push({
          name: 'payment_method',
          value: 'paypal'
        });
        // Send the freshly typed addresses, overriding the old hidden inputs
        formData.push({
          name: 'shipping_address',
          value: currentShippingAddress
        });
        formData.push({
          name: 'billing_address',
          value: currentShippingAddress
        });

        // Temporarily disable buttons/show loading state
        PAYPAL_CONTAINER.html('Processing Payment...');

        $.ajax({
          url: 'functions/paypal_capture.php',
          method: 'POST',
          data: formData,
          dataType: 'json',
          success: function (response) {
            if (response.success) {
              window.location.href = 'order_confirm.php?order_id=' + response.order_id;
            } else {
              window.location.href = 'checkout.php?error=1'; // Redirect with a generic error
            }
          },
          error: function (xhr, status, error) {
            // Log the entire XHR object for the most detail (optional)
            console.error("Full XHR Object:", xhr);

            // Log the specific details for easier debugging
            console.error("AJAX Error Details:");
            console.error("HTTP Status Code:", xhr.status);
            console.error("Status Text:", status); // This is the jQuery status text (e.g., 'error', 'timeout')
            console.error("Server Error Message (if available):", error); // This is the HTTP status text (e.g., 'Internal Server Error')
            console.error("Server Response/Output:", xhr.responseText); // <<< THIS IS THE MOST IMPORTANT PART

 
          }
        });
      }
    });
  </script>
</body>

</html>