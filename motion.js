/* =====================================================================
   ASH LAUNDROMAT - motion.js

   Extra animation on top of what script.js already does. It only ADDS
   effects: nothing in style.css or script.js is changed, and removing
   the <script src="motion.js"> tag puts the site back exactly as it was.

     1. Hero heading: words rise in one by one, "simple" shimmers
     2. Hero picture (the girl) breathes and drifts, the logo floats, the wave
        layer sways; all follow the mouse a little
     3. Buttons: arrow slides on hover, press-down on click
        (also gives .btn and the nav Book Now a working hover transition)
     4. Commitment icons pop in, then wiggle one after another
     5. Click the hero to release a burst of bubbles
     6. Navbar links get a sliding underline
     7. Thin scroll-progress bar at the top of the page
     8. "Why Choose ASH" cards lift on hover, booking step numbers pop

   Everything uses transform / opacity so it stays smooth, pauses while the
   hero is off screen, and is skipped completely for visitors who have
   "reduce motion" turned on.
   ===================================================================== */

(function () {

    // Respect visitors who asked their device for less motion
    const reducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)"
    ).matches;

    if (reducedMotion) return;


    const hero = document.querySelector(".dashboard");


    // ===== STYLES (added with JavaScript, same way script.js does it) =====

    const style = document.createElement("style");

    style.id = "ash-motion-style";

    style.textContent = `

        /* ----- 1. Hero heading ----- */

        .ash-word {
            display: inline-block;
            font-style: normal;
            animation: ashWordRise 0.9s cubic-bezier(0.2, 0.8, 0.2, 1) both;
            animation-delay: calc(0.35s + var(--i, 0) * 0.14s);
        }

        @keyframes ashWordRise {
            0%   { opacity: 0; transform: translateY(0.55em) rotate(3deg); filter: blur(6px); }
            100% { opacity: 1; transform: translateY(0) rotate(0); filter: blur(0); }
        }

        /* "simple" keeps its blue, with a light that slides across it */
        .main-content h1 span.ash-word {
            background: linear-gradient(100deg, #00a8e8 25%, #35c4ff 48%, #00a8e8 70%);
            background-size: 240% 100%;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
            animation:
                ashWordRise 0.9s cubic-bezier(0.2, 0.8, 0.2, 1) both,
                ashTextShimmer 5s ease-in-out infinite;
            animation-delay:
                calc(0.35s + var(--i, 0) * 0.14s),
                1.6s;
        }

        @keyframes ashTextShimmer {
            0%, 100% { background-position: 120% 0; }
            50%      { background-position: -20% 0; }
        }


        /* ----- 2. Hero logo + wave layer ----- */

        .main-logo img {
            animation: ashLogoFloat 6.5s ease-in-out 1.4s infinite;
            translate: calc(var(--hx, 0) * 14px) calc(var(--hy, 0) * 10px);
            will-change: transform;
        }

        @keyframes ashLogoFloat {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50%      { transform: translateY(-10px) rotate(-0.6deg); }
        }

        .dashboard::after {
            transform-origin: 50% 100%;
            animation: ashWaveSway 9s ease-in-out infinite;
            translate: calc(var(--hx, 0) * -16px) calc(var(--hy, 0) * -8px);
            will-change: transform;
        }

        /* The layer is pushed slightly DOWN and never up, so its bottom edge
           can't lift off the bottom of the hero and show a gap */
        @keyframes ashWaveSway {
            0%, 100% { transform: scale(1.03) translateY(14px); }
            50%      { transform: scale(1.05) translateY(6px); }
        }

        /* The hero picture (girl + background) used to slowly breathe and drift.
           It is a copy of the hero's own background sitting just behind it,
           enlarged a little so its edges never show. */
        .ash-hero-bg {
            position: absolute;
            inset: 0;
            z-index: -1;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            pointer-events: none;
            /* The picture is kept still on purpose: only the waves move.
               (To bring the slow drift back, restore the animation + translate lines.) */
        }

        @keyframes ashHeroBreathe {
            0%, 100% { transform: scale(1.06) translate3d(0, 0, 0) rotate(0deg); }
            50%      { transform: scale(1.095) translate3d(-8px, -5px, 0) rotate(-0.25deg); }
        }

        /* ----- 2e. A second, mirrored copy of the bottom waves slides the other way,
                   so the water along the bottom rolls instead of just swaying ----- */

        /* (the second wave's picture and place are in style.css; only its movement is here) */
        .ash-wave2 {
            animation: ashWaveRoll 11s ease-in-out infinite;
            translate: calc(var(--hx, 0) * 14px) 0;
            will-change: transform;
        }

        @keyframes ashWaveRoll {
            0%, 100% { transform: scale(-1.06, 1.06) translate(-2%, 10px); }
            50%      { transform: scale(-1.06, 1.06) translate(2%, 3px); }
        }


        /* Stop the loops while the hero is scrolled out of view */
        .dashboard.ash-hero-paused .ash-wave2,
        .dashboard.ash-hero-paused .ash-hero-bg,
        .dashboard.ash-hero-paused .main-logo img,
        .dashboard.ash-hero-paused::after,
        .dashboard.ash-hero-paused .main-content h1 span.ash-word,
        .dashboard.ash-hero-paused .commitment-item img {
            animation-play-state: paused;
        }


        /* ----- 3. Buttons ----- */

        .btn,
        .nav-menu .nav-book {
            transition:
                background 0.2s ease,
                transform 0.25s cubic-bezier(0.2, 0.8, 0.2, 1),
                box-shadow 0.25s ease;
        }

        .btn:hover {
            box-shadow: 0 10px 22px rgba(0, 59, 115, 0.18);
        }

        .btn:active,
        .nav-menu .nav-book:active {
            transform: translateY(0) scale(0.96);
        }

        .btn span {
            display: inline-block;
            transition: transform 0.25s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .btn:hover span {
            transform: translateX(6px);
        }


        /* ----- 4. Commitment icons ----- */

        .commitment-item {
            animation: ashPopIn 0.7s cubic-bezier(0.3, 1.5, 0.5, 1) both;
        }

        .commitment-item:nth-child(1) { animation-delay: 1.1s; }
        .commitment-item:nth-child(2) { animation-delay: 1.25s; }
        .commitment-item:nth-child(3) { animation-delay: 1.4s; }

        @keyframes ashPopIn {
            0%   { opacity: 0; transform: translateY(14px) scale(0.85); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* A small wiggle runs through the three icons in turn */
        .commitment-item img {
            transition: transform 0.3s cubic-bezier(0.3, 1.5, 0.5, 1);
            animation: ashIconWiggle 7.5s ease-in-out infinite;
        }

        .commitment-item:nth-child(2) img { animation-delay: 2.5s; }
        .commitment-item:nth-child(3) img { animation-delay: 5s; }

        @keyframes ashIconWiggle {
            0%, 8%, 100% { rotate: 0deg; }
            2%           { rotate: -14deg; }
            4%           { rotate: 12deg; }
            6%           { rotate: -6deg; }
        }

        .commitment-item:hover img {
            transform: scale(1.3);
        }


        /* ----- 5. Click bubbles ----- */

        .ash-pop-layer {
            position: absolute;
            inset: 0;
            z-index: 4;
            overflow: hidden;
            pointer-events: none;
        }

        .ash-pop {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.25);
            border: 1.5px solid rgba(255, 255, 255, 0.85);
            box-shadow:
                inset 0 0 10px rgba(255, 255, 255, 0.4),
                0 0 8px rgba(0, 168, 232, 0.25);
        }


        /* ----- 6. Navbar underline ----- */

        .nav-menu a:not(.nav-book) {
            position: relative;
        }

        .nav-menu a:not(.nav-book)::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: -6px;
            height: 3px;
            border-radius: 3px;
            background: var(--secondary, #00a8e8);
            transform: scaleX(0);
            transform-origin: left center;
            transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .nav-menu a:not(.nav-book):hover::after,
        .nav-menu a.active:not(.nav-book)::after {
            transform: scaleX(1);
        }


        /* ----- 7. Scroll progress bar ----- */

        .ash-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            z-index: 2000;
            background: linear-gradient(90deg, var(--secondary, #00a8e8), var(--yellow, #ffd166));
            transform: scaleX(0);
            transform-origin: 0 50%;
            pointer-events: none;
        }


        /* ----- 8. Card hovers (Why Choose ASH + booking steps) ----- */

        .highlights-card,
        .benefit-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .highlights-card:hover,
        .benefit-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 30px rgba(0, 59, 115, 0.12);
        }

        .highlights-card img {
            transition: transform 0.4s cubic-bezier(0.3, 1.5, 0.5, 1);
        }

        .highlights-card:hover img {
            transform: scale(1.07) rotate(-2deg);
        }

        .booking-step span {
            transition: transform 0.3s cubic-bezier(0.3, 1.5, 0.5, 1);
        }

        .booking-step:hover span {
            transform: scale(1.15) rotate(-6deg);
        }

    `;

    document.head.appendChild(style);


    // ===== 1. HERO HEADING: split into words so they can rise one by one =====

    const heading = document.querySelector(".main-content h1");

    if (heading && !heading.dataset.ashSplit) {

        heading.dataset.ashSplit = "1";

        let wordIndex = 0;

        // <i> (not <span>) so the heading's own "h1 span" colour rule only
        // touches the word "simple"
        const makeWord = text => {

            const word = document.createElement("i");

            word.className = "ash-word";
            word.textContent = text;
            word.style.setProperty("--i", wordIndex++);

            return word;

        };

        Array.from(heading.childNodes).forEach(node => {

            if (node.nodeType === 3) {

                // Plain text: one animated word per word, spaces kept as they are
                const fragment = document.createDocumentFragment();

                node.textContent.split(/(\s+)/).forEach(part => {

                    if (!part) return;

                    if (/^\s+$/.test(part)) {
                        fragment.appendChild(document.createTextNode(part));
                    } else {
                        fragment.appendChild(makeWord(part));
                    }

                });

                node.replaceWith(fragment);

            } else if (node.nodeType === 1) {

                // The existing <span>simple</span>: its leading space moves
                // outside so it doesn't vanish once the word is inline-block
                const first = node.firstChild;

                if (first && first.nodeType === 3 && /^\s/.test(first.textContent)) {
                    node.before(document.createTextNode(" "));
                    first.textContent = first.textContent.replace(/^\s+/, "");
                }

                node.classList.add("ash-word");
                node.style.setProperty("--i", wordIndex++);

            }

        });

    }


    if (hero) {

        // ===== 2a. HERO PICTURE: a moving copy of the hero background =====
        // The girl is part of one flat background image, so the whole picture
        // moves together. The original background stays underneath, so if
        // anything here fails the hero simply looks as it did before.

        const heroPicture = getComputedStyle(hero).backgroundImage;

        if (heroPicture && heroPicture !== "none") {

            const pictureLayer = document.createElement("div");

            pictureLayer.className = "ash-hero-bg";
            pictureLayer.setAttribute("aria-hidden", "true");
            pictureLayer.style.backgroundImage = heroPicture;

            hero.insertBefore(pictureLayer, hero.firstChild);

        }


        // ===== 2. HERO: logo + wave layer follow the mouse a little =====

        let targetX = 0;
        let targetY = 0;
        let currentX = 0;
        let currentY = 0;
        let frame = 0;

        function glide() {

            // Ease towards the target so movement is smooth, never jumpy
            currentX += (targetX - currentX) * 0.08;
            currentY += (targetY - currentY) * 0.08;

            hero.style.setProperty("--hx", currentX.toFixed(4));
            hero.style.setProperty("--hy", currentY.toFixed(4));

            const settled =
                Math.abs(targetX - currentX) < 0.001 &&
                Math.abs(targetY - currentY) < 0.001;

            frame = settled ? 0 : requestAnimationFrame(glide);

        }

        hero.addEventListener("pointermove", event => {

            // Mouse and pen only, not touch
            if (event.pointerType === "touch") return;

            const rect = hero.getBoundingClientRect();

            targetX = (event.clientX - rect.left) / rect.width - 0.5;
            targetY = (event.clientY - rect.top) / rect.height - 0.5;

            if (!frame) frame = requestAnimationFrame(glide);

        });

        hero.addEventListener("pointerleave", () => {

            targetX = 0;
            targetY = 0;

            if (!frame) frame = requestAnimationFrame(glide);

        });


        // Pause the looping animations while the hero is off screen
        if ("IntersectionObserver" in window) {

            new IntersectionObserver(entries => {

                hero.classList.toggle("ash-hero-paused", !entries[0].isIntersecting);

            }).observe(hero);

        }


        // ===== 5. CLICK THE HERO: BUBBLES POP UP =====

        if (typeof Element.prototype.animate === "function") {

            const popLayer = document.createElement("div");

            popLayer.className = "ash-pop-layer";
            popLayer.setAttribute("aria-hidden", "true");

            hero.appendChild(popLayer);

            hero.addEventListener("pointerdown", event => {

                // Not when pressing a link, button or form field
                if (event.target.closest("a, button, input, select, textarea, label")) return;

                // Never pile up too many at once
                if (popLayer.children.length > 40) return;

                const rect = hero.getBoundingClientRect();

                for (let i = 0; i < 7; i++) {

                    const size = 10 + Math.random() * 26;
                    const bubble = document.createElement("span");

                    bubble.className = "ash-pop";
                    bubble.style.width = size + "px";
                    bubble.style.height = size + "px";
                    bubble.style.left = (event.clientX - rect.left - size / 2) + "px";
                    bubble.style.top = (event.clientY - rect.top - size / 2) + "px";

                    popLayer.appendChild(bubble);

                    const driftX = (Math.random() - 0.5) * 110;
                    const riseY = -(70 + Math.random() * 130);

                    const animation = bubble.animate(
                        [
                            { opacity: 0.95, transform: "translate(0, 0) scale(0.4)" },
                            { opacity: 0.9, transform: "translate(" + (driftX * 0.6) + "px, " + (riseY * 0.6) + "px) scale(1)", offset: 0.6 },
                            { opacity: 0, transform: "translate(" + driftX + "px, " + riseY + "px) scale(1.25)" }
                        ],
                        {
                            duration: 900 + Math.random() * 700,
                            delay: i * 40,
                            easing: "ease-out",
                            fill: "backwards"
                        }
                    );

                    animation.onfinish = () => bubble.remove();

                }

            });

        }

    }


    // ===== 7. SCROLL PROGRESS BAR =====

    const progress = document.createElement("div");

    progress.className = "ash-progress";
    progress.setAttribute("aria-hidden", "true");

    document.body.appendChild(progress);

    let progressQueued = false;

    function updateProgress() {

        const scrollable =
            document.documentElement.scrollHeight - window.innerHeight;

        const amount = scrollable > 0
            ? Math.min(1, Math.max(0, window.scrollY / scrollable))
            : 0;

        progress.style.transform = "scaleX(" + amount.toFixed(4) + ")";

        progressQueued = false;

    }

    window.addEventListener("scroll", () => {

        if (!progressQueued) {
            progressQueued = true;
            requestAnimationFrame(updateProgress);
        }

    }, { passive: true });

    updateProgress();

})();