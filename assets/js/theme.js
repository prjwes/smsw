// Theme management
const currentTheme = localStorage.getItem("theme") || "light"

// Apply theme immediately on load (before DOM ready)
document.documentElement.setAttribute("data-theme", currentTheme)

window.toggleTheme = () => {
  const html = document.documentElement
  const currentTheme = html.getAttribute("data-theme")
  const newTheme = currentTheme === "light" ? "dark" : "light"

  console.log("[v0] Toggling theme to:", newTheme)
  html.setAttribute("data-theme", newTheme)
  localStorage.setItem("theme", newTheme)
  updateThemeIcon(newTheme)
}

function updateThemeIcon(theme) {
  const icon = document.getElementById("themeIcon") || document.querySelector(".theme-icon")
  if (icon) {
    icon.textContent = theme === "light" ? "🌙" : "☀️"
  }
}
;(() => {
  const html = document.documentElement
  const savedTheme = localStorage.getItem("theme") || "light"

  html.setAttribute("data-theme", savedTheme)

  // Update icon when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      updateThemeIcon(savedTheme)
    })
  } else {
    updateThemeIcon(savedTheme)
  }
})()
