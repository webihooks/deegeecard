<?php if ($business_info): ?>
<div class="business_details mt-4">
    <h6>Business</h6>
    <h2><?= htmlspecialchars($business_info['business_name']) ?></h2>
    <p><?= htmlspecialchars($business_info['business_description']) ?></p>
    
    <!-- Store Status Badge -->
    <div class="store-status-badge">
        <div class="status-indicator <?php echo $is_store_open ? 'open' : 'closed'; ?>">
            <?php echo $is_store_open ? '🟢 Open' : '🔴 Closed'; ?>
        </div>
        <div class="timing-info">
            <?php if ($is_store_open && $store_timing_data): ?>
                <small>Closes at <?php echo date('g:i A', strtotime($store_timing_data['close_time'])); ?></small>
            <?php elseif (!$is_store_open && $next_opening_time): ?>
                <small>Opens at <?php echo $next_opening_time; ?></small>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($business_info['website'])): ?>
    <p><i class="bi bi-globe"></i> <a href="https://<?= htmlspecialchars($business_info['website']) ?>" target="_blank" class="text-decoration-none"><?= htmlspecialchars($business_info['website']) ?></a></p>
    <?php endif; ?>
    <?php if (!empty($business_info['business_address'])): ?>
    <p><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($business_info['business_address']) ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>
