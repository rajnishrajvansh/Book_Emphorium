<?php
session_start();
require_once "./functions/database_functions.php";
require_once "./functions/cart_functions.php";

// Connect to the database
$conn = db_connect();

// Handle adding new items to the cart
if (isset($_POST['bookisbn'])) {
    $book_isbn = $_POST['bookisbn'];
}

if (isset($book_isbn)) {
    // Initialize cart if not already set
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = array();
        $_SESSION['total_items'] = 0;
        $_SESSION['total_price'] = '0.00';
    }

    // Add item to cart or increase quantity
    if (!isset($_SESSION['cart'][$book_isbn])) {
        $_SESSION['cart'][$book_isbn] = 1;
    } elseif (isset($_POST['cart'])) {
        $_SESSION['cart'][$book_isbn]++;
        unset($_POST);
    }
}

// Handle saving changes to the cart
if (isset($_POST['save_change'])) {
    foreach ($_SESSION['cart'] as $isbn => $qty) {
        if ($_POST[$isbn] == '0') {
            unset($_SESSION['cart'][$isbn]);
        } else {
            $_SESSION['cart'][$isbn] = $_POST[$isbn];
        }
    }

    // Recalculate total items and total price
    $_SESSION['total_items'] = total_items($_SESSION['cart']);
    $_SESSION['total_price'] = total_price($_SESSION['cart']);

    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode([
        'total_items' => $_SESSION['total_items'],
        'total_price' => number_format($_SESSION['total_price'], 2)
    ]);
    exit;
}

// Set the page title
$title = "Your shopping cart";
require "./template/header.php";

// Check if cart is not empty
if (isset($_SESSION['cart']) && (array_count_values($_SESSION['cart']))) {
    $_SESSION['total_price'] = total_price($_SESSION['cart']);
    $_SESSION['total_items'] = total_items($_SESSION['cart']);
?>
    <form action="cart.php" method="post">
        <table class="table">
            <tr>
                <th>Item</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Total</th>
            </tr>
            <?php
            foreach ($_SESSION['cart'] as $isbn => $qty) {
                $conn = db_connect();
                $book = mysqli_fetch_assoc(getBookByIsbn($conn, $isbn));
            ?>
                <tr>
                    <td><?php echo $book['book_title'] . " by " . $book['book_author']; ?></td>
                    <td><?php echo "$" . $book['book_price']; ?></td>
                    <td><input type="text" value="<?php echo $qty; ?>" size="2" name="<?php echo $isbn; ?>"></td>
                    <td><?php echo "$" . $qty * $book['book_price']; ?></td>
                </tr>
            <?php } ?>
            <tr>
                <th>&nbsp;</th>
                <th>&nbsp;</th>
                <th id="total-items"><?php echo $_SESSION['total_items']; ?></th>
                <th id="total-price"><?php echo "$" . number_format($_SESSION['total_price'], 2); ?></th>
            </tr>
        </table>
        <button type="submit" class="btn btn-primary" name="save_change"><span class="glyphicon glyphicon-ok"></span>&nbsp;Save Changes</button>
    </form>
    <br /><br />
    <a href="checkout.php" class="btn btn-primary">Go To Checkout</a>
    <a href="books.php" class="btn btn-primary">Continue Shopping</a>
<?php
} else {
    echo "<p class=\"text-warning\">Your cart is empty! Please make sure you add some books in it!</p>";
}

// Fetch purchase history if user is logged in
if (isset($_SESSION['user'])) {
    $customer = getCustomerIdbyEmail($_SESSION['email']);
    $customerid = $customer['id'];
    $query = "SELECT * FROM cart 
              JOIN cartitems ON cart.id = cartitems.cartid 
              JOIN books ON cartitems.productid = books.book_isbn 
              JOIN customers ON cart.customerid = customers.id 
              WHERE customers.id = '$customerid'";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) != 0) {
        echo '<br><br><br><h4>Your Purchase History</h4><table class="table">
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Date</th>
                </tr>';
        while ($query_row = mysqli_fetch_assoc($result)) {
            echo '<tr>
                    <td>
                        <a href="book.php?bookisbn=' . $query_row['book_isbn'] . '">
                            <img style="height:100px;width:80px" class="img-responsive img-thumbnail" src="./bootstrap/img/' . $query_row['book_image'] . '">
                        </a>
                    </td>
                    <td>' . $query_row['quantity'] . '</td>
                    <td>' . $query_row['date'] . '</td>
                  </tr>';
        }
        echo '</table>';
    }
}

// Close the database connection
if (isset($conn)) {
    mysqli_close($conn);
}
?>

<!-- Include jQuery -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
    $(document).ready(function () {
        // Attach a submit event to the form
        $('form').on('submit', function (e) {
            e.preventDefault(); // Prevent default form submission

            // Serialize the form data and send it via AJAX
            $.ajax({
                type: 'POST',
                url: 'cart.php',
                data: $(this).serialize(),
                success: function (response) {
                    // Update the cart count and total price dynamically
                    $('#cart-count').text(response.total_items);
                    $('#total-items').text(response.total_items);
                    $('#total-price').text('$' + response.total_price);

                    alert('Cart updated successfully!');
                },
                error: function () {
                    alert('An error occurred while updating the cart.');
                }
            });
        });
    });
</script>
