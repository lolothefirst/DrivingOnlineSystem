// Main JavaScript file

// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", function (e) {
    e.preventDefault()
    const target = document.querySelector(this.getAttribute("href"))
    if (target) {
      target.scrollIntoView({
        behavior: "smooth",
      })
    }
  })
})

// Auto-hide alerts
setTimeout(() => {
  const alerts = document.querySelectorAll(".alert")
  alerts.forEach((alert) => {
    alert.style.transition = "opacity 0.5s"
    alert.style.opacity = "0"
    setTimeout(() => alert.remove(), 500)
  })
}, 5000)

// Form validation
const forms = document.querySelectorAll("form")
forms.forEach((form) => {
  form.addEventListener("submit", (e) => {
    const requiredFields = form.querySelectorAll("[required]")
    let isValid = true

    requiredFields.forEach((field) => {
      if (!field.value.trim()) {
        isValid = false
        field.style.borderColor = "var(--danger-color)"
      } else {
        field.style.borderColor = "var(--border-color)"
      }
    })

    if (!isValid) {
      e.preventDefault()
      alert("Please fill in all required fields")
    }
  })
})

// Sidebar Toggle
const hamburgerMenu = document.getElementById("hamburgerMenu")
const sidebar = document.getElementById("sidebar")
const sidebarClose = document.getElementById("sidebarClose")

if (hamburgerMenu && sidebar) {
  hamburgerMenu.addEventListener("click", () => {
    sidebar.classList.add("active")
  })
}

if (sidebarClose && sidebar) {
  sidebarClose.addEventListener("click", () => {
    sidebar.classList.remove("active")
  })
}

// Close sidebar when clicking outside on mobile
document.addEventListener("click", (e) => {
  if (sidebar && sidebar.classList.contains("active")) {
    if (!sidebar.contains(e.target) && !hamburgerMenu.contains(e.target)) {
      sidebar.classList.remove("active")
    }
  }
})

// Highlight active navigation item
const currentPath = window.location.pathname
const navItems = document.querySelectorAll(".nav-item")

navItems.forEach((item) => {
  const href = item.getAttribute("href")
  if (currentPath.includes(href)) {
    item.classList.add("active")
  }
})
