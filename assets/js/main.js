// Main JavaScript functionality

const menuToggle = document.getElementById("menuToggle")
const sidebar = document.getElementById("sidebar")

if (menuToggle && sidebar) {
  menuToggle.addEventListener("click", (e) => {
    e.stopPropagation()
    sidebar.classList.toggle("show")
    menuToggle.setAttribute("aria-expanded", sidebar.classList.contains("show"))
  })

  // Close sidebar when clicking outside
  document.addEventListener("click", (e) => {
    const isClickInsideSidebar = sidebar.contains(e.target)
    const isClickOnToggle = menuToggle.contains(e.target)
    if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains("show")) {
      sidebar.classList.remove("show")
      menuToggle.setAttribute("aria-expanded", "false")
    }
  })

  // Close sidebar on escape key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && sidebar.classList.contains("show")) {
      sidebar.classList.remove("show")
      menuToggle.setAttribute("aria-expanded", "false")
    }
  })
}

const userMenuToggle = document.getElementById("userMenuToggle")
const userDropdown = document.getElementById("userDropdown")

if (userMenuToggle && userDropdown) {
  userMenuToggle.addEventListener("click", (e) => {
    e.stopPropagation()
    userDropdown.classList.toggle("show")
  })

  // Close dropdown when clicking outside
  document.addEventListener("click", (e) => {
    if (!userDropdown.contains(e.target) && !userMenuToggle.contains(e.target)) {
      userDropdown.classList.remove("show")
    }
  })
}

// Form validation
function validateForm(formId) {
  const form = document.getElementById(formId)
  if (!form) return

  form.addEventListener("submit", (e) => {
    const inputs = form.querySelectorAll("input[required], select[required], textarea[required]")
    let isValid = true

    inputs.forEach((input) => {
      if (!input.value.trim()) {
        isValid = false
        input.style.borderColor = "var(--danger-color)"
      } else {
        input.style.borderColor = "var(--border-color)"
      }
    })

    if (!isValid) {
      e.preventDefault()
      alert("Please fill in all required fields")
    }
  })
}

// Confirm delete
function confirmDelete(message) {
  return confirm(message || "Are you sure you want to delete this item?")
}

// Show notification
function showNotification(message, type = "success") {
  const notification = document.createElement("div")
  notification.className = `alert alert-${type}`
  notification.textContent = message
  notification.style.position = "fixed"
  notification.style.top = "80px"
  notification.style.right = "20px"
  notification.style.zIndex = "1000"
  notification.style.minWidth = "300px"

  document.body.appendChild(notification)

  setTimeout(() => {
    notification.remove()
  }, 3000)
}

