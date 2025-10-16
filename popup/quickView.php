<?php
include_once '../admin/db.php';

if (!isset($conn)) {
	die("Connection failed: " . mysqli_connect_error());
}

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$product = null;

function get_image_paths($attachments_json)
{
	$attachments = json_decode($attachments_json, true);
	if (is_array($attachments) && !empty($attachments)) {
		return array_map(function ($path) {
			return '../' . $path;
		}, $attachments);
	}

	return ['assets/images/placeholder.jpg'];
}
// ------------------------------------


$sql = "SELECT p.*, c.category_name
        FROM tbl_products p
        JOIN tbl_categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.isActive = 1
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {

	$product = $result->fetch_assoc();
	$image_paths = get_image_paths($product['attachments']);
}

if (!$product) {
	die('<div class="container p-5 text-center">Product not found or is inactive.</div>');
}

$product_name = htmlspecialchars($product['product_name']);
$product_price = number_format($product['price'], 2);
$product_description = htmlspecialchars($product['description'] ?? 'No description available.');
$category_name = htmlspecialchars($product['category_name']);
$product_id_safe = $product['id'];

?>

<div class="container quickView-container">
	<div class="quickView-content">
		<div class="row">
			<div class="col-lg-7 col-md-6">
				<div class="row">
					<div class="product-left">
						<?php
						foreach ($image_paths as $index => $path):
							$hash = "img-" . ($index + 1);
							
							?>
							<a href="#<?php echo $hash; ?>"
								class="carousel-dot <?php echo ($index === 0) ? 'active' : ''; ?>">
								<img src="<?php echo "admin". $path; ?>"
									alt="<?php echo $product_name . ' image ' . ($index + 1); ?>">
							</a>
						<?php endforeach; ?>
					</div>

					<div class="product-right">
						<div class="owl-carousel owl-theme owl-nav-inside owl-light mb-0" data-toggle="owl"
							data-owl-options='{
							"dots": false,
							"nav": false, 
							"URLhashListener": true,
							"responsive": {
								"900": {
									"nav": true,
									"dots": true
								}
							}
						}'>
							 <?php 
                            // Loop through image paths for main carousel slides
                            foreach ($image_paths as $index => $path): 
                                $hash = "img-" . ($index + 1);
                            ?>
                            <div class="intro-slide" data-hash="<?php echo $hash; ?>">
                                <img src="<?php echo "admin". $path; ?>" alt="<?php echo $product_name; ?>">
                                
                              <a href="#" id="btn-product-gallery" class="btn-product-gallery">
                                                <i class="icon-arrows"></i>
                                            </a>
                                </div>
                            <?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>

			<div class="col-lg-5 col-md-6">
				<h2 class="product-title"><?php echo $product_name; ?></h2>
				<h3 class="product-price">$<?php echo $product_price; ?></h3>

				<div class="ratings-container">
					<div class="ratings">
						<div class="ratings-val" style="width: 20%;"></div>
					</div>
					<span class="ratings-text">( 2 Reviews )</span>
				</div>

				<p class="product-txt"><?php echo $product_description; ?></p>



				<!-- <div class="details-filter-row details-row-size">
					<label for="size">Size:</label>
					<div class="select-custom">
						<select name="size" id="size" class="form-control">
							<option value="#" selected="selected">Select a size</option>
							<option value="s">Small</option>
							<option value="m">Medium</option>
						</select>
					</div>
				</div> -->

				<div class="details-filter-row details-row-size">
					<label for="qty">Qty:</label>
					<div class="product-details-quantity">
						<input type="number" id="qty" class="form-control" value="1" min="1"
							max="<?php echo $product['stock'] ?? 10; ?>" step="1" data-decimals="0" required>
					</div>
				</div>

				<div class="product-details-action">
					<div class="details-action-wrapper">
						<a href="#" class="btn-product btn-wishlist" title="Wishlist"><span>Add to Wishlist</span></a>
						
					</div>
					<a href="#" class="btn-product btn-cart"><span>add to cart</span></a>
				</div>

				<div class="product-details-footer">
					<div class="product-cat">
						<span>Category:</span>
						<a href="#"><?php echo $category_name; ?></a>
					</div>

					<!-- <div class="social-icons social-icons-sm">
						<span class="social-label">Share:</span>
						<a href="#" class="social-icon" title="Facebook" target="_blank"><i
								class="icon-facebook-f"></i></a>
						<a href="#" class="social-icon" title="Twitter" target="_blank"><i class="icon-twitter"></i></a>
						<a href="#" class="social-icon" title="Instagram" target="_blank"><i
								class="icon-instagram"></i></a>
						<a href="#" class="social-icon" title="Pinterest" target="_blank"><i
								class="icon-pinterest"></i></a>
					</div> -->
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	document.getElementById('btn-product-gallery').addEventListener('click', function(event) {
		event.preventDefault();
		const activeSlide = document.querySelector('.intro-slide img');
		if (activeSlide) {
			const imageUrl = activeSlide.src;
			const magnificPopup = $.magnificPopup.instance;
			if (magnificPopup) {
				magnificPopup.close();
			}
			$.magnificPopup.open({
				items: {
					src: imageUrl
				},
				type: 'image',
				mainClass: 'mfp-zoom-in',
				closeOnContentClick: true
			});
		}
	});
</script>
