// AOS Animation Setup
AOS.init({
  easing: 'ease-in-out',
  once: true
});

// Hamburger Menu with Lottie
const hamburger = document.getElementById("hamburger");
const navMenu = document.querySelector(".nav-menu");

const animation = lottie.loadAnimation({
  container: hamburger,
  renderer: "svg",
  loop: false,
  autoplay: false,
  path: "icons/hamburger.json"
});

animation.setSpeed(2.5);

let menuOpen = false;

function toggleMenu(forceClose = false) {
  menuOpen = forceClose ? false : !menuOpen;

  animation.setDirection(menuOpen ? 1 : -1);
  animation.play();

  navMenu.classList.toggle("active", menuOpen);
  document.body.style.overflow = menuOpen ? "hidden" : "auto";
}

// Click hamburger icon
hamburger.addEventListener("click", () => toggleMenu());

// ESC key closes menu
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && menuOpen) {
    toggleMenu(true);
  }
});

// Click outside to close menu
document.addEventListener("click", (e) => {
  if (
    menuOpen &&
    !hamburger.contains(e.target) &&
    !navMenu.contains(e.target)
  ) {
    toggleMenu(true);
  }
});

