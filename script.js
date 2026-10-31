/* =====================================================================
   ASH LAUNDROMAT - script.js

   This file is organized in two halves:

     1. ANIMATIONS  - everything purely visual (shine effects, scroll
                       reveal, navbar scroll, floating bubbles, entrance
                       animation, hover lifts, banner decoration).
                       None of this talks to the database.

     2. FUNCTIONALITY - everything that reads real data from the PHP/
                         MySQL (XAMPP) backend: Track Order, Order
                         History, Customer Reviews.

   Keeping them separate makes it easy to find "why does X move like
   that" vs. "where does X's data come from".
   ===================================================================== */



/* =====================================================================
   PART 1: ANIMATIONS
   ===================================================================== */

// Check if the visitor prefers reduced motion
const reducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)"
).matches;


// ===== 1. SHINING BOOK NOW & BOOK A PICKUP BUTTONS =====

const bookButton = document.querySelector(".nav-book");
const pickupButton = document.querySelector(".dash-primary-b");

if (!reducedMotion) {

    // Create animation styles using JavaScript
    const animationStyle = document.createElement("style");

    animationStyle.textContent = `

        /* ===== NAVBAR BOOK NOW SHINE ===== */

        .nav-menu .nav-book.ash-shine {
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .nav-menu .nav-book.ash-shine::after {
            content: "";
            position: absolute;
            inset: 0;

            background: linear-gradient(
                110deg,
                transparent 30%,
                rgba(255, 255, 255, 0.08) 43%,
                rgba(255, 255, 255, 0.75) 50%,
                rgba(255, 255, 255, 0.08) 57%,
                transparent 70%
            );

            transform: translateX(-150%);
            animation: bookShine 6s ease-in-out infinite;

            pointer-events: none;
        }

        @keyframes bookShine {

            0%, 45% {
                transform: translateX(-150%);
            }

            90%, 100% {
                transform: translateX(150%);
            }

        }


        /* ===== DASHBOARD BOOK A PICKUP SHINE ===== */

        .dashboard .dash-primary-b.pickup-shine {
            position: relative;
            overflow: hidden;
            isolation: isolate;

            animation: pickupGlow 4s ease-in-out infinite;
        }

        /* White shine passing across the yellow button */
        .dashboard .dash-primary-b.pickup-shine::after {
            content: "";
            position: absolute;
            inset: 0;

            background: linear-gradient(
                110deg,
                transparent 30%,
                rgba(255, 255, 255, 0.05) 42%,
                rgba(255, 255, 255, 0.8) 50%,
                rgba(255, 255, 255, 0.05) 58%,
                transparent 70%
            );

            transform: translateX(-150%);

            animation: pickupShine 6s ease-in-out infinite;
            animation-delay: 1s;

            pointer-events: none;
        }

        @keyframes pickupShine {

            0%, 45% {
                transform: translateX(-150%);
            }

            90%, 100% {
                transform: translateX(150%);
            }

        }

        /* Soft yellow glow around Book a Pickup */
        @keyframes pickupGlow {

            0%, 100% {
                box-shadow:
                    0 2px 6px rgba(0, 0, 0, 0.1);
            }

            50% {
                box-shadow:
                    0 0 20px rgba(255, 209, 102, 0.8),
                    0 4px 12px rgba(0, 59, 115, 0.08);
            }

        }

        /* Pause the glow when hovering over the button */
        .dashboard .dash-primary-b.pickup-shine:hover {
            animation-play-state: paused;
        }


        /* ===== ASH CARDS HOVER ANIMATION ===== */

        .highlights-card {
            transition:
                transform 0.3s ease,
                box-shadow 0.3s ease;
        }

        .highlights-card:hover {
            transform: translateY(-6px);

            box-shadow:
                0 12px 35px rgba(0, 59, 115, 0.12);
        }


        /* ===== SERVICE BENEFITS HOVER ANIMATION ===== */

        .benefit-card {
            transition:
                transform 0.3s ease,
                box-shadow 0.3s ease;
        }

        .benefit-card:hover {
            transform: translateY(-4px);

            box-shadow:
                0 6px 20px rgba(0, 59, 115, 0.08);
        }


        /* ===== DASHBOARD FLOATING BUBBLES ===== */

        .ash-ambient {
            position: absolute;
            inset: 0;

            z-index: 2;

            overflow: hidden;
            pointer-events: none;

            transform: translate3d(
                var(--ash-x, 0px),
                var(--ash-y, 0px),
                0
            );

            transition: transform 0.6s ease-out;
        }

        .ash-bubble {
            position: absolute;
            bottom: -90px;

            border-radius: 50%;

            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.65);

            box-shadow:
                inset 0 0 12px rgba(255, 255, 255, 0.25),
                0 0 8px rgba(0, 168, 232, 0.12);

            animation: ashFloat linear infinite;

            pointer-events: none;
        }

        @keyframes ashFloat {

            0% {
                transform: translateY(0) scale(0.85);
                opacity: 0;
            }

            10% {
                opacity: 0.65;
            }

            85% {
                opacity: 0.65;
            }

            100% {
                transform: translateY(-115vh) scale(1.15);
                opacity: 0;
            }

        }

        /* Show fewer bubbles on small screens */
        @media (max-width: 700px) {

            .ash-bubble:nth-child(n+5) {
                display: none;
            }

        }

    `;

    document.head.appendChild(animationStyle);


    // Apply the shining effect to both buttons
    if (bookButton) {
        bookButton.classList.add("ash-shine");
    }

    if (pickupButton) {
        pickupButton.classList.add("pickup-shine");
    }

}



