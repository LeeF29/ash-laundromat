// ===== Account: session, Customer Access dialog, navbar, My Profile drawer =====
// Talks to the PHP endpoints in /api. Exposes window.AshAuth for other scripts
// (price-section.js uses this instead of handling login itself).

(function () {
  const API = "api/";
  const PAYMENT_LABELS = {
    Cash: "Cash on Pickup",
    GCash: "GCash",
    Maya: "Maya",
    Card: "Card Payment"
  };

  let user = null;
  let pending = null;
  let pendingCallback = null; // runs once, right after a successful login/register

  let csrfToken = "";

  async function loadCsrf() {
    try {
      const res = await fetch(API + "csrf-token.php");
      const data = await res.json();
      csrfToken = data.csrf_token || "";
    } catch (e) {
      csrfToken = "";
    }
  }

  const $ = (id) => document.getElementById(id);
  const initial = (name) => (name || "?").trim().charAt(0).toUpperCase();
  const firstName = (name) => (name || "").trim().split(/\s+/)[0] || "";

  async function postJSON(url, body) {
    const res = await fetch(API + url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body || {}),
    });
    let data = {};
    try { data = await res.json(); } catch (e) { /* not JSON */ }
    return { ok: res.ok, status: res.status, data };
  }

  // ---------- session ----------
  async function refresh() {
    try {
      const res = await fetch(API + "me.php");
      const data = await res.json();
      user = data.user || null;
      pending = data.pending || null;
    } catch (e) {
      user = null;
      pending = null;
    }
    render();
    return user;
  }

  // ---------- navbar: chip / welcome badge / guarded booking links ----------
  function ensureNavAccount() {
    let el = $("navAccount");
    if (el) return el;

    el = document.createElement("div");
    el.id = "navAccount";
    el.className = "nav-account";
    el.hidden = true;
    el.innerHTML =
      '<div class="nav-chip">' +
        '<span class="nav-avatar" id="navAvatar"></span>' +
        '<span class="nav-chip-name" id="navChipName"></span>' +
        '<button type="button" class="nav-gear" id="navGear" aria-label="Account settings"><img class="icon-img-lg" src="Assets/Gear-Icon.svg" alt=""></button>' +
      '</div>' +
      '<button type="button" class="nav-logout" id="navLogoutBtn"><img class="icon-img-lg" src="Assets/Logout-Icon.svg" alt=""><span>Logout</span></button>';

    const navBook = document.querySelector(".nav-book");
    if (navBook) navBook.insertAdjacentElement("beforebegin", el);
    else document.querySelector(".nav-menu")?.appendChild(el);

    $("navGear").addEventListener("click", openDrawer);
    $("navLogoutBtn").addEventListener("click", doLogout);
    return el;
  }

  function ensureWelcomeBadge() {
    let el = $("welcomeBadge");
    if (el) return el;

    const host = document.querySelector(".main-content");
    if (!host) return null;

    el = document.createElement("div");
    el.id = "welcomeBadge";
    el.className = "welcome-badge";
    el.hidden = true;
    el.innerHTML = '<span class="nav-avatar" id="welcomeAvatar"></span>Welcome back, <strong id="welcomeName"></strong>!';
    host.insertBefore(el, host.firstChild);
    return el;
  }

  // Guests get sent to Customer Access instead of following the link.
  function guardBookingLinks() {
    document.querySelectorAll(".nav-book, .dash-primary-b").forEach((el) => {
      el.addEventListener("click", (e) => {
        if (!user) {
          e.preventDefault();
          openDialog("login");
        }
      });
    });
  }

  function render() {
    const navBook = document.querySelector(".nav-book");
    const navAccount = ensureNavAccount();
    const badge = ensureWelcomeBadge();

    if (user) {
      if (navBook) navBook.hidden = true;
      navAccount.hidden = false;
      $("navAvatar").textContent = initial(user.name);
      $("navChipName").textContent = firstName(user.name);
      if (badge) {
        badge.hidden = false;
        $("welcomeAvatar").textContent = initial(user.name);
        $("welcomeName").textContent = firstName(user.name);
      }
    } else {
      if (navBook) navBook.hidden = false;
      navAccount.hidden = true;
      if (badge) badge.hidden = true;
    }

    document.dispatchEvent(new CustomEvent("ashauth:change", { detail: { user } }));

  }

  // ---------- Customer Access dialog ----------
  const dlg = $("authDialog");
  const loginForm = $("loginForm");
  const registerForm = $("registerForm");

  function switchTab(tab) {
    dlg.querySelectorAll("[data-tab-link]").forEach((b) => {
      if (b.closest(".auth-tabs")) b.classList.toggle("is-active", b.dataset.tabLink === tab);
    });
    loginForm.hidden = tab !== "login";
    registerForm.hidden = tab !== "register";
  }

  function openDialog(tab) {
    switchTab(tab || "login");
    loginForm.reset();
    registerForm.reset();
    loginForm.querySelector(".auth-error").hidden = true;
    registerForm.querySelector(".auth-error").hidden = true;
    if (!dlg.open) dlg.showModal();
  }

  dlg.querySelectorAll("[data-tab-link]").forEach((b) => b.addEventListener("click", () => switchTab(b.dataset.tabLink)));
  $("authClose").addEventListener("click", () => dlg.close());
  dlg.addEventListener("click", (e) => { if (e.target === dlg) dlg.close(); });

  dlg.querySelectorAll(".pw-toggle").forEach((btn) => {
    btn.addEventListener("click", () => {
      const input = btn.previousElementSibling;
      const showing = input.type === "text";
      input.type = showing ? "password" : "text";
      // Only one eye icon file is in use, so the icon itself stays put;
      // the pressed state and label still reflect what will happen next.
      btn.setAttribute("aria-label", showing ? "Show password" : "Hide password");
      btn.setAttribute("aria-pressed", String(!showing));
    });
  });

  async function handleAuth(form, url) {
    const errEl = form.querySelector(".auth-error");
    const btn = form.querySelector("button[type='submit']");
    errEl.hidden = true;
    if (!form.reportValidity()) return;

    btn.disabled = true;
    try {
      const r = await postJSON(url, Object.assign(Object.fromEntries(new FormData(form)), { csrf_token: csrfToken }));
      if (!r.ok) {
        errEl.textContent = r.data.error || "Something went wrong. Please try again.";
        errEl.hidden = false;
        return;
      }
      user = r.data.user;
      dlg.close();
      render();
      if (pendingCallback) {
        const cb = pendingCallback;
        pendingCallback = null;
        cb(user);
      }
    } catch (err) {
      console.error(err);
      errEl.textContent = "We couldn't reach the server. Please check your connection and try again.";
      errEl.hidden = false;
    } finally {
      btn.disabled = false;
    }
  }

  loginForm.addEventListener("submit", (e) => { e.preventDefault(); handleAuth(loginForm, "login.php"); });
  registerForm.addEventListener("submit", (e) => { e.preventDefault(); handleAuth(registerForm, "register.php"); });

  async function doLogout() {
    await postJSON("logout.php", {});
    user = null;
    pending = null;
    render();
    closeDrawer();
  }

  // ---------- My Profile drawer ----------
  const overlay = $("drawerOverlay");
  const drawer = $("profileDrawer");
  const profileForm = $("profileForm");
  const editToggleBtn = $("editToggle");
  const editCancelBtn = $("editCancel");
  const drawerActions = $("drawerActions");
  const editNote = $("editNote");
  const drawerError = $("drawerError");

  function populateDrawer() {
    if (!user) return;
    $("drawerAvatar").textContent = initial(user.name);
    $("drawerName").textContent = user.name;
    $("drawerEmail").textContent = user.email;

    const values = {
      full_name: user.name,
      phone: user.phone,
      address: user.address,
      pickup_day: user.pickup_day || "No preference",
      pickup_time: user.pickup_time || "No preference",
      payment_method: PAYMENT_LABELS[user.payment_method] || user.payment_method,
    };
    profileForm.querySelectorAll(".drawer-value").forEach((el) => {
      el.textContent = values[el.dataset.view] || "";
    });

    profileForm.elements.full_name.value = user.name || "";
    profileForm.elements.phone.value = user.phone || "";
    profileForm.elements.address.value = user.address || "";
    profileForm.elements.pickup_day.value = user.pickup_day || "";
    profileForm.elements.pickup_time.value = user.pickup_time || "";
    profileForm.elements.payment_method.value = user.payment_method || "Cash";

    if (pending) {
      $("drawerPending").hidden = false;
      $("drawerPending").textContent = "You have a profile update pending. It will apply automatically within 24-48 hours.";
    } else {
      $("drawerPending").hidden = true;
    }
  }

  function enterEdit() {
    profileForm.querySelectorAll(".drawer-value").forEach((v) => (v.hidden = true));
    profileForm.querySelectorAll(".drawer-input").forEach((i) => (i.hidden = false));
    editNote.hidden = false;
    drawerActions.hidden = false;
    editToggleBtn.hidden = true;
  }

  function exitEdit() {
    profileForm.querySelectorAll(".drawer-value").forEach((v) => (v.hidden = false));
    profileForm.querySelectorAll(".drawer-input").forEach((i) => (i.hidden = true));
    editNote.hidden = true;
    drawerActions.hidden = true;
    editToggleBtn.hidden = false;
    drawerError.hidden = true;
  }

  editToggleBtn.addEventListener("click", enterEdit);
  editCancelBtn.addEventListener("click", () => { populateDrawer(); exitEdit(); });

  profileForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    drawerError.hidden = true;

    if (!profileForm.reportValidity()) return;

    const fd = new FormData(profileForm);
    const day = fd.get("pickup_day");
    const time = fd.get("pickup_time");
    const profilePayload = {
      full_name: fd.get("full_name").trim(),
      phone: fd.get("phone").trim(),
      address: fd.get("address").trim(),
      payment_method: fd.get("payment_method"),
    };

    const saveBtn = profileForm.querySelector(".btn-create");
    saveBtn.disabled = true;
    try {
      if (day !== (user.pickup_day || "") || time !== (user.pickup_time || "")) {
        const r1 = await postJSON("update-preference.php", { pickup_day: day || null, pickup_time: time || null, csrf_token: csrfToken });
        if (!r1.ok) throw new Error(r1.data.error || "Could not update pickup preferences.");
      }

      const changed =
        profilePayload.full_name !== user.name ||
        profilePayload.phone !== user.phone ||
        profilePayload.address !== user.address ||
        profilePayload.payment_method !== user.payment_method;

      if (changed) {
        const r2 = await postJSON("profile-update.php", Object.assign(profilePayload, { csrf_token: csrfToken }));
        if (!r2.ok) throw new Error(r2.data.error || "Could not save your changes.");
      }

      await refresh();
      populateDrawer();
      exitEdit();
    } catch (err) {
      drawerError.textContent = err.message;
      drawerError.hidden = false;
    } finally {
      saveBtn.disabled = false;
    }
  });

  function openDrawer() {
    populateDrawer();
    exitEdit();
    overlay.hidden = false;
    requestAnimationFrame(() => drawer.classList.add("is-open"));
  }

  function closeDrawer() {
    drawer.classList.remove("is-open");
    setTimeout(() => { overlay.hidden = true; }, 280);
  }

  $("drawerClose").addEventListener("click", closeDrawer);
  overlay.addEventListener("click", (e) => { if (e.target === overlay) closeDrawer(); });
  $("signOutBtn").addEventListener("click", doLogout);

  // ---------- public API ----------
  const ready = Promise.all([refresh(), loadCsrf()]).then(() => { guardBookingLinks(); });

  window.AshAuth = {
    ready,
    getUser: () => user,
    // Runs onSuccess now if already logged in; otherwise opens the dialog
    // and runs it once login/registration succeeds.
    requireAuth(onSuccess) {
      if (user) {
        onSuccess(user);
        return true;
      }
      pendingCallback = onSuccess;
      openDialog("login");
      return false;
    },
    openLogin: () => openDialog("login"),
  };
})();