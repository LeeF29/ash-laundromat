// Create Order page: add-on items picked from the Pricing list + live estimated total.
// The server recalculates the real total (from the database prices) when the order is saved.

(function () {
  const form = document.getElementById("orderForm");
  if (!form) return;

  const service    = document.getElementById("serviceSelect");
  const weight     = document.getElementById("weightInput");
  const weightRate = document.getElementById("weightRate");
  const press      = document.getElementById("addPress");
  const pickup     = document.getElementById("addPickup");
  const pickupDate = document.getElementById("pickupDate");
  const totalNum   = document.getElementById("totalNum");
  const totalSub   = document.getElementById("totalSub");

  const itemCategory = document.getElementById("itemCategory");
  const itemSelect   = document.getElementById("itemSelect");
  const itemQty      = document.getElementById("itemQty");
  const itemList     = document.getElementById("itemList");
  const itemHidden   = document.getElementById("itemHidden");

  const CATEGORY_LABELS = { bedding: "Bedding/Linen", footwear: "Footwear", special: "Special Item" };
  const ITEM_DATA = window.AP_ITEM_DATA || { bedding: [], footwear: [], special: [] };

  const money = (n) => {
    n = Number(n) || 0;
    return "₱" + n.toLocaleString("en-PH", {
      minimumFractionDigits: Number.isInteger(n) ? 0 : 2,
      maximumFractionDigits: 2,
    });
  };
  const round2 = (n) => Math.round(n * 100) / 100;

  const unitShort = (type) => (type === "per_pair" ? "pair" : type === "per_piece" ? "pc" : "kg");
  const unitLabel = (type, qty) => {
    if (type === "per_pair") return qty == 1 ? " pair" : " pairs";
    if (type === "per_piece") return " pc";
    return "kg";
  };
  const itemTitle = (it) => it.name + (it.variant ? " (" + it.variant + ")" : "");

  function findItem(category, id) {
    return (ITEM_DATA[category] || []).find((i) => i.id === id) || null;
  }

  // items already added (restored after a validation error)
  let items = (Array.isArray(window.AP_OLD_ITEMS) ? window.AP_OLD_ITEMS : [])
    .map((i) => ({ category: i.category, id: Number(i.id), qty: parseFloat(i.qty) }))
    .filter((i) => findItem(i.category, i.id) && i.qty > 0);

  function servicePrice() {
    const opt = service.options[service.selectedIndex];
    return opt ? parseFloat(opt.dataset.price) || 0 : 0;
  }

  function lineTotal(row) {
    const it = findItem(row.category, row.id);
    return it ? round2(it.price * row.qty) : 0;
  }

  function recalc() {
    const w = parseFloat(weight.value) || 0;
    const sp = servicePrice();

    let total = round2(sp * w);
    if (press && press.checked) total += round2((parseFloat(press.dataset.price) || 0) * w);
    if (pickup && pickup.checked) total += round2(parseFloat(pickup.dataset.price) || 0);
    items.forEach((row) => { total += lineTotal(row); });

    weightRate.textContent = "— " + money(sp) + "/kg";
    totalNum.textContent = money(total);
    totalSub.textContent = w + "kg × " + money(sp) + " base";
    pickupDate.required = !!(pickup && pickup.checked);
  }

  // fill the item dropdown for the chosen category, with prices from the Pricing list
  function fillItemSelect() {
    const list = ITEM_DATA[itemCategory.value] || [];
    itemSelect.innerHTML = "";

    if (!list.length) {
      const opt = document.createElement("option");
      opt.textContent = "No items in the price list";
      opt.value = "";
      itemSelect.appendChild(opt);
      itemSelect.disabled = true;
      itemQty.disabled = true;
      return;
    }

    itemSelect.disabled = false;
    itemQty.disabled = false;

    list.forEach((it) => {
      const opt = document.createElement("option");
      opt.value = it.id;
      opt.textContent = itemTitle(it) + " - " + money(it.price) + "/" + unitShort(it.pricing_type);
      itemSelect.appendChild(opt);
    });

    fillQtyRules();
  }

  function fillQtyRules() {
    const it = findItem(itemCategory.value, Number(itemSelect.value));
    if (!it) return;

    if (it.pricing_type === "per_pair") {
      itemQty.placeholder = "Pairs"; itemQty.step = "1"; itemQty.min = "1";
    } else if (it.pricing_type === "per_piece") {
      itemQty.placeholder = "Qty"; itemQty.step = "1"; itemQty.min = "1";
    } else {
      itemQty.placeholder = "Kg"; itemQty.step = "0.5"; itemQty.min = "0.5";
    }
  }

  function renderItems() {
    itemList.innerHTML = "";
    itemHidden.innerHTML = "";

    items.forEach((row, index) => {
      const it = findItem(row.category, row.id);

      const li = document.createElement("li");
      const text = document.createElement("span");
      text.textContent =
        (CATEGORY_LABELS[row.category] || row.category) + " – " + itemTitle(it) +
        " – " + row.qty + unitLabel(it.pricing_type, row.qty) + " – " + money(lineTotal(row));
      li.appendChild(text);

      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "ap-item-remove";
      remove.textContent = "Remove";
      remove.addEventListener("click", () => {
        items.splice(index, 1);
        renderItems();
        recalc();
      });
      li.appendChild(remove);
      itemList.appendChild(li);

      [["item_category", row.category], ["item_id", row.id], ["item_qty", row.qty]].forEach(([name, value]) => {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = name + "[]";
        input.value = value;
        itemHidden.appendChild(input);
      });
    });
  }

  function addItem() {
    const id = Number(itemSelect.value);
    const qty = parseFloat(itemQty.value);

    if (!id || !findItem(itemCategory.value, id)) return false;
    if (!qty || qty <= 0) { itemQty.focus(); return false; }

    items.push({ category: itemCategory.value, id, qty });
    itemQty.value = "";
    renderItems();
    recalc();
    return true;
  }

  document.getElementById("itemAdd").addEventListener("click", addItem);
  itemCategory.addEventListener("change", fillItemSelect);
  itemSelect.addEventListener("change", fillQtyRules);

  // an item with a quantity typed but never added is added on submit, so it isn't silently lost
  form.addEventListener("submit", (e) => {
    if (itemQty.value !== "") {
      if (!addItem()) e.preventDefault();
    }
  });

  [service, weight, press, pickup].forEach((el) => el && el.addEventListener("input", recalc));
  [service, press, pickup].forEach((el) => el && el.addEventListener("change", recalc));

  // ---- customer lookup: fill in a customer who is already on file (by phone or email) ----
  const fName    = form.elements["customer_name"];
  const fPhone   = form.elements["contact_number"];
  const fEmail   = form.elements["customer_email"];
  const fAddress = form.elements["customer_address"];
  const lookupBox = document.getElementById("custLookup");

  let lastFill = null;   // what we filled in from a found customer, so stale values can be cleared again
  let lookupSeq = 0;

  function showLookup(status, message) {
    if (!lookupBox) return;
    lookupBox.hidden = !message;
    lookupBox.textContent = message || "";
    lookupBox.className = "ap-lookup" + (status ? " ap-lookup-" + status : "");
  }

  // the customer was switched (a filled-in phone/email was changed): clear what belonged to the old one
  function clearFilled(except) {
    if (!lastFill) return;
    [["name", fName], ["phone", fPhone], ["email", fEmail], ["address", fAddress]].forEach(([key, el]) => {
      if (el !== except && lastFill[key] !== undefined && el.value === lastFill[key]) el.value = "";
    });
    lastFill = null;
  }

  function fillFrom(c) {
    const filled = {};
    if (c.name)    { fName.value = c.name;       filled.name = c.name; }
    if (c.phone)   { fPhone.value = c.phone;     filled.phone = c.phone; }
    if (c.email)   { fEmail.value = c.email;     filled.email = c.email; }
    if (c.address) { fAddress.value = c.address; filled.address = c.address; }
    lastFill = filled;
  }

  function lookupCustomer() {
    const phone = fPhone.value.trim();
    const email = fEmail.value.trim();
    const seq = ++lookupSeq;

    if (!phone && !email) { showLookup("", ""); return; }

    fetch("create-order.php?lookup=1&phone=" + encodeURIComponent(phone) + "&email=" + encodeURIComponent(email), {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    })
      .then((res) => res.json())
      .then((data) => {
        if (seq !== lookupSeq) return;   // an older answer arriving late
        if (data.customer) fillFrom(data.customer);
        showLookup(data.status === "idle" ? "" : data.status, data.status === "idle" ? "" : data.message);
      })
      .catch(() => { if (seq === lookupSeq) showLookup("", ""); });
  }

  [fPhone, fEmail].forEach((el) => {
    if (!el) return;
    el.addEventListener("change", () => {
      // changing a value that we filled in means a different customer: drop the old customer's details first
      if (lastFill && lastFill[el === fPhone ? "phone" : "email"] !== undefined && el.value !== lastFill[el === fPhone ? "phone" : "email"]) {
        clearFilled(el);
      }
      lookupCustomer();
    });
  });

  fillItemSelect();
  renderItems();
  recalc();
})();