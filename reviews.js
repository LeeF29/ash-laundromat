// Customer Reviews: fills the "What Our Customers Say" section from the database
// (api/reviews.php) and lets a logged-in customer review a Completed order
// (api/submit-review.php). New reviews are hidden until an admin approves them.
// Uses the existing markup: #reviewsSummary, #summaryScore, #summaryStars, #summaryCount, #reviewsEmpty, #reviewsGrid.

(function () {
  const grid      = document.getElementById("reviewsGrid");
  const summaryEl = document.getElementById("reviewsSummary");
  const scoreEl   = document.getElementById("summaryScore");
  const starsEl   = document.getElementById("summaryStars");
  const countEl   = document.getElementById("summaryCount");
  const emptyEl   = document.getElementById("reviewsEmpty");
  const container = document.querySelector(".reviews-container");

  if (!grid || !summaryEl || !emptyEl || !container) return;

  // nothing is shown until the database has answered
  summaryEl.hidden = true;
  emptyEl.hidden = true;

  const starText = (n) => "★".repeat(n) + "☆".repeat(5 - n);

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  // ---------- "Leave a Review" block (built here, so index.html needs no extra markup) ----------

  const wrap = el("div", "review-form-wrap");
  wrap.id = "reviewFormWrap";

  const lockedBox = el("div", "history-state");
  lockedBox.hidden = true;
  const lockedText = el("p");
  const lockedStrong = el("strong", "", "Want to leave a review?");
  lockedText.appendChild(lockedStrong);
  lockedText.appendChild(document.createElement("br"));
  lockedText.appendChild(document.createTextNode("Log in to review one of your completed orders."));
  const loginBtn = el("button", "btn-small", "Log In");
  loginBtn.type = "button";
  lockedBox.appendChild(lockedText);
  lockedBox.appendChild(loginBtn);

  const noOrdersBox = el("div", "history-state");
  noOrdersBox.hidden = true;
  const noOrdersText = el("p");
  noOrdersText.appendChild(el("strong", "", "Nothing to review yet."));
  noOrdersText.appendChild(document.createElement("br"));
  noOrdersText.appendChild(document.createTextNode("You can leave a review once one of your orders is Completed."));
  noOrdersBox.appendChild(noOrdersText);

  const form = el("form", "review-form");
  form.hidden = true;
  form.noValidate = true;

  form.appendChild(el("h3", "", "Leave a Review"));
  form.appendChild(el("p", "review-form-sub", "Tell us how your completed order went. Reviews appear on the website after we approve them."));

  const orderLabel = el("label", "review-field");
  orderLabel.appendChild(el("span", "", "Order"));
  const orderSelect = el("select");
  orderSelect.name = "order_id";
  orderLabel.appendChild(orderSelect);
  form.appendChild(orderLabel);

  const ratingBox = el("div", "review-field");
  ratingBox.appendChild(el("span", "", "Your rating"));
  const starRow = el("div", "review-rating-stars");
  starRow.setAttribute("role", "group");
  starRow.setAttribute("aria-label", "Your rating");
  const starButtons = [];
  let rating = 0;

  for (let i = 1; i <= 5; i++) {
    const b = el("button", "review-star", "★");
    b.type = "button";
    b.setAttribute("aria-label", i + (i === 1 ? " star" : " stars"));
    b.setAttribute("aria-pressed", "false");
    b.addEventListener("click", () => setRating(i));
    starButtons.push(b);
    starRow.appendChild(b);
  }
  ratingBox.appendChild(starRow);
  form.appendChild(ratingBox);

  function setRating(n) {
    rating = n;
    starButtons.forEach((b, idx) => {
      b.classList.toggle("is-on", idx < n);
      b.setAttribute("aria-pressed", idx + 1 === n ? "true" : "false");
    });
  }

  const commentLabel = el("label", "review-field");
  commentLabel.appendChild(el("span", "", "Your review"));
  const comment = el("textarea");
  comment.name = "comment";
  comment.maxLength = 500;
  comment.rows = 4;
  comment.placeholder = "How was the service, the cleanliness, the pickup or delivery?";
  commentLabel.appendChild(comment);
  const counter = el("small", "review-field-count", "0 / 500");
  commentLabel.appendChild(counter);
  comment.addEventListener("input", () => { counter.textContent = comment.value.length + " / 500"; });
  form.appendChild(commentLabel);

  const errorEl = el("p", "form-message");
  errorEl.hidden = true;
  form.appendChild(errorEl);

  const submitBtn = el("button", "btn-create", "Submit Review");
  submitBtn.type = "submit";
  form.appendChild(submitBtn);

  const successEl = el("p", "order-success");
  successEl.hidden = true;

  wrap.appendChild(lockedBox);
  wrap.appendChild(noOrdersBox);
  wrap.appendChild(form);
  wrap.appendChild(successEl);
  container.appendChild(wrap);

  function showError(message) {
    errorEl.textContent = message;
    errorEl.hidden = false;
  }

  // ---------- load + draw ----------

  // the CSRF token is fetched when the form is submitted (not on page load), so it always
  // belongs to the session the browser is actually using at that moment
  function getCsrfToken() {
    return fetch("api/csrf-token.php", { credentials: "same-origin", headers: { Accept: "application/json" } })
      .then((res) => res.json())
      .then((data) => data.csrf_token || "");
  }

  let loadId = 0;

  function load() {
    const thisLoad = ++loadId;

    fetch("api/reviews.php", { credentials: "same-origin", headers: { Accept: "application/json" } })
      .then((res) => res.json())
      .then((data) => { if (thisLoad === loadId) draw(data); })
      .catch(() => { if (thisLoad === loadId) draw({ summary: { count: 0 }, reviews: [], logged_in: false, eligible_orders: [] }); });
  }

  function draw(data) {
    const reviews = (data && data.reviews) || [];
    const total = data && data.summary ? Number(data.summary.count) || 0 : 0;

    grid.innerHTML = "";

    if (!reviews.length) {
      summaryEl.hidden = true;
      grid.hidden = true;
      emptyEl.hidden = false;
    } else {
      const average = Number(data.summary.average) || 0;
      scoreEl.textContent = average.toFixed(1);
      starsEl.textContent = starText(Math.max(0, Math.min(5, Math.round(average))));
      countEl.textContent = "Based on " + total + " review" + (total === 1 ? "" : "s");
      summaryEl.hidden = false;
      emptyEl.hidden = true;

      reviews.forEach((r) => {
        const card = el("div", "review-card");
        card.appendChild(el("div", "review-card-stars", starText(Math.max(0, Math.min(5, Number(r.rating) || 0)))));
        card.appendChild(el("p", "", r.comment));

        const who = el("div", "review-card-who");
        who.appendChild(el("span", "review-avatar", r.initial));
        const text = el("div");
        text.appendChild(el("div", "review-card-name", r.name));
        text.appendChild(el("div", "review-card-location", r.detail));
        who.appendChild(text);
        card.appendChild(who);

        grid.appendChild(card);
      });

      grid.hidden = false;
    }

    // the "Leave a Review" block
    const loggedIn = !!(data && data.logged_in);
    const orders = (data && data.eligible_orders) || [];

    lockedBox.hidden = loggedIn;
    noOrdersBox.hidden = !loggedIn || orders.length > 0;
    form.hidden = !loggedIn || orders.length === 0;
    if (!loggedIn) successEl.hidden = true;

    orderSelect.innerHTML = "";
    orders.forEach((o) => {
      const opt = el("option", "", o.order_no + (o.service ? " - " + o.service : ""));
      opt.value = o.id;
      orderSelect.appendChild(opt);
    });
  }

  // ---------- actions ----------

  loginBtn.addEventListener("click", () => {
    if (window.AshAuth) window.AshAuth.requireAuth(() => load());
  });

  document.addEventListener("ashauth:change", load);

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    errorEl.hidden = true;
    successEl.hidden = true;

    const text = comment.value.trim();

    if (!orderSelect.value) return showError("Please choose the order you are reviewing.");
    if (!rating) return showError("Please choose a star rating.");
    if (text.length < 5) return showError("Please write at least a few words about your order.");

    submitBtn.disabled = true;

    getCsrfToken()
      .then((token) => fetch("api/submit-review.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ csrf_token: token, order_id: Number(orderSelect.value), rating, comment: text }),
      }))
      .then((res) => res.json().catch(() => ({ success: false, error: "Something went wrong. Please try again." })))
      .then((data) => {
        submitBtn.disabled = false;
        if (data && data.success) {
          comment.value = "";
          counter.textContent = "0 / 500";
          setRating(0);
          successEl.textContent = data.message;
          successEl.hidden = false;
          load();
        } else {
          showError((data && data.error) || "Something went wrong. Please try again.");
        }
      })
      .catch(() => {
        submitBtn.disabled = false;
        showError("Something went wrong. Please try again.");
      });
  });

  load();
})();