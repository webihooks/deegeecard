<?php if (!empty($services)): ?>
<div class="services">
    <h6>Services</h6>
    <div class="row">
        <?php foreach ($services as $service): ?>
        <div class="col-sm-12">
            <div class="card service-card">
                <img src="<?= !empty($service['image_path']) ? htmlspecialchars($service['image_path']) : 'images/no-image.jpg' ?>" 
                     class="card-img-top service-img" 
                     alt="<?= htmlspecialchars($service['service_name']) ?>">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($service['service_name']) ?></h5>
                    <p class="card-text"><?= htmlspecialchars($service['description']) ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-primary fw-bold">₹<?= number_format($service['price']) ?></span>
                        <span class="badge duration-badge">
                            <i class="bi bi-clock"></i> <?= htmlspecialchars($service['duration']) ?>
                        </span>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-success w-100" 
                            onclick="sendServiceEnquiry(
                                '<?= htmlspecialchars($service['service_name']) ?>',
                                '<?= number_format($service['price'], 2) ?>',
                                '<?= htmlspecialchars($service['description']) ?>',
                                '<?= htmlspecialchars($service['duration']) ?>'
                            )">
                            <i class="bi bi-whatsapp"></i> Order on WhatsApp
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>