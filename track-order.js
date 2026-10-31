// Track Order: look an order up by its number and show its status.
// Uses the existing markup in index.html (#trackorderForm, #orderStatus, #trackorderResult ...).
// Data comes from api/track-order.php, so it always matches what the admin panel shows.
//
// Steps shown depend on the order:
//   Waiting > [For Pickup: pickup orders only] > Received > Washing > Drying
//   > [Pressing & Ironing: only if included] > Ready > Completed

(function () {
  const form      = document.getElementById("trackorderForm");
  const input     = document.getElementById("orderStatus");
  const errorEl   = document.getElementById("trackstatus-Error");
  const resultEl  = document.getElementById("trackorderResult");
  const idEl      = document.getElementById("resultOrderId");
  const detailEl  = document.getElementById("resultCustomer");
  const payEl     = document.getElementById("resultPayment");
  const fillEl    = document.getElementById("progressFill");
  const stepsWrap = document.getElementById("progressSteps");

  if (!form || !input || !errorEl || !resultEl || !stepsWrap) return;

  // a short explanation of the current step, shown under the progress bar
  const progressEl = stepsWrap.parentElement;
  let noteEl = document.getElementById("resultStatusNote");
  if (!noteEl) {
    noteEl = document.createElement("p");
    noteEl.id = "resultStatusNote";
    noteEl.className = "trackorder-status-note";
    progressEl.appendChild(noteEl);
  }

  const LABELS = {
    waiting:    "Waiting",
    for_pickup: "For Pickup",
    received:   "Received",
    washing:    "Washing",
    drying:     "Drying",
    pressing:   "Pressing & Ironing",
    ready:      "Ready",
    completed:  "Completed",
  };

  function noteFor(status, pickup) {
    switch (status) {
      case "waiting":    return "Your order has been placed and is waiting for the next step." + (pickup ? " Your laundry is waiting to be collected." : "");
      case "for_pickup": return "Your laundry is awaiting collection from your address.";
      case "received":   return "Your laundry has arrived at the laundromat and is ready for processing.";
      case "washing":    return "Your laundry is currently being washed.";
      case "drying":     return "Your laundry is being dried after washing.";
      case "pressing":   return "Your laundry is being pressed or ironed.";
      case "ready":      return pickup ? "Your laundry is clean and ready for delivery to you." : "Your laundry is clean and ready for pickup at the store.";
      case "completed":  return pickup ? "Your laundry has been delivered." : "You have received your laundry.";
    }
    return "";
  }

  // small line icons for the status card (fixed markup, drawn with currentColor)
  const ICONS = {
    waiting:    '<path d="M5 22h14"/><path d="M5 2h14"/><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"/><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/>',
    for_pickup: '<g class="tn-move"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></g>',
    received:   '<g class="tn-pop"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="m7.5 4.27 9 5.15"/></g>',
    washing:    '<rect x="3" y="2" width="18" height="20" rx="3"/><path d="M3 7h18"/><path d="M7 4.5h.01"/><path d="M11 4.5h.01"/><g class="tn-drum"><circle cx="12" cy="14.5" r="5"/><path d="M8.6 14.5c1.1-1.6 2.3-1.6 3.4 0s2.3 1.6 3.4 0"/></g>',
    drying:     '<path class="tn-heat tn-heat-1" d="M5 21c-2.2-2.6 2.2-4 0-6.6s2.2-4 0-6.6s2.2-4 0-6.6"/><path class="tn-heat tn-heat-2" d="M12 21c-2.2-2.6 2.2-4 0-6.6s2.2-4 0-6.6s2.2-4 0-6.6"/><path class="tn-heat tn-heat-3" d="M19 21c-2.2-2.6 2.2-4 0-6.6s2.2-4 0-6.6s2.2-4 0-6.6"/>',
    pressing:   '<g class="tn-iron"><path d="M3 17h17.2a1.3 1.3 0 0 0 1.3-1.4C21 10.5 17 7 12 7H9a6 6 0 0 0-6 6z"/><path d="M3 21h18"/><path d="M12 11h4"/></g>',
    ready:      '<g class="tn-bounce"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></g>',
    completed:  '<circle cx="12" cy="12" r="10"/><path class="tn-check" d="m8 12.5 3 3 5-6.5"/>',
  };

  // fill the status card: icon, title and sentence (the animation restarts for every search)
  function renderNote(status, pickup) {
    noteEl.className = "trackorder-status-note is-" + status;
    noteEl.textContent = "";

    const icon = document.createElement("div");
    icon.className = "trackorder-note-icon";
    icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[status] || "") + "</svg>";

    const text = document.createElement("div");
    text.className = "trackorder-note-text";

    const title = document.createElement("strong");
    title.className = "trackorder-note-title";
    title.textContent = LABELS[status] || status;

    const sentence = document.createElement("span");
    sentence.textContent = noteFor(status, pickup);

    text.appendChild(title);
    text.appendChild(sentence);
    noteEl.appendChild(icon);
    noteEl.appendChild(text);

    noteEl.classList.remove("is-in");
    void noteEl.offsetWidth;   // restart the entrance animation
    noteEl.classList.add("is-in");
  }

  // the steps this order goes through, in order
  function stepsFor(order) {
    const flow = [
      ["waiting", true],
      ["for_pickup", order.pickup],
      ["received", true],
      ["washing", true],
      ["drying", true],
      ["pressing", order.press],
      ["ready", true],
      ["completed", true],
    ];
    // a conditional step is also kept if the order is on it right now, so the bar never loses its place
    return flow.filter(([key, needed]) => needed || key === order.status).map(([key]) => key);
  }

  // the normal "not found" sentence is the one written in index.html;
  // a different sentence is shown when the server says there have been too many lookups
  const NOT_FOUND_MESSAGE = errorEl.textContent;
  const LIMITED_MESSAGE = "Too many lookups in a short time. Please wait a few minutes and try again.";

  let requestId = 0;

  function showError(message) {
    errorEl.textContent = message || NOT_FOUND_MESSAGE;
    resultEl.hidden = true;
    errorEl.hidden = false;
  }

  function showResult(order) {
    const keys = stepsFor(order);
    const index = keys.indexOf(order.status);

    idEl.textContent = "Order " + order.order_no;

    const weight = order.weight !== null && order.weight !== undefined ? Number(order.weight) + "kg" : "";
    detailEl.textContent = [weight, order.service].filter(Boolean).join(" • ");

    const paid = order.payment_status === "paid";
    payEl.textContent = paid ? "Paid" : "Unpaid";
    payEl.classList.toggle("is-paid", paid);
    payEl.classList.toggle("is-unpaid", !paid);

    stepsWrap.innerHTML = "";
    keys.forEach((key, i) => {
      const step = document.createElement("span");
      step.className = "step" + (index !== -1 && i <= index ? " is-active" : "");
      step.dataset.step = LABELS[key];
      step.textContent = LABELS[key];
      stepsWrap.appendChild(step);
    });

    fillEl.style.width = index === -1 ? "0%" : ((index + 1) / keys.length) * 100 + "%";
    renderNote(order.status, order.pickup);

    errorEl.hidden = true;
    resultEl.hidden = false;
  }

  form.addEventListener("submit", (e) => {
    e.preventDefault();

    const orderNo = input.value.trim();
    if (!orderNo) { showError(); input.focus(); return; }

    const thisRequest = ++requestId;

    fetch("api/track-order.php?order_no=" + encodeURIComponent(orderNo), { headers: { Accept: "application/json" } })
      .then((res) => res.json().catch(() => ({})).then((data) => {
        data = data || {};
        if (res.status === 429) data.limited = true;
        return data;
      }))
      .then((data) => {
        if (thisRequest !== requestId) return;   // an older search finishing late
        if (data.found) showResult(data);
        else if (data.limited) showError(data.message || LIMITED_MESSAGE);
        else showError();
      })
      .catch(() => { if (thisRequest === requestId) showError(); });
  });
})();