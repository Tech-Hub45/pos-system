document.addEventListener('DOMContentLoaded', () => {
    // POS Register State Management
    let cart = [];
    const taxPercent = Number(window.systemTaxRate || 8) || 8;
    const taxRate = taxPercent / 100;

    // DOM Elements
    const productGridContainer = document.getElementById('product-grid-container');
    const searchProductInput = document.getElementById('search-product');
    const categoryFilterSelect = document.getElementById('category-filter');
    const barcodeScanInput = document.getElementById('barcode-scan-input');
    const toggleBarcodeScannerBtn = document.getElementById('toggle-barcode-scanner-btn');
    const taxRateLabel = document.getElementById('tax-rate-label');
    const taxRateInput = document.getElementById('tax-rate-input');
    const saveTaxRateBtn = document.getElementById('save-tax-rate-btn');
    const cartItemsContainer = document.getElementById('cart-items-container');
    const cartSubtotalEl = document.getElementById('cart-subtotal');
    const cartTaxEl = document.getElementById('cart-tax');
    const cartGrandTotalEl = document.getElementById('cart-grand-total');
    const paymentMethodSelect = document.getElementById('payment-method');
    const amountPaidInput = document.getElementById('amount-paid');
    const changeDueEl = document.getElementById('change-due');
    const checkoutBtn = document.getElementById('checkout-btn');
    const clearCartBtn = document.getElementById('clear-cart-btn');

    if (taxRateLabel) {
        taxRateLabel.textContent = `${taxPercent.toFixed(2)}%`;
    }

    if (taxRateInput) {
        taxRateInput.value = taxPercent.toFixed(2);
    }

    // Receipt Modal Elements
    const receiptModal = document.getElementById('receipt-modal');
    const receiptPrintArea = document.getElementById('receipt-print-area');
    const closeReceiptModal = document.getElementById('close-receipt-modal');
    const printReceiptBtn = document.getElementById('print-receipt-btn');
    const newSaleBtn = document.getElementById('new-sale-btn');

    if (toggleBarcodeScannerBtn && barcodeScanInput) {
        toggleBarcodeScannerBtn.addEventListener('click', () => {
            barcodeScanInput.focus();
            barcodeScanInput.select();
        });
    }

    if (saveTaxRateBtn && taxRateInput) {
        saveTaxRateBtn.addEventListener('click', async () => {
            const value = parseFloat(taxRateInput.value);
            if (Number.isNaN(value) || value < 0 || value > 100) {
                alert('Tax percentage must be between 0 and 100.');
                return;
            }

            try {
                const response = await fetch('api/update_tax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tax_rate: value })
                });

                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.message || 'Unable to update tax rate.');
                }

                window.systemTaxRate = value;
                if (taxRateLabel) {
                    taxRateLabel.textContent = `${value.toFixed(2)}%`;
                }
                updateCartUI();
                alert('Tax rate saved successfully.');
            } catch (error) {
                alert(error.message);
            }
        });
    }

    // 1. Catalog filtering and search
    if (searchProductInput && categoryFilterSelect) {
        searchProductInput.addEventListener('input', filterCatalog);
        categoryFilterSelect.addEventListener('change', filterCatalog);
    }

    function filterCatalog() {
        const query = searchProductInput.value.toLowerCase().trim();
        const selectedCat = categoryFilterSelect.value;
        const cards = productGridContainer.getElementsByClassName('product-card');

        Array.from(cards).forEach(card => {
            const name = card.getAttribute('data-name').toLowerCase();
            const barcode = card.getAttribute('data-barcode').toLowerCase();
            const cat = card.getAttribute('data-category');

            const matchesQuery = name.includes(query) || barcode.includes(query);
            const matchesCategory = !selectedCat || cat === selectedCat;

            if (matchesQuery && matchesCategory) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // 2. Barcode Scanning Simulator
    if (barcodeScanInput) {
        barcodeScanInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const barcode = barcodeScanInput.value.trim();
                if (barcode) {
                    const foundProduct = findProductByBarcode(barcode);
                    if (foundProduct) {
                        addToCart(foundProduct);
                    } else {
                        alert(`Product with Barcode: "${barcode}" not found.`);
                    }
                    barcodeScanInput.value = '';
                }
            }
        });
    }

    function findProductByBarcode(barcode) {
        if (typeof productCatalog !== 'undefined') {
            return productCatalog.find(p => p.barcode === barcode);
        }
        return null;
    }

    // 3. Product Catalog Grid Click Handler
    if (productGridContainer) {
        productGridContainer.addEventListener('click', (e) => {
            const card = e.target.closest('.product-card');
            if (card) {
                const product = {
                    id: parseInt(card.getAttribute('data-id')),
                    barcode: card.getAttribute('data-barcode'),
                    name: card.getAttribute('data-name'),
                    sell_price: parseFloat(card.getAttribute('data-price')),
                    stock_qty: parseInt(card.getAttribute('data-stock'))
                };
                addToCart(product);
            }
        });
    }

    // 4. Cart Core Logic
    function addToCart(product) {
        if (product.stock_qty <= 0) {
            alert(`"${product.name}" is out of stock!`);
            return;
        }

        const existingItem = cart.find(item => item.id === product.id);

        if (existingItem) {
            if (existingItem.quantity >= product.stock_qty) {
                alert(`Cannot add more. Stock limit reached (${product.stock_qty} available).`);
                return;
            }
            existingItem.quantity++;
        } else {
            cart.push({
                id: product.id,
                name: product.name,
                barcode: product.barcode,
                price: product.sell_price,
                quantity: 1,
                max_stock: product.stock_qty
            });
        }
        updateCartUI();
    }

    function updateCartUI() {
        const currentTaxRate = Number(window.systemTaxRate || 8) / 100;

        if (cart.length === 0) {
            cartItemsContainer.innerHTML = `
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); gap: 10px;">
                    <i class="fa-solid fa-basket-shopping" style="font-size: 40px;"></i>
                    <p>Order Cart is empty</p>
                </div>
            `;
            cartSubtotalEl.textContent = '$0.00';
            cartTaxEl.textContent = '$0.00';
            cartGrandTotalEl.textContent = '$0.00';
            changeDueEl.textContent = '$0.00';
            if (amountPaidInput) amountPaidInput.value = '';
            return;
        }

        let subtotal = 0;
        cartItemsContainer.innerHTML = '';

        cart.forEach(item => {
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;

            const cartRow = document.createElement('div');
            cartRow.className = 'cart-item';
            cartRow.innerHTML = `
                <div class="cart-item-name">${item.name}</div>
                <div class="cart-item-qty">
                    <button class="cart-qty-btn decrease-qty" data-id="${item.id}">-</button>
                    <span style="font-weight:600; min-width: 20px; text-align:center;">${item.quantity}</span>
                    <button class="cart-qty-btn increase-qty" data-id="${item.id}">+</button>
                </div>
                <div class="cart-item-price">$${itemTotal.toFixed(2)}</div>
            `;
            cartItemsContainer.appendChild(cartRow);
        });

        const tax = subtotal * currentTaxRate;
        const grandTotal = subtotal + tax;

        cartSubtotalEl.textContent = `$${subtotal.toFixed(2)}`;
        cartTaxEl.textContent = `$${tax.toFixed(2)}`;
        cartGrandTotalEl.textContent = `$${grandTotal.toFixed(2)}`;

        // Trigger change calculation
        calculateChange();
    }

    // Handle cart quantity adjust clicks
    if (cartItemsContainer) {
        cartItemsContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('increase-qty')) {
                const id = parseInt(e.target.getAttribute('data-id'));
                const item = cart.find(i => i.id === id);
                if (item) {
                    if (item.quantity >= item.max_stock) {
                        alert(`Cannot exceed stock capacity (${item.max_stock} units).`);
                        return;
                    }
                    item.quantity++;
                    updateCartUI();
                }
            } else if (e.target.classList.contains('decrease-qty')) {
                const id = parseInt(e.target.getAttribute('data-id'));
                const itemIndex = cart.findIndex(i => i.id === id);
                if (itemIndex > -1) {
                    if (cart[itemIndex].quantity > 1) {
                        cart[itemIndex].quantity--;
                    } else {
                        cart.splice(itemIndex, 1);
                    }
                    updateCartUI();
                }
            }
        });
    }

    // 5. Change due calculations
    if (amountPaidInput) {
        amountPaidInput.addEventListener('input', calculateChange);
    }
    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', () => {
            if (paymentMethodSelect.value !== 'Cash') {
                amountPaidInput.value = getGrandTotalValue().toFixed(2);
                amountPaidInput.disabled = true;
            } else {
                amountPaidInput.value = '';
                amountPaidInput.disabled = false;
            }
            calculateChange();
        });
    }

    function getGrandTotalValue() {
        return parseFloat(cartGrandTotalEl.textContent.replace('$', '')) || 0;
    }

    function calculateChange() {
        const grandTotal = getGrandTotalValue();
        const paid = parseFloat(amountPaidInput.value) || 0;
        
        if (paid >= grandTotal) {
            const change = paid - grandTotal;
            changeDueEl.textContent = `$${change.toFixed(2)}`;
        } else {
            changeDueEl.textContent = '$0.00';
        }
    }

    // 6. Clear Cart
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', () => {
            if (cart.length > 0 && confirm('Are you sure you want to cancel and clear the transaction?')) {
                cart = [];
                updateCartUI();
            }
        });
    }

    // 7. Complete Checkout Transaction
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', () => {
            if (cart.length === 0) {
                alert('Your order cart is empty.');
                return;
            }

            const total = getGrandTotalValue();
            const paid = parseFloat(amountPaidInput.value) || 0;
            const paymentMethod = paymentMethodSelect.value;

            if (paymentMethod === 'Cash' && paid < total) {
                alert(`Insufficient payment. Customer paid $${paid.toFixed(2)} but total is $${total.toFixed(2)}.`);
                return;
            }

            const change = Math.max(0, paid - total);

            // API POST Payload
            const transactionData = {
                cart: cart,
                total_amount: total,
                amount_paid: paymentMethod === 'Cash' ? paid : total,
                change_amount: paymentMethod === 'Cash' ? change : 0,
                payment_method: paymentMethod
            };

            // Call checkout PHP endpoint
            checkoutBtn.disabled = true;
            checkoutBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Processing...';

            fetch('api/process_sale.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(transactionData)
            })
            .then(res => res.json())
            .then(data => {
                checkoutBtn.disabled = false;
                checkoutBtn.innerHTML = '<i class="fa-solid fa-cash-register"></i> Complete Transaction';

                if (data.success) {
                    displayReceipt(data.invoice);
                    // Update local catalog stocks to avoid page reload discrepancy
                    updateLocalStocks();
                    cart = [];
                    updateCartUI();
                } else {
                    alert('Error completing transaction: ' + data.message);
                }
            })
            .catch(err => {
                checkoutBtn.disabled = false;
                checkoutBtn.innerHTML = '<i class="fa-solid fa-cash-register"></i> Complete Transaction';
                console.error(err);
                alert('Connection failure or server error. Please check database connectivity.');
            });
        });
    }

    function updateLocalStocks() {
        if (typeof productCatalog !== 'undefined') {
            cart.forEach(item => {
                const catProd = productCatalog.find(p => p.id === item.id);
                if (catProd) {
                    catProd.stock_qty -= item.quantity;
                    // Update UI representation inside HTML Product Catalog Grid
                    const gridCard = productGridContainer.querySelector(`.product-card[data-id="${item.id}"]`);
                    if (gridCard) {
                        gridCard.setAttribute('data-stock', catProd.stock_qty);
                        const stockText = gridCard.querySelector('.product-card-stock');
                        if (stockText) {
                            if (catProd.stock_qty <= 0) {
                                stockText.textContent = 'Out of Stock';
                                stockText.className = 'product-card-stock low-stock';
                            } else {
                                stockText.textContent = 'Stock: ' + catProd.stock_qty;
                                if (catProd.stock_qty <= catProd.min_stock_qty) {
                                    stockText.className = 'product-card-stock low-stock';
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    function displayReceipt(invoice) {
        const datetime = new Date().toLocaleString();
        
        let receiptHtml = `
            <div style="text-align: center; margin-bottom: 12px;">
                <h2 style="font-size: 16px; font-weight: bold;">NEXUSPOS OUTLET</h2>
                <p style="font-size: 11px; color: #555;">123 Business Boulevard, Retail Park</p>
                <p style="font-size: 11px; color: #555;">Tel: +1-555-987-6543</p>
            </div>
            <div style="font-size: 11px; margin-bottom: 10px;">
                <strong>Invoice:</strong> ${invoice.invoice_no}<br>
                <strong>Date:</strong> ${datetime}<br>
                <strong>Cashier:</strong> ${invoice.cashier}<br>
                <strong>Payment:</strong> ${invoice.payment_method}
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 10px;">
                <thead>
                    <tr style="border-bottom: 1px dashed #000; border-top: 1px dashed #000; text-align: left;">
                        <th style="padding: 4px 0;">Item</th>
                        <th style="text-align: center; padding: 4px 0;">Qty</th>
                        <th style="text-align: right; padding: 4px 0;">Price</th>
                    </tr>
                </thead>
                <tbody>
        `;

        invoice.items.forEach(item => {
            receiptHtml += `
                <tr>
                    <td style="padding: 4px 0;">${item.name}</td>
                    <td style="text-align: center; padding: 4px 0;">${item.quantity}</td>
                    <td style="text-align: right; padding: 4px 0;">$${(item.price * item.quantity).toFixed(2)}</td>
                </tr>
            `;
        });

        receiptHtml += `
                </tbody>
            </table>
            <div style="border-top: 1px dashed #000; padding-top: 6px; font-size: 11px; text-align: right;">
                <div>Subtotal: $${invoice.subtotal.toFixed(2)}</div>
                <div>Tax (8%): $${invoice.tax.toFixed(2)}</div>
                <div style="font-weight: bold; font-size: 13px; margin-top: 4px;">Grand Total: $${invoice.total.toFixed(2)}</div>
                <div style="margin-top: 2px;">Paid: $${invoice.paid.toFixed(2)}</div>
                <div>Change: $${invoice.change.toFixed(2)}</div>
            </div>
            <div style="text-align: center; margin-top: 20px; font-size: 11px;">
                <p>Thank you for shopping with us!</p>
                <p>Please keep this receipt for returns.</p>
            </div>
        `;

        receiptPrintArea.innerHTML = receiptHtml;
        receiptModal.classList.add('active');
    }

    // Modal Control actions
    if (closeReceiptModal) {
        closeReceiptModal.addEventListener('click', () => {
            receiptModal.classList.remove('active');
        });
    }

    if (newSaleBtn) {
        newSaleBtn.addEventListener('click', () => {
            receiptModal.classList.remove('active');
        });
    }

    if (printReceiptBtn) {
        printReceiptBtn.addEventListener('click', () => {
            window.print();
        });
    }
});
