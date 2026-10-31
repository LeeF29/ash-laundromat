/* =====================================================================
   ASH LAUNDROMAT - price-section.js

   Powers the Price List / booking form (#prices section):
     - Loads live prices from api/services.php
     - Fills the Service, Bedding/Linen, Footwear and Special Item
       dropdowns from that data
     - Lets the customer add optional items to the order
     - Calculates and displays an estimated total as they fill the form

   This only ESTIMATES the total for display. The real order total is
   always recalculated on the server using the database prices - the
   server will never trust a total sent from here. That part comes in
   the next step.
   ===================================================================== */

(function () {

    const peso = new Intl.NumberFormat("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    function formatPeso(amount) {
        return "₱" + peso.format(amount);
    }


    // ===== Elements =====

    const serviceSelect  = document.getElementById("serviceSelect");
    const weightInput    = document.getElementById("weight");

    const beddingSelect  = document.getElementById("beddingSelect");
    const beddingQty     = document.getElementById("beddingQty");

    const footwearSelect = document.getElementById("footwearSelect");
    const footwearQty    = document.getElementById("footwearQty");

    const specialItem    = document.getElementById("specialItem");
    const specialQty     = document.getElementById("specialQty");
    const specialVariant = document.getElementById("specialVariant");

    const itemsBody      = document.getElementById("itemsBody");

    const addPress       = document.getElementById("addPress");
    const pressHint      = document.getElementById("pressHint");

    const addPickup      = document.getElementById("addPickup");
    const pickupTitle    = document.getElementById("pickupTitle");

    const sumLines        = document.getElementById("sumLines");
    const sumTotal         = document.getElementById("sumTotal");
    const sumBig          = document.getElementById("sumBig");

    const bookingForm = document.getElementById("bookingForm");
    const formMessage = document.getElementById("formMessage");

    const custName    = document.getElementById("custName");
    const custPhone   = document.getElementById("custPhone");
    const pickupDateInput = document.getElementById("pickupDate");
    const createBtn   = document.getElementById("createBtn");

    const guestFields   = document.getElementById("guestFields");
    const cnxDetails    = document.getElementById("cnxDetails");
    const prefillBanner = document.getElementById("prefillBanner");
    const cnxName       = document.getElementById("cnxName");
    const cnxPhone      = document.getElementById("cnxPhone");
    const cnxAddress    = document.getElementById("cnxAddress");

    const historyLocked    = document.getElementById("historyLocked");
    const historyEmpty     = document.getElementById("historyEmpty");
    const historyTableWrap = document.getElementById("historyTableWrap");
    const historyBody      = document.getElementById("historyBody");
    const historyLoginBtn  = document.getElementById("historyLoginBtn");

    if (!bookingForm) return; // this page doesn't have the price section


    // ===== State =====

    let catalog = { laundry: [], bedding: [], footwear: [], special: [], addon: [] };
    let addonBySlug = {};
    let addedItems = [];
    let nextRowId = 1;

    // ===== CSRF token =====

    let csrfToken = "";

    fetch("api/csrf-token.php")
        .then((response) => response.json())
        .then((data) => { csrfToken = data.csrf_token || ""; })
        .catch(() => {});


    // ===== Load prices =====

    fetch("api/services.php")
        .then((response) => response.json())
        .then((data) => {

            catalog = data;

            (catalog.addon || []).forEach((item) => {
                if (item.slug) addonBySlug[item.slug] = item;
            });

            fillServiceSelect();
            fillPickerSelect(beddingSelect, catalog.bedding, "kg");
            fillPickerSelect(footwearSelect, catalog.footwear, "pair");
            fillSpecialSelects();

            updateAddonLabels();
            recalcTotal();

        })
        .catch(() => {
            if (serviceSelect) {
                serviceSelect.innerHTML = '<option value="">Could not load prices</option>';
            }
        });


    function fillServiceSelect() {

        if (!serviceSelect) return;

        if (!catalog.laundry.length) {
            serviceSelect.innerHTML = '<option value="">No services available yet</option>';
            return;
        }

        serviceSelect.innerHTML = '<option value="">Select a service</option>' +
            catalog.laundry.map((item) =>
                `<option value="${item.id}" data-price="${item.price}">${item.name} - ${formatPeso(item.price)}/kg</option>`
            ).join("");

    }


    function fillPickerSelect(selectEl, items, unitLabel) {

        if (!selectEl) return;

        if (!items || !items.length) {
            selectEl.innerHTML = '<option value="">None available yet</option>';
            return;
        }

        selectEl.innerHTML = '<option value="">Select Item</option>' +
            items.map((item) =>
                `<option value="${item.id}" data-price="${item.price}">${item.name} - ${formatPeso(item.price)}/${unitLabel}</option>`
            ).join("");

    }

        function fillSpecialSelects() {

        if (!specialItem) return;

        const items = catalog.special || [];

        if (!items.length) {
            specialItem.innerHTML = '<option value="">None available yet</option>';
            if (specialVariant) {
                specialVariant.innerHTML = '<option value="">Select Item first</option>';
                specialVariant.disabled = true;
            }
            return;
        }

        const names = [...new Set(items.map((i) => i.name))];

        specialItem.innerHTML = '<option value="">Select Item</option>' +
            names.map((name) => `<option value="${name}">${name}</option>`).join("");

        if (specialVariant) {
            specialVariant.innerHTML = '<option value="">Select Item first</option>';
            specialVariant.disabled = true;
        }

    }

    if (specialItem) {
        specialItem.addEventListener("change", () => {

            if (!specialVariant) return;

            const name = specialItem.value;
            const variants = (catalog.special || []).filter((i) => i.name === name);

            if (!name || !variants.length) {
                specialVariant.innerHTML = '<option value="">Select Item first</option>';
                specialVariant.disabled = true;
                return;
            }

            specialVariant.innerHTML = '<option value="">Select Type</option>' +
                variants.map((v) => {
                    const unit = v.pricing_type === "per_pair" ? "pair" : v.pricing_type === "per_piece" ? "pc" : "kg";
                    return `<option value="${v.id}" data-price="${v.price}" data-pricing-type="${v.pricing_type}">${v.variant || v.name} - ${formatPeso(v.price)}/${unit}</option>`;
                }).join("");

            specialVariant.disabled = false;

        });
    }

        const specialQtyLabel = document.querySelector('label[for="specialQty"]');

    if (specialVariant) {
        specialVariant.addEventListener("change", () => {

            const selected = specialVariant.selectedOptions[0];
            const pricingType = selected ? selected.dataset.pricingType : "";

            if (pricingType === "per_pair") {
                if (specialQtyLabel) specialQtyLabel.textContent = "Pairs";
                specialQty.step = "1";
                specialQty.min = "1";
            } else if (pricingType === "per_piece") {
                if (specialQtyLabel) specialQtyLabel.textContent = "Qty";
                specialQty.step = "1";
                specialQty.min = "1";
            } else {
                if (specialQtyLabel) specialQtyLabel.textContent = "Weight (kg)";
                specialQty.step = "0.5";
                specialQty.min = "0.5";
            }

        });
    }


    function updateAddonLabels() {

        const press = addonBySlug["press_ironing"];
        const pickup = addonBySlug["pickup_delivery"];

        if (press && pressHint) {
            pressHint.textContent = `Professional Finishing (+${formatPeso(press.price)}/kg)`;
        }

        if (pickup && pickupTitle) {
            pickupTitle.textContent = `Add Pickup & Delivery (+${formatPeso(pickup.price)})`;
        }

        if (!press && addPress) addPress.closest(".price-addon").style.display = "none";
        if (!pickup && addPickup) addPickup.closest(".price-addon").style.display = "none";

    }


    function unitLabelFor(pricingType, qty) {
        if (pricingType === "per_pair") return qty == 1 ? " pair" : " pairs";
        if (pricingType === "per_piece") return " pc";
        return "kg";
    }

    function addItem(category, selectEl, qtyEl) {

        const itemId = selectEl.value;
        const qty = parseFloat(qtyEl.value);

        if (!itemId || !qty || qty <= 0) return;

        const item = (catalog[category] || []).find((i) => String(i.id) === String(itemId));
        if (!item) return;

        const lineTotal = item.price * qty;
        const unitLabel = unitLabelFor(item.pricing_type, qty);

        addedItems.push({
            rowId: nextRowId++,
            category,
            id: item.id,
            qty,
            name: item.variant ? `${item.name} (${item.variant})` : item.name,
            detail: `${qty}${unitLabel}`,
            lineTotal,
        });

        selectEl.value = "";
        qtyEl.value = "";

        if (category === "special" && specialItem && specialVariant) {
            specialItem.value = "";
            specialVariant.innerHTML = '<option value="">Select Item first</option>';
            specialVariant.disabled = true;
        }

        renderAddedItems();
        recalcTotal();

    }

    document.querySelectorAll("[data-add]").forEach((button) => {

        button.addEventListener("click", () => {

            const type = button.dataset.add;

            if (type === "bedding") addItem("bedding", beddingSelect, beddingQty);
            else if (type === "footwear") addItem("footwear", footwearSelect, footwearQty);
            else if (type === "special") addItem("special", specialVariant, specialQty);

        });

    });


    const categoryLabels = {
        bedding: "Bedding / Linen",
        footwear: "Footwear",
        special: "Special Items",
    };

    function renderAddedItems() {

        if (!itemsBody) return;

        if (!addedItems.length) {
            itemsBody.innerHTML = '<tr><td colspan="5">No items added yet.</td></tr>';
            return;
        }

        itemsBody.innerHTML = addedItems.map((row, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${categoryLabels[row.category] || row.category}</td>
                <td>${row.name}</td>
                <td>${row.detail}</td>
                <td class="col-actions">
                    <button type="button" class="btn-icon" data-remove="${row.rowId}">\u{1F5D1}</button>
                </td>
            </tr>
        `).join("");

        itemsBody.querySelectorAll("[data-remove]").forEach((button) => {

            button.addEventListener("click", () => {
                const rowId = Number(button.dataset.remove);
                addedItems = addedItems.filter((row) => row.rowId !== rowId);
                renderAddedItems();
                recalcTotal();
            });

        });

    }


    function recalcTotal() {

        const lines = [];
        let total = 0;

        const selectedOption = serviceSelect ? serviceSelect.selectedOptions[0] : null;
        const weight = parseFloat(weightInput ? weightInput.value : "");

        if (selectedOption && selectedOption.value && weight > 0) {

            const price = parseFloat(selectedOption.dataset.price);
            const lineTotal = price * weight;

            lines.push([`${selectedOption.textContent.split(" - ")[0]} (${weight}kg)`, lineTotal]);
            total += lineTotal;

            if (addPress && addPress.checked && addonBySlug["press_ironing"]) {
                const addonTotal = addonBySlug["press_ironing"].price * weight;
                lines.push(["Press & Ironing", addonTotal]);
                total += addonTotal;
            }

        }

        if (addPickup && addPickup.checked && addonBySlug["pickup_delivery"]) {
            const fee = addonBySlug["pickup_delivery"].price;
            lines.push(["Pickup & Delivery", fee]);
            total += fee;
        }

        addedItems.forEach((row) => {
            lines.push([`${row.name} (${row.detail})`, row.lineTotal]);
            total += row.lineTotal;
        });

        if (sumLines) {
            sumLines.innerHTML = lines.length
                ? lines.map(([label, amount]) => `<li><span>${label}</span><span>${formatPeso(amount)}</span></li>`).join("")
                : '<li class="summary-empty">No items yet</li>';
        }

        if (sumTotal) sumTotal.textContent = formatPeso(total);
        if (sumBig) sumBig.textContent = formatPeso(total);

    }

    [serviceSelect, weightInput, addPress, addPickup].forEach((el) => {
        if (el) el.addEventListener("input", recalcTotal);
        if (el) el.addEventListener("change", recalcTotal);
    });


    function setFieldError(id, message) {
        const el = document.getElementById("err-" + id);
        if (!el) return;
        el.textContent = message;
        el.hidden = !message;
    }

    function clearFieldErrors() {
        ["serviceSelect", "weight", "pickupDate"].forEach((id) => setFieldError(id, ""));
    }

    function validateBookingForm() {

        let ok = true;

        const selectedOption = serviceSelect ? serviceSelect.selectedOptions[0] : null;
        if (!selectedOption || !selectedOption.value) {
            setFieldError("serviceSelect", "Please select a service.");
            ok = false;
        }

        const weight = parseFloat(weightInput ? weightInput.value : "");
        if (!weight || weight < 0.5 || weight > 50) {
            setFieldError("weight", "Enter a weight between 0.5 and 50 kg.");
            ok = false;
        }

        if (addPickup && addPickup.checked && (!pickupDateInput || !pickupDateInput.value)) {
            setFieldError("pickupDate", "Please choose a pickup date.");
            ok = false;
        }

        return ok;

    }

    function submitOrder() {

        clearFieldErrors();

        if (formMessage) {
            formMessage.hidden = true;
            formMessage.textContent = "";
            formMessage.classList.remove("form-message-error", "form-message-ok");
        }

        if (!validateBookingForm()) return;

        const selectedOption = serviceSelect.selectedOptions[0];

        const payload = {
            csrf_token: csrfToken,
            serviceId: selectedOption.value,
            weight: parseFloat(weightInput.value),
            addPress: !!(addPress && addPress.checked),
            addPickup: !!(addPickup && addPickup.checked),
            pickupDate: addPickup && addPickup.checked ? pickupDateInput.value : "",
            items: addedItems.map((row) => ({ category: row.category, id: row.id, qty: row.qty })),
        };

        if (createBtn) {
            createBtn.disabled = true;
            createBtn.textContent = "Creating...";
        }

        fetch("api/create-order.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        })
            .then((response) => response.json())
            .then((data) => {

                if (!data.success) {
                    if (formMessage) {
                        formMessage.hidden = false;
                        formMessage.textContent = data.error || "Something went wrong. Please try again.";
                        formMessage.classList.add("form-message-error");
                    }
                    return;
                }

                if (formMessage) {
                    formMessage.hidden = false;
                    formMessage.textContent = `Order ${data.order_no} created! Estimated total: ${formatPeso(data.total)}.`;
                    formMessage.classList.add("form-message-ok");
                }

                bookingForm.reset();
                addedItems = [];
                renderAddedItems();
                recalcTotal();

                if (specialVariant) {
                    specialVariant.innerHTML = '<option value="">Select Item first</option>';
                    specialVariant.disabled = true;
                }

                loadOrderHistory();

            })
            .catch(() => {
                if (formMessage) {
                    formMessage.hidden = false;
                    formMessage.textContent = "Could not reach the server. Please check your connection and try again.";
                    formMessage.classList.add("form-message-error");
                }
            })
            .finally(() => {
                if (createBtn) {
                    createBtn.disabled = false;
                    createBtn.textContent = "Create Laundry Order";
                }
            });

    }

    // ===== Auth-aware UI (guest fields vs logged-in chip, order history) =====

function applyAuthState() {

    const user = window.AshAuth ? window.AshAuth.getUser() : null;

    if (user) {

        if (guestFields)   guestFields.hidden = true;
        if (cnxDetails)    cnxDetails.hidden = false;
        if (prefillBanner) prefillBanner.hidden = false;

        if (cnxName)    cnxName.textContent    = user.name || "";
        if (cnxPhone)   cnxPhone.textContent   = user.phone || "";
        if (cnxAddress) cnxAddress.textContent = user.address || "";

        if (historyLocked) historyLocked.hidden = true;

        loadOrderHistory();

    } else {

        if (guestFields)   guestFields.hidden = false;
        if (cnxDetails)    cnxDetails.hidden = true;
        if (prefillBanner) prefillBanner.hidden = true;

        if (historyLocked)    historyLocked.hidden = false;
        if (historyEmpty)     historyEmpty.hidden = true;
        if (historyTableWrap) historyTableWrap.hidden = true;

    }

}

function loadOrderHistory() {

    const user = window.AshAuth ? window.AshAuth.getUser() : null;
    if (!user) return;

    fetch("api/my-orders.php")
        .then((response) => response.json())
        .then((data) => {

            const orders = (data && data.orders) || [];

            if (historyLocked) historyLocked.hidden = true;

            if (!orders.length) {
                if (historyEmpty)     historyEmpty.hidden = false;
                if (historyTableWrap) historyTableWrap.hidden = true;
                return;
            }

            if (historyEmpty)     historyEmpty.hidden = true;
            if (historyTableWrap) historyTableWrap.hidden = false;

            if (historyBody) {
                historyBody.innerHTML = orders.map((order) => `
                    <tr>
                        <td>${order.order_no}</td>
                        <td>${order.service}</td>
                        <td>${order.weight}</td>
                        <td>${order.status}</td>
                    </tr>
                `).join("");
            }

        })
        .catch(() => {});

}

if (historyLoginBtn) {
    historyLoginBtn.addEventListener("click", () => {
        if (window.AshAuth) window.AshAuth.requireAuth(() => applyAuthState());
    });
}

document.addEventListener("ashauth:change", applyAuthState);

applyAuthState(); // set correct state immediately in case the user is already logged in

    bookingForm.addEventListener("submit", (event) => {

        event.preventDefault();

        if (window.AshAuth && !window.AshAuth.getUser()) {
            window.AshAuth.requireAuth(() => submitOrder());
            return;
        }

        submitOrder();

    });

})();