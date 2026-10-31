// ===== mobile.js: phone menu button (opens / closes the navbar links) =====
// Only adds behaviour. It does not change any other script.

(function () {

    const toggle = document.querySelector(".nav-toggle");
    const menu = document.querySelector(".nav-menu");

    if (!toggle || !menu) return;

    if (!menu.id) menu.id = "navMenu";
    toggle.setAttribute("aria-controls", menu.id);

    function setOpen(open) {

        menu.classList.toggle("is-open", open);
        toggle.classList.toggle("is-open", open);
        toggle.setAttribute("aria-expanded", open ? "true" : "false");
        toggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");

    }

    toggle.addEventListener("click", function (event) {

        event.stopPropagation();
        setOpen(!menu.classList.contains("is-open"));

    });

    // Choosing a link (or Book Now) closes the menu. Buttons inside it, such as
    // the profile gear and Logout, keep working and close it too.
    menu.addEventListener("click", function (event) {

        if (event.target.closest("a, button")) setOpen(false);

    });

    // Tapping anywhere else closes it
    document.addEventListener("click", function (event) {

        if (!menu.contains(event.target) && !toggle.contains(event.target)) setOpen(false);

    });

    document.addEventListener("keydown", function (event) {

        if (event.key === "Escape") setOpen(false);

    });

    // Back to a wide screen: forget the open state
    window.addEventListener("resize", function () {

        if (window.innerWidth > 900) setOpen(false);

    });

})();