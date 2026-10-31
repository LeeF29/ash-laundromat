// ===== Contact Us form =====
// Sends the message to api/contact.php (saved in MySQL, read by the admin in admin/messages.php).
// Needs account.js to load first only for the small convenience of filling in the
// name and email of a logged-in customer; the form works fine without it.

(function () {
  "use strict";

  const API = "api/";

  const form = document.getElementById("contactForm");
  if (!form) return;

  const nameInput    = document.getElementById("contactName");
  const emailInput   = document.getElementById("contactEmail");
  const concernInput = document.getElementById("contactConcern");
  const messageInput = document.getElementById("contactMessage");
  const trapInput    = document.getElementById("contactWebsite");   // hidden spam trap
  const button       = form.querySelector(".btn-send");
  const statusBox    = document.getElementById("contactStatus");

  const BUTTON_TEXT = button ? button.textContent : "Send Message";
  let sending = false;


  // ----- Small helpers -----

  function showStatus(text, kind) {
    if (!statusBox) return;
    statusBox.textContent = text || "";
    statusBox.className = "contact-status" + (kind ? " is-" + kind : "");
  }

  // Returns { field, message } for the first problem, or null when everything is fine.
  // The server checks all of this again; this just saves a trip and points at the field.
  function findProblem(values) {
    if (values.name.length < 2) {
      return { field: nameInput, message: "Please enter your full name." };
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email)) {
      return { field: emailInput, message: "Please enter a valid email address." };
    }
    if (!values.concern) {
      return { field: concernInput, message: "Please choose a type of concern." };
    }
    if (values.message.length < 10) {
      return { field: messageInput, message: "Please write a little more so we can help (at least 10 characters)." };
    }
    if (values.message.length > 1000) {
      return { field: messageInput, message: "Your message is too long (1000 characters maximum)." };
    }
    return null;
  }

  async function getCsrfToken() {
    // Fetched at the moment of sending so it always belongs to the current session
    const res = await fetch(API + "csrf-token.php", { credentials: "same-origin" });
    const data = await res.json();
    return data.csrf_token || "";
  }


  // ----- Sending -----

  form.addEventListener("submit", async function (event) {
    event.preventDefault();

    if (sending) return;

    const values = {
      name:    nameInput.value.trim(),
      email:   emailInput.value.trim(),
      concern: concernInput.value,
      message: messageInput.value.trim()
    };

    const problem = findProblem(values);

    if (problem) {
      showStatus(problem.message, "error");
      problem.field.focus();
      return;
    }

    sending = true;
    button.disabled = true;
    button.textContent = "Sending...";
    showStatus("");

    try {

      const token = await getCsrfToken();

      const res = await fetch(API + "contact.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          csrf_token: token,
          name:       values.name,
          email:      values.email,
          concern:    values.concern,
          message:    values.message,
          website:    trapInput ? trapInput.value : ""
        })
      });

      let data = null;
      try { data = await res.json(); } catch (e) { data = null; }

      if (res.ok && data && data.success) {

        form.reset();
        fillFromAccount(window.AshAuth ? window.AshAuth.getUser() : null);
        showStatus("Thank you! Your message has been sent. We'll reply to your email soon.", "ok");

      } else {

        showStatus((data && data.error) || "Sorry, something went wrong. Please try again.", "error");

      }

    } catch (e) {

      showStatus("We couldn't reach the server. Please check your connection and try again.", "error");

    } finally {

      sending = false;
      button.disabled = false;
      button.textContent = BUTTON_TEXT;

    }
  });

  // Typing again clears an old error message
  form.addEventListener("input", function () {
    if (statusBox && statusBox.classList.contains("is-error")) showStatus("");
  });


  // ----- Logged-in customers: name and email filled in for them -----

  let filled = { name: "", email: "" };

  function fillFromAccount(user) {

    if (!user) return;

    // Never overwrite something the visitor already typed
    if (!nameInput.value && user.name) {
      nameInput.value = user.name;
      filled.name = user.name;
    }

    if (!emailInput.value && user.email) {
      emailInput.value = user.email;
      filled.email = user.email;
    }

  }

  function clearAccountFill() {

    // After logout, remove only what we filled in ourselves
    if (filled.name && nameInput.value === filled.name) nameInput.value = "";
    if (filled.email && emailInput.value === filled.email) emailInput.value = "";

    filled = { name: "", email: "" };

  }

  if (window.AshAuth && window.AshAuth.ready) {
    window.AshAuth.ready.then(function () { fillFromAccount(window.AshAuth.getUser()); });
  }

  document.addEventListener("ashauth:change", function (event) {

    const user = event.detail ? event.detail.user : null;

    if (user) fillFromAccount(user);
    else clearAccountFill();

  });

})();