<?php
$pageTitle = 'Product Form - Admin';
require_once '../includes/header.php';
if (!isAdmin()) { redirect(url('index.php')); }

$conn = getDBConnection();
$product = null;
$isEdit = false;

// Check if editing existing product
if (isset($_GET['id'])) {
    $isEdit = true;
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $product = $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    try {
        // Basic product data
        $name = clean($_POST['name']);
        $brand = clean($_POST['brand']);
        $description = clean($_POST['description']);
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock'];
        $cpu = clean($_POST['cpu']);
        $ram = clean($_POST['ram']);
        $storage = clean($_POST['storage']);
        $gpu = clean($_POST['gpu']);
        $screen_size = clean($_POST['screen_size']);
        $resolution = clean($_POST['resolution']);
        $battery = clean($_POST['battery']);
        $weight = clean($_POST['weight']);
        $os = clean($_POST['os']);
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        // Validatiting fields
        $formErrors = [];
        $pr = constraints('product');
        v_string_length($name, 'Product name', $pr['name']['min'], $pr['name']['max'], $formErrors);
        v_string_length($brand, 'Brand', $pr['brand']['min'], $pr['brand']['max'], $formErrors);
        // Optional brand whitelist 
        $allowedBrands = ['Dell','Apple','Lenovo','HP','ASUS','Acer'];
        if (!in_array($brand, $allowedBrands, true)) {
            $formErrors[] = 'Brand must be one of: ' . implode(', ', $allowedBrands);
        }
        v_decimal_range($price, 'Price', 0.01, 100000, $formErrors);
        if ($stock < 0) { $formErrors[] = 'Stock cannot be negative'; }
        v_string_length($cpu, 'CPU', $pr['cpu']['min'], $pr['cpu']['max'], $formErrors);
        v_string_length($ram, 'RAM', $pr['ram']['min'], $pr['ram']['max'], $formErrors);
        v_string_length($storage, 'Storage', $pr['storage']['min'], $pr['storage']['max'], $formErrors);
        v_string_length($gpu, 'GPU', $pr['gpu']['min'], $pr['gpu']['max'], $formErrors);
        v_string_length($screen_size, 'Screen size', $pr['screen_size']['min'], $pr['screen_size']['max'], $formErrors);
        v_string_length($resolution, 'Resolution', $pr['resolution']['min'], $pr['resolution']['max'], $formErrors);
        v_string_length($battery, 'Battery', $pr['battery']['min'], $pr['battery']['max'], $formErrors);
        v_string_length($weight, 'Weight', $pr['weight']['min'], $pr['weight']['max'], $formErrors);
        v_string_length($os, 'OS', $pr['os']['min'], $pr['os']['max'], $formErrors);

        // Image handling
        $uploadDir = '../uploads/products/';
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 5242880; // 5MB
        $allowedExtensions = ['jpg','jpeg','png','gif','webp'];
        $maxDimensions = [4000, 4000];
        
        // Upload Directory
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Image Handler
        $existingImages = [];
        if ($isEdit && !empty($product['pictures'])) {
            $existingImages = json_decode($product['pictures'], true) ?: [];
            
            if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $index) {
                    if (isset($existingImages[$index])) {
                        $filePath = '..' . $existingImages[$index];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        unset($existingImages[$index]);
                    }
                }
                $existingImages = array_values($existingImages);
            }
        }
        
        // Handle new images
        $newImages = [];
        $uploadErrors = [];
        
        if (isset($_FILES['pictures']) && !empty($_FILES['pictures']['name'][0])) {
            $fileCount = count($_FILES['pictures']['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                // Skip if no file
                if ($_FILES['pictures']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                
                // Error handler
                if ($_FILES['pictures']['error'][$i] !== UPLOAD_ERR_OK) {
                    $uploadErrors[] = "Error uploading: " . $_FILES['pictures']['name'][$i];
                    continue;
                }
                
                // Type handler
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES['pictures']['tmp_name'][$i]);
                finfo_close($finfo);
                
                if (!in_array($mimeType, $allowedTypes)) {
                    $uploadErrors[] = "Invalid file type: " . $_FILES['pictures']['name'][$i];
                    continue;
                }
                
                // Size handler
                if ($_FILES['pictures']['size'][$i] > $maxFileSize) {
                    $uploadErrors[] = "File too large: " . $_FILES['pictures']['name'][$i];
                    continue;
                }

                // Extensions handler
                $origName = $_FILES['pictures']['name'][$i];
                $extension = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedExtensions)) {
                    $uploadErrors[] = "Invalid file extension: " . $origName;
                    continue;
                }

                // Dimensions handler
                $dim = @getimagesize($_FILES['pictures']['tmp_name'][$i]);
                if (!$dim) {
                    $uploadErrors[] = "Invalid image file: " . $origName;
                    continue;
                }
                if ($dim[0] > $maxDimensions[0] || $dim[1] > $maxDimensions[1]) {
                    $uploadErrors[] = "Image too large (max " . $maxDimensions[0] . "x" . $maxDimensions[1] . "): " . $origName;
                    continue;
                }
                
                // Name Handler
                $filename = uniqid('product_', true) . '.' . $extension;
                $targetPath = $uploadDir . $filename;
                
                // Move File
                if (move_uploaded_file($_FILES['pictures']['tmp_name'][$i], $targetPath)) {
                    // Strip metadata by re-encoding where supported
                    try {
                        $imgData = @file_get_contents($targetPath);
                        if ($imgData !== false) {
                            $image = @imagecreatefromstring($imgData);
                            if ($image !== false) {
                                if ($mimeType === 'image/jpeg') {
                                    @imagejpeg($image, $targetPath, 85);
                                } elseif ($mimeType === 'image/png') {
                                    @imagepng($image, $targetPath, 6);
                                } elseif ($mimeType === 'image/webp' && function_exists('imagewebp')) {
                                    @imagewebp($image, $targetPath, 85);
                                }
                                imagedestroy($image);
                            }
                        }
                    } catch (Exception $e) { /* ignore */ }
                    $newImages[] = '/uploads/products/' . $filename;
                } else {
                    $uploadErrors[] = "Failed to save: " . $_FILES['pictures']['name'][$i];
                }
            }
        }
        
        // Merge image
        $allImages = array_merge($existingImages, $newImages);
        $picturesJson = json_encode($allImages);
        
        // Show errors
        if (!empty($uploadErrors)) {
            setFlash('warning', 'Some images failed: ' . implode(', ', $uploadErrors));
        }
        
        // Update OR Insert product
        if ($isEdit) {
            $stmt = $conn->prepare("UPDATE products SET name = ?, brand = ?, description = ?, price = ?, stock = ?, cpu = ?, ram = ?, storage = ?, gpu = ?, screen_size = ?, resolution = ?, battery = ?, weight = ?, os = ?, is_featured = ?,pictures = ? WHERE id = ?");
            $stmt->execute([
                $name, $brand, $description, $price, $stock, 
                $cpu, $ram, $storage, $gpu, $screen_size, 
                $resolution, $battery, $weight, $os, $is_featured,
                $picturesJson, $_GET['id']
            ]);
            setFlash('success', 'Product updated successfully!');
        } else {
            $stmt = $conn->prepare("INSERT INTO products (name, brand, description, price, stock, cpu, ram, storage, gpu, screen_size, resolution, battery, weight, os, is_featured, pictures, main_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'placeholder.jpg')");
            $stmt->execute([
                $name, $brand, $description, $price, $stock, 
                $cpu, $ram, $storage, $gpu, $screen_size, 
                $resolution, $battery, $weight, $os, $is_featured,
                $picturesJson
            ]);
            setFlash('success', 'Product created successfully!');
        }
        
        redirect(url('admin/products.php'));
        
    } catch (Exception $e) {
        setFlash('error', 'Something went wrong while saving the product. Please try again.');
    }
}
?>

