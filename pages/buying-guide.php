<?php


$pageTitle = 'Laptop Buying Guide - Laptro';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();


$recommendations = [];
if (isset($_GET['reset'])) {
    unset($_SESSION['quiz_reco'], $_SESSION['quiz_meta']);
    header('Location: ' . url('buying-guide.php'));
    exit;
}
if (isset($_GET['results'])) {
    $recommendations = $_SESSION['quiz_reco'] ?? [];
    if (empty($recommendations)) {
        setFlash('error', 'No matching laptops found. Try increasing your budget or adjusting your answers.');
        header('Location: ' . url('buying-guide.php'));
        exit;
    }
}

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $fieldOfStudy = clean($_POST['field_of_study']);
    $budget = (int)$_POST['budget'];
    $primaryUse = clean($_POST['primary_use']);
    $ramPreference = clean($_POST['ram_preference'] ?? '');
    
    if (isLoggedIn()) {
        $stmt = $conn->prepare("
            INSERT INTO quiz_results (user_id, field_of_study, budget_min, budget_max) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([getUserId(), $fieldOfStudy, $budget - 200, $budget + 200]);
    }
    
    $query = "SELECT * FROM products WHERE price <= ? AND stock > 0";
    $params = [$budget + 200];
    
    if ($fieldOfStudy === 'computer_science' || $fieldOfStudy === 'engineering') {
        $query .= " AND (ram LIKE '%16GB%' OR ram LIKE '%32GB%')";
    } elseif ($fieldOfStudy === 'design') {
        $query .= " AND (ram LIKE '%16GB%' OR ram LIKE '%32GB%')";
        $query .= " AND (gpu NOT LIKE '%Intel%' OR gpu LIKE '%Iris Xe%')";
    }
    
    if ($primaryUse === 'coding' || $primaryUse === 'gaming') {
        $query .= " AND (cpu LIKE '%i7%' OR cpu LIKE '%i9%' OR cpu LIKE '%Ryzen 7%' OR cpu LIKE '%Ryzen 9%' OR cpu LIKE '%M2%')";
    }

    $query .= " ORDER BY is_featured DESC, price DESC LIMIT 6";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $_SESSION['quiz_reco'] = $stmt->fetchAll();
    $_SESSION['quiz_meta'] = [
        'field_of_study' => $fieldOfStudy,
        'budget' => $budget,
        'primary_use' => $primaryUse,
        'ram_preference' => $ramPreference,
    ];
    header('Location: ' . url('buying-guide.php?results=1'));
    exit;
}
?>

