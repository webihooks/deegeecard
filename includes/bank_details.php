<?php if (!empty($bank_details)): ?>
<div class="bank_details">
    <h6>Bank Accounts</h6>
    <div class="row">
        <?php foreach ($bank_details as $account): ?>
        <div class="col-sm-12 mb-2">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($account['account_name']) ?></h5>
                    <div class="bank-details">
                        <p><strong>Bank:</strong> <?= htmlspecialchars($account['bank_name']) ?></p>
                        <p><strong>Account Number:</strong> <?= htmlspecialchars($account['account_number']) ?></p>
                        <p><strong>Account Type:</strong> <?= htmlspecialchars($account['account_type']) ?></p>
                        <p><strong>IFSC Code:</strong> <?= htmlspecialchars($account['ifsc_code']) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>