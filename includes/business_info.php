<?php if ($business_info): ?>
<div class="business_details mt-4">
    <h6>Business</h6>
    <h2><?= htmlspecialchars($business_info['business_name']) ?></h2>
    <p><?= htmlspecialchars($business_info['business_description']) ?></p>
    <?php if (!empty($business_info['website'])): ?>
    <p><i class="bi bi-globe"></i> <a href="https://<?= htmlspecialchars($business_info['website']) ?>" target="_blank" class="text-decoration-none"><?= htmlspecialchars($business_info['website']) ?></a></p>
    <?php endif; ?>
    <?php if (!empty($business_info['business_address'])): ?>
    <p><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($business_info['business_address']) ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>