<style>
    .navbar {
        display: none;
    }
    .img-thumbnail {
        max-width: 100%;
        height: 150px;
        object-fit: cover;
        border-radius: 8px;
    }
    .position-relative {
        position: relative;
    }
    .image-preview-container {
        border: 2px dashed #ddd;
        border-radius: 8px;
        padding: 15px;
        background: #f9f9f9;
    }
</style>

<div class="container py-5">
    <h3><?php echo $isEdit ? 'Edit' : 'Add New'; ?> Product</h3>
    
    <form method="POST" enctype="multipart/form-data" class="mt-4">
        <?php echo csrf_field(); ?>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="prod_name">Product name *</label>
                <input type="text" id="prod_name" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="prod_brand">Brand *</label>
                <input type="text" id="prod_brand" name="brand" class="form-control" value="<?php echo htmlspecialchars($product['brand'] ?? ''); ?>" required>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label" for="prod_price">Price ($) *</label>
                <input type="number" id="prod_price" step="0.01" name="price" class="form-control" value="<?php echo $product['price'] ?? ''; ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="prod_stock">Stock *</label>
                <input type="number" id="prod_stock" name="stock" class="form-control" value="<?php echo $product['stock'] ?? ''; ?>" required>
            </div>
        </div>
        
        <h4 class="mt-4">Specifications</h4>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">CPU</label>
                <input type="text" name="cpu" class="form-control" value="<?php echo htmlspecialchars($product['cpu'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">RAM</label>
                <input type="text" name="ram" class="form-control" value="<?php echo htmlspecialchars($product['ram'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Storage</label>
                <input type="text" name="storage" class="form-control" value="<?php echo htmlspecialchars($product['storage'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">GPU</label>
                <input type="text" name="gpu" class="form-control" value="<?php echo htmlspecialchars($product['gpu'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Screen Size</label>
                <input type="text" name="screen_size" class="form-control" value="<?php echo htmlspecialchars($product['screen_size'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Resolution</label>
                <input type="text" name="resolution" class="form-control" value="<?php echo htmlspecialchars($product['resolution'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Battery</label>
                <input type="text" name="battery" class="form-control" value="<?php echo htmlspecialchars($product['battery'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Weight</label>
                <input type="text" name="weight" class="form-control" value="<?php echo htmlspecialchars($product['weight'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Operating System</label>
                <input type="text" name="os" class="form-control" value="<?php echo htmlspecialchars($product['os'] ?? ''); ?>">
            </div>
            
            <div class="col-md-12 mb-3">
                <label class="form-label">Product Images</label>
                <input type="file" name="pictures[]" class="form-control p-2" multiple accept="image/*" id="imageUpload">
                <small class="text-muted">You can select multiple images (JPEG, PNG, GIF, WEBP). Max 5MB per image.</small>
                
                <?php if (isset($product['pictures']) && !empty($product['pictures'])): ?>
                    <div class="mt-3 image-preview-container">
                        <label class="form-label fw-bold">Current Images:</label>
                        <div class="row">
                            <?php 
                            $images = json_decode($product['pictures'], true);
                            if (is_array($images) && !empty($images)) {
                                foreach ($images as $index => $image): 
                            ?>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <div class="position-relative">
                                        <img src="<?php echo htmlspecialchars($image); ?>" class="img-thumbnail" alt="Product Image">
                                        <div class="form-check mt-2">
                                            <input type="checkbox" class="form-check-input" name="delete_images[]" value="<?php echo $index; ?>" id="delete_<?php echo $index; ?>">
                                            <label class="form-check-label" for="delete_<?php echo $index; ?>">
                                                <small class="text-danger">Delete this image</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                endforeach;
                            } else {
                                echo '<p class="text-muted">No images uploaded yet.</p>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="is_featured" id="featured" <?php echo isset($product['is_featured']) && $product['is_featured'] ? 'checked' : ''; ?>>
            <label class="form-check-label" for="featured">Featured Product</label>
        </div>
        
        <button type="submit" class="btn btn-primary">Save Product</button>
        <a href="<?php echo url('admin/products.php'); ?>" class="btn btn-outline">Cancel</a>
    </form>
</div>

<script>

// Optional preview
document.getElementById('imageUpload').addEventListener('change', function(e) {
    const files = e.target.files;
    if (files.length > 0) {
        console.log(`${files.length} image(s) selected for upload`);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