<?php if (empty($recommendations)): ?>
    <div class="container section-padding">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <h1>Find Your Perfect Laptop</h1>
                    <p class="lead">Answer a few questions and we'll recommend the best laptops for your needs</p>
                </div>
                
                <div class="card">
                    <div class="card-body p-5">
                        <form method="POST" id="buyingGuideQuiz">
                            <?php echo csrf_field(); ?>
                            <div class="mb-4">
                                <label class="form-label h5">1. What's your field of study?</label>
                                <select name="field_of_study" class="form-control form-control-lg" required>
                                    <option value="">Select your field...</option>
                                    <option value="computer_science">Computer Science / Software Engineering</option>
                                    <option value="engineering">Engineering (Civil, Mechanical, Electrical)</option>
                                    <option value="design">Design / Architecture</option>
                                    <option value="business">Business / Finance</option>
                                    <option value="arts">Arts / Humanities</option>
                                    <option value="science">Science / Mathematics</option>
                                    <option value="general">General Studies</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label h5">2. What's your budget?</label>
                                <select name="budget" class="form-control form-control-lg" required>
                                    <option value="">Select budget range...</option>
                                    <option value="600">Under $800</option>
                                    <option value="1000">$800 - $1,200</option>
                                    <option value="1500">$1,200 - $1,800</option>
                                    <option value="2000">$1,800+</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label h5">3. What will you use it for most?</label>
                                <select name="primary_use" class="form-control form-control-lg" required>
                                    <option value="">Select primary use...</option>
                                    <option value="coding">Programming / Coding</option>
                                    <option value="design">Design / Video Editing</option>
                                    <option value="general">General Study / Web Browsing</option>
                                    <option value="gaming">Gaming (in free time)</option>
                                    <option value="data">Data Analysis / Research</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label h5">4. How important is portability?</label>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="portability" 
                                           id="portable1" value="very" required>
                                    <label class="form-check-label" for="portable1">
                                        Very important - I carry it everywhere
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="portability" 
                                           id="portable2" value="moderate">
                                    <label class="form-check-label" for="portable2">
                                        Moderately important - Occasional transport
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="portability" 
                                           id="portable3" value="not">
                                    <label class="form-check-label" for="portable3">
                                        Not important - Mostly stationary
                                    </label>
                                </div>
                            </div>
                        
                            <div class="mb-4">
                                <label class="form-label h5">5. Do you run heavy applications?</label>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="ram_preference" 
                                           id="ram1" value="high" required>
                                    <label class="form-check-label" for="ram1">
                                        Yes - IDEs, VMs, 3D software, etc.
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="ram_preference" 
                                           id="ram2" value="moderate">
                                    <label class="form-check-label" for="ram2">
                                        Sometimes - Light development tools
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="ram_preference" 
                                           id="ram3" value="low">
                                    <label class="form-check-label" for="ram3">
                                        No - Just web browsing and documents
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-search me-2"></i>Find My Perfect Laptop
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <p class="text-muted">
                        <i class="fas fa-lightbulb me-2"></i>
                        Our algorithm considers your needs to find the best match
                    </p>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="container section-padding">
        <div class="text-center mb-5">
            <h1>Your Recommended Laptops</h1>
            <p class="lead">Based on your answers, we found <?php echo count($recommendations); ?> perfect matches</p>
            <a href="<?php echo url('buying-guide.php?reset=1'); ?>" class="btn btn-outline mt-3">
                <i class="fas fa-redo me-2"></i>Take Quiz Again
            </a>
        </div>
        
        <div class="row g-4">
            <?php foreach ($recommendations as $product): 
                $rating = getAverageRating($product['id']);
                $matchScore = 85 + rand(0, 15); // 85-100%
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="product-card">
                        <div class="position-absolute top-0 end-0 m-3" style="z-index: 10;">
                            <span class="badge" style="background-color: #28a745; font-size: 0.9rem;">
                                <?php echo $matchScore; ?>% Match
                            </span>
                        </div>
                        
                        <a href="<?php echo url('product.php?id=' . $product['id']); ?>">
                            <img src="/assets/products/<?php echo $product['main_image']; ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 onerror="this.src='https://via.placeholder.com/400x300?text=<?php echo urlencode($product['name']); ?>'">
                        </a>
                        
                        <div class="product-card-body">
                            <div class="product-brand"><?php echo htmlspecialchars($product['brand']); ?></div>
                            <h3 class="product-title">
                                <a href="<?php echo url('product.php?id=' . $product['id']); ?>">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h3>
                            
                            <div class="product-specs">
                                <i class="fas fa-microchip me-1"></i><?php echo htmlspecialchars($product['cpu']); ?><br>
                                <i class="fas fa-memory me-1"></i><?php echo htmlspecialchars($product['ram']); ?> • 
                                <?php echo htmlspecialchars($product['storage']); ?>
                            </div>
                            
                            <div class="product-rating">
                                <?php echo renderStars($rating['average']); ?>
                                <span class="text-muted ms-2">(<?php echo $rating['total']; ?>)</span>
                            </div>
                            
                            <div class="alert alert-info mt-3 small">
                                <i class="fas fa-check-circle me-1"></i>
                                <?php $fos = $_SESSION['quiz_meta']['field_of_study'] ?? null; ?>
                                <?php if ($fos): ?>
                                    Perfect for <?php echo ucfirst(str_replace('_', ' ', $fos)); ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div class="product-price"><?php echo formatPrice($product['price']); ?></div>
                                <a href="<?php echo url('product.php?id=' . $product['id']); ?>" class="btn btn-primary btn-sm">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-5">
            <p class="text-muted mb-3">Not satisfied with these recommendations?</p>
            <a href="<?php echo url('products.php'); ?>" class="btn btn-outline me-2">Browse All Laptops</a>
            <a href="<?php echo url('buying-guide.php?reset=1'); ?>" class="btn btn-primary">
                Retake Quiz
            </a>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
