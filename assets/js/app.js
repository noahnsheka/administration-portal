document.addEventListener("DOMContentLoaded", () => {
  const year = document.querySelector("[data-year]");
  if (year) {
    year.textContent = new Date().getFullYear();
  }

  const initializeLoginForm = () => {
    const loginForm = document.querySelector("#loginForm");
    if (!loginForm || loginForm.dataset.bound === "true") {
      return;
    }

    loginForm.dataset.bound = "true";
    const accountInput = document.querySelector("#studentId");
    const pinInput = document.querySelector("#pin");
    const roleInput = document.querySelector("#role");
    const loginAlert = document.querySelector("#loginAlert");
    const loginButton = document.querySelector("#loginButton");

    loginForm.addEventListener("submit", async (event) => {
      event.preventDefault();

      const payload = {
        accountNumber: accountInput ? accountInput.value.trim() : "",
        pin: pinInput ? pinInput.value.trim() : "",
        role: roleInput ? roleInput.value : "",
      };

      if (!payload.accountNumber || !payload.pin || !payload.role) {
        if (loginAlert) {
          loginAlert.textContent = "Please fill in account number, PIN, and role.";
          loginAlert.classList.remove("d-none");
        }
        return;
      }

      if (loginAlert) {
        loginAlert.classList.add("d-none");
      }
      if (loginButton) {
        loginButton.disabled = true;
        loginButton.textContent = "Signing In...";
      }

      try {
        const response = await fetch("auth/login.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(payload),
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.message || "Unable to sign in.");
        }

        window.location.href = result.redirect;
      } catch (error) {
        if (loginAlert) {
          loginAlert.textContent = error.message || "Login failed. Please try again.";
          loginAlert.classList.remove("d-none");
        }
      } finally {
        if (loginButton) {
          loginButton.disabled = false;
          loginButton.textContent = "Sign In";
        }
      }
    });
  };

  const initializeComingSoonItems = (root = document) => {
    const comingSoonItems = root.querySelectorAll("[data-coming-soon]");
    comingSoonItems.forEach((el) => {
      if (el.dataset.bound === "true") {
        return;
      }

      el.dataset.bound = "true";
      el.addEventListener("click", (event) => {
        event.preventDefault();
        alert("This feature will be connected to live data in the next step.");
      });
    });
  };

  const initializeRosterButtons = (root = document) => {
    const rosterButtons = root.querySelectorAll("[data-add-roster-rows]");
    rosterButtons.forEach((button) => {
      if (button.dataset.bound === "true") {
        return;
      }

      button.dataset.bound = "true";
      button.addEventListener("click", () => {
        const targetId = button.getAttribute("data-target-body");
        const body = targetId ? document.getElementById(targetId) : null;
        const rowCount = Number.parseInt(button.getAttribute("data-add-roster-rows") || "0", 10);

        if (!body || Number.isNaN(rowCount) || rowCount <= 0) {
          return;
        }

        const existingRows = body.querySelectorAll("tr").length;
        for (let offset = 0; offset < rowCount; offset += 1) {
          const rowNumber = existingRows + offset + 1;
          const row = document.createElement("tr");
          row.innerHTML = `
            <td class="text-secondary fw-semibold">${rowNumber}</td>
            <td>
              <input
                type="text"
                name="student_identifiers[]"
                class="form-control spreadsheet-input"
                placeholder="e.g. STU-1001 or Amina Yusuf"
              />
            </td>
          `;
          body.appendChild(row);
        }
      });
    });
  };

  const initializeClientConnectionTools = (root = document) => {
    const copyButtons = root.querySelectorAll("[data-copy-text]");
    copyButtons.forEach((button) => {
      if (button.dataset.bound === "true") {
        return;
      }

      button.dataset.bound = "true";
      button.dataset.defaultLabel = button.dataset.copyLabel || button.textContent || "Copy";
      button.addEventListener("click", async () => {
        const text = button.getAttribute("data-copy-text") || "";
        if (!text) {
          return;
        }

        try {
          if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
            await navigator.clipboard.writeText(text);
          } else {
            const helper = document.createElement("textarea");
            helper.value = text;
            helper.setAttribute("readonly", "readonly");
            helper.style.position = "absolute";
            helper.style.left = "-9999px";
            document.body.appendChild(helper);
            helper.select();
            document.execCommand("copy");
            document.body.removeChild(helper);
          }

          button.textContent = "Copied";
        } catch (error) {
          console.error(error);
          button.textContent = "Copy failed";
        }

        window.setTimeout(() => {
          button.textContent = button.dataset.defaultLabel || "Copy";
        }, 1600);
      });
    });

    const qrTargets = root.querySelectorAll("[data-qr-code]");
    qrTargets.forEach((target) => {
      if (target.dataset.qrRendered === "true") {
        return;
      }

      target.dataset.qrRendered = "true";
      const text = target.getAttribute("data-qr-code") || "";
      const size = Number.parseInt(target.getAttribute("data-qr-size") || "220", 10);
      if (!text) {
        return;
      }

      if (window.QRCode) {
        target.innerHTML = "";
        // Render a readable LAN-login QR inside the existing card shell.
        new window.QRCode(target, {
          text,
          width: Number.isNaN(size) ? 220 : size,
          height: Number.isNaN(size) ? 220 : size,
          colorDark: "#18324a",
          colorLight: "#ffffff",
          correctLevel: window.QRCode.CorrectLevel.M,
        });
        return;
      }

      target.classList.add("is-fallback");
      target.textContent = "QR preview unavailable. Use the URL on this page.";
    });
  };

  const initializePortalNavigation = () => {
    const portalHeader = document.querySelector(".portal-header");
    if (!portalHeader || portalHeader.dataset.bound === "true") {
      return;
    }

    portalHeader.dataset.bound = "true";

    const collapse = portalHeader.querySelector("#portalNavigation");
    const toggleButton = portalHeader.querySelector(".portal-navbar-toggler");
    const navLinks = portalHeader.querySelectorAll(".portal-nav-link");
    const mediaQuery = window.matchMedia("(max-width: 991.98px)");

    if (!collapse || !toggleButton) {
      return;
    }

    if (window.bootstrap && typeof window.bootstrap.Collapse === "function") {
      return;
    }

    const setExpandedState = (isOpen) => {
      toggleButton.setAttribute("aria-expanded", isOpen ? "true" : "false");
      collapse.classList.toggle("show", isOpen);
    };

    const closeNavigation = () => {
      setExpandedState(false);
    };

    const toggleNavigation = () => {
      if (!mediaQuery.matches) {
        closeNavigation();
        return;
      }

      setExpandedState(!collapse.classList.contains("show"));
    };

    toggleButton.addEventListener("click", toggleNavigation);

    navLinks.forEach((link) => {
      link.addEventListener("click", () => {
        if (mediaQuery.matches) {
          closeNavigation();
        }
      });
    });

    const handleViewportChange = (event) => {
      if (!event.matches) {
        closeNavigation();
      }
    };

    if (typeof mediaQuery.addEventListener === "function") {
      mediaQuery.addEventListener("change", handleViewportChange);
    } else if (typeof mediaQuery.addListener === "function") {
      mediaQuery.addListener(handleViewportChange);
    }

    document.addEventListener("click", (event) => {
      if (!mediaQuery.matches || !collapse.classList.contains("show")) {
        return;
      }

      if (!portalHeader.contains(event.target)) {
        closeNavigation();
      }
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        closeNavigation();
      }
    });
  };

  const initializeDynamicBehaviors = (root = document) => {
    initializeComingSoonItems(root);
    initializeRosterButtons(root);
    initializeClientConnectionTools(root);
    initializeAsyncNavigation(root);
  };

  const updateAsyncRegion = (target, html) => {
    target.innerHTML = html;
    initializeDynamicBehaviors(target);
  };

  const setBusyState = (target, isBusy) => {
    target.setAttribute("aria-busy", isBusy ? "true" : "false");
    target.classList.toggle("is-loading", isBusy);
  };

  const buildAsyncRequestUrl = (formOrLinkUrl, fragmentKey, searchParams) => {
    const url = new URL(formOrLinkUrl, window.location.href);
    if (searchParams) {
      url.search = searchParams.toString();
    }
    if (fragmentKey) {
      url.searchParams.set("partial", fragmentKey);
    }

    return url;
  };

  async function runAsyncRequest({ url, fetchOptions, target, pushUrl, scrollTarget }) {
    setBusyState(target, true);

    try {
      const response = await fetch(url.toString(), {
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
        ...fetchOptions,
      });

      if (!response.ok) {
        throw new Error("The requested section could not be loaded.");
      }

      const html = await response.text();
      updateAsyncRegion(target, html);

      if (pushUrl) {
        const visibleUrl = new URL(url.toString());
        visibleUrl.searchParams.delete("partial");
        window.history.pushState({}, "", visibleUrl.toString());
      }

      if (scrollTarget) {
        target.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    } catch (error) {
      console.error(error);
      window.location.href = url.toString().replace(/[?&]partial=[^&]+/, "").replace(/[?&]$/, "");
    } finally {
      setBusyState(target, false);
    }
  }

  function initializeAsyncNavigation(root = document) {
    const asyncForms = root.querySelectorAll("form[data-async-form]");
    asyncForms.forEach((form) => {
      if (form.dataset.bound === "true") {
        return;
      }

      form.dataset.bound = "true";
      form.addEventListener("submit", async (event) => {
        event.preventDefault();

        const targetId = form.getAttribute("data-async-target");
        const fragmentKey = form.getAttribute("data-async-fragment") || "";
        const target = targetId ? document.getElementById(targetId) : null;
        if (!target) {
          form.submit();
          return;
        }

        const method = (form.getAttribute("method") || "get").toLowerCase();
        const action = form.getAttribute("action") || window.location.href;
        const pushUrl = form.getAttribute("data-async-push-url") === "true";
        const scrollTarget = form.getAttribute("data-async-scroll") === "true";
        const formData = new FormData(form);

        if (method === "get") {
          const searchParams = new URLSearchParams();
          for (const [key, value] of formData.entries()) {
            searchParams.append(key, String(value));
          }

          await runAsyncRequest({
            url: buildAsyncRequestUrl(action, fragmentKey, searchParams),
            fetchOptions: { method: "GET" },
            target,
            pushUrl,
            scrollTarget,
          });
          return;
        }

        formData.set("partial", fragmentKey);
        const requestUrl = new URL(action, window.location.href);
        await runAsyncRequest({
          url: requestUrl,
          fetchOptions: {
            method: method.toUpperCase(),
            body: formData,
          },
          target,
          pushUrl: false,
          scrollTarget,
        });
      });
    });

    const asyncLinks = root.querySelectorAll("a[data-async-link]");
    asyncLinks.forEach((link) => {
      if (link.dataset.bound === "true") {
        return;
      }

      link.dataset.bound = "true";
      link.addEventListener("click", async (event) => {
        event.preventDefault();

        const targetId = link.getAttribute("data-async-target");
        const fragmentKey = link.getAttribute("data-async-fragment") || "";
        const target = targetId ? document.getElementById(targetId) : null;
        const href = link.getAttribute("href");

        if (!target || !href) {
          window.location.href = href || window.location.href;
          return;
        }

        await runAsyncRequest({
          url: buildAsyncRequestUrl(href, fragmentKey),
          fetchOptions: { method: "GET" },
          target,
          pushUrl: link.getAttribute("data-async-push-url") === "true",
          scrollTarget: link.getAttribute("data-async-scroll") === "true",
        });
      });
    });
  }

  initializeLoginForm();
  initializePortalNavigation();
  initializeDynamicBehaviors(document);
});
