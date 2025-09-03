document.addEventListener('DOMContentLoaded', function () {
    const testModeCheckbox = document.querySelector('#woocommerce_hbl_himalayan_bank_payment_gateway_enabled_test_mode');

    function toggleEncryptionKeyFields() {
        if (testModeCheckbox === null) {
            return;
        }
        
        const isTestMode = testModeCheckbox.checked;
        
        // Get the encryption keys title row
        const encryptionKeysTitle = document.querySelector('#woocommerce_hbl_himalayan_bank_payment_gateway_encryption_keys');
        
        // Get all encryption key field rows
        const encryptionKeyFields = [
            'merchant_id',
            'encryption_key', 
            'access_token',
            'merchant_sign_private_key',
            'merchant_decrypt_private_key',
            'paco_sign_public_key',
            'paco_encrypt_public_key'
        ];
        
        // Hide/show encryption keys title
        if (encryptionKeysTitle) {
            encryptionKeysTitle.style.display = isTestMode ? 'none' : 'table-row';
        }
        
        // Hide/show all encryption key fields
        encryptionKeyFields.forEach(function(fieldId) {
            const fieldRow = document.querySelector('#woocommerce_hbl_himalayan_bank_payment_gateway_' + fieldId);
            if (fieldRow) {
                // Find the closest table row (tr) element
                const tableRow = fieldRow.closest('tr');
                if (tableRow) {
                    tableRow.style.display = isTestMode ? 'none' : 'table-row';
                }
            }
        });
        
        // Also handle the form table that follows the encryption keys title
        const formTable = document.querySelector('#woocommerce_hbl_himalayan_bank_payment_gateway_encryption_keys + .form-table');
        if (formTable) {
            formTable.style.display = isTestMode ? 'none' : 'table';
        }
    }

    // Add event listener and run initial toggle
    if (testModeCheckbox) {
        testModeCheckbox.addEventListener('change', toggleEncryptionKeyFields);
        toggleEncryptionKeyFields(); // Run on page load
    }
});