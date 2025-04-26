<?php if ($business_info): ?>
<div class="business_details">
    <h6>Business</h6>
    <h2><?= htmlspecialchars($business_info['business_name']) ?></h2>
    <p><?= htmlspecialchars($business_info['business_description']) ?></p>
    <?php if (!empty($business_info['business_address'])): ?>
    <p><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($business_info['business_address']) ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>