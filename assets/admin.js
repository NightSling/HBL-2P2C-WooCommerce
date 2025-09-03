document.addEventListener('DOMContentLoaded', function () {
    const nexHBLTestModeCheckbox = document.querySelector('#woocommerce_nexhbp_himalayan_bank_payment_gateway_enabled_test_mode');

    function nexHBLToggleFields() {
        if (nexHBLTestModeCheckbox === null) {
            return;
        }
        const isChecked = nexHBLTestModeCheckbox.checked;
        const encryptionKeys = document.querySelector('#woocommerce_nexhbp_himalayan_bank_payment_gateway_encryption_keys');
        const formTable = document.querySelector('#woocommerce_nexhbp_himalayan_bank_payment_gateway_encryption_keys + .form-table');
        if (isChecked) {
            encryptionKeys.style.display = 'none';
            formTable.style.display = 'none';
        } else {
            encryptionKeys.style.display = 'block';
            formTable.style.display = 'table';
        }
    }

    nexHBLTestModeCheckbox?.addEventListener('change', nexHBLToggleFields);
    nexHBLToggleFields();
});