/* =====================================================================
   ASH LAUNDROMAT - admin/admin-motion.js

   Extra life for the admin panel. It only ADDS effects: the layout,
   colours and every existing rule in admin-panel.css stay as they are,
   and removing the <script> line in sidebar.php brings back the plain look.

     1. Page entrance: header drops in, cards / tables rise in one by one
     2. Numbers on the Dashboard count up
     3. Sidebar: links slide and icons tilt on hover
     4. Cards, stat tiles and pipeline boxes lift on hover
     5. Table rows highlight on hover
     6. Buttons, tabs and inputs: smooth press / hover / focus glow
     7. Pending reviews and the "Unpaid" stat gently pulse
     8. Messages slide in (errors shake once)
     9. Create Order: the estimated total pops when it changes

   Skipped completely for anyone who has "reduce motion" turned on.
   ===================================================================== */

(function () {

    const reducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)"
    ).matches;

    if (reducedMotion) return;


    const all = (selector, root) =>
        Array.from((root || document).querySelectorAll(selector));


    // ===== STYLES =====

    const style = document.createElement("style");

    style.id = "ash-admin-motion-style";

    style.textContent = `

        /* ----- 1. Entrance (they only play once, when the page opens) ----- */

        .ap-header {
            animation: ashAdminDrop 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) backwards;
        }

        .ash-in {
            animation: ashAdminRise 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) backwards;
            animation-delay: calc(0.1s + var(--i, 0) * 0.09s);
        }

        .ash-pop {
            animation: ashAdminPop 0.55s cubic-bezier(0.3, 1.4, 0.5, 1) backwards;
            animation-delay: calc(0.2s + var(--i, 0) * 0.08s);
        }

        .ash-row {
            animation: ashAdminRow 0.45s ease-out backwards;
            animation-delay: calc(0.3s + var(--i, 0) * 0.035s);
        }

        @keyframes ashAdminDrop {
            0%   { opacity: 0; transform: translateY(-14px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        @keyframes ashAdminRise {
            0%   { opacity: 0; transform: translateY(22px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        @keyframes ashAdminPop {
            0%   { opacity: 0; transform: translateY(16px) scale(0.92); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes ashAdminRow {
            0%   { opacity: 0; transform: translateX(-12px); }
            100% { opacity: 1; transform: translateX(0); }
        }


        /* ----- 3. Sidebar ----- */

        .ap-nav a {
            transition:
                background 0.2s ease,
                color 0.2s ease,
                transform 0.25s cubic-bezier(0.2, 0.8, 0.2, 1),
                box-shadow 0.25s ease;
        }

        .ap-nav a:not(.is-active):hover {
            color: #ffffff;
            transform: translateX(4px);
        }

        .ap-nav a.is-active {
            box-shadow: 0 6px 16px rgba(0, 168, 232, 0.35);
        }

        .ap-nav a .ap-ico {
            transition: transform 0.3s cubic-bezier(0.3, 1.5, 0.5, 1);
        }

        .ap-nav a:hover .ap-ico {
            transform: scale(1.18) rotate(-8deg);
        }

        .ap-brand img {
            transition: transform 0.4s cubic-bezier(0.3, 1.5, 0.5, 1);
        }

        .ap-brand:hover img {
            transform: scale(1.05) rotate(-2deg);
        }


        /* ----- 4. Cards ----- */

        .ap-stat,
        .ap-pipe {
            transition:
                transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1),
                box-shadow 0.3s ease,
                border-color 0.3s ease;
        }

        .ap-stat:hover {
            transform: translateY(-5px);
            box-shadow: 0 14px 28px rgba(0, 59, 115, 0.13);
        }

        .ap-pipe:hover {
            transform: translateY(-4px);
            border-color: #bfe3f6;
            box-shadow: 0 10px 22px rgba(0, 59, 115, 0.1);
        }

        .ap-stat-ico {
            transition: transform 0.35s cubic-bezier(0.3, 1.5, 0.5, 1);
        }

        .ap-stat:hover .ap-stat-ico {
            transform: scale(1.18) rotate(-8deg);
        }


        /* ----- 5. Table rows ----- */

        .ap-table tbody td {
            transition: background 0.2s ease;
        }

        .ap-table tbody tr:hover td:not(.ap-empty) {
            background: #f3f9fd;
        }


        /* ----- 6. Buttons, tabs, inputs ----- */

        .ap-btn,
        .ap-btn-solid,
        .ap-chip,
        .ap-tab {
            transition:
                transform 0.18s cubic-bezier(0.2, 0.8, 0.2, 1),
                filter 0.18s ease,
                background 0.2s ease,
                box-shadow 0.2s ease;
        }

        .ap-btn:hover,
        .ap-btn-solid:hover,
        .ap-chip:hover,
        .ap-tab:hover {
            transform: translateY(-2px);
        }

        .ap-btn:active,
        .ap-btn-solid:active,
        .ap-chip:active,
        .ap-tab:active {
            transform: translateY(0) scale(0.95);
        }

        .ap-btn svg {
            transition: transform 0.2s ease;
        }

        .ap-btn:hover svg {
            transform: translateX(2px);
        }

        .ap-field input,
        .ap-field select,
        .ap-item-row input,
        .ap-item-row select,
        .ap-search input {
            transition:
                border-color 0.2s ease,
                background 0.2s ease,
                box-shadow 0.2s ease;
        }

        .ap-field input:focus,
        .ap-field select:focus,
        .ap-item-row input:focus,
        .ap-item-row select:focus,
        .ap-search input:focus {
            box-shadow: 0 0 0 4px rgba(0, 168, 232, 0.16);
        }


        /* ----- 7. Gentle attention pulse ----- */

        .ap-badge-pending {
            animation: ashPulseAmber 2.4s ease-out infinite;
        }

        .ap-tone-red {
            animation: ashPulseRed 2.6s ease-out infinite;
        }

        @keyframes ashPulseAmber {
            0%   { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0.45); }
            70%  { box-shadow: 0 0 0 7px rgba(234, 179, 8, 0); }
            100% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0); }
        }

        @keyframes ashPulseRed {
            0%   { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.35); }
            70%  { box-shadow: 0 0 0 9px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }


        /* ----- 8. Messages ----- */

        .ap-alert,
        .ap-lookup:not([hidden]) {
            animation: ashAdminDrop 0.4s cubic-bezier(0.2, 0.8, 0.2, 1) backwards;
        }

        .ap-alert-error {
            animation:
                ashAdminDrop 0.4s cubic-bezier(0.2, 0.8, 0.2, 1) backwards,
                ashAdminShake 0.5s ease 0.4s;
        }

        @keyframes ashAdminShake {
            0%, 100% { transform: translateX(0); }
            20%      { transform: translateX(-6px); }
            40%      { transform: translateX(5px); }
            60%      { transform: translateX(-4px); }
            80%      { transform: translateX(3px); }
        }


        /* ----- 9. Estimated total pops when it changes ----- */

        .ash-bump {
            display: inline-block;
            transform-origin: left center;
            animation: ashBump 0.35s ease;
        }

        @keyframes ashBump {
            0%   { transform: scale(1); }
            40%  { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

    `;

    document.head.appendChild(style);


    // ===== 1. ENTRANCE: give each block its place in the order =====

    // The stat tiles and pipeline boxes pop in on their own, so their
    // wrappers are left still.
    all(".ap-content > *").forEach((element, index) => {

        if (element.classList.contains("ap-stats")) return;

        element.classList.add("ash-in");
        element.style.setProperty("--i", index);

    });

    all(".ap-stats .ap-stat").forEach((element, index) => {

        element.classList.add("ash-pop");
        element.style.setProperty("--i", index);

    });

    all(".ap-pipe").forEach((element, index) => {

        element.classList.add("ash-pop");
        element.style.setProperty("--i", index + 3);

    });

    // Only the first rows animate, so a long table doesn't take long to appear
    all(".ap-table tbody tr").slice(0, 14).forEach((row, index) => {

        row.classList.add("ash-row");
        row.style.setProperty("--i", index);

    });


    // ===== 2. NUMBERS COUNT UP =====

    function countUp(element) {

        const original = element.textContent.trim();

        // "12", "₱750", "₱1,250.50": text before, the number, text after
        const match = original.match(/^([^\d-]*)(-?[\d,]*\.?\d+)(.*)$/);

        if (!match) return;

        const target = parseFloat(match[2].replace(/,/g, ""));

        if (!isFinite(target) || target === 0) return;

        const decimals = (match[2].split(".")[1] || "").length;
        const useCommas = match[2].indexOf(",") !== -1;

        const format = value => {

            let number = value.toFixed(decimals);

            if (useCommas) {
                number = Number(number).toLocaleString("en-US", {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                });
            }

            return match[1] + number + match[3];

        };

        const duration = 900;
        const delay = 350;
        let start = null;

        element.textContent = format(0);

        function step(time) {

            if (start === null) start = time;

            const progress = Math.min(1, (time - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);

            element.textContent = progress < 1 ? format(target * eased) : original;

            if (progress < 1) requestAnimationFrame(step);

        }

        setTimeout(() => requestAnimationFrame(step), delay);

    }

    all(".ap-stat-num, .ap-pipe-num").forEach(countUp);


    // ===== 9. CREATE ORDER: the estimated total pops when it changes =====

    const totalNumber = document.getElementById("totalNum");

    if (totalNumber) {

        let lastTotal = totalNumber.textContent;

        new MutationObserver(() => {

            if (totalNumber.textContent === lastTotal) return;

            lastTotal = totalNumber.textContent;

            totalNumber.classList.remove("ash-bump");

            // Reading this makes the browser restart the animation
            void totalNumber.offsetWidth;

            totalNumber.classList.add("ash-bump");

        }).observe(totalNumber, {
            childList: true,
            characterData: true,
            subtree: true
        });

    }

})();