// ===== 2. SCROLL REVEAL ANIMATION (dashboard / about section) =====

const animatedElements = document.querySelectorAll(

    ".about-heading, " +
    ".highlights-card, " +
    ".benefit-card, " +
    ".guide-heading, " +
    ".booking-step"

);

if (
    !reducedMotion &&
    "IntersectionObserver" in window &&
    typeof Element.prototype.animate === "function"
) {

    const scrollObserver = new IntersectionObserver((entries, observer) => {

        entries.forEach(entry => {

            if (entry.isIntersecting) {

                const element = entry.target;

                // Delay cards so they appear one after another
                let delay = 0;

                if (element.matches(
                    ".highlights-card, .benefit-card, .booking-step"
                )) {

                    const index = Array.from(
                        element.parentElement.children
                    ).indexOf(element);

                    delay = index * 120;

                }


                // Fade in and move slightly upward
                element.animate(

                    [
                        {
                            opacity: 0,
                            transform: "translateY(25px)"
                        },

                        {
                            opacity: 1,
                            transform: "translateY(0)"
                        }
                    ],

                    {
                        duration: 700,
                        delay: delay,
                        easing: "ease-out",
                        fill: "backwards"
                    }

                );

                // Animate each element only once
                observer.unobserve(element);

            }

        });

    }, {
        threshold: 0.1
    });


    animatedElements.forEach(element => {

        scrollObserver.observe(element);

    });

}



// ===== 3. NAVIGATION BAR SCROLL ANIMATION =====

const navbar = document.querySelector(".navbar");

if (navbar) {

    const navbarStyle = document.createElement("style");

    navbarStyle.textContent = `

        .navbar {
            transition:
                background-color 0.3s ease,
                box-shadow 0.3s ease;
        }

        .navbar.ash-scrolled {
            background-color: rgba(255, 255, 255, 0.95);

            box-shadow:
                0 2px 12px rgba(0, 59, 115, 0.08);
        }

    `;

    document.head.appendChild(navbarStyle);


    function updateNavbar() {

        navbar.classList.toggle(
            "ash-scrolled",
            window.scrollY > 50
        );

    }


    window.addEventListener("scroll", updateNavbar, {
        passive: true
    });

    updateNavbar();

}



// ===== 4. DASHBOARD FLOATING BUBBLES (+ follow the mouse) =====

const dashboard = document.querySelector(".dashboard");

if (dashboard && !reducedMotion) {

    // Create a container for decorative bubbles
    const bubbleContainer = document.createElement("div");

    bubbleContainer.className = "ash-ambient";

    bubbleContainer.setAttribute("aria-hidden", "true");


    // Bubble size, position, speed, and starting time
    const bubbles = [

        { size: 25, left: 8, duration: 13, delay: -2 },

        { size: 42, left: 22, duration: 17, delay: -7 },

        { size: 20, left: 37, duration: 12, delay: -4 },

        { size: 48, left: 53, duration: 19, delay: -9 },

        { size: 30, left: 66, duration: 15, delay: -5 },

        { size: 55, left: 78, duration: 21, delay: -11 },

        { size: 22, left: 89, duration: 14, delay: -3 },

        { size: 36, left: 96, duration: 18, delay: -8 }

    ];


    // Create each bubble
    bubbles.forEach(bubble => {

        const element = document.createElement("span");

        element.className = "ash-bubble";

        element.style.width = bubble.size + "px";
        element.style.height = bubble.size + "px";

        element.style.left = bubble.left + "%";

        element.style.animationDuration =
            bubble.duration + "s";

        element.style.animationDelay =
            bubble.delay + "s";

        bubbleContainer.appendChild(element);

    });


    // Add bubbles inside your existing dashboard
    dashboard.appendChild(bubbleContainer);


    // Bubbles follow mouse movement
    dashboard.addEventListener("pointermove", event => {

        // Ignore touch movement
        if (
            event.pointerType !== "mouse" &&
            event.pointerType !== "pen"
        ) {
            return;
        }


        // Get dashboard dimensions and position
        const rect = dashboard.getBoundingClientRect();


        // Get mouse position within the dashboard
        const mouseX = event.clientX - rect.left;
        const mouseY = event.clientY - rect.top;


        // Convert mouse position to values from -0.5 to 0.5
        const x = (mouseX / rect.width) - 0.5;
        const y = (mouseY / rect.height) - 0.5;


        // Move bubbles slightly with the mouse
        bubbleContainer.style.setProperty(
            "--ash-x",
            (x * 18) + "px"
        );

        bubbleContainer.style.setProperty(
            "--ash-y",
            (y * 12) + "px"
        );

    });


    // Return the bubbles to their original position
    dashboard.addEventListener("pointerleave", () => {

        bubbleContainer.style.setProperty("--ash-x", "0px");

        bubbleContainer.style.setProperty("--ash-y", "0px");

    });

}