// Export table to CSV
function exportTableToCSV(tableId, filename) {
  const table = document.getElementById(tableId)
  if (!table) return

  const csv = []
  const rows = table.querySelectorAll("tr")

  rows.forEach((row) => {
    const cols = row.querySelectorAll("td, th")
    const rowData = Array.from(cols).map((col) => {
      return '"' + col.textContent.trim().replace(/"/g, '""') + '"'
    })
    csv.push(rowData.join(","))
  })

  const csvContent = csv.join("\n")
  const blob = new Blob([csvContent], { type: "text/csv" })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement("a")
  a.href = url
  a.download = filename + ".csv"
  a.click()
  window.URL.revokeObjectURL(url)
}

function enableAutoSave(formId, saveButtonName = "submit") {
  const form = document.getElementById(formId)
  if (!form) return

  // Set up auto-save on form changes
  form.addEventListener("change", (e) => {
    // Auto-submit form on change
    if (e.target.tagName !== "BUTTON") {
      setTimeout(() => {
        form.submit()
      }, 500)
    }
  })

  // Auto-submit textarea on blur (after typing stops)
  const textareas = form.querySelectorAll("textarea")
  textareas.forEach((textarea) => {
    let timeout
    textarea.addEventListener("input", () => {
      clearTimeout(timeout)
      timeout = setTimeout(() => {
        form.submit()
      }, 2000)
    })
  })
}

document.addEventListener("DOMContentLoaded", () => {
  const forms = document.querySelectorAll('form[method="POST"]')

  forms.forEach((form) => {
    form.addEventListener("submit", (e) => {
      const submitButtons = form.querySelectorAll('button[type="submit"]')
      let shouldAutoReload = false

      submitButtons.forEach((btn) => {
        const btnName = btn.getAttribute("name") || ""
        // Check if this is a post, comment, or message form
        if (
          btnName.includes("create_post") ||
          btnName.includes("post_comment") ||
          btnName.includes("send_message") ||
          btnName.includes("post_news") ||
          btnName.includes("submit")
        ) {
          shouldAutoReload = true
        }
      })

      if (shouldAutoReload) {
        // Allow form submission to complete, then reload
        setTimeout(() => {
          location.reload()
        }, 800)
      }
    })
  })

  const searchInput = document.getElementById("search_query")
  const searchTypeSelect = document.querySelector('select[name="search_type"]')

  if (searchInput && searchTypeSelect) {
    const suggestionsDiv = document.createElement("div")
    suggestionsDiv.id = "searchSuggestions"
    suggestionsDiv.style.cssText = `
      position: fixed;
      top: 80px;
      left: 50%;
      transform: translateX(-50%);
      background-color: var(--bg-primary);
      border: 1px solid var(--border-color);
      border-radius: 4px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      display: none;
      z-index: 999;
      max-height: 400px;
      overflow-y: auto;
      width: 90%;
      max-width: 500px;
    `
    document.body.appendChild(suggestionsDiv)

    let searchTimeout
    searchInput.addEventListener("input", async (e) => {
      clearTimeout(searchTimeout)
      const query = searchInput.value.trim()
      const searchType = searchTypeSelect.value

      if (query.length < 2) {
        suggestionsDiv.style.display = "none"
        return
      }

      searchTimeout = setTimeout(async () => {
        try {
          const response = await fetch(
            `api_search.php?q=${encodeURIComponent(query)}&type=${encodeURIComponent(searchType)}`,
          )
          const data = await response.json()
          const results = data.results || []

          suggestionsDiv.innerHTML = ""

          if (!Array.isArray(results) || results.length === 0) {
            suggestionsDiv.innerHTML =
              '<div style="padding: 12px; color: var(--text-secondary); text-align: center;">No results found</div>'
            suggestionsDiv.style.display = "block"
            return
          }

          results.forEach((result) => {
            const suggestion = document.createElement("div")
            suggestion.style.cssText = `
              padding: 12px;
              cursor: pointer;
              border-bottom: 1px solid var(--border-color);
              transition: background-color 0.2s;
            `
            suggestion.onmouseover = () => (suggestion.style.backgroundColor = "var(--bg-secondary)")
            suggestion.onmouseout = () => (suggestion.style.backgroundColor = "transparent")

            suggestion.innerHTML = `<strong>${result.title}</strong><br><small>${result.subtitle}</small>`
            suggestion.onclick = () => (window.location.href = result.link)

            suggestionsDiv.appendChild(suggestion)
          })

          suggestionsDiv.style.display = "block"
        } catch (error) {
          console.error("[v0] Search error:", error)
          suggestionsDiv.innerHTML =
            '<div style="padding: 12px; color: var(--danger-color); text-align: center;">Search failed. Please try again.</div>'
          suggestionsDiv.style.display = "block"
        }
      }, 300)
    })

    // Hide suggestions when clicking outside
    document.addEventListener("click", (e) => {
      if (!e.target.closest("#search_query") && !e.target.closest("#searchSuggestions")) {
        suggestionsDiv.style.display = "none"
      }
    })
  }

  const commentTextareas = document.querySelectorAll(".comment-form textarea, .news-form textarea")
  commentTextareas.forEach((textarea) => {
    textarea.addEventListener("keypress", (e) => {
      if (e.key === "Enter" && e.ctrlKey === false) {
        e.preventDefault()
      }
    })
  })
})

document.addEventListener("visibilitychange", () => {
  if (!document.hidden) {
    if (document.body.dataset.autoReload !== "false") {
      setTimeout(() => {
        location.reload()
      }, 1000)
    }
  }
})
