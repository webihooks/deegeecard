<?php if (!empty($qr_codes)): ?>
<div class="qr_code_details">
    <h6>QR Code Payment Methods</h6>
    <div class="row">
        <?php foreach ($qr_codes as $qr): ?>
        <div class="col-sm-12 mb-2">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-sm-12 text-center">
                            <img src="uploads/qrcodes/<?= htmlspecialchars($qr['upload_qr_code']) ?>" 
                                 class="img-fluid qr-code-img" 
                                 alt="Payment QR Code"
                                 style="max-width: 150px;">
                        </div>
                        <div class="col-sm-12">
                            <h5><?= htmlspecialchars($qr['payment_type']) ?></h5>
                            <p class="mb-1">
                                <i class="bi bi-phone"></i> <?= htmlspecialchars($qr['mobile_number']) ?>
                            </p>
                            <?php if ($qr['is_default']): ?>
                            <span class="badge bg-success">Default Payment Method</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-12 text-md-end mt-3 mt-md-0">
                            <button class="btn btn-outline-primary btn-sm" 
                                onclick="showQrModal('<?= htmlspecialchars($qr['payment_type']) ?>', 'uploads/qrcodes/<?= htmlspecialchars($qr['upload_qr_code']) ?>')">
                                <i class="bi bi-zoom-in"></i> Enlarge
                            </button>
                            <a href="upi://pay?pa=<?= urlencode($qr['mobile_number']) ?>" 
                               class="btn btn-primary btn-sm">
                                <i class="bi bi-arrow-up-right-circle"></i> Pay Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrModalTitle">QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalQrImage" src="" class="img-fluid" alt="QR Code">
                <div class="mt-3">
                    <a href="#" id="payNowLink" class="btn btn-primary">
                        <i class="bi bi-arrow-up-right-circle"></i> Pay Now
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>