// ===== 5. DASHBOARD ENTRANCE ANIMATION =====

// Animate the homepage elements one after another when it loads.

if (
    dashboard &&
    !reducedMotion &&
    typeof Element.prototype.animate === "function"
) {

    const entranceElements = document.querySelectorAll(

        ".main-logo, " +
        ".main-content h1, " +
        ".desci, " +
        ".dashboard-actions, " +
        ".commitment"

    );


    entranceElements.forEach((element, index) => {

        element.animate(

            [
                {
                    opacity: 0,
                    transform: "translateY(25px)"
                },

                {
                    opacity: 1,
                    transform: "translateY(0)"
                }
            ],

            {
                duration: 800,

                // Each element appears after the previous one
                delay: index * 150,

                easing: "ease-out",

                fill: "backwards"
            }

        );

    });

}



// ===== 6. ANIMATIONS FOR THE REST OF THE PAGE =====
// Services, Track Order, Price List, Order History, Reviews, FAQ,
// Contact, the "Ready for fresh laundry?" banner, and the footer.
// Self-contained in its own IIFE so its local `reducedMotion` doesn't
// clash with the one declared at the top of this file.

(function () {

    const reducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)"
    ).matches;

    if (reducedMotion) return;


    // ----- 6a. Scroll reveal for the newer sections -----

    const revealSelectors = [

        ".services-heading", ".services-card", ".services-subcard", ".services-also",
        ".trackorder-heading", ".track-order-form",
        ".price-heading", ".booking-card", ".summary-card",
        ".history-heading", ".history-card",
        ".reviews-heading",
        ".faq-heading", ".faq-card",
        ".contact-info", ".contact-form",
        ".banner-heading", ".banner-actions",
        ".footer-brand", ".footer-contact"

    ].join(", ");

    const revealElements = document.querySelectorAll(revealSelectors);

    if ("IntersectionObserver" in window && typeof Element.prototype.animate === "function") {

        // Cards in a row get a slight stagger so they don't all pop at once
        const groupSelector = ".services-card, .services-subcard, .faq-card";

        const revealObserver = new IntersectionObserver((entries, observer) => {

            entries.forEach((entry) => {

                if (!entry.isIntersecting) return;

                const element = entry.target;
                let delay = 0;

                if (element.matches(groupSelector)) {

                    const index = Array.from(
                        element.parentElement.children
                    ).indexOf(element);

                    delay = index * 100;

                }

                element.animate(

                    [
                        { opacity: 0, transform: "translateY(25px)" },
                        { opacity: 1, transform: "translateY(0)" }
                    ],

                    {
                        duration: 700,
                        delay: delay,
                        easing: "ease-out",
                        fill: "backwards"
                    }

                );

                observer.unobserve(element);

            });

        }, { threshold: 0.1 });

        revealElements.forEach((element) => revealObserver.observe(element));

    }


    // ----- 6b. Reveal content that loads from the database -----

    // Order History rows and Review cards are inserted by PART 2 below,
    // after a fetch() finishes, so the observer above never sees them in
    // time. This watches those two containers and fades in whatever gets
    // added to them, whenever that happens.

    function animateNewChildren(container) {

        if (!container) return;

        const mo = new MutationObserver((mutations) => {

            mutations.forEach((mutation) => {

                mutation.addedNodes.forEach((node) => {

                    if (node.nodeType !== 1) return; // elements only

                    node.animate(

                        [
                            { opacity: 0, transform: "translateY(12px)" },
                            { opacity: 1, transform: "translateY(0)" }
                        ],

                        {
                            duration: 450,
                            easing: "ease-out",
                            fill: "backwards"
                        }

                    );

                });

            });

        });

        mo.observe(container, { childList: true });

    }

    animateNewChildren(document.getElementById("historyBody"));
    animateNewChildren(document.getElementById("reviewsGrid"));


    // ----- 6c. Soft entrance for panels that toggle [hidden] -----

    // Track Order's result/error, and Order History / Reviews' locked,
    // empty and loaded states, all just flip a "hidden" attribute on
    // and off. This gives that moment a gentle entrance instead of an
    // abrupt pop-in.

    function revealOnUnhide(ids) {

        ids.forEach((id) => {

            const el = document.getElementById(id);
            if (!el) return;

            const mo = new MutationObserver(() => {

                if (el.hidden) return;

                el.animate(

                    [
                        { opacity: 0, transform: "translateY(10px)" },
                        { opacity: 1, transform: "translateY(0)" }
                    ],

                    { duration: 500, easing: "ease-out" }

                );

            });

            mo.observe(el, { attributes: true, attributeFilter: ["hidden"] });

        });

    }

    revealOnUnhide([
        "trackorderResult", "trackstatus-Error",
        "historyLocked", "historyEmpty", "historyTableWrap",
        "reviewsSummary", "reviewsEmpty"
    ]);


    // ----- 6d. Hover lift, banner decoration, banner button shine -----

    const extraStyle = document.createElement("style");

    extraStyle.textContent = `

        /* ----- Hover lift for the newer cards ----- */

        .services-card,
        .summary-card,
        .history-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .services-card:hover,
        .summary-card:hover,
        .history-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 35px rgba(0, 59, 115, 0.12);
        }

        .services-subcard,
        .review-card,
        .faq-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .services-subcard:hover,
        .review-card:hover,
        .faq-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0, 59, 115, 0.08);
        }

        .services-tag,
        .contact-icon-circle,
        .footer-social-link {
            transition: transform 0.2s ease;
        }

        .services-tag:hover,
        .contact-icon-circle:hover,
        .footer-social-link:hover {
            transform: translateY(-2px) scale(1.05);
        }

        /* ----- Contact form: a soft focus glow instead of the plain default ----- */

        .contact-form input,
        .contact-form textarea {
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .contact-form input:focus,
        .contact-form textarea:focus {
            box-shadow: 0 0 0 3px rgba(0, 168, 232, 0.15);
        }

        /* ----- Banner decoration -----
           The three circles share one class (.banner-circle) with no
           per-circle size, so nth-child gives each one its own size,
           position, and a slow out-of-phase pulse instead of three
           identical rings stacked on top of each other. */

        .banner-circle {
            position: absolute;
            border: 4px solid #ffffff;
            border-radius: 50%;
            animation: bannerPulse 6s ease-in-out infinite;
        }

        .banner-circle:nth-child(1) {
            width: 128px; height: 128px; left: 3%; top: 11%;
        }

        .banner-circle:nth-child(2) {
            width: 192px; height: 192px; right: 3%; top: 36%;
            animation-delay: -2s;
        }

        .banner-circle:nth-child(3) {
            width: 64px; height: 64px; left: 33%; top: 50%;
            border-width: 2px;
            animation-delay: -4s;
        }

        @keyframes bannerPulse {
            0%, 100% { transform: scale(1);     opacity: 1;   }
            50%      { transform: scale(1.08);  opacity: 0.7; }
        }

        .banner-wave {
            animation: bannerDrift 14s ease-in-out infinite;
        }

        @keyframes bannerDrift {
            0%, 100% { transform: translateX(0);   }
            50%      { transform: translateX(-2%); }
        }

        /* ----- Shine for the banner's "Book a Pickup Today" button -----
           The shine style block in PART 1 already shines the dashboard's
           Book a Pickup button, but that effect is scoped to .dashboard,
           so the banner's copy (same .dash-primary-b class, different
           section) was missed. */

        .banner-section .banner-btn-primary {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            animation: bannerBtnGlow 4s ease-in-out infinite;
        }

        .banner-section .banner-btn-primary::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(
                110deg,
                transparent 30%,
                rgba(255, 255, 255, 0.05) 42%,
                rgba(255, 255, 255, 0.8) 50%,
                rgba(255, 255, 255, 0.05) 58%,
                transparent 70%
            );
            transform: translateX(-150%);
            animation: bannerBtnShine 6s ease-in-out infinite;
            animation-delay: 1s;
            pointer-events: none;
        }

        @keyframes bannerBtnShine {
            0%, 45%  { transform: translateX(-150%); }
            90%, 100% { transform: translateX(150%);  }
        }

        @keyframes bannerBtnGlow {
            0%, 100% { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1); }
            50%      { box-shadow: 0 0 20px rgba(255, 209, 102, 0.8), 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        }

        .banner-section .banner-btn-primary:hover {
            animation-play-state: paused;
        }

    `;

    document.head.appendChild(extraStyle);

})();



/* =====================================================================
   PART 2: FUNCTIONALITY
   (XAMPP: Apache + PHP + MySQL/MariaDB via phpMyAdmin)
   ===================================================================== */

